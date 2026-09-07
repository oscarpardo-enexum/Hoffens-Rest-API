<?php

namespace Hoffens\B2B\Port;

use Hoffens\B2B\Domain\Transaction\TransactionalOperation;

interface TransactionOutboxInterface
{
    public function enqueue(TransactionalOperation $operation);
}
