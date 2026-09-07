<?php

namespace Hoffens\B2B\Port;

interface CatalogSynchronizerInterface
{
    public function synchronize(array $items);
}
