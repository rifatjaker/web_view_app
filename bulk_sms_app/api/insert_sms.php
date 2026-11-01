<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/sms_segments.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(405, [
        'status' => 'error',
        'message' => 'Use POST with JSON: {"client_name":"...","receiver":"...","message":"..."} or an array of those objects.',
    ]);
}

$configuredKey = (string) (app_config()['api_key'] ?? '');
if ($configuredKey === '' || $configuredKey === 'CHANGE_ME') {
    json_out(500, [
        'status' => 'error',
        'message' => 'API key is not configured.',
    ]);
}

$givenKey = request_api_key();
if ($givenKey === '' || !hash_equals($configuredKey, $givenKey)) {
    json_out(401, [
        'status' => 'error',
        'message' => 'Invalid API key.',
    ]);
}

$payload = request_payload();
if ($payload === null) {
    json_out(400, [
        'status' => 'error',
        'message' => 'Send JSON with client_name, receiver, and message. segments and sms_cost are calculated here.',
    ]);
}

$items = normalize_payload_items($payload);
if (isset($items['error'])) {
    json_out(400, [
        'status' => 'error',
        'message' => $items['error'],
    ]);
}

if (count($items) > 100) {
    json_out(400, [
        'status' => 'error',
        'message' => 'Send at most 100 messages per request.',
    ]);
}

$errors = [];
$ready = [];
foreach ($items as $index => $item) {
    $checked = validate_sms_item($item);
    if (isset($checked['error'])) {
        $errors[] = [
            'index' => $index,
            'message' => $checked['error'],
        ];
        continue;
    }
    $ready[] = $checked;
}

if ($errors !== []) {
    json_out(422, [
        'status' => 'error',
        'message' => 'Validation failed. Nothing was saved.',
        'errors' => $errors,
    ]);
}

try {
    $pdo = db();
    $unknown = unknown_client_names($pdo, array_column($ready, 'client_name'));
    if ($unknown !== []) {
        json_out(422, [
            'status' => 'error',
            'message' => 'Unknown client: ' . implode(', ', $unknown) . '. Add it in client_list.',
        ]);
    }
    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        'INSERT INTO sms_queue (client_name, receiver, message, segments, sms_cost, status) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $ids = [];
    foreach ($ready as $row) {
        $insert->execute([
            $row['client_name'],
            $row['receiver'],
            $row['message'],
            $row['segments'],
            $row['sms_cost'],
            'pending',
        ]);
        $ids[] = (int) $pdo->lastInsertId();
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $select = $pdo->prepare(
        "SELECT id, client_name, receiver, message, segments, sms_cost, status, created_at
         FROM sms_queue
         WHERE id IN ($placeholders)
         ORDER BY id ASC"
    );
    $select->execute($ids);
    $saved = $select->fetchAll();
    $pdo->commit();
    try {
        require_once __DIR__ . '/../lib/fcm.php';
        fcm_notify_clients($pdo, array_column($ready, 'client_name'));
    } catch (Throwable $e) {
        // The SMS is already saved. The phone still picks it up on the backup poll.
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(500, [
        'status' => 'error',
        'message' => 'Could not save the SMS queue.',
    ]);
}

$saved = attach_encoding($saved, $ready);

json_out(201, [
    'status' => 'success',
    'count' => count($saved),
    'data' => count($saved) === 1 ? $saved[0] : $saved,
]);

function json_out(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_api_key(): string
{
    $header = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($header !== '') {
        return $header;
    }

    $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (preg_match('/^Bearer\s+(\S+)$/i', $authorization, $match) === 1) {
        return $match[1];
    }

    return '';
}

function request_payload(): ?array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    if ($_POST !== []) {
        return $_POST;
    }

    return null;
}

function normalize_payload_items(array $payload): array
{
    if (array_is_list($payload)) {
        if ($payload === []) {
            return ['error' => 'Send at least one client_name, receiver, and message.'];
        }
        foreach ($payload as $item) {
            if (!is_array($item)) {
                return ['error' => 'Each item must be an object with client_name, receiver, and message.'];
            }
        }

        return $payload;
    }

    return [$payload];
}

function validate_sms_item(array $item): array
{
    $clientName = trim((string) ($item['client_name'] ?? ''));
    $receiver = normalize_receiver((string) ($item['receiver'] ?? ''));
    $message = trim((string) ($item['message'] ?? ''));

    if ($clientName === '') {
        return ['error' => 'Client name is required.'];
    }
    if (utf8_length($clientName) > 100) {
        return ['error' => 'Client name is longer than 100 characters.'];
    }
    if ($receiver === '') {
        return ['error' => 'Receiver is required.'];
    }
    if (preg_match('/^(?:\+[0-9]{8,19}|[0-9]{8,20})$/', $receiver) !== 1) {
        return ['error' => 'Receiver must be 8 to 20 digits, with an optional leading +.'];
    }
    if ($message === '') {
        return ['error' => 'Message is required.'];
    }
    if (utf8_length($message) > 2000) {
        return ['error' => 'Message is longer than 2000 characters.'];
    }

    $stats = sms_text_stats($message);
    if ($stats['segments'] < 1) {
        return ['error' => 'Message is required.'];
    }

    $rate = (float) (app_config()['sms_rate'] ?? 0.48);

    return [
        'client_name' => $clientName,
        'receiver' => $receiver,
        'message' => $message,
        'segments' => $stats['segments'],
        'sms_cost' => sms_cost_from_segments($stats['segments'], $rate),
        'encoding' => $stats['encoding'],
        'length' => $stats['length'],
    ];
}

function normalize_receiver(string $receiver): string
{
    $receiver = trim($receiver);
    return str_replace([' ', '-', '(', ')'], '', $receiver);
}

function attach_encoding(array $saved, array $ready): array
{
    foreach ($saved as $index => $row) {
        $saved[$index]['id'] = (int) $row['id'];
        $saved[$index]['segments'] = (int) $row['segments'];
        $saved[$index]['sms_cost'] = number_format((float) $row['sms_cost'], 2, '.', '');
        $saved[$index]['encoding'] = $ready[$index]['encoding'] ?? '';
        $saved[$index]['length'] = $ready[$index]['length'] ?? 0;
    }

    return $saved;
}
