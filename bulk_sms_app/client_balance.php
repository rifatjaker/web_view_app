<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/panel.php';

$dbError = '';
$notice = '';
$clients = [];
$balances = [];
$recent = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $clientName = trim((string) ($_POST['client_name'] ?? ''));
    $amountRaw = trim((string) ($_POST['amount'] ?? ''));
    $note = trim((string) ($_POST['note'] ?? ''));
    $amount = is_numeric($amountRaw) ? round((float) $amountRaw, 2) : 0.0;

    try {
        if ($clientName === '' || unknown_client_names(db(), [$clientName]) !== []) {
            $notice = 'Client list থেকে একটি ক্লায়েন্ট বেছে নিন।';
        } elseif ($amount <= 0 || $amount > 10000000) {
            $notice = 'Amount 0-এর বেশি এবং 10000000-এর কম হতে হবে।';
        } elseif (utf8_length($note) > 255) {
            $notice = 'Note 255 অক্ষরের মধ্যে রাখুন।';
        } else {
            $insert = db()->prepare(
                'INSERT INTO client_balance (client_name, amount, note) VALUES (?, ?, ?)'
            );
            $insert->execute([$clientName, number_format($amount, 2, '.', ''), $note === '' ? null : $note]);
            header('Location: client_balance.php?saved=1');
            exit;
        }
    } catch (Throwable $e) {
        $dbError = 'Database is not connected. Check host, database, user, and password in config.php.';
    }
}

if (isset($_GET['saved'])) {
    $notice = 'Balance যোগ হয়েছে।';
}

try {
    $clients = client_names();
    $balances = client_balances();
    $recent = db()->query(
        'SELECT id, client_name, amount, note, created_at
         FROM client_balance
         ORDER BY id DESC
         LIMIT 30'
    )->fetchAll();
} catch (Throwable $e) {
    $dbError = 'Database is not connected. Check host, database, user, and password in config.php.';
}

page_open('Client Balance', 'balance');
?>
<?php if ($dbError !== ''): ?>
    <div class="alert alert-warning"><?= h($dbError) ?></div>
<?php elseif ($notice !== ''): ?>
    <div class="alert <?= isset($_GET['saved']) ? 'alert-success' : 'alert-warning' ?>"><?= h($notice) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Add balance</h2>
        <form method="post" class="row g-3" autocomplete="off">
            <div class="col-md-4">
                <label for="client_name" class="form-label">Client</label>
                <select class="form-select" id="client_name" name="client_name" required>
                    <option value="">Select client</option>
                    <?php foreach ($clients as $clientName): ?>
                        <option value="<?= h((string) $clientName) ?>"><?= h((string) $clientName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="amount" class="form-label">Amount</label>
                <input type="number" class="form-control" id="amount" name="amount" min="0.01" step="0.01" required>
            </div>
            <div class="col-md-5">
                <label for="note" class="form-label">Note</label>
                <input type="text" class="form-control" id="note" name="note" maxlength="255" placeholder="Cash / bKash">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Add balance</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Current balance</h2>
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
                            <?php $lowBalance = (float) $row['balance'] < 50; ?>
                            <tr>
                                <td><?= h((string) $row['client_name']) ?></td>
                                <td><?= h((string) ($row['sim_number'] ?? '')) ?></td>
                                <td class="text-end"><?= h(money($row['added'])) ?></td>
                                <td class="text-end"><?= h(money($row['used'])) ?></td>
                                <td class="text-end<?= $lowBalance ? ' text-danger fw-bold' : '' ?>"><?= h(money($row['balance'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5">Recent top-ups</h2>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th class="text-end">Amount</th>
                        <th>Note</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent === []): ?>
                        <tr><td colspan="5" class="text-secondary">এখনো ব্যালেন্স যোগ হয়নি।</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent as $row): ?>
                            <tr>
                                <td><?= (int) $row['id'] ?></td>
                                <td><?= h((string) $row['client_name']) ?></td>
                                <td class="text-end"><?= h(money($row['amount'])) ?></td>
                                <td><?= h((string) ($row['note'] ?? '')) ?></td>
                                <td><?= h((string) $row['created_at']) ?></td>
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
