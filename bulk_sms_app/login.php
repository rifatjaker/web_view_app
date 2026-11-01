<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth.php';

if (auth_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $postedNext = (string) ($_POST['next'] ?? '');
    if (!auth_csrf_ok((string) ($_POST['csrf'] ?? '')) || !auth_attempt($username, $password)) {
        $error = 'Username or password is wrong.';
    } else {
        header('Location: ' . auth_safe_next($postedNext));
        exit;
    }
}

$next = auth_safe_next((string) ($_POST['next'] ?? $_GET['next'] ?? ''));
$token = auth_csrf_token();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link rel="icon" href="https://a2technologiesbd.com/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f8; }
        .brand-logo { width: 48px; height: 48px; border-radius: 10px; background: #fff; object-fit: contain; }
        .login-card { max-width: 420px; }
        .input-group-text { background: #f4f7fb; color: #1565C0; }
    </style>
</head>
<body>
    <main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
        <div class="card shadow-sm login-card w-100">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img class="brand-logo mb-3" src="https://a2technologiesbd.com/favicon.png" alt="A2 Technologies" width="48" height="48">
                    <h1 class="h4 mb-1">Bulk SMS</h1>
                    <p class="text-secondary mb-0">Super Admin login</p>
                </div>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
                <?php endif; ?>
                <form method="post" action="login.php">
                    <input type="hidden" name="csrf" value="<?= h($token) ?>">
                    <input type="hidden" name="next" value="<?= h($next) ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
                            <input class="form-control" id="username" name="username" autocomplete="username" required autofocus>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Login</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
