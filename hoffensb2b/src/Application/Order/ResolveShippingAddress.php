<?php

namespace Hoffens\B2B\Application\Order;

use Hoffens\B2B\Exception\IntegrationException;
use Hoffens\B2B\Port\ShippingAddressCodeProviderInterface;

final class ResolveShippingAddress
{
    private $addresses;

    public function __construct(ShippingAddressCodeProviderInterface $addresses)
    {
        $this->addresses = $addresses;
    }

    public function execute($customerId, $addressId, array $customerProfile)
    {
        $localCode = trim((string) $this->addresses->codeForCustomerAddress($customerId, $addressId));
        if ($localCode === '') {
            throw new IntegrationException('La dirección seleccionada no tiene correlativo SAP.');
        }
        if (!isset($customerProfile['direcciones']) || !is_array($customerProfile['direcciones'])) {
            throw new IntegrationException('SAP no devolvió una colección válida de direcciones.');
        }

        foreach ($customerProfile['direcciones'] as $address) {
            $remoteCode = is_array($address) && isset($address['codigo'])
                ? trim((string) $address['codigo']) : '';
            if ($remoteCode !== '' && strcasecmp($remoteCode, $localCode) === 0) {
                return $remoteCode;
            }
        }
        throw new IntegrationException('La dirección seleccionada ya no está vigente en SAP.');
    }
}
