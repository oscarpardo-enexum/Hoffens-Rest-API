<?php

namespace Hoffens\B2B\Adapter\Persistence;

use Hoffens\B2B\Domain\Transaction\TransactionalOperation;
use Hoffens\B2B\Exception\IntegrationException;
use Hoffens\B2B\Port\TransactionOutboxInterface;

final class DbTransactionOutbox implements TransactionOutboxInterface
{
    public function enqueue(TransactionalOperation $operation)
    {
        $db = \Db::getInstance();
        $existing = $db->getRow(
            'SELECT id_outbox,payload_hash,idempotency_key FROM `' . _DB_PREFIX_ . 'hoffens_b2b_outbox` WHERE '
            . "operation_type='" . pSQL($operation->type()) . "' AND local_reference='"
            . pSQL($operation->localReference()) . "'"
        );

        if ($existing) {
            if (!hash_equals((string) $existing['payload_hash'], $operation->payloadHash())
                || (string) $existing['idempotency_key'] !== $operation->idempotencyKey()) {
                throw new IntegrationException(
                    'La operación ya fue encolada con otro contenido o clave de idempotencia.'
                );
            }
            return (int) $existing['id_outbox'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $inserted = $db->insert('hoffens_b2b_outbox', array(
            'operation_type' => pSQL($operation->type()),
            'local_reference' => pSQL($operation->localReference()),
            'idempotency_key' => pSQL($operation->idempotencyKey()),
            'payload_hash' => pSQL($operation->payloadHash()),
            'payload' => pSQL($operation->payloadJson(), true),
            'status' => 'queued',
            'attempts' => 0,
            'available_at' => pSQL($now),
            'created_at' => pSQL($now),
            'updated_at' => pSQL($now),
        ), true);
        if (!$inserted) {
            throw new IntegrationException('No fue posible encolar la operación transaccional.');
        }
        return (int) $db->Insert_ID();
    }

    public function countDispatchable()
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'hoffens_b2b_outbox` '
            . "WHERE status IN ('queued','retry','accepted') AND available_at<=UTC_TIMESTAMP()"
        );
    }

    public function claimBatch($limit)
    {
        $limit = max(1, min(50, (int) $limit));
        $db = \Db::getInstance();
        $db->execute('START TRANSACTION');
        try {
            $rows = $db->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . 'hoffens_b2b_outbox` '
                . "WHERE status IN ('queued','retry','accepted') AND available_at<=UTC_TIMESTAMP() "
                . 'AND (lock_token IS NULL OR locked_at<DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)) '
                . 'ORDER BY available_at,id_outbox LIMIT ' . $limit . ' FOR UPDATE'
            );
            if (!$rows) {
                $db->execute('COMMIT');
                return array();
            }

            $token = $this->lockToken();
            $ids = array_map('intval', array_column($rows, 'id_outbox'));
            $updated = $db->execute(
                'UPDATE `' . _DB_PREFIX_ . 'hoffens_b2b_outbox` SET lock_token=\'' . pSQL($token)
                . "',locked_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id_outbox IN ("
                . implode(',', $ids) . ')'
            );
            if (!$updated) {
                throw new \RuntimeException('No fue posible bloquear el lote transaccional.');
            }
            $db->execute('COMMIT');
            foreach ($rows as &$row) {
                $row['lock_token'] = $token;
            }
            unset($row);
            return $rows;
        } catch (\Exception $exception) {
            $db->execute('ROLLBACK');
            throw $exception;
        }
    }

    public function markAccepted($id, $lockToken, $requestId, $remoteStatus, $remoteReference, $statusUrl, $delaySeconds)
    {
        return $this->updateLocked($id, $lockToken, array(
            'remote_request_id' => pSQL($this->limit($requestId, 100)),
            'remote_status' => pSQL($this->limit($remoteStatus, 20)),
            'remote_reference' => pSQL($this->limit($remoteReference, 100)),
            'remote_status_url' => pSQL($this->limit($statusUrl, 500)),
            'status' => 'accepted',
            'available_at' => pSQL(gmdate('Y-m-d H:i:s', time() + max(5, (int) $delaySeconds))),
            'last_error' => null,
        ), true);
    }

    public function markCompleted($id, $lockToken, $remoteStatus, $remoteReference)
    {
        return $this->updateLocked($id, $lockToken, array(
            'remote_status' => pSQL($this->limit($remoteStatus, 20)),
            'remote_reference' => pSQL($this->limit($remoteReference, 100)),
            'status' => 'completed',
            'last_error' => null,
            'completed_at' => pSQL(gmdate('Y-m-d H:i:s')),
        ), true);
    }

    public function markObserved($id, $lockToken, $remoteStatus, $error)
    {
        return $this->updateLocked($id, $lockToken, array(
            'remote_status' => pSQL($this->limit($remoteStatus, 20)),
            'status' => 'observed',
            'last_error' => pSQL($this->sanitizeError($error)),
            'completed_at' => pSQL(gmdate('Y-m-d H:i:s')),
        ), true);
    }

    public function markRetry($id, $lockToken, $error, $delaySeconds)
    {
        return $this->updateLocked($id, $lockToken, array(
            'status' => 'retry',
            'available_at' => pSQL(gmdate('Y-m-d H:i:s', time() + max(5, (int) $delaySeconds))),
            'last_error' => pSQL($this->sanitizeError($error)),
        ), true);
    }

    public function markManualReview($id, $lockToken, $error)
    {
        return $this->updateLocked($id, $lockToken, array(
            'status' => 'manual_review',
            'last_error' => pSQL($this->sanitizeError($error)),
            'completed_at' => pSQL(gmdate('Y-m-d H:i:s')),
        ), true);
    }

    private function updateLocked($id, $lockToken, array $fields, $incrementAttempts = false)
    {
        $fields['lock_token'] = null;
        $fields['locked_at'] = null;
        $fields['last_attempt_at'] = pSQL(gmdate('Y-m-d H:i:s'));
        $fields['updated_at'] = pSQL(gmdate('Y-m-d H:i:s'));
        if ($incrementAttempts) {
            $fields['attempts'] = array('type' => 'sql', 'value' => '`attempts`+1');
        }
        return (bool) \Db::getInstance()->update(
            'hoffens_b2b_outbox',
            $fields,
            'id_outbox=' . (int) $id . " AND lock_token='" . pSQL($lockToken) . "'",
            0,
            true
        );
    }

    private function sanitizeError($error)
    {
        $error = preg_replace('/[\r\n\t]+/', ' ', trim((string) $error));
        return \Tools::substr($error, 0, 500);
    }

    private function limit($value, $length)
    {
        return \Tools::substr(trim((string) $value), 0, (int) $length);
    }

    private function lockToken()
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Exception $exception) {
            return hash('sha256', uniqid('', true) . mt_rand());
        }
    }
}
