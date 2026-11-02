<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/panel.php';

$dbError = '';
$counts = [
    'pending' => 0,
    'sending' => 0,
    'sent' => 0,
    'failed' => 0,
];
$balances = [];

try {
    $stmt = db()->query(
        "SELECT status, COUNT(*) AS total
         FROM sms_queue
         WHERE created_at >= CURDATE()
           AND created_at < CURDATE() + INTERVAL 1 DAY
         GROUP BY status"
    );
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) $row['status'];
        if (isset($counts[$status])) {
            $counts[$status] = (int) $row['total'];
        }
    }
    $balances = client_balances();
} catch (Throwable $e) {
    $dbError = 'Database is not connected. Check host, database, user, and password in config.php.';
}

page_open('Dashboard', 'dashboard');
?>
<?php if ($dbError !== ''): ?>
    <div class="alert alert-warning"><?= h($dbError) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="meter sent">
            <span class="meter-icon" aria-hidden="true"><i class="bi bi-send-check"></i></span>
            <div>
                <span class="label">Today Sent</span>
                <strong><?= $counts['sent'] ?></strong>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="meter delivered">
            <span class="meter-icon" aria-hidden="true"><i class="bi bi-check2-all"></i></span>
            <div>
                <span class="label">Today Delivered</span>
                <strong><?= $counts['sent'] ?></strong>
                <span class="label">Phone accepted the SMS</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="meter failed">
            <span class="meter-icon" aria-hidden="true"><i class="bi bi-x-circle"></i></span>
            <div>
                <span class="label">Today Failed</span>
                <strong><?= $counts['failed'] ?></strong>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="meter pending">
            <span class="meter-icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
            <div>
                <span class="label">Today Pending</span>
                <strong><?= $counts['pending'] + $counts['sending'] ?></strong>
                <span class="label">Pending <?= $counts['pending'] ?> · Sending <?= $counts['sending'] ?></span>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5">Client balance</h2>
        <p class="text-secondary small">Balance = added amount − cost of sent SMS. Rate is per segment.</p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>SIM</th>
                        <th class="text-end">Added</th>
                        <th class="text-end">Used</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($balances === []): ?>
                        <tr><td colspan="5" class="text-secondary">কোনো ক্লায়েন্ট নেই।</td></tr>
                    <?php else: ?>
                        <?php foreach ($balances as $row): ?>
                            <tr>
                                <td><?= h((string) $row['client_name']) ?></td>
                                <td><?= h((string) ($row['sim_number'] ?? '')) ?></td>
                                <td class="text-end"><?= h(money($row['added'])) ?></td>
                                <td class="text-end"><?= h(money($row['used'])) ?></td>
                                <td class="text-end"><?= h(money($row['balance'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
page_close();
