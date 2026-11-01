<?php

declare(strict_types=1);

const AUTH_IDLE_SECONDS = 43200;

function auth_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

    session_name('bulk_sms_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function auth_csrf_token(): string
{
    auth_boot();
    $token = $_SESSION['csrf'] ?? '';
    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(16));
        $_SESSION['csrf'] = $token;
    }

    return $token;
}

function auth_csrf_ok(?string $token): bool
{
    auth_boot();
    $stored = $_SESSION['csrf'] ?? '';

    return is_string($stored) && $stored !== '' && is_string($token) && hash_equals($stored, $token);
}

function auth_safe_next(string $next): string
{
    $allowed = [
        'dashboard.php',
        'sms_queue_insert.php',
        'client_balance.php',
        'sms_report.php',
    ];

    return in_array($next, $allowed, true) ? $next : 'dashboard.php';
}

function auth_logged_in(): bool
{
    auth_boot();
    $user = $_SESSION['admin_user'] ?? '';
    $seen = $_SESSION['admin_seen'] ?? 0;
    if (!is_string($user) || $user === '' || !is_numeric($seen)) {
        return false;
    }

    if ((time() - (int) $seen) > AUTH_IDLE_SECONDS) {
        auth_clear_session();
        return false;
    }

    $expected = (string) (app_config()['admin']['username'] ?? '');
    if ($expected === '' || !hash_equals($expected, $user)) {
        auth_clear_session();
        return false;
    }

    $_SESSION['admin_seen'] = time();

    return true;
}

function auth_require(): void
{
    if (auth_logged_in()) {
        return;
    }

    $next = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $target = 'login.php';
    if (auth_safe_next($next) === $next) {
        $target .= '?next=' . rawurlencode($next);
    }
    header('Location: ' . $target);
    exit;
}

function auth_attempt(string $username, string $password): bool
{
    $admin = app_config()['admin'] ?? [];
    $expectedUser = (string) ($admin['username'] ?? '');
    $hash = (string) ($admin['password_hash'] ?? '');
    $username = trim($username);
    $userOk = $expectedUser !== '' && hash_equals($expectedUser, $username);
    $passOk = $hash !== '' && password_verify($password, $hash);
    if (!$userOk || !$passOk) {
        return false;
    }

    auth_boot();
    session_regenerate_id(true);
    $_SESSION['admin_user'] = $expectedUser;
    $_SESSION['admin_seen'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(16));

    return true;
}

function auth_clear_session(): void
{
    auth_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] !== '' ? $params['path'] : '/',
            'domain' => (string) ($params['domain'] ?? ''),
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ]);
    }
    session_destroy();
}
