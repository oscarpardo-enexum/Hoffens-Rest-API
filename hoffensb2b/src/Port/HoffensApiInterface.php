<?php

namespace Hoffens\B2B\Port;

interface HoffensApiInterface
{
    public function health();
    public function loginSnapshot($cardCode);
    public function customer($cardCode);
    public function customerPrices($cardCode);
    public function catalog();
    public function customerOrders($cardCode, $page, $pageSize);
    public function customerDocuments($cardCode);
    public function document($type, $docEntry);
    public function submitOrderRequest(array $payload, $idempotencyKey);
    public function orderRequestStatus($requestId);
    public function submitPayment(array $payload, $idempotencyKey);
    public function paymentStatus($requestId);
}
