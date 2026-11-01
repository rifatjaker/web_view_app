<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/sms_segments.php';
require_once __DIR__ . '/../lib/api_guard.php';

api_require_body();

try {
    $rows = db()->query(
        'SELECT id, client_name FROM client_list ORDER BY client_name ASC'
    )->fetchAll();
} catch (Throwable $e) {
    api_fail(500, 'Could not load clients.');
}

$data = [];
foreach ($rows as $row) {
    $data[] = [
        'id' => (int) $row['id'],
        'client_name' => (string) $row['client_name'],
    ];
}

api_ok([
    'count' => count($data),
    'data' => $data,
]);
