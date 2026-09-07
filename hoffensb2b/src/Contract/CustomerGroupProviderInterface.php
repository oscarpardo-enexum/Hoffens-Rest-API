<?php

namespace Hoffens\B2B\Contract;

interface CustomerGroupProviderInterface
{
    /** @return int[] */
    public function groupsForCustomer($customerId);
}

