<?php

namespace Hoffens\B2B\Tests\Unit\Application\Order;

use Hoffens\B2B\Application\Order\BuildOrderRequest;
use Hoffens\B2B\Exception\IntegrationException;
use PHPUnit\Framework\TestCase;

final class BuildOrderRequestTest extends TestCase
{
    public function testBuildsOnlyContractFields()
    {
        $payload = (new BuildOrderRequest())->execute(
            'C770672790',
            '2026-09-10',
            'DESPACHO',
            'Pedido web 123',
            array(array('itemCode' => '80430', 'cantidad' => 2))
        );

        $this->assertSame(array('cardCode', 'fechaEspera', 'direccionDespacho', 'glosa', 'lineas'), array_keys($payload));
        $this->assertSame('DESPACHO', $payload['direccionDespacho']);
        $this->assertSame(2.0, $payload['lineas'][0]['cantidad']);
    }

    public function testRejectsAddressWithoutSapCode()
    {
        $this->expectException(IntegrationException::class);
        (new BuildOrderRequest())->execute(
            'C770672790',
            '2026-09-10',
            '',
            '',
            array(array('itemCode' => '80430', 'cantidad' => 1))
        );
    }

    public function testRejectsDuplicateSku()
    {
        $this->expectException(IntegrationException::class);
        (new BuildOrderRequest())->execute(
            'C770672790',
            '2026-09-10',
            'DESPACHO',
            '',
            array(
                array('itemCode' => '80430', 'cantidad' => 1),
                array('itemCode' => '80430', 'cantidad' => 2),
            )
        );
    }
}
