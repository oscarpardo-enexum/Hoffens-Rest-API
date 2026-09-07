<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_0_4_0($module)
{
    $db = Db::getInstance();
    $stateTable = _DB_PREFIX_ . 'hoffens_b2b_price_sync';
    if (!$db->execute(
        'CREATE TABLE IF NOT EXISTS `' . bqSQL($stateTable) . '` (
            `id_customer` INT UNSIGNED NOT NULL,
            `source_hash` CHAR(64) NOT NULL,
            `mapped_hash` CHAR(64) NOT NULL,
            `received_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `mapped_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `unmatched_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `synced_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_customer`),
            KEY `idx_synced_at` (`synced_at`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8'
    )) {
        return false;
    }

    $metricTable = _DB_PREFIX_ . 'hoffens_b2b_login_metric';
    $existing = $db->executeS(
        "SHOW COLUMNS FROM `" . bqSQL($metricTable) . "` LIKE 'price_unchanged'"
    );
    return count($existing) > 0 || $db->execute(
        'ALTER TABLE `' . bqSQL($metricTable)
        . '` ADD `price_unchanged` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `price_group_id`'
    );
}
