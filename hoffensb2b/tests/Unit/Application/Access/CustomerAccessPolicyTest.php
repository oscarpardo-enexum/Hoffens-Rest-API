<?php

namespace Hoffens\B2B\Tests\Unit\Application\Access;

use Hoffens\B2B\Application\Access\CustomerAccessPolicy;
use Hoffens\B2B\Port\CustomerCardCodeProviderInterface;
use PHPUnit\Framework\TestCase;

final class CustomerAccessPolicyTest extends TestCase
{
    public function testValidCardCodeCanPurchase()
    {
        $policy = new CustomerAccessPolicy(new InMemoryCardCodeProvider('C770672790'));

        $this->assertTrue($policy->canBrowseCatalog(25));
        $this->assertTrue($policy->canPurchase(25));
        $this->assertSame('C770672790', $policy->cardCode(25)->value());
    }

    public function testCustomerWithoutCardCodeCanOnlyBrowse()
    {
        $policy = new CustomerAccessPolicy(new InMemoryCardCodeProvider(null));

        $this->assertTrue($policy->canBrowseCatalog(25));
        $this->assertFalse($policy->canPurchase(25));
        $this->assertNull($policy->cardCode(25));
    }

    public function testInvalidCardCodeCannotPurchase()
    {
        $policy = new CustomerAccessPolicy(new InMemoryCardCodeProvider('codigo con espacios'));

        $this->assertFalse($policy->canPurchase(25));
    }
}

final class InMemoryCardCodeProvider implements CustomerCardCodeProviderInterface
{
    private $cardCode;

    public function __construct($cardCode) { $this->cardCode = $cardCode; }
    public function cardCodeForCustomer($customerId) { return $this->cardCode; }
}
