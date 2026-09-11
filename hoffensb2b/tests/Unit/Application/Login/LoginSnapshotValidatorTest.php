<?php

namespace Hoffens\B2B\Tests\Unit\Application\Login;

use Hoffens\B2B\Application\Login\LoginSnapshotValidator;
use Hoffens\B2B\Domain\Customer\CardCode;
use Hoffens\B2B\Exception\IntegrationException;
use PHPUnit\Framework\TestCase;

final class LoginSnapshotValidatorTest extends TestCase
{
    public function testAcceptsCompleteContractSnapshot()
    {
        (new LoginSnapshotValidator())->validate(new CardCode('C1'), $this->snapshot());
        $this->assertTrue(true);
    }

    public function testRejectsProfileForAnotherCustomer()
    {
        $snapshot = $this->snapshot();
        $snapshot['customer']['cardCode'] = 'C2';
        $this->expectException(IntegrationException::class);
        (new LoginSnapshotValidator())->validate(new CardCode('C1'), $snapshot);
    }

    public function testRejectsEmptyPrices()
    {
        $snapshot = $this->snapshot();
        $snapshot['prices'] = array();
        $this->expectException(IntegrationException::class);
        (new LoginSnapshotValidator())->validate(new CardCode('C1'), $snapshot);
    }

    public function testAcceptsNegativeDebtAsCustomerCreditBalance()
    {
        $snapshot = $this->snapshot();
        $snapshot['customer']['deudaTotal'] = -1500;
        (new LoginSnapshotValidator())->validate(new CardCode('C1'), $snapshot);
        $this->assertTrue(true);
    }

    private function snapshot()
    {
        return array(
            'customer' => array(
                'cardCode' => 'C1', 'razonSocial' => 'Cliente',
                'lineaCredito' => 1000, 'deudaTotal' => 200,
                'cobranza' => array('saldoVencido' => 10, 'documentosVencidos' => 1),
                'direcciones' => array(), 'cuentaCorriente' => array(),
            ),
            'prices' => array(array('itemCode' => 'SKU1')),
        );
    }
}
