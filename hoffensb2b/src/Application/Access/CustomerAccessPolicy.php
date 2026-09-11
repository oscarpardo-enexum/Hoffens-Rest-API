<?php

namespace Hoffens\B2B\Application\Access;

use Hoffens\B2B\Domain\Customer\CardCode;
use Hoffens\B2B\Exception\IntegrationException;
use Hoffens\B2B\Port\CustomerCardCodeProviderInterface;

final class CustomerAccessPolicy
{
    private $cardCodes;

    public function __construct(CustomerCardCodeProviderInterface $cardCodes)
    {
        $this->cardCodes = $cardCodes;
    }

    /** @return CardCode|null */
    public function cardCode($customerId)
    {
        if ((int) $customerId <= 0) {
            return null;
        }

        try {
            $value = $this->cardCodes->cardCodeForCustomer((int) $customerId);
            return $value === null ? null : new CardCode($value);
        } catch (IntegrationException $exception) {
            return null;
        }
    }

    public function canBrowseCatalog($customerId)
    {
        return true;
    }

    public function canPurchase($customerId)
    {
        return $this->cardCode($customerId) !== null;
    }
}
