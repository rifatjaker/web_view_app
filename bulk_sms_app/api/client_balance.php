<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/sms_segments.php';
require_once __DIR__ . '/../lib/api_guard.php';

$data = api_require_body();
$clientName = api_client_name($data);

try {
    $pdo = db();
    $unknown = unknown_client_names($pdo, [$clientName]);
    if ($unknown !== []) {
        api_fail(422, 'Unknown client: ' . $clientName . '.');
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(b.added, 0) AS added,
                COALESCE(u.used, 0) AS used,
                COALESCE(b.added, 0) - COALESCE(u.used, 0) AS balance
         FROM client_list c
         LEFT JOIN (
             SELECT client_name, SUM(amount) AS added
             FROM client_balance
             GROUP BY client_name
         ) b ON b.client_name = c.client_name
         LEFT JOIN (
             SELECT client_name, SUM(sms_cost) AS used
             FROM sms_queue
             WHERE status = 'sent'
             GROUP BY client_name
         ) u ON u.client_name = c.client_name
         WHERE c.client_name = ?
         LIMIT 1"
    );
    $stmt->execute([$clientName]);
    $row = $stmt->fetch();
    if ($row === false) {
        api_fail(422, 'Unknown client: ' . $clientName . '.');
    }
} catch (Throwable $e) {
    api_fail(500, 'Could not load the balance.');
}

api_ok([
    'client_name' => $clientName,
    'added' => number_format((float) $row['added'], 2, '.', ''),
    'used' => number_format((float) $row['used'], 2, '.', ''),
    'balance' => number_format((float) $row['balance'], 2, '.', ''),
]);
