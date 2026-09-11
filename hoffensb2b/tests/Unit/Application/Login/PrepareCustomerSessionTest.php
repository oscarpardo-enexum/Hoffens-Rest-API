<?php

namespace Hoffens\B2B\Tests\Unit\Application\Login;

use Hoffens\B2B\Application\Access\CustomerAccessPolicy;
use Hoffens\B2B\Application\Login\PrepareCustomerSession;
use Hoffens\B2B\Port\CustomerCardCodeProviderInterface;
use Hoffens\B2B\Port\CustomerPriceSynchronizerInterface;
use Hoffens\B2B\Port\HoffensApiInterface;
use Hoffens\B2B\Port\LoginSnapshotCacheInterface;
use PHPUnit\Framework\TestCase;

final class PrepareCustomerSessionTest extends TestCase
{
    public function testEveryAuthenticatedCustomerWithCardCodeCallsApi()
    {
        $api = new FakeApi();
        $useCase = $this->useCase($api, 'C1');

        $decision = $useCase->execute(10);

        $this->assertTrue($decision->isAllowed());
        $this->assertTrue($decision->isB2B());
        $this->assertSame('b2b_ready', $decision->reason());
        $this->assertSame(1, $api->loginCalls);
    }

    public function testB2CWithoutCardCodeRemainsObserver()
    {
        $api = new FakeApi();
        $decision = $this->useCase($api, null)->execute(10);

        $this->assertTrue($decision->isAllowed());
        $this->assertFalse($decision->isB2B());
        $this->assertSame('b2c_observer', $decision->reason());
        $this->assertSame(0, $api->loginCalls);
    }

    public function testB2BLoadsProfileAndPricesOnce()
    {
        $api = new FakeApi();
        $useCase = $this->useCase($api, 'C1');

        $decision = $useCase->execute(10);

        $this->assertTrue($decision->isAllowed());
        $this->assertTrue($decision->isB2B());
        $this->assertSame(1, $api->loginCalls);
    }

    public function testCustomerWithoutCardCodeRemainsObserverWithoutCallingApi()
    {
        $api = new FakeApi();
        $useCase = $this->useCase($api, null);

        $decision = $useCase->execute(10);

        $this->assertTrue($decision->isAllowed());
        $this->assertFalse($decision->isB2B());
        $this->assertSame('b2c_observer', $decision->reason());
        $this->assertSame(0, $api->loginCalls);
    }

    private function useCase(FakeApi $api, $cardCode)
    {
        return new PrepareCustomerSession(
            new CustomerAccessPolicy(new FakeCardCodes($cardCode)),
            $api,
            new FakeCache(),
            new FakePriceSynchronizer(),
            0
        );
    }
}

final class FakeCardCodes implements CustomerCardCodeProviderInterface
{
    private $cardCode;
    public function __construct($cardCode) { $this->cardCode = $cardCode; }
    public function cardCodeForCustomer($customerId) { return $this->cardCode; }
}

final class FakeCache implements LoginSnapshotCacheInterface
{
    public function getFresh($customerId, $maxAgeSeconds) { return null; }
    public function put($customerId, array $snapshot) { return true; }
}

final class FakePriceSynchronizer implements CustomerPriceSynchronizerInterface
{
    public function synchronize($customerId, array $prices)
    {
        return array(
            'received' => count($prices),
            'mapped' => count($prices),
            'unmatched' => 0,
            'written' => count($prices),
            'groupId' => 8,
            'unchanged' => false,
        );
    }
}

final class FakeApi implements HoffensApiInterface
{
    public $loginCalls = 0;
    public function loginSnapshot($cardCode)
    {
        $this->loginCalls++;
        return array(
            'customer' => array(
                'cardCode' => 'C1',
                'razonSocial' => 'Cliente de prueba',
                'lineaCredito' => 1000,
                'deudaTotal' => 100,
                'cobranza' => array('saldoVencido' => 0, 'documentosVencidos' => 0),
                'direcciones' => array(),
                'cuentaCorriente' => array(),
            ),
            'prices' => array(array('itemCode' => 'SKU1')),
        );
    }
    public function health() { return array(); }
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
