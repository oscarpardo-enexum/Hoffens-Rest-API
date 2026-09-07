<?php

namespace Hoffens\B2B\Domain\Transaction;

use Hoffens\B2B\Exception\IntegrationException;

final class TransactionalOperation
{
    const ORDER = 'order';
    const PAYMENT = 'payment';

    private $type;
    private $localReference;
    private $idempotencyKey;
    private $payload;
    private $payloadJson;
    private $payloadHash;

    public function __construct($type, $localReference, $idempotencyKey, array $payload)
    {
        $type = trim((string) $type);
        if (!in_array($type, array(self::ORDER, self::PAYMENT), true)) {
            throw new IntegrationException('El tipo de operación transaccional no es válido.');
        }

        $localReference = trim((string) $localReference);
        if ($localReference === '' || strlen($localReference) > 100) {
            throw new IntegrationException('La referencia local debe contener entre 1 y 100 caracteres.');
        }

        $idempotencyKey = trim((string) $idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            throw new IntegrationException('La clave de idempotencia debe contener entre 1 y 100 caracteres.');
        }

        if (count($payload) === 0) {
            throw new IntegrationException('El payload transaccional no puede estar vacío.');
        }

        $normalized = $this->normalize($payload);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new IntegrationException('No fue posible serializar el payload transaccional.');
        }

        $this->type = $type;
        $this->localReference = $localReference;
        $this->idempotencyKey = $idempotencyKey;
        $this->payload = $normalized;
        $this->payloadJson = $json;
        $this->payloadHash = hash('sha256', $json);
    }

    public function type() { return $this->type; }
    public function localReference() { return $this->localReference; }
    public function idempotencyKey() { return $this->idempotencyKey; }
    public function payload() { return $this->payload; }
    public function payloadJson() { return $this->payloadJson; }
    public function payloadHash() { return $this->payloadHash; }

    private function normalize(array $value)
    {
        if ($this->isList($value)) {
            $result = array();
            foreach ($value as $item) {
                $result[] = is_array($item) ? $this->normalize($item) : $item;
            }
            return $result;
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalize($item);
            }
        }
        return $value;
    }

    private function isList(array $value)
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
