<?php

namespace Hoffens\B2B\Adapter\Persistence;

use Hoffens\B2B\Domain\Pricing\CustomerPrice;
use Hoffens\B2B\Port\LoginSnapshotCacheInterface;

final class DbLoginSnapshotCache implements LoginSnapshotCacheInterface
{
    public function getFresh($customerId, $maxAgeSeconds)
    {
        $customerId = (int) $customerId;
        $maxAgeSeconds = max(0, (int) $maxAgeSeconds);
        $row = \Db::getInstance()->getRow(
            'SELECT `payload` FROM `' . _DB_PREFIX_ . 'hoffens_b2b_login_cache`
            WHERE `id_customer` = ' . $customerId . '
              AND `fetched_at` >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $maxAgeSeconds . ' SECOND)'
        );
        if (!is_array($row) || empty($row['payload'])) {
            return null;
        }

        $compressed = base64_decode($row['payload'], true);
        if ($compressed === false) {
            return null;
        }
        $json = function_exists('gzdecode') ? gzdecode($compressed) : false;
        if ($json === false) {
            return null;
        }
        $snapshot = json_decode($json, true);
        if (!is_array($snapshot) || !isset($snapshot['prices']) || !is_array($snapshot['prices'])) {
            return null;
        }
        $prices = array();
        foreach ($snapshot['prices'] as $row) {
            $currency = isset($row['moneda']) ? trim((string) $row['moneda']) : '';
            if ($currency === '' || $currency === '$') {
                $currency = 'CLP';
            }
            $prices[] = new CustomerPrice(
                isset($row['itemCode']) ? $row['itemCode'] : '',
                $currency,
                isset($row['precio']) ? $row['precio'] : 0
            );
        }
        $snapshot['prices'] = $prices;
        return $snapshot;
    }

    public function put($customerId, array $snapshot)
    {
        $json = json_encode($snapshot);
        if ($json === false) {
            return false;
        }
        $payload = base64_encode(gzencode($json, 6));
        return \Db::getInstance()->execute(
            'REPLACE INTO `' . _DB_PREFIX_ . 'hoffens_b2b_login_cache`
             (`id_customer`, `payload`, `fetched_at`) VALUES ('
            . (int) $customerId . ", '" . pSQL($payload) . "', UTC_TIMESTAMP())"
        );
    }
}
