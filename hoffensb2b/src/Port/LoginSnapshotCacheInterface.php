<?php

namespace Hoffens\B2B\Port;

interface LoginSnapshotCacheInterface
{
    /** @return array|null Retorna null cuando no existe o está vencido. */
    public function getFresh($customerId, $maxAgeSeconds);
    public function put($customerId, array $snapshot);
}

