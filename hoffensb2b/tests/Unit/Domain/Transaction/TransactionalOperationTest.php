<?php

namespace Hoffens\B2B\Tests\Unit\Domain\Transaction;

use Hoffens\B2B\Domain\Transaction\TransactionalOperation;
use Hoffens\B2B\Exception\IntegrationException;
use PHPUnit\Framework\TestCase;

final class TransactionalOperationTest extends TestCase
{
    public function testHashIsStableForAssociativeKeyOrder()
    {
        $first = new TransactionalOperation('order', '123', 'ps-order-1-123', array(
            'cardCode' => 'C1', 'fechaEspera' => '2026-09-10',
        ));
        $second = new TransactionalOperation('order', '123', 'ps-order-1-123', array(
            'fechaEspera' => '2026-09-10', 'cardCode' => 'C1',
        ));

        $this->assertSame($first->payloadHash(), $second->payloadHash());
        $this->assertSame($first->payloadJson(), $second->payloadJson());
    }

    public function testRejectsUnknownOperationType()
    {
        $this->expectException(IntegrationException::class);
        new TransactionalOperation('unknown', '123', 'key', array('value' => 1));
    }
}
