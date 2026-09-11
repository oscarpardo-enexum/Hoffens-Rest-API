<?php

namespace Hoffens\B2B\Application\Login;

use Hoffens\B2B\Domain\Customer\CardCode;
use Hoffens\B2B\Exception\IntegrationException;

final class LoginSnapshotValidator
{
    public function validate(CardCode $expectedCardCode, array $snapshot)
    {
        if (!isset($snapshot['customer']) || !is_array($snapshot['customer'])) {
            throw new IntegrationException('La respuesta no contiene el perfil del cliente.');
        }
        if (!isset($snapshot['prices']) || !is_array($snapshot['prices']) || count($snapshot['prices']) === 0) {
            throw new IntegrationException('La respuesta no contiene precios vigentes para el cliente.');
        }

        $customer = $snapshot['customer'];
        $cardCode = isset($customer['cardCode']) ? trim((string) $customer['cardCode']) : '';
        if (strcasecmp($cardCode, $expectedCardCode->value()) !== 0) {
            throw new IntegrationException('El perfil recibido no corresponde al CardCode solicitado.');
        }
        if (!isset($customer['razonSocial']) || trim((string) $customer['razonSocial']) === '') {
            throw new IntegrationException('El perfil no contiene la razón social del cliente.');
        }

        $this->number($customer, 'lineaCredito', 'línea de crédito');
        $this->number($customer, 'deudaTotal', 'deuda total');

        if (!isset($customer['cobranza']) || !is_array($customer['cobranza'])) {
            throw new IntegrationException('El perfil no contiene el resumen de cobranza.');
        }
        $this->number($customer['cobranza'], 'saldoVencido', 'saldo vencido');
        $this->nonNegativeNumber($customer['cobranza'], 'documentosVencidos', 'documentos vencidos');

        foreach (array('direcciones', 'cuentaCorriente') as $field) {
            if (!isset($customer[$field]) || !is_array($customer[$field])) {
                throw new IntegrationException('El perfil no contiene una colección válida de ' . $field . '.');
            }
        }
    }

    private function nonNegativeNumber(array $data, $field, $label)
    {
        if (!array_key_exists($field, $data) || !is_numeric($data[$field]) || (float) $data[$field] < 0) {
            throw new IntegrationException('El perfil contiene un valor inválido para ' . $label . '.');
        }
    }

    private function number(array $data, $field, $label)
    {
        if (!array_key_exists($field, $data) || !is_numeric($data[$field]) || !is_finite((float) $data[$field])) {
            throw new IntegrationException('El perfil contiene un valor inválido para ' . $label . '.');
        }
    }
}
