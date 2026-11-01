<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/sms_segments.php';
require_once __DIR__ . '/../lib/api_guard.php';

$data = api_require_body();
$token = trim((string) ($data['token'] ?? ''));
if ($token === '' || strlen($token) > 512) {
    api_fail(422, 'Device token is required.');
}

$names = [];
if (isset($data['client_names']) && is_array($data['client_names'])) {
    foreach ($data['client_names'] as $name) {
        $name = trim((string) $name);
        if ($name !== '' && !in_array($name, $names, true)) {
            $names[] = $name;
        }
    }
}
if (count($names) > 50) {
    api_fail(422, 'Select at most 50 clients for one phone.');
}

try {
    $pdo = db();
    if ($names !== []) {
        $unknown = unknown_client_names($pdo, $names);
        if ($unknown !== []) {
            api_fail(422, 'Unknown client: ' . implode(', ', $unknown) . '.');
        }
    }

    $pdo->beginTransaction();
    $delete = $pdo->prepare('DELETE FROM device_push WHERE token = ?');
    $delete->execute([$token]);
    if ($names !== []) {
        $insert = $pdo->prepare(
            'INSERT INTO device_push (token, client_name) VALUES (?, ?)'
        );
        foreach ($names as $name) {
            $insert->execute([$token, $name]);
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_fail(500, 'Could not save the phone.');
}

api_ok([
    'client_names' => $names,
]);
