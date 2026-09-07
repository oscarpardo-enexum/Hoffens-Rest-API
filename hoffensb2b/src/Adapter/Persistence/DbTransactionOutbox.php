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
        ));
        if (!$inserted) {
            throw new IntegrationException('No fue posible encolar la operación transaccional.');
        }
        return (int) $db->Insert_ID();
    }
}
