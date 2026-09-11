<?php

namespace Hoffens\B2B\Adapter\PrestaShop;

use Hoffens\B2B\Exception\IntegrationException;
use Hoffens\B2B\Port\ShippingAddressCodeProviderInterface;

final class DbShippingAddressCodeProvider implements ShippingAddressCodeProviderInterface
{
    public function codeForCustomerAddress($customerId, $addressId)
    {
        $customerId = (int) $customerId;
        $addressId = (int) $addressId;
        if ($customerId <= 0 || $addressId <= 0) {
            throw new IntegrationException('La dirección de despacho seleccionada no es válida.');
        }

        $row = \Db::getInstance()->getRow(
            'SELECT correlativo_sap FROM `' . _DB_PREFIX_ . 'address` WHERE id_address=' . $addressId
            . ' AND id_customer=' . $customerId . ' AND deleted=0 AND active=1'
        );
        $code = $row && isset($row['correlativo_sap']) ? trim((string) $row['correlativo_sap']) : '';
        if ($code === '') {
            throw new IntegrationException('La dirección seleccionada no tiene un código SAP vigente.');
        }
        return $code;
    }
}
