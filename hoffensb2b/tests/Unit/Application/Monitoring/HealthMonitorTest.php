<?php

namespace Hoffens\B2B\Tests\Unit\Application\Monitoring;

use Hoffens\B2B\Application\Monitoring\HealthMonitor;
use Hoffens\B2B\Port\HoffensApiInterface;
use Hoffens\B2B\Port\IncidentStoreInterface;
use PHPUnit\Framework\TestCase;

final class HealthMonitorTest extends TestCase
{
    public function testHealthyDependenciesRecoverIncident()
    {
        $store = new MonitoringIncidentStore();
        $result = (new HealthMonitor(new MonitoringApi(array(
            'status' => 'healthy', 'database' => 'available',
        )), $store, 2000, 30))->execute();

        $this->assertTrue($result['healthy']);
        $this->assertSame(1, $store->recoveries);
        $this->assertSame(0, $store->failures);
    }

    public function testUnavailableDependencyOpensIncident()
    {
        $store = new MonitoringIncidentStore();
        $result = (new HealthMonitor(new MonitoringApi(array(
            'status' => 'degraded', 'database' => 'unavailable',
        )), $store, 2000, 30))->execute();

        $this->assertFalse($result['healthy']);
        $this->assertSame(1, $store->failures);
        $this->assertSame(0, $store->recoveries);
    }

    public function testSlowHealthyResponseIsReportedWithoutOpeningOutage()
    {
        $store = new MonitoringIncidentStore();
        $result = (new HealthMonitor(new MonitoringApi(array(
            'status' => 'healthy', 'database' => 'available',
        ), 120), $store, 100, 30))->execute();

        $this->assertTrue($result['healthy']);
        $this->assertTrue($result['slow']);
        $this->assertSame(0, $store->failures);
        $this->assertSame(1, $store->recoveries);
    }
}

final class MonitoringIncidentStore implements IncidentStoreInterface
{
    public $failures = 0;
    public $recoveries = 0;
    public function reportFailure($fingerprint, $message, $cooldownMinutes) { ++$this->failures; }
    public function reportRecovery($fingerprint, $message) { ++$this->recoveries; }
    public function pendingNotifications() { return array(); }
    public function markNotified($incidentId) { return true; }
}

final class MonitoringApi implements HoffensApiInterface
{
    private $health;
    private $delayMs;
    public function __construct(array $health, $delayMs = 0) { $this->health = $health; $this->delayMs = $delayMs; }
    public function health() { if ($this->delayMs > 0) { usleep($this->delayMs * 1000); } return $this->health; }
    public function loginSnapshot($cardCode) { return array(); }
    public function customer($cardCode) { return array(); }
    public function customerPrices($cardCode) { return array(); }
    public function catalog() { return array(); }
    public function customerOrders($cardCode, $page, $pageSize) { return array(); }
    public function customerDocuments($cardCode) { return array(); }
    public function document($type, $docEntry) { return array(); }
    public function submitOrderRequest(array $payload, $idempotencyKey) { return array(); }
    public function orderRequestStatus($requestId) { return array(); }
    public function submitPayment(array $payload, $idempotencyKey) { return array(); }
    public function paymentStatus($requestId) { return array(); }
}
