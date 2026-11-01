<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && auth_csrf_ok((string) ($_POST['csrf'] ?? ''))) {
    auth_clear_session();
    header('Location: login.php');
    exit;
}

header('Location: ' . (auth_logged_in() ? 'dashboard.php' : 'login.php'));
exit;
