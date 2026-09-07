<?php

namespace Hoffens\B2B\Application\Diagnostics;

use Hoffens\B2B\Exception\ApiException;
use Hoffens\B2B\Port\HoffensApiInterface;

final class CheckEndpoint
{
    private $api;

    public function __construct(HoffensApiInterface $api)
    {
        $this->api = $api;
    }

    public function execute($endpoint, array $parameters = array())
    {
        $endpoint = (string) $endpoint;
        $startedAt = microtime(true);
        try {
            $data = $this->call($endpoint, $parameters);
            return EndpointCheckResult::success(
                $endpoint,
                $this->elapsedMs($startedAt),
                $this->summarize($endpoint, $data)
            );
        } catch (ApiException $exception) {
            return EndpointCheckResult::failure(
                $endpoint,
                $exception->statusCode(),
                $this->elapsedMs($startedAt),
                $exception->getMessage()
            );
        } catch (\Exception $exception) {
            return EndpointCheckResult::failure(
                $endpoint,
                0,
                $this->elapsedMs($startedAt),
                $exception->getMessage()
            );
        }
    }

    private function call($endpoint, array $parameters)
    {
        $cardCode = isset($parameters['cardCode']) ? $parameters['cardCode'] : '';
        switch ($endpoint) {
            case 'health':
                return $this->api->health();
            case 'catalog':
                return $this->api->catalog();
            case 'customer':
                return $this->api->customer($cardCode);
            case 'prices':
                return $this->api->customerPrices($cardCode);
            case 'orders':
                return $this->api->customerOrders($cardCode, 1, 10);
            case 'documents':
                return $this->api->customerDocuments($cardCode);
            case 'document':
                return $this->api->document(
                    isset($parameters['documentType']) ? $parameters['documentType'] : '',
                    isset($parameters['docEntry']) ? $parameters['docEntry'] : 0
                );
            default:
                throw new ApiException('Endpoint de diagnóstico no permitido.');
        }
    }

    private function summarize($endpoint, $data)
    {
        switch ($endpoint) {
            case 'health':
                return array(
                    'Estado' => isset($data['status']) ? $data['status'] : 'desconocido',
                    'Base de datos' => isset($data['database']) ? $data['database'] : 'desconocida',
                    'Tiempo reportado por API' => isset($data['responseTimeMs']) ? $data['responseTimeMs'] . ' ms' : '—',
                );
            case 'catalog':
                return array('Artículos' => is_array($data) ? count($data) : 0);
            case 'prices':
                return array('Precios' => is_array($data) ? count($data) : 0);
            case 'customer':
                return array(
                    'Cliente encontrado' => isset($data['cardCode']) ? 'Sí' : 'No',
                    'Direcciones' => isset($data['direcciones']) && is_array($data['direcciones']) ? count($data['direcciones']) : 0,
                    'Movimientos cuenta corriente' => isset($data['cuentaCorriente']) && is_array($data['cuentaCorriente']) ? count($data['cuentaCorriente']) : 0,
                );
            case 'orders':
                return array(
                    'Registros obtenidos' => isset($data['items']) && is_array($data['items']) ? count($data['items']) : 0,
                    'Registros totales' => isset($data['pagination']['totalRecords']) ? (int) $data['pagination']['totalRecords'] : 0,
                );
            case 'documents':
                return array('Documentos' => is_array($data) ? count($data) : 0);
            case 'document':
                return array(
                    'Documento encontrado' => is_array($data) && count($data) > 0 ? 'Sí' : 'No',
                    'Ítems' => isset($data['items']) && is_array($data['items']) ? count($data['items']) : 0,
                );
        }
        return array();
    }

    private function elapsedMs($startedAt)
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}

