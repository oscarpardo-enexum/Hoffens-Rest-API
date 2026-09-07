<?php

namespace Hoffens\B2B\Adapter\PrestaShop;

use Hoffens\B2B\Port\CustomerCardCodeProviderInterface;

final class CustomerCardCodeProvider implements CustomerCardCodeProviderInterface
{
    public function cardCodeForCustomer($customerId)
    {
        $value = \Db::getInstance()->getValue(
            'SELECT `card_code` FROM `' . _DB_PREFIX_ . 'customer` WHERE `id_customer` = ' . (int) $customerId
        );
        if ($value === false || trim((string) $value) === '') {
            return null;
        }
        return trim((string) $value);
    }
}

