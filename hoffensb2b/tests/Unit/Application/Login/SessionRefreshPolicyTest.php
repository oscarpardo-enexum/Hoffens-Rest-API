<?php

namespace Hoffens\B2B\Tests\Unit\Application\Login;

use Hoffens\B2B\Application\Login\SessionRefreshPolicy;
use PHPUnit\Framework\TestCase;

final class SessionRefreshPolicyTest extends TestCase
{
    public function testRefreshesWhenReturningAfterInactivity()
    {
        $policy = new SessionRefreshPolicy(1800, 86400);
        $this->assertTrue($policy->shouldRefresh(100000, 98000, 99000, 0));
    }

    public function testDoesNotRefreshOnEveryActivePage()
    {
        $policy = new SessionRefreshPolicy(1800, 86400);
        $this->assertFalse($policy->shouldRefresh(100000, 99900, 99000, 0));
    }

    public function testRefreshesLongRunningSessionAtMaximumAge()
    {
        $policy = new SessionRefreshPolicy(1800, 86400);
        $this->assertTrue($policy->shouldRefresh(100000, 99900, 10000, 0));
    }

    public function testHonorsFailureBackoff()
    {
        $policy = new SessionRefreshPolicy(1800, 86400);
        $this->assertFalse($policy->shouldRefresh(100000, 90000, 0, 100300));
    }
}
