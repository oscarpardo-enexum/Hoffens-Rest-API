<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_0_11_0($module)
{
    // La identidad B2B queda determinada únicamente por customer.card_code.
    Configuration::deleteByName(\Hoffens\B2B\Configuration\ConfigKeys::B2B_GROUPS);
    return $module->registerHook('actionFrontControllerInitAfter')
        && $module->registerHook('displayCustomerLoginFormAfter')
        && $module->registerHook('displayCustomerAccount');
}
