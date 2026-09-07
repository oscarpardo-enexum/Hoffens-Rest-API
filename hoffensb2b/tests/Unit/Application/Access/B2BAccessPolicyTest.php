<?php

namespace Hoffens\B2B\Tests\Unit\Application\Access;

use Hoffens\B2B\Application\Access\B2BAccessPolicy;
use Hoffens\B2B\Contract\CustomerGroupProviderInterface;
use PHPUnit\Framework\TestCase;

final class B2BAccessPolicyTest extends TestCase
{
    public function testB2BCustomerCanPurchase()
    {
        $groups = $this->groups(array(4, 8));
        $policy = new B2BAccessPolicy($groups, array(8));

        $this->assertTrue($policy->canPurchase(25));
    }

    public function testB2CCustomerCanBrowseButCannotPurchase()
    {
        $groups = $this->groups(array(3));
        $policy = new B2BAccessPolicy($groups, array(8));

        $this->assertTrue($policy->canBrowseCatalog(25));
        $this->assertFalse($policy->canPurchase(25));
    }

    public function testEveryAuthenticatedCustomerIsObservedAtLogin()
    {
        $policy = new B2BAccessPolicy($this->groups(array(3)), array(8));

        $this->assertTrue($policy->shouldIntegrateLogin(25));
        $this->assertFalse($policy->shouldIntegrateLogin(0));
    }

    private function groups(array $ids)
    {
        return new InMemoryCustomerGroups($ids);
    }
}

final class InMemoryCustomerGroups implements CustomerGroupProviderInterface
{
    private $ids;

    public function __construct(array $ids) { $this->ids = $ids; }
    public function groupsForCustomer($customerId) { return $this->ids; }
}
