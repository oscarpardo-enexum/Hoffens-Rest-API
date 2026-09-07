<?php

namespace Hoffens\B2B\PrestaShop;

use Hoffens\B2B\Contract\CustomerGroupProviderInterface;

final class CustomerGroupProvider implements CustomerGroupProviderInterface
{
    public function groupsForCustomer($customerId)
    {
        $groups = \Customer::getGroupsStatic((int) $customerId);
        return is_array($groups) ? $groups : array();
    }
}

