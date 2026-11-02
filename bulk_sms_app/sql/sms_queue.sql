-- MySQL 8. Database a2techno_bulk_sms already exists on the host.
-- The API also creates this table on first use.

USE `a2techno_bulk_sms`;

-- client_name: which client sent the request.
-- sms_cost: 0.48 x segments, calculated on insert.

CREATE TABLE IF NOT EXISTS `sms_queue` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `client_name` VARCHAR(100) NOT NULL,
    `receiver` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `segments` INT NOT NULL DEFAULT 1,
    `sms_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'sending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `claimed_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `delivered_at` DATETIME NULL DEFAULT NULL,
    `sender` VARCHAR(20) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sms_queue_status` (`status`, `id`),
    KEY `idx_sms_queue_client` (`client_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `sms_queue`
    MODIFY `status` ENUM('pending', 'sending', 'sent', 'failed') NOT NULL DEFAULT 'pending';

SET @claimed_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sms_queue'
      AND COLUMN_NAME = 'claimed_at'
);
SET @claimed_sql := IF(
    @claimed_exists = 0,
    'ALTER TABLE `sms_queue` ADD COLUMN `claimed_at` DATETIME NULL DEFAULT NULL AFTER `status`',
    'SELECT 1'
);
PREPARE claimed_stmt FROM @claimed_sql;
EXECUTE claimed_stmt;
DEALLOCATE PREPARE claimed_stmt;

SET @delivered_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sms_queue'
      AND COLUMN_NAME = 'delivered_at'
);
SET @delivered_sql := IF(
    @delivered_exists = 0,
    'ALTER TABLE `sms_queue` ADD COLUMN `delivered_at` DATETIME NULL DEFAULT NULL AFTER `created_at`',
    'SELECT 1'
);
PREPARE delivered_stmt FROM @delivered_sql;
EXECUTE delivered_stmt;
DEALLOCATE PREPARE delivered_stmt;

SET @sender_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sms_queue'
      AND COLUMN_NAME = 'sender'
);
SET @sender_sql := IF(
    @sender_exists = 0,
    'ALTER TABLE `sms_queue` ADD COLUMN `sender` VARCHAR(20) NULL DEFAULT NULL AFTER `receiver`',
    'SELECT 1'
);
PREPARE sender_stmt FROM @sender_sql;
EXECUTE sender_stmt;
DEALLOCATE PREPARE sender_stmt;

CREATE TABLE IF NOT EXISTS `client_list` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `client_name` VARCHAR(100) NOT NULL,
    `sim_number` VARCHAR(20) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_client_list_name` (`client_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sim_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'client_list'
      AND COLUMN_NAME = 'sim_number'
);
SET @sim_sql := IF(
    @sim_exists = 0,
    'ALTER TABLE `client_list` ADD COLUMN `sim_number` VARCHAR(20) NULL DEFAULT NULL AFTER `client_name`',
    'SELECT 1'
);
PREPARE sim_stmt FROM @sim_sql;
EXECUTE sim_stmt;
DEALLOCATE PREPARE sim_stmt;

CREATE TABLE IF NOT EXISTS `client_balance` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `client_name` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `note` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_client_balance_name` (`client_name`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `device_push` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `token` VARCHAR(512) NOT NULL,
    `client_name` VARCHAR(100) NOT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_device_push_token_client` (`token`, `client_name`),
    KEY `idx_device_push_client` (`client_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
