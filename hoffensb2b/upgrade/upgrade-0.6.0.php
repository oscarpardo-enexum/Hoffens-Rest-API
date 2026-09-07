<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_0_6_0($module)
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'hoffens_b2b_incident';
    $columns = array(
        'recovered_at' => 'ADD `recovered_at` DATETIME NULL AFTER `last_notified_at`',
        'message' => 'ADD `message` VARCHAR(255) NOT NULL DEFAULT "" AFTER `recovered_at`',
        'pending_notification' => 'ADD `pending_notification` VARCHAR(20) NULL AFTER `message`',
    );
    foreach ($columns as $column => $definition) {
        if (count($db->executeS("SHOW COLUMNS FROM `" . bqSQL($table) . "` LIKE '" . pSQL($column) . "'")) === 0
            && !$db->execute('ALTER TABLE `' . bqSQL($table) . '` ' . $definition)) {
            return false;
        }
    }
    $indexes = $db->executeS('SHOW INDEX FROM `' . bqSQL($table) . '`');
    $byName = array();
    foreach ($indexes as $index) {
        $byName[$index['Key_name']] = true;
    }
    if (isset($byName['uniq_open_fingerprint'])) {
        if (!$db->execute('ALTER TABLE `' . bqSQL($table) . '` DROP INDEX `uniq_open_fingerprint`')) {
            return false;
        }
    }
    if (!isset($byName['uniq_fingerprint'])
        && !$db->execute('ALTER TABLE `' . bqSQL($table) . '` ADD UNIQUE KEY `uniq_fingerprint` (`fingerprint`)')) {
        return false;
    }
    if (!isset($byName['idx_pending'])
        && !$db->execute('ALTER TABLE `' . bqSQL($table) . '` ADD KEY `idx_pending` (`pending_notification`)')) {
        return false;
    }
    $keys = '\\Hoffens\\B2B\\Configuration\\ConfigKeys';
    return Configuration::updateValue($keys::ALERT_EMAILS, (string) Configuration::get('PS_SHOP_EMAIL'))
        && Configuration::updateValue($keys::ALERT_COOLDOWN, 30)
        && Configuration::updateValue($keys::HEALTH_MAX_MS, 2000)
        && Configuration::updateValue($keys::METRIC_RETENTION_DAYS, 30)
        && Configuration::updateValue($keys::CRON_TOKEN, Tools::passwdGen(40));
}
