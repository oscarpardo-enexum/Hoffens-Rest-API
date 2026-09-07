<?php

namespace Hoffens\B2B\Adapter\Persistence;

use Hoffens\B2B\Domain\Pricing\CustomerPrice;
use Hoffens\B2B\Exception\IntegrationException;
use Hoffens\B2B\Port\CustomerPriceSynchronizerInterface;

final class DbCustomerPriceSynchronizer implements CustomerPriceSynchronizerInterface
{
    const LOOKUP_BATCH_SIZE = 400;
    const INSERT_BATCH_SIZE = 250;

    public function synchronize($customerId, array $prices)
    {
        $customerId = (int) $customerId;
        if ($customerId <= 0 || count($prices) === 0) {
            throw new IntegrationException('No hay precios válidos para sincronizar.');
        }

        $bySku = array();
        foreach ($prices as $price) {
            if (!$price instanceof CustomerPrice) {
                throw new IntegrationException('La respuesta contiene un precio inválido.');
            }
            $bySku[$price->itemCode()] = $price;
        }
        ksort($bySku, SORT_STRING);

        $products = $this->findProducts(array_keys($bySku));
        $rows = array();
        foreach ($bySku as $sku => $price) {
            if (!isset($products[$sku])) {
                continue;
            }
            $key = $products[$sku]['id_product'] . '-' . $products[$sku]['id_product_attribute'];
            $rows[$key] = array(
                'id_product' => $products[$sku]['id_product'],
                'id_product_attribute' => $products[$sku]['id_product_attribute'],
                'price' => $price->amount(),
            );
        }

        if (count($rows) === 0) {
            throw new IntegrationException('Ningún SKU de la respuesta existe en PrestaShop.');
        }
        ksort($rows, SORT_STRING);

        $sourceHash = $this->sourceHash($bySku);
        $mappedHash = $this->mappedHash($rows);

        // El endpoint ya entrega precios personalizados por CardCode. El grupo
        // cero permite que el precio del cliente aplique aunque cambie su grupo
        // predeterminado o pertenezca a más de una lista comercial.
        $groupId = 0;
        $db = \Db::getInstance();
        $lockName = 'hoffensb2b_price_' . $customerId;
        if ((int) $db->getValue("SELECT GET_LOCK('" . pSQL($lockName) . "', 5)") !== 1) {
            throw new IntegrationException('Ya existe una actualización de precios para este cliente.');
        }
        try {
            $state = $db->getRow(
                'SELECT source_hash, mapped_hash FROM `' . _DB_PREFIX_ . 'hoffens_b2b_price_sync`'
                . ' WHERE id_customer = ' . $customerId
            );
            $current = $db->getRow(
                'SELECT COUNT(*) AS total, SUM(id_group = 0) AS compatible FROM `' . _DB_PREFIX_ . 'specific_price`'
                . ' WHERE id_customer = ' . $customerId . ' AND id_specific_price_rule = 0'
            );
            $databaseIsCurrent = (int) $current['total'] === count($rows)
                && (int) $current['compatible'] === count($rows);
            if ($state && hash_equals((string) $state['source_hash'], $sourceHash)
                && hash_equals((string) $state['mapped_hash'], $mappedHash) && $databaseIsCurrent) {
                return $this->result($bySku, $rows, $groupId, 0, true);
            }

            if (!$db->execute('START TRANSACTION')) {
                throw new IntegrationException('No fue posible iniciar la actualización de precios.');
            }
            try {
                if (!$db->delete('specific_price', 'id_customer = ' . $customerId . ' AND id_specific_price_rule = 0')) {
                    throw new IntegrationException('No fue posible reemplazar los precios anteriores.');
                }
                foreach (array_chunk(array_values($rows), self::INSERT_BATCH_SIZE) as $batch) {
                    if (!$this->insertBatch($batch, $customerId, $groupId)) {
                        throw new IntegrationException('No fue posible guardar la lista completa de precios.');
                    }
                }
                if (!$this->saveState($customerId, $sourceHash, $mappedHash, count($bySku), count($rows))) {
                    throw new IntegrationException('No fue posible guardar el estado de la sincronización.');
                }
                if (!$db->execute('COMMIT')) {
                    throw new IntegrationException('No fue posible confirmar la actualización de precios.');
                }
            } catch (\Exception $exception) {
                $db->execute('ROLLBACK');
                if ($exception instanceof IntegrationException) {
                    throw $exception;
                }
                throw new IntegrationException('Falló la actualización de precios en PrestaShop.', 0, $exception);
            }
        } finally {
            $db->getValue("SELECT RELEASE_LOCK('" . pSQL($lockName) . "')");
        }

        \SpecificPrice::flushCache();
        return $this->result($bySku, $rows, $groupId, count($rows), false);
    }

