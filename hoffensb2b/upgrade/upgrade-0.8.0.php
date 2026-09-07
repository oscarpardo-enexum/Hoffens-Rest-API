<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_0_8_0($module)
{
    return Db::getInstance()->execute(
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
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8'
    );
}
