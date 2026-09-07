<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$queries = array(
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hoffens_b2b_login_cache` (
        `id_customer` INT UNSIGNED NOT NULL,
        `payload` MEDIUMTEXT NOT NULL,
        `fetched_at` DATETIME NOT NULL,
        PRIMARY KEY (`id_customer`),
        KEY `idx_fetched_at` (`fetched_at`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8',
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hoffens_b2b_incident` (
        `id_incident` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `fingerprint` VARCHAR(190) NOT NULL,
        `status` VARCHAR(20) NOT NULL,
        `first_seen_at` DATETIME NOT NULL,
        `last_seen_at` DATETIME NOT NULL,
        `last_notified_at` DATETIME NULL,
        `recovered_at` DATETIME NULL,
        `message` VARCHAR(255) NOT NULL DEFAULT "",
        `pending_notification` VARCHAR(20) NULL,
        `occurrences` INT UNSIGNED NOT NULL DEFAULT 1,
        PRIMARY KEY (`id_incident`),
        UNIQUE KEY `uniq_fingerprint` (`fingerprint`),
        KEY `idx_pending` (`pending_notification`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8',
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hoffens_b2b_price_sync` (
        `id_customer` INT UNSIGNED NOT NULL,
        `source_hash` CHAR(64) NOT NULL,
        `mapped_hash` CHAR(64) NOT NULL,
        `received_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `mapped_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `unmatched_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `synced_at` DATETIME NOT NULL,
        PRIMARY KEY (`id_customer`),
        KEY `idx_synced_at` (`synced_at`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8',
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
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hoffens_b2b_login_metric` (
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
        `price_sync_ms` INT UNSIGNED NULL,
        `price_mapped` INT UNSIGNED NOT NULL DEFAULT 0,
        `price_unmatched` INT UNSIGNED NOT NULL DEFAULT 0,
        `price_written` INT UNSIGNED NOT NULL DEFAULT 0,
        `price_group_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `price_unchanged` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        `profile_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
        `prices_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id_metric`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_customer_created` (`id_customer`, `created_at`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8',
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hoffens_b2b_outbox` (
        `id_outbox` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `operation_type` VARCHAR(20) NOT NULL,
        `local_reference` VARCHAR(100) NOT NULL,
        `idempotency_key` VARCHAR(100) NOT NULL,
        `payload_hash` CHAR(64) NOT NULL,
        `payload` MEDIUMTEXT NOT NULL,
        `remote_request_id` VARCHAR(100) NULL,
        `remote_status` VARCHAR(20) NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT "queued",
        `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        `available_at` DATETIME NOT NULL,
        `last_attempt_at` DATETIME NULL,
        `last_error` VARCHAR(500) NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        `completed_at` DATETIME NULL,
        PRIMARY KEY (`id_outbox`),
        UNIQUE KEY `uniq_operation_local` (`operation_type`, `local_reference`),
        UNIQUE KEY `uniq_idempotency` (`idempotency_key`),
        KEY `idx_dispatch` (`status`, `available_at`),
        KEY `idx_remote_request` (`remote_request_id`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8',
);

foreach ($queries as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}
return true;
