<?php

namespace Hoffens\B2B\Infrastructure\Http;

final class TransientFailurePolicy
{
    public function isRetryableStatus($statusCode)
    {
        return in_array((int) $statusCode, array(408, 429, 500, 502, 503, 504), true);
    }
}