    private function sourceHash(array $prices)
    {
        $parts = array();
        foreach ($prices as $sku => $price) {
            $parts[] = $sku . '|' . $price->currency() . '|'
                . number_format($price->amount(), 6, '.', '');
        }
        return hash('sha256', implode("\n", $parts));
    }

    private function mappedHash(array $rows)
    {
        $parts = array();
        foreach ($rows as $key => $row) {
            $parts[] = $key . '|' . number_format((float) $row['price'], 6, '.', '');
        }
        return hash('sha256', implode("\n", $parts));
    }

    private function saveState($customerId, $sourceHash, $mappedHash, $received, $mapped)
    {
        return \Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'hoffens_b2b_price_sync` '
            . '(id_customer,source_hash,mapped_hash,received_count,mapped_count,unmatched_count,synced_at) VALUES ('
            . (int) $customerId . ",'" . pSQL($sourceHash) . "','" . pSQL($mappedHash) . "',"
            . (int) $received . ',' . (int) $mapped . ',' . max(0, (int) $received - (int) $mapped)
            . ",'" . pSQL(gmdate('Y-m-d H:i:s')) . "') ON DUPLICATE KEY UPDATE "
            . 'source_hash=VALUES(source_hash),mapped_hash=VALUES(mapped_hash),'
            . 'received_count=VALUES(received_count),mapped_count=VALUES(mapped_count),'
            . 'unmatched_count=VALUES(unmatched_count),synced_at=VALUES(synced_at)'
        );
    }

    private function result(array $prices, array $rows, $groupId, $written, $unchanged)
    {
        return array(
            'received' => count($prices),
            'mapped' => count($rows),
            'unmatched' => count($prices) - count($rows),
            'written' => (int) $written,
            'groupId' => (int) $groupId,
            'unchanged' => (bool) $unchanged,
        );
    }

    private function findProducts(array $skus)
    {
        $found = array();
        foreach (array_chunk($skus, self::LOOKUP_BATCH_SIZE) as $batch) {
            $quoted = array();
            foreach ($batch as $sku) {
                $quoted[] = "'" . pSQL($sku) . "'";
            }
            $in = implode(',', $quoted);
            $combinations = \Db::getInstance()->executeS(
                'SELECT reference, id_product, id_product_attribute FROM `' . _DB_PREFIX_
                . 'product_attribute` WHERE reference IN (' . $in . ') AND id_product > 0'
            );
            foreach ($combinations as $row) {
                $found[(string) $row['reference']] = array(
                    'id_product' => (int) $row['id_product'],
                    'id_product_attribute' => (int) $row['id_product_attribute'],
                );
            }
            $simpleProducts = \Db::getInstance()->executeS(
                'SELECT reference, id_product FROM `' . _DB_PREFIX_ . 'product` WHERE reference IN (' . $in . ')'
            );
            foreach ($simpleProducts as $row) {
                $sku = (string) $row['reference'];
                if (!isset($found[$sku])) {
                    $found[$sku] = array('id_product' => (int) $row['id_product'], 'id_product_attribute' => 0);
                }
            }
        }
        return $found;
    }

    private function insertBatch(array $rows, $customerId, $groupId)
    {
        $context = \Context::getContext();
        $shopId = isset($context->shop->id) ? (int) $context->shop->id : 1;
        $shopGroupId = isset($context->shop->id_shop_group) ? (int) $context->shop->id_shop_group : 0;
        $values = array();
        foreach ($rows as $row) {
            $values[] = '(0,0,' . (int) $row['id_product'] . ',' . $shopId . ',' . $shopGroupId . ',0,0,' . (int) $groupId . ','
                . (int) $customerId . ',' . (int) $row['id_product_attribute'] . ','
                . number_format((float) $row['price'], 6, '.', '')
                . ",1,0.000000,1,'amount','0000-00-00 00:00:00','0000-00-00 00:00:00')";
        }
        return \Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'specific_price` '
            . '(id_specific_price_rule,id_cart,id_product,id_shop,id_shop_group,id_currency,id_country,id_group,'
            . 'id_customer,id_product_attribute,price,from_quantity,reduction,reduction_tax,reduction_type,`from`,`to`) VALUES '
            . implode(',', $values)
        );
    }
}
