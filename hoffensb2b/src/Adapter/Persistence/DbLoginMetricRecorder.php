<?php

namespace Hoffens\B2B\Adapter\Persistence;

use Hoffens\B2B\Application\Login\LoginDecision;
use Hoffens\B2B\Port\LoginMetricRecorderInterface;

final class DbLoginMetricRecorder implements LoginMetricRecorderInterface
{
    public function record($customerId, $mode, LoginDecision $decision, $totalMs)
    {
        $metrics = $decision->metrics();
        $endpoints = isset($metrics['endpoints']) ? $metrics['endpoints'] : array();
        $profile = isset($endpoints['customer']) ? $endpoints['customer'] : array();
        $prices = isset($endpoints['prices']) ? $endpoints['prices'] : array();

        $result = \Db::getInstance()->insert('hoffens_b2b_login_metric', array(
            'id_customer' => (int) $customerId,
            'mode' => pSQL((string) $mode),
            'result' => pSQL($decision->reason()),
            'cache_hit' => $decision->isCacheHit() ? 1 : 0,
            'total_ms' => (int) $totalMs,
            'mapping_ms' => isset($metrics['cardCodeLookupMs']) ? (int) $metrics['cardCodeLookupMs'] : null,
            'cache_ms' => isset($metrics['cacheMs']) ? (int) $metrics['cacheMs'] : null,
            'api_batch_ms' => isset($metrics['apiBatchMs']) ? (int) $metrics['apiBatchMs'] : null,
            'profile_ms' => isset($profile['totalMs']) ? (int) $profile['totalMs'] : null,
            'prices_ms' => isset($prices['totalMs']) ? (int) $prices['totalMs'] : null,
            'price_count' => isset($metrics['priceCount']) ? (int) $metrics['priceCount'] : 0,
            'price_sync_ms' => isset($metrics['priceSyncMs']) ? (int) $metrics['priceSyncMs'] : null,
            'price_mapped' => isset($metrics['priceMapped']) ? (int) $metrics['priceMapped'] : 0,
            'price_unmatched' => isset($metrics['priceUnmatched']) ? (int) $metrics['priceUnmatched'] : 0,
            'price_written' => isset($metrics['priceWritten']) ? (int) $metrics['priceWritten'] : 0,
            'price_group_id' => isset($metrics['priceGroupId']) ? (int) $metrics['priceGroupId'] : 0,
            'price_unchanged' => !empty($metrics['priceUnchanged']) ? 1 : 0,
            'profile_bytes' => isset($profile['decodedBytes']) ? (int) $profile['decodedBytes'] : 0,
            'prices_bytes' => isset($prices['decodedBytes']) ? (int) $prices['decodedBytes'] : 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ), true, true, \Db::INSERT);

        // Limpieza amortizada: aproximadamente una vez cada cien logins.
        if (mt_rand(1, 100) === 1) {
            \Db::getInstance()->delete(
                'hoffens_b2b_login_metric',
                '`created_at` < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)'
            );
        }
        return $result;
    }
}
