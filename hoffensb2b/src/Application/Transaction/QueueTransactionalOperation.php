<?php

namespace Hoffens\B2B\Application\Transaction;

use Hoffens\B2B\Domain\Transaction\TransactionalOperation;
use Hoffens\B2B\Port\TransactionOutboxInterface;

final class QueueTransactionalOperation
{
    private $outbox;

    public function __construct(TransactionOutboxInterface $outbox)
    {
        $this->outbox = $outbox;
    }

    public function execute(TransactionalOperation $operation)
    {
        return $this->outbox->enqueue($operation);
    }
}
