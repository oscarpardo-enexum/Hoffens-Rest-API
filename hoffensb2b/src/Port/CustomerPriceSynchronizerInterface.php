<?php

namespace Hoffens\B2B\Port;

interface CustomerPriceSynchronizerInterface
{
    /** @return array{received:int,mapped:int,unmatched:int,written:int,groupId:int,unchanged:bool} */
    public function synchronize($customerId, array $prices);
}
