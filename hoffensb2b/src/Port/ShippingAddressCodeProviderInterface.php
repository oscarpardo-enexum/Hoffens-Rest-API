<?php

namespace Hoffens\B2B\Port;

interface ShippingAddressCodeProviderInterface
{
    public function codeForCustomerAddress($customerId, $addressId);
}
