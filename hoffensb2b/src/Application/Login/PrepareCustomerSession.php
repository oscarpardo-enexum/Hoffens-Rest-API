<?php

namespace Hoffens\B2B\Application\Login;

use Hoffens\B2B\Application\Access\B2BAccessPolicy;
use Hoffens\B2B\Domain\Customer\CardCode;
use Hoffens\B2B\Exception\IntegrationException;
use Hoffens\B2B\Port\CustomerCardCodeProviderInterface;
use Hoffens\B2B\Port\CustomerPriceSynchronizerInterface;
use Hoffens\B2B\Port\HoffensApiInterface;
use Hoffens\B2B\Port\LoginSnapshotCacheInterface;

final class PrepareCustomerSession
{
    private $access;
    private $cardCodes;
    private $api;
    private $cache;
    private $priceSynchronizer;
    private $cacheTtl;

    public function __construct(
        B2BAccessPolicy $access,
        CustomerCardCodeProviderInterface $cardCodes,
        HoffensApiInterface $api,
        LoginSnapshotCacheInterface $cache,
        CustomerPriceSynchronizerInterface $priceSynchronizer,
        $cacheTtl
    ) {
        $this->access = $access;
        $this->cardCodes = $cardCodes;
        $this->api = $api;
        $this->cache = $cache;
        $this->priceSynchronizer = $priceSynchronizer;
        $this->cacheTtl = max(0, (int) $cacheTtl);
    }

    public function execute($customerId)
    {
        $startedAt = microtime(true);
        $metrics = array();
        $customerId = (int) $customerId;
        if (!$this->access->shouldIntegrateLogin($customerId)) {
            return LoginDecision::observer();
        }

        if ($this->cacheTtl > 0) {
            $cacheStartedAt = microtime(true);
            $cached = $this->cache->getFresh($customerId, $this->cacheTtl);
            $metrics['cacheMs'] = $this->elapsedMs($cacheStartedAt);
            if (is_array($cached)) {
                $metrics['totalUseCaseMs'] = $this->elapsedMs($startedAt);
                return LoginDecision::allowed($cached, true, $metrics);
            }
        }

        $mappingStartedAt = microtime(true);
        $rawCardCode = $this->cardCodes->cardCodeForCustomer($customerId);
        $metrics['cardCodeLookupMs'] = $this->elapsedMs($mappingStartedAt);
        if ($rawCardCode === null || trim($rawCardCode) === '') {
            $metrics['totalUseCaseMs'] = $this->elapsedMs($startedAt);
            return LoginDecision::denied('missing_card_code', $metrics);
        }

        try {
            $apiStartedAt = microtime(true);
            $snapshot = $this->api->loginSnapshot(new CardCode($rawCardCode));
            $metrics['apiBatchMs'] = $this->elapsedMs($apiStartedAt);
            if (isset($snapshot['_telemetry']) && is_array($snapshot['_telemetry'])) {
                $metrics['endpoints'] = $snapshot['_telemetry'];
                unset($snapshot['_telemetry']);
            }
            $metrics['priceCount'] = isset($snapshot['prices']) && is_array($snapshot['prices'])
                ? count($snapshot['prices']) : 0;
            $syncStartedAt = microtime(true);
            $sync = $this->priceSynchronizer->synchronize($customerId, $snapshot['prices']);
            $metrics['priceSyncMs'] = $this->elapsedMs($syncStartedAt);
            $metrics['priceMapped'] = (int) $sync['mapped'];
            $metrics['priceUnmatched'] = (int) $sync['unmatched'];
            $metrics['priceWritten'] = (int) $sync['written'];
            $metrics['priceGroupId'] = (int) $sync['groupId'];
            $metrics['priceUnchanged'] = !empty($sync['unchanged']);
            if ($this->cacheTtl > 0) {
                $this->cache->put($customerId, $snapshot);
            }
            $metrics['totalUseCaseMs'] = $this->elapsedMs($startedAt);
            return LoginDecision::allowed($snapshot, false, $metrics);
        } catch (IntegrationException $exception) {
            $metrics['totalUseCaseMs'] = $this->elapsedMs($startedAt);
            return LoginDecision::denied('integration_unavailable', $metrics);
        }
    }

    private function elapsedMs($startedAt)
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
