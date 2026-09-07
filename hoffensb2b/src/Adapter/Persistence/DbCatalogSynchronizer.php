<?php

namespace Hoffens\B2B\Adapter\Persistence;

use Hoffens\B2B\Domain\Catalog\CatalogItem;
use Hoffens\B2B\Exception\IntegrationException;
use Hoffens\B2B\Port\CatalogSynchronizerInterface;

final class DbCatalogSynchronizer implements CatalogSynchronizerInterface
{
    const BATCH_SIZE = 250;

    public function synchronize(array $items)
    {
        if (count($items) === 0) {
            throw new IntegrationException('El catálogo REST está vacío.');
        }
        $bySku = array();
        foreach ($items as $item) {
            if (!$item instanceof CatalogItem) {
                throw new IntegrationException('El catálogo contiene un artículo inválido.');
            }
            $bySku[$item->itemCode()] = $item;
        }
        ksort($bySku, SORT_STRING);
        $mapped = $this->findProducts(array_keys($bySku));
        $sourceJson = json_encode(array_values($bySku));
        if ($sourceJson === false) {
            throw new IntegrationException('No fue posible serializar el catálogo REST.');
        }
        $sourceHash = hash('sha256', $sourceJson);
        $mappingParts = array();
        foreach ($mapped as $sku => $target) {
            $mappingParts[] = $sku . '|' . $target['scope'] . '|'
                . $target['id_product'] . '|' . $target['id_product_attribute'];
        }
        sort($mappingParts, SORT_STRING);
        $mappingHash = hash('sha256', implode("\n", $mappingParts));

        $db = \Db::getInstance();
        if ((int) $db->getValue("SELECT GET_LOCK('hoffensb2b_catalog', 5)") !== 1) {
            throw new IntegrationException('Ya existe una sincronización de catálogo en curso.');
        }
        try {
            $state = $db->getRow('SELECT source_hash, mapping_hash FROM `'
                . _DB_PREFIX_ . 'hoffens_b2b_catalog_state` WHERE id_state = 1');
            if ($state && hash_equals($state['source_hash'], $sourceHash)
                && hash_equals($state['mapping_hash'], $mappingHash)
                && !$this->hasMultipleDrift($bySku, $mapped)) {
                return $this->result($bySku, $mapped, 0, true);
            }
            if (!$db->execute('START TRANSACTION')) {
                throw new IntegrationException('No fue posible iniciar la sincronización del catálogo.');
            }
            try {
                if (!$db->delete('hoffens_b2b_catalog')) {
                    throw new IntegrationException('No fue posible reemplazar el catálogo local.');
                }
                foreach (array_chunk($bySku, self::BATCH_SIZE, true) as $batch) {
                    if (!$this->insertCatalogBatch($batch, $mapped)) {
                        throw new IntegrationException('No fue posible guardar el catálogo local.');
                    }
                }
                $updated = $this->updateMultiples($bySku, $mapped);
                if (!$this->saveState($sourceHash, $mappingHash, count($bySku), count($mapped))) {
                    throw new IntegrationException('No fue posible guardar el estado del catálogo.');
                }
                if (!$db->execute('COMMIT')) {
                    throw new IntegrationException('No fue posible confirmar el catálogo.');
                }
            } catch (\Exception $exception) {
                $db->execute('ROLLBACK');
                if ($exception instanceof IntegrationException) {
                    throw $exception;
                }
                throw new IntegrationException('Falló la sincronización del catálogo.', 0, $exception);
            }
        } finally {
            $db->getValue("SELECT RELEASE_LOCK('hoffensb2b_catalog')");
        }
        return $this->result($bySku, $mapped, $updated, false);
    }

    private function findProducts(array $skus)
    {
        $found = array();
        foreach (array_chunk($skus, 400) as $batch) {
            $quoted = array();
            foreach ($batch as $sku) { $quoted[] = "'" . pSQL($sku) . "'"; }
            $in = implode(',', $quoted);
            foreach (\Db::getInstance()->executeS('SELECT reference,id_product,id_product_attribute FROM `'
                . _DB_PREFIX_ . 'product_attribute` WHERE reference IN (' . $in . ')') as $row) {
                $found[$row['reference']] = array('scope' => 'combination', 'id_product' => (int) $row['id_product'],
                    'id_product_attribute' => (int) $row['id_product_attribute']);
            }
            foreach (\Db::getInstance()->executeS('SELECT reference,id_product FROM `'
                . _DB_PREFIX_ . 'product` WHERE reference IN (' . $in . ')') as $row) {
                if (!isset($found[$row['reference']])) {
                    $found[$row['reference']] = array('scope' => 'product', 'id_product' => (int) $row['id_product'],
                        'id_product_attribute' => 0);
                }
            }
        }
        return $found;
    }

