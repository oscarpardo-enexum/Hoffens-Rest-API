<?php

namespace Hoffens\B2B\Application\Transaction;

use Hoffens\B2B\Domain\Transaction\TransactionalOperation;
use Hoffens\B2B\Exception\ApiException;
use Hoffens\B2B\Exception\IntegrationException;
use Hoffens\B2B\Port\HoffensApiInterface;
use Hoffens\B2B\Port\TransactionOutboxInterface;

final class ProcessTransactionalOutbox
{
    private $outbox;
    private $api;
    private $maxAttempts;

    public function __construct(TransactionOutboxInterface $outbox, HoffensApiInterface $api, $maxAttempts = 8)
    {
        $this->outbox = $outbox;
        $this->api = $api;
        $this->maxAttempts = max(1, min(20, (int) $maxAttempts));
    }

    public function execute($mode, $limit = 10)
    {
        $result = array(
            'mode' => (string) $mode,
            'pending' => $this->outbox->countDispatchable(),
            'claimed' => 0,
            'accepted' => 0,
            'completed' => 0,
            'observed' => 0,
            'retried' => 0,
            'manualReview' => 0,
        );
        if ($mode !== 'rest') {
            return $result;
        }

        $rows = $this->outbox->claimBatch($limit);
        $result['claimed'] = count($rows);
        foreach ($rows as $row) {
            $outcome = $this->process($row);
            $result[$outcome]++;
        }
        $result['pending'] = $this->outbox->countDispatchable();
        return $result;
    }

    private function process(array $row)
    {
        $id = (int) $row['id_outbox'];
        $lockToken = (string) $row['lock_token'];
        $attempts = (int) $row['attempts'];
        try {
            $payload = $this->payload($row);
            $requestId = trim((string) $row['remote_request_id']);
            $response = $requestId === ''
                ? $this->submit((string) $row['operation_type'], $payload, (string) $row['idempotency_key'])
                : $this->poll((string) $row['operation_type'], $requestId);

            $remote = $this->remoteResult($response, $requestId, $row);
            if ($remote['terminal'] === 'completed') {
                $this->outbox->markCompleted($id, $lockToken, $remote['status'], $remote['reference']);
                return 'completed';
            }
            if ($remote['terminal'] === 'observed') {
                $this->outbox->markObserved($id, $lockToken, $remote['status'], $remote['message']);
                return 'observed';
            }
            if ($remote['requestId'] === '') {
                throw new IntegrationException('La API aceptó la operación sin entregar un identificador de seguimiento.');
            }
            $this->outbox->markAccepted(
                $id,
                $lockToken,
                $remote['requestId'],
                $remote['status'],
                $remote['reference'],
                $remote['statusUrl'],
                $remote['status'] === 'reintento' ? 60 : 30
            );
            return 'accepted';
        } catch (ApiException $exception) {
            if ($this->isPermanentHttpError($exception->statusCode()) || $attempts + 1 >= $this->maxAttempts) {
                $this->outbox->markManualReview($id, $lockToken, $exception->getMessage());
                return 'manualReview';
            }
            $this->outbox->markRetry($id, $lockToken, $exception->getMessage(), $this->retryDelay($attempts));
            return 'retried';
        } catch (IntegrationException $exception) {
            $this->outbox->markManualReview($id, $lockToken, $exception->getMessage());
            return 'manualReview';
        } catch (\Exception $exception) {
            if ($attempts + 1 >= $this->maxAttempts) {
                $this->outbox->markManualReview($id, $lockToken, 'Fallo interno al procesar la operación.');
                return 'manualReview';
            }
            $this->outbox->markRetry($id, $lockToken, 'Fallo interno al procesar la operación.', $this->retryDelay($attempts));
            return 'retried';
        }
    }

    private function payload(array $row)
    {
        $json = (string) $row['payload'];
        if (!hash_equals((string) $row['payload_hash'], hash('sha256', $json))) {
            throw new IntegrationException('El payload almacenado no coincide con su hash.');
        }
        $payload = json_decode($json, true);
        if (!is_array($payload) || count($payload) === 0) {
            throw new IntegrationException('El payload almacenado no es JSON válido.');
        }
        return $payload;
    }

    private function submit($type, array $payload, $idempotencyKey)
    {
        if ($type === TransactionalOperation::ORDER) {
            return $this->api->submitOrderRequest($payload, $idempotencyKey);
        }
        if ($type === TransactionalOperation::PAYMENT) {
            return $this->api->submitPayment($payload, $idempotencyKey);
        }
        throw new IntegrationException('Tipo de operación no soportado por el trabajador.');
    }

    private function poll($type, $requestId)
    {
        if ($type === TransactionalOperation::ORDER) {
            return $this->api->orderRequestStatus($requestId);
        }
        if ($type === TransactionalOperation::PAYMENT) {
            return $this->api->paymentStatus($requestId);
        }
        throw new IntegrationException('Tipo de operación no soportado por el seguimiento.');
    }

    private function remoteResult(array $response, $currentRequestId, array $row)
    {
        if (isset($response['data']) && is_array($response['data'])) {
            $response = $response['data'];
        }
        $status = strtolower(trim((string) $this->first($response, array('estado', 'status'))));
        if ($status === '') {
            throw new IntegrationException('La API no informó el estado de la operación.');
        }
        $normalized = str_replace(array('_', '-', ' '), '', $status);
        $terminal = null;
        if (in_array($normalized, array('creadasap', 'creadoensap', 'completada', 'completed'), true)) {
            $terminal = 'completed';
        } elseif (in_array($normalized, array('observada', 'observado', 'rejected'), true)) {
            $terminal = 'observed';
        } elseif (!in_array($normalized, array('pendiente', 'procesando', 'reintento', 'accepted', 'processing'), true)) {
            throw new IntegrationException('La API informó un estado transaccional desconocido: ' . $status);
        }

        return array(
            'status' => $status,
            'terminal' => $terminal,
            'requestId' => trim((string) ($this->first($response, array(
                'solicitudId', 'pagoId', 'requestId', 'id',
            )) ?: $currentRequestId)),
            'reference' => trim((string) ($this->first($response, array(
                'numAtCard', 'numeroSap', 'docNum', 'referenciaSap',
            )) ?: (isset($row['remote_reference']) ? $row['remote_reference'] : ''))),
            'statusUrl' => trim((string) ($this->first($response, array('estadoUrl', 'statusUrl'))
                ?: (isset($row['remote_status_url']) ? $row['remote_status_url'] : ''))),
            'message' => trim((string) ($this->first($response, array('detalle', 'detail', 'mensaje', 'message'))
                ?: 'SAP observó la operación sin entregar detalle.')),
        );
    }

    private function first(array $data, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }
        return null;
    }

    private function isPermanentHttpError($statusCode)
    {
        return in_array((int) $statusCode, array(400, 401, 403, 404, 409, 422), true);
    }

    private function retryDelay($attempts)
    {
        return min(3600, 30 * (int) pow(2, min(7, max(0, (int) $attempts))));
    }
}
