<?php

declare(strict_types=1);

/**
 * Copy this file to config.php and fill in the real values.
 * config.php stays off git because this repository is public.
 */
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'a2techno_bulk_sms',
        'user' => 'a2techno_bulk_sms',
        'pass' => 'change-me',
        'charset' => 'utf8mb4',
    ],
    'api_key' => 'change-me',
    'sms_rate' => 0.48,
    'admin' => [
        'username' => 'admin',
        'password_hash' => 'generate-with-password_hash',
    ],
    'fcm' => [
        'service_account' => __DIR__ . '/secrets/firebase-service-account.json',
    ],
];
