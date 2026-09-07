<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_0_3_0($module)
{
    $table = _DB_PREFIX_ . 'hoffens_b2b_login_metric';
    $columns = array(
        'price_sync_ms' => 'INT UNSIGNED NULL AFTER `price_count`',
        'price_mapped' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `price_sync_ms`',
        'price_unmatched' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `price_mapped`',
        'price_written' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `price_unmatched`',
        'price_group_id' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `price_written`',
    );
    foreach ($columns as $name => $definition) {
        $existing = Db::getInstance()->executeS(
            "SHOW COLUMNS FROM `" . bqSQL($table) . "` LIKE '" . pSQL($name) . "'"
        );
        if (count($existing) === 0 && !Db::getInstance()->execute(
            'ALTER TABLE `' . bqSQL($table) . '` ADD `' . bqSQL($name) . '` ' . $definition
        )) {
            return false;
        }
    }
    return true;
}
