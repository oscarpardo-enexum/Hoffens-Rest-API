<?php

namespace Hoffens\B2B\Tests\Unit\Application\Payment;

use Hoffens\B2B\Application\Payment\BuildPaymentRequest;
use Hoffens\B2B\Exception\IntegrationException;
use PHPUnit\Framework\TestCase;

final class BuildPaymentRequestTest extends TestCase
{
    public function testBuildsPaymentUsingInvoiceMinusCreditNote()
    {
        $payload = (new BuildPaymentRequest())->execute(
            'TBK-123', 'C1', '2026-09-11', 90000, 'Autorización 123',
            array(
                array('tipoDocumento' => 'FC', 'docEntry' => 10, 'montoAplicado' => 100000),
                array('tipoDocumento' => 'NC', 'docEntry' => 20, 'montoAplicado' => 10000),
            )
        );

        $this->assertSame(90000.0, $payload['montoTransaccion']);
        $this->assertSame(10, $payload['documentos'][0]['docEntry']);
    }

    public function testRejectsMismatchedTotal()
    {
        $this->expectException(IntegrationException::class);
        (new BuildPaymentRequest())->execute(
            'TBK-123', 'C1', '2026-09-11', 95000, 'Autorización 123',
            array(array('tipoDocumento' => 'FC', 'docEntry' => 10, 'montoAplicado' => 100000))
        );
    }

    public function testRejectsDocumentWithoutDocEntry()
    {
        $this->expectException(IntegrationException::class);
        (new BuildPaymentRequest())->execute(
            'TBK-123', 'C1', '2026-09-11', 100000, 'Autorización 123',
            array(array('tipoDocumento' => 'FC', 'docEntry' => 0, 'montoAplicado' => 100000))
        );
    }
}
