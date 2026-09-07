<?php

namespace Hoffens\B2B\Application\Order;

use Hoffens\B2B\Domain\Customer\CardCode;
use Hoffens\B2B\Exception\IntegrationException;

final class BuildOrderRequest
{
    public function execute($cardCode, $expectedDate, $shippingAddressCode, $note, array $lines)
    {
        $cardCode = new CardCode($cardCode);
        $shippingAddressCode = trim((string) $shippingAddressCode);
        if ($shippingAddressCode === '' || strlen($shippingAddressCode) > 100) {
            throw new IntegrationException('La dirección de despacho no tiene un código SAP válido.');
        }

        $expectedDate = trim((string) $expectedDate);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $expectedDate);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new IntegrationException('La fecha de espera debe usar el formato AAAA-MM-DD.');
        }

        $mappedLines = array();
        $seen = array();
        foreach ($lines as $line) {
            $itemCode = isset($line['itemCode']) ? trim((string) $line['itemCode']) : '';
            $quantity = isset($line['cantidad']) ? $line['cantidad'] : 0;
            if ($itemCode === '' || strlen($itemCode) > 64) {
                throw new IntegrationException('Todas las líneas deben tener un SKU válido.');
            }
            if (!is_numeric($quantity) || (float) $quantity <= 0) {
                throw new IntegrationException('Todas las líneas deben tener una cantidad mayor que cero.');
            }
            if (isset($seen[$itemCode])) {
                throw new IntegrationException('El SKU ' . $itemCode . ' está repetido en la solicitud.');
            }
            $seen[$itemCode] = true;
            $mappedLines[] = array('itemCode' => $itemCode, 'cantidad' => (float) $quantity);
        }
        if (count($mappedLines) === 0) {
            throw new IntegrationException('La solicitud de pedido debe contener al menos una línea.');
        }

        return array(
            'cardCode' => $cardCode->value(),
            'fechaEspera' => $expectedDate,
            'direccionDespacho' => $shippingAddressCode,
            'glosa' => trim((string) $note),
            'lineas' => $mappedLines,
        );
    }
}
