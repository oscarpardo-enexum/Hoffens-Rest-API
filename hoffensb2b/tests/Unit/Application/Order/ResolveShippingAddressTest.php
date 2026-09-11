<?php

namespace Hoffens\B2B\Tests\Unit\Application\Order;

use Hoffens\B2B\Application\Order\ResolveShippingAddress;
use Hoffens\B2B\Exception\IntegrationException;
use Hoffens\B2B\Port\ShippingAddressCodeProviderInterface;
use PHPUnit\Framework\TestCase;

final class ResolveShippingAddressTest extends TestCase
{
    public function testReturnsLocalCodeOnlyWhenStillPresentInSap()
    {
        $resolver = new ResolveShippingAddress(new AddressCodeProvider('DESPACHO'));
        $code = $resolver->execute(1, 2, array(
            'direcciones' => array(array('codigo' => 'DESPACHO')),
        ));
        $this->assertSame('DESPACHO', $code);
    }

    public function testRejectsStaleLocalCode()
    {
        $this->expectException(IntegrationException::class);
        $resolver = new ResolveShippingAddress(new AddressCodeProvider('ANTIGUA'));
        $resolver->execute(1, 2, array(
            'direcciones' => array(array('codigo' => 'DESPACHO')),
        ));
    }
}

final class AddressCodeProvider implements ShippingAddressCodeProviderInterface
{
    private $code;
    public function __construct($code) { $this->code = $code; }
    public function codeForCustomerAddress($customerId, $addressId) { return $this->code; }
}
