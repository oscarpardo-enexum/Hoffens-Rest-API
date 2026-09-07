<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_0_5_0($module)
{
    $queries = array(
        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hoffens_b2b_catalog` (
            `item_code` VARCHAR(64) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `purchase_multiple` DECIMAL(20,6) NOT NULL,
            `sale_multiple` DECIMAL(20,6) NOT NULL,
            `stock` DECIMAL(20,6) NOT NULL,
            `found_scope` VARCHAR(20) NOT NULL,
            `id_product` INT UNSIGNED NOT NULL DEFAULT 0,
            `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
            `payload` MEDIUMTEXT NOT NULL,
            `synced_at` DATETIME NOT NULL,
            PRIMARY KEY (`item_code`),
            KEY `idx_mapping` (`found_scope`, `id_product`, `id_product_attribute`),
            KEY `idx_synced_at` (`synced_at`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8',
        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hoffens_b2b_catalog_state` (
            `id_state` TINYINT UNSIGNED NOT NULL,
            `source_hash` CHAR(64) NOT NULL,
            `mapping_hash` CHAR(64) NOT NULL,
            `received_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `mapped_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `missing_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `synced_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_state`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8',
    );
    foreach ($queries as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }
    return true;
}
