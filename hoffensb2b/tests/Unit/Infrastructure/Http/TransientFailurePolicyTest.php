<?php

namespace Hoffens\B2B\Tests\Unit\Infrastructure\Http;

use Hoffens\B2B\Infrastructure\Http\TransientFailurePolicy;
use PHPUnit\Framework\TestCase;

final class TransientFailurePolicyTest extends TestCase
{
    public function testRetriesOnlyTransientHttpFailures()
    {
        $policy = new TransientFailurePolicy();
        foreach (array(408, 429, 500, 502, 503, 504) as $status) {
            $this->assertTrue($policy->isRetryableStatus($status));
        }
        foreach (array(200, 400, 401, 403, 404, 409, 422) as $status) {
            $this->assertFalse($policy->isRetryableStatus($status));
        }
    }
}
