<?php

namespace Hoffens\B2B\Application\Payment;

use Hoffens\B2B\Domain\Customer\CardCode;
use Hoffens\B2B\Exception\IntegrationException;

final class BuildPaymentRequest
{
    public function execute(
        $transactionNumber,
        $cardCode,
        $paymentDate,
        $transactionAmount,
        $receipt,
        array $documents
    ) {
        $transactionNumber = $this->requiredText($transactionNumber, 100, 'número de transacción');
        $cardCode = new CardCode($cardCode);
        $paymentDate = $this->date($paymentDate);
        $receipt = $this->requiredText($receipt, 255, 'comprobante');
        $transactionAmount = $this->positiveAmount($transactionAmount, 'monto de la transacción');

        $mapped = array();
        $seen = array();
        $calculatedAmount = 0.0;
        foreach ($documents as $document) {
            $type = isset($document['tipoDocumento'])
                ? strtoupper(trim((string) $document['tipoDocumento'])) : '';
            if (!in_array($type, array('FC', 'NC'), true)) {
                throw new IntegrationException('Los pagos solo admiten documentos FC o NC.');
            }
            $docEntry = isset($document['docEntry']) ? (int) $document['docEntry'] : 0;
            if ($docEntry <= 0) {
                throw new IntegrationException('Todos los documentos deben incluir un DocEntry válido.');
            }
            $amount = $this->positiveAmount(
                isset($document['montoAplicado']) ? $document['montoAplicado'] : 0,
                'monto aplicado'
            );
            $key = $type . ':' . $docEntry;
            if (isset($seen[$key])) {
                throw new IntegrationException('El documento ' . $key . ' está repetido en el pago.');
            }
            $seen[$key] = true;
            $calculatedAmount += $type === 'NC' ? -$amount : $amount;
            $mapped[] = array(
                'tipoDocumento' => $type,
                'docEntry' => $docEntry,
                'montoAplicado' => $amount,
            );
        }
        if (count($mapped) === 0) {
            throw new IntegrationException('El pago debe aplicar al menos un documento.');
        }
        if (abs($calculatedAmount - $transactionAmount) > 0.005) {
            throw new IntegrationException('El monto de la transacción no coincide con la suma de FC menos NC.');
        }

        return array(
            'numeroTransaccion' => $transactionNumber,
            'cardCode' => $cardCode->value(),
            'fechaPago' => $paymentDate,
            'montoTransaccion' => $transactionAmount,
            'comprobante' => $receipt,
            'documentos' => $mapped,
        );
    }

    private function requiredText($value, $maxLength, $label)
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > $maxLength || preg_match('/[\r\n]/', $value)) {
            throw new IntegrationException('El ' . $label . ' no es válido.');
        }
        return $value;
    }

    private function date($value)
    {
        $value = trim((string) $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new IntegrationException('La fecha de pago debe usar el formato AAAA-MM-DD.');
        }
        return $value;
    }

    private function positiveAmount($value, $label)
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0) {
            throw new IntegrationException('El ' . $label . ' debe ser mayor que cero.');
        }
        return round((float) $value, 2);
    }
}
