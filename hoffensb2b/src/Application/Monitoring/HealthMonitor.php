<?php

namespace Hoffens\B2B\Application\Monitoring;

use Hoffens\B2B\Port\HoffensApiInterface;
use Hoffens\B2B\Port\IncidentStoreInterface;

final class HealthMonitor
{
    const FINGERPRINT = 'api_health';

    private $api;
    private $incidents;
    private $maxMs;
    private $cooldownMinutes;

    public function __construct(
        HoffensApiInterface $api,
        IncidentStoreInterface $incidents,
        $maxMs,
        $cooldownMinutes
    ) {
        $this->api = $api;
        $this->incidents = $incidents;
        $this->maxMs = max(100, (int) $maxMs);
        $this->cooldownMinutes = max(1, (int) $cooldownMinutes);
    }

    public function execute()
    {
        $startedAt = microtime(true);
        try {
            $health = $this->api->health();
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            $status = isset($health['status']) ? strtolower((string) $health['status']) : '';
            $database = isset($health['database']) ? strtolower((string) $health['database']) : '';
            if ($status !== 'healthy' || $database !== 'available') {
                throw new \RuntimeException('Healthcheck no saludable (API=' . $status . ', BD=' . $database . ').');
            }
            $this->incidents->reportRecovery(self::FINGERPRINT, 'API y base de datos disponibles en ' . $elapsedMs . ' ms.');
            $slow = $elapsedMs > $this->maxMs;
            return array(
                'healthy' => true,
                'slow' => $slow,
                'elapsedMs' => $elapsedMs,
                'message' => $slow
                    ? 'Healthcheck lento: ' . $elapsedMs . ' ms (máximo ' . $this->maxMs . ' ms).'
                    : 'healthy',
            );
        } catch (\Exception $exception) {
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->incidents->reportFailure(self::FINGERPRINT, $exception->getMessage(), $this->cooldownMinutes);
            return array('healthy' => false, 'slow' => false, 'elapsedMs' => $elapsedMs, 'message' => $exception->getMessage());
        }
    }
}