    private function insertCatalogBatch(array $items, array $mapped)
    {
        $values = array();
        foreach ($items as $sku => $item) {
            $target = isset($mapped[$sku]) ? $mapped[$sku] : array('scope' => 'none', 'id_product' => 0, 'id_product_attribute' => 0);
            $payload = json_encode($item);
            if ($payload === false) {
                throw new IntegrationException('No fue posible serializar el artículo ' . $sku . '.');
            }
            $values[] = "('" . pSQL($sku) . "','" . pSQL($item->name()) . "',"
                . number_format($item->purchaseMultiple(), 6, '.', '') . ','
                . number_format($item->saleMultiple(), 6, '.', '') . ','
                . number_format($item->stock(), 6, '.', '') . ",'" . pSQL($target['scope']) . "',"
                . (int) $target['id_product'] . ',' . (int) $target['id_product_attribute'] . ",'"
                . pSQL($payload, true) . "','" . pSQL(gmdate('Y-m-d H:i:s')) . "')";
        }
        return \Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'hoffens_b2b_catalog` '
            . '(item_code,name,purchase_multiple,sale_multiple,stock,found_scope,id_product,id_product_attribute,payload,synced_at) VALUES '
            . implode(',', $values));
    }

    private function updateMultiples(array $items, array $mapped)
    {
        list($product, $combination) = $this->multipleValues($items, $mapped);
        return $this->updateQuantityTables($product, 'product', 'id_product')
            + $this->updateQuantityTables($combination, 'product_attribute', 'id_product_attribute');
    }

    private function hasMultipleDrift(array $items, array $mapped)
    {
        list($product, $combination) = $this->multipleValues($items, $mapped);
        return count($this->changedQuantityValues($product, 'product', 'id_product')) > 0
            || count($this->changedQuantityValues(
                $combination,
                'product_attribute',
                'id_product_attribute'
            )) > 0;
    }

    private function multipleValues(array $items, array $mapped)
    {
        $product = array();
        $combination = array();
        foreach ($mapped as $sku => $target) {
            // PrestaShop 1.7 almacena minimal_quantity como entero.
            $multiple = max(1, (int) round($items[$sku]->purchaseMultiple()));
            if ($target['scope'] === 'combination') { $combination[$target['id_product_attribute']] = $multiple; }
            else { $product[$target['id_product']] = $multiple; }
        }
        return array($product, $combination);
    }

    private function updateQuantityTables(array $values, $table, $idColumn)
    {
        $updated = 0;
        foreach (array_chunk($values, self::BATCH_SIZE, true) as $batch) {
            $batch = $this->changedQuantityValues($batch, $table, $idColumn);
            if (count($batch) === 0) {
                continue;
            }
            $case = ''; $ids = array();
            foreach ($batch as $id => $multiple) { $ids[] = (int) $id; $case .= ' WHEN ' . (int) $id . ' THEN ' . (int) $multiple; }
            foreach (array($table, $table . '_shop') as $target) {
                if (!\Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . bqSQL($target)
                    . '` SET minimal_quantity = CASE `' . bqSQL($idColumn) . '`' . $case . ' END WHERE `'
                    . bqSQL($idColumn) . '` IN (' . implode(',', $ids) . ')')) {
                    throw new IntegrationException('No fue posible actualizar los múltiplos en ' . $target . '.');
                }
            }
            $updated += count($batch);
        }
        return $updated;
    }

    private function changedQuantityValues(array $values, $table, $idColumn)
    {
        if (count($values) === 0) {
            return array();
        }
        $ids = array_map('intval', array_keys($values));
        $changed = array();
        foreach (array($table, $table . '_shop') as $target) {
            $rows = \Db::getInstance()->executeS('SELECT `' . bqSQL($idColumn)
                . '`, minimal_quantity FROM `' . _DB_PREFIX_ . bqSQL($target) . '` WHERE `'
                . bqSQL($idColumn) . '` IN (' . implode(',', $ids) . ')');
            foreach ($rows as $row) {
                $id = (int) $row[$idColumn];
                if (isset($values[$id]) && (int) $row['minimal_quantity'] !== (int) $values[$id]) {
                    $changed[$id] = $values[$id];
                }
            }
        }
        return $changed;
    }

    private function saveState($sourceHash, $mappingHash, $received, $mapped)
    {
        return \Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'hoffens_b2b_catalog_state` '
            . "(id_state,source_hash,mapping_hash,received_count,mapped_count,missing_count,synced_at) VALUES (1,'"
            . pSQL($sourceHash) . "','" . pSQL($mappingHash) . "'," . (int) $received . ',' . (int) $mapped . ','
            . max(0, $received - $mapped) . ",'" . pSQL(gmdate('Y-m-d H:i:s')) . "') ON DUPLICATE KEY UPDATE "
            . 'source_hash=VALUES(source_hash),mapping_hash=VALUES(mapping_hash),received_count=VALUES(received_count),'
            . 'mapped_count=VALUES(mapped_count),missing_count=VALUES(missing_count),synced_at=VALUES(synced_at)');
    }

    private function result(array $items, array $mapped, $updated, $unchanged)
    {
        return array('received' => count($items), 'mapped' => count($mapped),
            'missing' => count($items) - count($mapped), 'multiplesUpdated' => (int) $updated,
            'unchanged' => (bool) $unchanged);
    }
}
