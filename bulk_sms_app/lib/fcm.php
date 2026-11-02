<?php

declare(strict_types=1);

function fcm_notify_clients(PDO $pdo, array $clientNames): void
{
    $account = fcm_service_account();
    if ($account === null) {
        return;
    }

    $names = [];
    foreach ($clientNames as $name) {
        $name = trim((string) $name);
        if ($name !== '' && !in_array($name, $names, true)) {
            $names[] = $name;
        }
    }
    if ($names === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT token FROM device_push WHERE client_name IN ($placeholders)"
    );
    $stmt->execute($names);
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tokens as $token) {
        fcm_send($pdo, $account, (string) $token);
    }
}

function fcm_service_account(): ?array
{
    $path = (string) (app_config()['fcm']['service_account'] ?? '');
    if ($path === '' || !is_file($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return null;
    }

    $projectId = trim((string) ($decoded['project_id'] ?? ''));
    $email = trim((string) ($decoded['client_email'] ?? ''));
    $privateKey = (string) ($decoded['private_key'] ?? '');
    if ($projectId === '' || $email === '' || $privateKey === '') {
        return null;
    }

    return [
        'project_id' => $projectId,
        'client_email' => $email,
        'private_key' => $privateKey,
    ];
}

function fcm_send(PDO $pdo, array $account, string $token): void
{
    if ($token === '') {
        return;
    }

    $accessToken = fcm_access_token($account);
    if ($accessToken === '') {
        return;
    }

    $body = json_encode([
        'message' => [
            'token' => $token,
            'data' => [
                'action' => 'poll',
            ],
            'android' => [
                'priority' => 'HIGH',
                'ttl' => '30s',
                'direct_boot_ok' => true,
            ],
        ],
    ], JSON_UNESCAPED_SLASHES);
    if (!is_string($body)) {
        return;
    }

    $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($account['project_id']) . '/messages:send';
    $ch = curl_init($url);
    if ($ch === false) {
        return;
    }

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json; charset=UTF-8',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 404 || fcm_token_unregistered(is_string($response) ? $response : '')) {
        $delete = $pdo->prepare('DELETE FROM device_push WHERE token = ?');
        $delete->execute([$token]);
    }
}

function fcm_token_unregistered(string $response): bool
{
    return str_contains($response, 'UNREGISTERED');
}

function fcm_access_token(array $account): string
{
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bulk-sms-fcm-token.json';
    if (is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        $token = is_array($cached) ? (string) ($cached['access_token'] ?? '') : '';
        $expires = is_array($cached) ? (int) ($cached['expires_at'] ?? 0) : 0;
        if ($token !== '' && $expires > time() + 60) {
            return $token;
        }
    }

    $now = time();
    $header = fcm_base64url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim = fcm_base64url((string) json_encode([
        'iss' => $account['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ]));
    $input = $header . '.' . $claim;
    $signature = '';
    if (!openssl_sign($input, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
        return '';
    }

    $jwt = $input . '.' . fcm_base64url($signature);
    $ch = curl_init('https://oauth2.googleapis.com/token');
    if ($ch === false) {
        return '';
    }

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $response = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode(is_string($response) ? $response : '', true);
    $accessToken = is_array($decoded) ? (string) ($decoded['access_token'] ?? '') : '';
    $expiresIn = is_array($decoded) ? (int) ($decoded['expires_in'] ?? 0) : 0;
    if ($accessToken === '' || $expiresIn < 60) {
        return '';
    }

    file_put_contents($cacheFile, json_encode([
        'access_token' => $accessToken,
        'expires_at' => time() + $expiresIn,
    ]));

    return $accessToken;
}

function fcm_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
