<?php

namespace Hoffens\B2B\Application\Access;

use Hoffens\B2B\Contract\CustomerGroupProviderInterface;

final class B2BAccessPolicy
{
    private $groups;
    private $b2bGroupIds;

    public function __construct(CustomerGroupProviderInterface $groups, array $b2bGroupIds)
    {
        $this->groups = $groups;
        $this->b2bGroupIds = array_map('intval', $b2bGroupIds);
    }

    public function isB2B($customerId)
    {
        if ((int) $customerId <= 0) {
            return false;
        }

        return count(array_intersect(
            $this->b2bGroupIds,
            array_map('intval', $this->groups->groupsForCustomer((int) $customerId))
        )) > 0;
    }

    /**
     * La integración de login se observa para todo cliente autenticado.
     * Los grupos siguen definiendo permisos de compra, no la telemetría.
     */
    public function shouldIntegrateLogin($customerId)
    {
        return (int) $customerId > 0;
    }

    public function canBrowseCatalog($customerId)
    {
        return true;
    }

    public function canPurchase($customerId)
    {
        return $this->isB2B($customerId);
    }
}
