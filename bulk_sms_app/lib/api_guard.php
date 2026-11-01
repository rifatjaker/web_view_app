<?php

declare(strict_types=1);

function api_headers(): void
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, Authorization');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
}

function api_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_ok(array $body, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(array_merge(['status' => 'success'], $body), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_require_body(): array
{
    api_headers();

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        api_fail(405, 'Use POST JSON.');
    }

    $configured = (string) (app_config()['api_key'] ?? '');
    $given = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($given === '' && preg_match('/^Bearer\s+(\S+)$/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $match) === 1) {
        $given = $match[1];
    }
    if ($configured === '' || $given === '' || !hash_equals($configured, $given)) {
        api_fail(401, 'Invalid API key.');
    }

    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            api_fail(400, 'Send a JSON body.');
        }

        return $decoded;
    }
    if ($_POST !== []) {
        return $_POST;
    }

    api_fail(400, 'Send a JSON body.');
}

function api_client_name(array $data): string
{
    $name = trim((string) ($data['client_name'] ?? ''));
    if ($name === '') {
        api_fail(422, 'Client name is required.');
    }
    if (utf8_length($name) > 100) {
        api_fail(422, 'Client name is longer than 100 characters.');
    }

    return $name;
}
