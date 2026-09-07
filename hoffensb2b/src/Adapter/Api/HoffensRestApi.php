<?php

namespace Hoffens\B2B\Adapter\Api;

use Hoffens\B2B\Domain\Customer\CardCode;
use Hoffens\B2B\Domain\Document\DocumentType;
use Hoffens\B2B\Domain\Catalog\CatalogItem;
use Hoffens\B2B\Domain\Pricing\CustomerPrice;
use Hoffens\B2B\Exception\ApiException;
use Hoffens\B2B\Infrastructure\Http\HoffensApiClient;
use Hoffens\B2B\Port\HoffensApiInterface;

final class HoffensRestApi implements HoffensApiInterface
{
    private $client;

    public function __construct(HoffensApiClient $client)
    {
        $this->client = $client;
    }

    public function health() { return $this->client->get('health'); }

    public function loginSnapshot($cardCode)
    {
        $cardCode = $this->cardCode($cardCode);
        $batch = $this->client->getManyWithMetrics(array(
            'customer' => 'clientes/' . $cardCode,
            'prices' => 'clientes/' . $cardCode . '/precios',
        ));
        $snapshot = $batch['data'];
        $snapshot['prices'] = $this->mapPrices($snapshot['prices']);
        $snapshot['_telemetry'] = $batch['metrics'];
        return $snapshot;
    }

    public function customer($cardCode)
    {
        return $this->client->get('clientes/' . $this->cardCode($cardCode));
    }

    public function customerPrices($cardCode)
    {
        return $this->mapPrices($this->client->get('clientes/' . $this->cardCode($cardCode) . '/precios'));
    }

    public function catalog()
    {
        $items = array();
        foreach ($this->client->get('articulos') as $row) {
            $items[] = new CatalogItem(
                isset($row['itemCode']) ? $row['itemCode'] : '',
                isset($row['nombre']) ? $row['nombre'] : '',
                isset($row['multiploCompra']) ? $row['multiploCompra'] : 0,
                isset($row['multiploVenta']) ? $row['multiploVenta'] : 0,
                isset($row['stock']) ? $row['stock'] : 0,
                $row
            );
        }
        return $items;
    }

    public function customerOrders($cardCode, $page, $pageSize)
    {
        $page = max(1, (int) $page);
        $pageSize = (int) $pageSize;
        if ($pageSize < 1 || $pageSize > 200) {
            throw new ApiException('pageSize debe estar entre 1 y 200.');
        }
        return $this->client->get(
            'clientes/' . $this->cardCode($cardCode) . '/pedidos?page=' . $page . '&pageSize=' . $pageSize
        );
    }

    public function customerDocuments($cardCode)
    {
        return $this->client->get('clientes/' . $this->cardCode($cardCode) . '/documentos');
    }

    public function document($type, $docEntry)
    {
        $type = new DocumentType($type);
        if ((int) $docEntry <= 0) {
            throw new ApiException('DocEntry inválido.');
        }
        return $this->client->get('documentos/' . $type->value() . '/' . (int) $docEntry);
    }

    public function submitOrderRequest(array $payload, $idempotencyKey)
    {
        return $this->client->post('solicitudes-pedido', $payload, $idempotencyKey);
    }

    public function orderRequestStatus($requestId)
    {
        return $this->client->get('solicitudes-pedido/' . $this->segment($requestId));
    }

    public function submitPayment(array $payload, $idempotencyKey)
    {
        return $this->client->post('pagos', $payload, $idempotencyKey);
    }

    public function paymentStatus($requestId)
    {
        return $this->client->get('pagos/' . $this->segment($requestId));
    }

    private function cardCode($value)
    {
        $cardCode = $value instanceof CardCode ? $value : new CardCode($value);
        return rawurlencode($cardCode->value());
    }

    private function segment($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new ApiException('El identificador no puede estar vacío.');
        }
        return rawurlencode($value);
    }

    private function mapPrices(array $rows)
    {
        $prices = array();
        foreach ($rows as $row) {
            $prices[] = new CustomerPrice(
                isset($row['itemCode']) ? $row['itemCode'] : '',
                $this->normalizeCurrency(isset($row['moneda']) ? $row['moneda'] : ''),
                isset($row['precio']) ? $row['precio'] : 0
            );
        }
        return $prices;
    }

    private function normalizeCurrency($currency)
    {
        $currency = trim((string) $currency);
        // El contrato limita esta primera etapa a moneda local; SAP retorna vacío o "$".
        if ($currency === '' || $currency === '$') {
            return 'CLP';
        }
        return strtoupper($currency);
    }
}
