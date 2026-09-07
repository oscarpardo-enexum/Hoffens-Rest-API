<?php

namespace Hoffens\B2B\Port;

interface CustomerCardCodeProviderInterface
{
    /** @return string|null */
    public function cardCodeForCustomer($customerId);
}

