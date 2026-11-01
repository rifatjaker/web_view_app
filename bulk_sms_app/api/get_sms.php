<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/sms_segments.php';
require_once __DIR__ . '/../lib/api_guard.php';

$data = api_require_body();
$clientNames = api_client_names($data);

try {
    $pdo = db();
    $unknown = unknown_client_names($pdo, $clientNames);
    if ($unknown !== []) {
        api_fail(422, 'Unknown client: ' . implode(', ', $unknown));
    }

    $pdo->beginTransaction();
    $placeholders = implode(',', array_fill(0, count($clientNames), '?'));

    $reset = $pdo->prepare(
        "UPDATE sms_queue
         SET status = 'pending', claimed_at = NULL
         WHERE client_name IN ($placeholders)
           AND status = 'sending'
           AND claimed_at IS NOT NULL
           AND claimed_at < (NOW() - INTERVAL 30 MINUTE)"
    );
    $reset->execute($clientNames);

    $pick = $pdo->prepare(
        "SELECT id, client_name, receiver, message, segments
         FROM sms_queue
         WHERE client_name IN ($placeholders) AND status = 'pending'
         ORDER BY id ASC
         LIMIT 10
         FOR UPDATE"
    );
    $pick->execute($clientNames);
    $rows = $pick->fetchAll();

    if ($rows !== []) {
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $claim = $pdo->prepare(
            "UPDATE sms_queue SET status = 'sending', claimed_at = NOW() WHERE id IN ($placeholders)"
        );
        $claim->execute($ids);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_fail(500, 'Could not load pending SMS.');
}

$payload = [];
foreach ($rows as $row) {
    $payload[] = [
        'id' => (int) $row['id'],
        'client_name' => (string) $row['client_name'],
        'receiver' => (string) $row['receiver'],
        'message' => (string) $row['message'],
        'segments' => (int) $row['segments'],
    ];
}

api_ok([
    'client_names' => $clientNames,
    'count' => count($payload),
    'data' => $payload,
]);

function api_client_names(array $data): array
{
    $names = [];
    if (isset($data['client_names']) && is_array($data['client_names'])) {
        foreach ($data['client_names'] as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $names[] = $name;
            }
        }
    }
    if ($names === []) {
        $single = trim((string) ($data['client_name'] ?? ''));
        if ($single !== '') {
            $names[] = $single;
        }
    }

    $names = array_values(array_unique($names));
    if ($names === []) {
        api_fail(422, 'Select at least one client.');
    }
    if (count($names) > 50) {
        api_fail(422, 'Select at most 50 clients.');
    }
    foreach ($names as $name) {
        if (utf8_length($name) > 100) {
            api_fail(422, 'Client name is longer than 100 characters.');
        }
    }

    return $names;
}
