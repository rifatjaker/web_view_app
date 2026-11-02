<?php

declare(strict_types=1);

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = app_config()['db'];
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $base = sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        $db['host'],
        (int) $db['port'],
        $db['charset']
    );
    $dsn = $base . ';dbname=' . $db['name'];

    try {
        $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
    } catch (PDOException $e) {
        $unknownDb = str_contains($e->getMessage(), '1049')
            || str_contains($e->getMessage(), 'Unknown database');
        if (!$unknownDb) {
            throw $e;
        }

        $server = new PDO($base, $db['user'], $db['pass'], $options);
        $name = str_replace('`', '``', $db['name']);
        $server->exec(
            "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
    }

    $pdo->exec("SET time_zone = '+06:00'");
    sms_queue_ensure_table($pdo);

    return $pdo;
}

function sms_queue_ensure_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `sms_queue` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $existing = [];
    foreach ($pdo->query('SHOW COLUMNS FROM sms_queue') as $column) {
        $existing[$column['Field']] = true;
    }
    if (!isset($existing['client_name'])) {
        $pdo->exec(
            "ALTER TABLE sms_queue ADD COLUMN client_name VARCHAR(100) NOT NULL DEFAULT '' AFTER id"
        );
    }
    if (!isset($existing['sms_cost'])) {
        $pdo->exec(
            "ALTER TABLE sms_queue ADD COLUMN sms_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER segments"
        );
    }
    if (!isset($existing['claimed_at'])) {
        $pdo->exec(
            'ALTER TABLE sms_queue ADD COLUMN claimed_at DATETIME NULL DEFAULT NULL AFTER status'
        );
    }
    if (!isset($existing['delivered_at'])) {
        $pdo->exec(
            'ALTER TABLE sms_queue ADD COLUMN delivered_at DATETIME NULL DEFAULT NULL AFTER created_at'
        );
    }
    if (!isset($existing['sender'])) {
        $pdo->exec(
            'ALTER TABLE sms_queue ADD COLUMN sender VARCHAR(20) NULL DEFAULT NULL AFTER receiver'
        );
    }

    $pdo->exec(
        "ALTER TABLE sms_queue
         MODIFY status ENUM('pending', 'sending', 'sent', 'failed') NOT NULL DEFAULT 'pending'"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `client_list` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `client_name` VARCHAR(100) NOT NULL,
            `sim_number` VARCHAR(20) NULL DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_client_list_name` (`client_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $clientColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM client_list') as $column) {
        $clientColumns[$column['Field']] = true;
    }
    if (!isset($clientColumns['sim_number'])) {
        $pdo->exec(
            'ALTER TABLE client_list ADD COLUMN sim_number VARCHAR(20) NULL DEFAULT NULL AFTER client_name'
        );
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `client_balance` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `client_name` VARCHAR(100) NOT NULL,
            `amount` DECIMAL(12,2) NOT NULL,
            `note` VARCHAR(255) NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_client_balance_name` (`client_name`, `id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `device_push` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `token` VARCHAR(512) NOT NULL,
            `client_name` VARCHAR(100) NOT NULL,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_device_push_token_client` (`token`, `client_name`),
            KEY `idx_device_push_client` (`client_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function unknown_client_names(PDO $pdo, array $names): array
{
    $names = array_values(array_unique(array_map(static fn ($name): string => (string) $name, $names)));
    if ($names === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $stmt = $pdo->prepare("SELECT client_name FROM client_list WHERE client_name IN ($placeholders)");
    $stmt->execute($names);
    $found = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return array_values(array_diff($names, $found));
}
