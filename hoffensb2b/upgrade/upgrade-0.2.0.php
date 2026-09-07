<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_0_2_0($module)
{
    $query = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hoffens_b2b_login_metric` (
        `id_metric` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_customer` INT UNSIGNED NOT NULL,
        `mode` VARCHAR(20) NOT NULL,
        `result` VARCHAR(50) NOT NULL,
        `cache_hit` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        `total_ms` INT UNSIGNED NOT NULL,
        `mapping_ms` INT UNSIGNED NULL,
        `cache_ms` INT UNSIGNED NULL,
        `api_batch_ms` INT UNSIGNED NULL,
        `profile_ms` INT UNSIGNED NULL,
        `prices_ms` INT UNSIGNED NULL,
        `price_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `profile_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
        `prices_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id_metric`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_customer_created` (`id_customer`, `created_at`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

    if (!Db::getInstance()->execute($query)) {
        return false;
    }
    if (Configuration::get(\Hoffens\B2B\Configuration\ConfigKeys::RETRIES) === false) {
        Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::RETRIES, 2);
    }
    if (Configuration::get(\Hoffens\B2B\Configuration\ConfigKeys::PRICE_TTL) === false) {
        Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::PRICE_TTL, 0);
    }
    return $module->registerHook('actionAuthentication')
        && $module->registerHook('displayCustomerLoginFormAfter');
}

