<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/lib/sms_segments.php';
require_once __DIR__ . '/auth.php';

auth_require();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function excerpt(string $message, int $limit = 80): string
{
    return utf8_excerpt($message, $limit);
}

function money(float|string $amount): string
{
    return number_format((float) $amount, 2, '.', '');
}

function panel_nav(string $active): void
{
    $items = [
        'dashboard' => ['dashboard.php', 'Dashboard', 'bi-speedometer2'],
        'insert' => ['sms_queue_insert.php', 'Insert SMS', 'bi-send'],
        'balance' => ['client_balance.php', 'Client Balance', 'bi-wallet2'],
        'report' => ['sms_report.php', 'Report', 'bi-bar-chart-line'],
    ];

    echo '<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">';
    echo '<div class="container-fluid">';
    echo '<a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">';
    echo '<img class="brand-logo" src="https://a2technologiesbd.com/favicon.png" alt="A2 Technologies" width="32" height="32">';
    echo 'Bulk SMS</a>';
    echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">';
    echo '<span class="navbar-toggler-icon"></span></button>';
    echo '<div class="collapse navbar-collapse" id="mainNav"><ul class="navbar-nav ms-auto">';
    foreach ($items as $key => $item) {
        $class = $key === $active ? 'nav-link active' : 'nav-link';
        echo '<li class="nav-item"><a class="' . $class . '" href="' . h($item[0]) . '"><i class="bi ' . h($item[2]) . '" aria-hidden="true"></i>' . h($item[1]) . '</a></li>';
    }
    echo '<li class="nav-item"><span class="nav-link text-white-50"><i class="bi bi-person-badge" aria-hidden="true"></i>Super Admin</span></li>';
    echo '<li class="nav-item"><form method="post" action="logout.php" class="logout-form">';
    echo '<input type="hidden" name="csrf" value="' . h(auth_csrf_token()) . '">';
    echo '<button type="submit" class="nav-link"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Logout</button>';
    echo '</form></li>';
    echo '</ul></div></div></nav>';
}

function page_open(string $title, string $active): void
{
    echo '<!DOCTYPE html><html lang="bn"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<link rel="icon" href="https://a2technologiesbd.com/favicon.png" type="image/png">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">';
    echo '<style>
        body { background: #f4f6f8; }
        .brand-logo { width: 32px; height: 32px; border-radius: 6px; background: #fff; object-fit: contain; }
        .navbar-nav .nav-link { display: flex; align-items: center; gap: .4rem; }
        .navbar-nav .nav-link .bi { font-size: 1.05rem; line-height: 1; }
        .logout-form { margin: 0; }
        .logout-form .nav-link { width: 100%; background: transparent; border: 0; color: rgba(255,255,255,.85); }
        .logout-form .nav-link:hover { color: #fff; }
        .message-hover { cursor: help; border-bottom: 1px dotted #90a4ae; }
        .sms-popover { max-width: 420px; }
        .sms-popover .popover-body { white-space: pre-wrap; max-height: 240px; overflow: auto; }
        textarea { min-height: 140px; }
        .stat-box, .meter { background: #fff; border: 1px solid #e6eaee; border-radius: .75rem; padding: 1rem 1.1rem; height: 100%; }
        .meter { display: flex; align-items: center; gap: .85rem; }
        .meter-icon { width: 46px; height: 46px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.35rem; flex: 0 0 auto; }
        .meter.sent .meter-icon { background: #e3f2fd; color: #1565C0; }
        .meter.delivered .meter-icon { background: #e8f5e9; color: #2e7d32; }
        .meter.failed .meter-icon { background: #ffebee; color: #c62828; }
        .meter.pending .meter-icon { background: #fff3e0; color: #ef6c00; }
        .meter .label { color: #607d8b; font-size: .85rem; }
        .meter strong { display: block; font-size: 1.7rem; line-height: 1.2; }
        .meter.sent strong { color: #1565C0; }
        .meter.failed strong { color: #c62828; }
        .meter.delivered strong { color: #2e7d32; }
        .meter.pending strong { color: #ef6c00; }
        .table { font-size: 0.8125rem; }
        .table > :not(caption) > * > * { border: 1px solid #b7c3ce; }
        .table thead th { background: #e8eef3; }
        .table tfoot th { background: #f4f7f9; }
        code { font-size: .85rem; }
    </style></head><body>';
    panel_nav($active);
    echo '<div class="container-fluid pb-5">';
    echo '<h1 class="h3 mb-3">' . h($title) . '</h1>';
}

function page_close(string $script = ''): void
{
    echo '</div>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    echo $script;
    echo '<script>document.querySelectorAll("[data-bs-toggle=popover]").forEach(function (el) { bootstrap.Popover.getOrCreateInstance(el, {container: "body"}); });</script>';
    echo '</body></html>';
}

function client_names(): array
{
    return db()->query(
        'SELECT client_name FROM client_list ORDER BY client_name ASC'
    )->fetchAll(PDO::FETCH_COLUMN);
}

function client_balances(): array
{
    $sql = "SELECT c.client_name,
                COALESCE(b.added, 0) AS added,
                COALESCE(u.used, 0) AS used,
                COALESCE(b.added, 0) - COALESCE(u.used, 0) AS balance
            FROM client_list c
            LEFT JOIN (
                SELECT client_name, SUM(amount) AS added
                FROM client_balance
                GROUP BY client_name
            ) b ON b.client_name = c.client_name
            LEFT JOIN (
                SELECT client_name, SUM(sms_cost) AS used
                FROM sms_queue
                WHERE status = 'sent'
                GROUP BY client_name
            ) u ON u.client_name = c.client_name
            ORDER BY c.client_name ASC";

    return db()->query($sql)->fetchAll();
}
