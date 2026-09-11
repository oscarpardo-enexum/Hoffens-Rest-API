<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_0_12_0($module)
{
    if (!Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::TRANSACTION_WRITES_ENABLED, 0)) {
        return false;
    }
    $table = _DB_PREFIX_ . 'hoffens_b2b_outbox';
    $columns = array(
        'remote_reference' => 'VARCHAR(100) NULL AFTER `remote_status`',
        'remote_status_url' => 'VARCHAR(500) NULL AFTER `remote_reference`',
        'lock_token' => 'VARCHAR(64) NULL AFTER `last_error`',
        'locked_at' => 'DATETIME NULL AFTER `lock_token`',
    );
    foreach ($columns as $name => $definition) {
        $exists = Db::getInstance()->executeS(
            "SHOW COLUMNS FROM `" . bqSQL($table) . "` LIKE '" . pSQL($name) . "'"
        );
        if (!$exists && !Db::getInstance()->execute(
            'ALTER TABLE `' . bqSQL($table) . '` ADD `' . bqSQL($name) . '` ' . $definition
        )) {
            return false;
        }
    }
    $index = Db::getInstance()->executeS(
        "SHOW INDEX FROM `" . bqSQL($table) . "` WHERE Key_name='idx_lock'"
    );
    if (!$index && !Db::getInstance()->execute(
        'ALTER TABLE `' . bqSQL($table) . '` ADD KEY `idx_lock` (`lock_token`,`locked_at`)'
    )) {
        return false;
    }
    return true;
}
