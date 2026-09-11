<?php

namespace Hoffens\B2B\Tests\Unit\Application\Transaction;

use Hoffens\B2B\Application\Transaction\ProcessTransactionalOutbox;
use Hoffens\B2B\Exception\ApiException;
use Hoffens\B2B\Port\HoffensApiInterface;
use Hoffens\B2B\Port\TransactionOutboxInterface;
use PHPUnit\Framework\TestCase;

final class ProcessTransactionalOutboxTest extends TestCase
{
    public function testShadowReportsPendingWithoutClaimingOrPosting()
    {
        $outbox = new WorkerFakeOutbox(array($this->row()));
        $api = new WorkerFakeApi();

        $result = (new ProcessTransactionalOutbox($outbox, $api))->execute('shadow');

        $this->assertSame(1, $result['pending']);
        $this->assertSame(0, $result['claimed']);
        $this->assertSame(0, $outbox->claims);
        $this->assertSame(0, $api->posts);
    }

    public function testAcceptedOrderIsScheduledForPolling()
    {
        $outbox = new WorkerFakeOutbox(array($this->row()));
        $api = new WorkerFakeApi(array('estado' => 'pendiente', 'solicitudId' => 'REQ-1'));

        $result = (new ProcessTransactionalOutbox($outbox, $api))->execute('rest');

        $this->assertSame(1, $result['accepted']);
        $this->assertSame('accepted', $outbox->lastAction);
        $this->assertSame(1, $api->posts);
    }

    public function testAcceptedOperationIsPolledUntilCompleted()
    {
        $row = $this->row();
        $row['status'] = 'accepted';
        $row['remote_request_id'] = 'REQ-1';
        $outbox = new WorkerFakeOutbox(array($row));
        $api = new WorkerFakeApi(array('estado' => 'creadaSap', 'numAtCard' => 'NV-10'));

        $result = (new ProcessTransactionalOutbox($outbox, $api))->execute('rest');

        $this->assertSame(1, $result['completed']);
        $this->assertSame('completed', $outbox->lastAction);
        $this->assertSame(1, $api->polls);
    }

    public function testConflictRequiresManualReviewWithoutRetry()
    {
        $outbox = new WorkerFakeOutbox(array($this->row()));
        $api = new WorkerFakeApi(new ApiException('Conflicto de idempotencia.', 409));

        $result = (new ProcessTransactionalOutbox($outbox, $api))->execute('rest');

        $this->assertSame(1, $result['manualReview']);
        $this->assertSame('manualReview', $outbox->lastAction);
    }

    private function row()
    {
        $json = '{"cardCode":"C1"}';
        return array(
            'id_outbox' => 1,
            'operation_type' => 'order',
            'idempotency_key' => 'ps-order-1-10',
            'payload' => $json,
            'payload_hash' => hash('sha256', $json),
            'remote_request_id' => '',
            'remote_reference' => '',
            'remote_status_url' => '',
            'status' => 'queued',
            'attempts' => 0,
            'lock_token' => 'lock-1',
        );
    }
}

final class WorkerFakeApi implements HoffensApiInterface
{
    public $posts = 0;
    public $polls = 0;
    private $response;
    public function __construct($response = array()) { $this->response = $response; }
    private function result() { if ($this->response instanceof \Exception) { throw $this->response; } return $this->response; }
    public function submitOrderRequest(array $payload, $key) { $this->posts++; return $this->result(); }
    public function orderRequestStatus($id) { $this->polls++; return $this->result(); }
    public function submitPayment(array $payload, $key) { $this->posts++; return $this->result(); }
    public function paymentStatus($id) { $this->polls++; return $this->result(); }
    public function health() { return array(); }
    public function loginSnapshot($cardCode) { return array(); }
    public function customer($cardCode) { return array(); }
    public function customerPrices($cardCode) { return array(); }
    public function catalog() { return array(); }
    public function customerOrders($cardCode, $page, $pageSize) { return array(); }
    public function customerDocuments($cardCode) { return array(); }
    public function document($type, $docEntry) { return array(); }
}

final class WorkerFakeOutbox implements TransactionOutboxInterface
{
    private $rows;
    public $claims = 0;
    public $lastAction;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function enqueue(\Hoffens\B2B\Domain\Transaction\TransactionalOperation $operation) { return 1; }
    public function countDispatchable() { return count($this->rows); }
    public function claimBatch($limit) { $this->claims++; return $this->rows; }
    public function markAccepted($id, $token, $requestId, $status, $reference, $url, $delay) { $this->lastAction = 'accepted'; }
    public function markCompleted($id, $token, $status, $reference) { $this->lastAction = 'completed'; }
    public function markObserved($id, $token, $status, $error) { $this->lastAction = 'observed'; }
    public function markRetry($id, $token, $error, $delay) { $this->lastAction = 'retried'; }
    public function markManualReview($id, $token, $error) { $this->lastAction = 'manualReview'; }
}
