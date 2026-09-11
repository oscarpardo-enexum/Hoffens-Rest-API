<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_0_10_0($module)
{
    $keys = '\\Hoffens\\B2B\\Configuration\\ConfigKeys';
    return Configuration::updateValue($keys::SESSION_IDLE_SECONDS, 1800)
        && Configuration::updateValue($keys::SESSION_MAX_AGE_SECONDS, 86400)
        && $module->registerHook('actionFrontControllerInitAfter');
}
