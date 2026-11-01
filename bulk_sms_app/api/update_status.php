<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/sms_segments.php';
require_once __DIR__ . '/../lib/api_guard.php';

$data = api_require_body();
$clientName = api_client_name($data);
$id = (int) ($data['id'] ?? 0);
$status = (string) ($data['status'] ?? '');
$sender = preg_replace('/[^\d+]/', '', (string) ($data['sender'] ?? '')) ?? '';
if (preg_match('/^\+?\d{8,20}$/', $sender) !== 1) {
    $sender = '';
}

if ($id < 1) {
    api_fail(422, 'id is required.');
}
if ($status !== 'sent' && $status !== 'failed') {
    api_fail(422, 'Status must be sent or failed.');
}

try {
    $pdo = db();
    $currentStmt = $pdo->prepare(
        'SELECT status FROM sms_queue WHERE id = ? AND client_name = ? LIMIT 1'
    );
    $currentStmt->execute([$id, $clientName]);
    $current = $currentStmt->fetchColumn();
    if ($current === false) {
        api_fail(404, 'SMS was not found for this client.');
    }
    if ($current === 'sent' || $current === 'failed') {
        api_ok([
            'id' => $id,
            'client_name' => $clientName,
            'status' => (string) $current,
        ]);
    }

    $update = $pdo->prepare(
        "UPDATE sms_queue
         SET status = ?,
             claimed_at = NULL,
             delivered_at = CASE WHEN ? = 'sent' THEN NOW() ELSE delivered_at END,
             sender = CASE WHEN ? <> '' THEN ? ELSE sender END
         WHERE id = ? AND client_name = ? AND status IN ('pending', 'sending')"
    );
    $update->execute([$status, $status, $sender, $sender, $id, $clientName]);
} catch (Throwable $e) {
    api_fail(500, 'Could not update SMS status.');
}

api_ok([
    'id' => $id,
    'client_name' => $clientName,
    'status' => $status,
]);
