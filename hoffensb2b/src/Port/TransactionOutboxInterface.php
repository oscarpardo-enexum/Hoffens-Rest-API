<?php

namespace Hoffens\B2B\Port;

use Hoffens\B2B\Domain\Transaction\TransactionalOperation;

interface TransactionOutboxInterface
{
    public function enqueue(TransactionalOperation $operation);
    public function countDispatchable();
    public function claimBatch($limit);
    public function markAccepted($id, $lockToken, $requestId, $remoteStatus, $remoteReference, $statusUrl, $delaySeconds);
    public function markCompleted($id, $lockToken, $remoteStatus, $remoteReference);
    public function markObserved($id, $lockToken, $remoteStatus, $error);
    public function markRetry($id, $lockToken, $error, $delaySeconds);
    public function markManualReview($id, $lockToken, $error);
}
