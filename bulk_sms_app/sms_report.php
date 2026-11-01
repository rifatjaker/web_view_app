<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/panel.php';

$today = date('Y-m-d');
$dateFrom = (string) ($_GET['date_from'] ?? $today);
$dateTo = (string) ($_GET['date_to'] ?? $today);
$client = trim((string) ($_GET['client_name'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$allowedStatus = ['pending', 'sending', 'sent', 'failed'];

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) {
    $dateFrom = $today;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1) {
    $dateTo = $today;
}
if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}
if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

$dbError = '';
$clients = [];
$rows = [];
$totalCost = 0.0;
$totalSegments = 0;
$summary = [
    'pending' => 0,
    'sending' => 0,
    'sent' => 0,
    'failed' => 0,
    'total' => 0,
];

try {
    $clients = client_names();
    if ($client !== '' && !in_array($client, $clients, true)) {
        $client = '';
    }

    $where = 'created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $params = [$dateFrom . ' 00:00:00', $dateTo];
    if ($client !== '') {
        $where .= ' AND client_name = ?';
        $params[] = $client;
    }

    $countStmt = db()->prepare(
        "SELECT status, COUNT(*) AS total
         FROM sms_queue
         WHERE $where
         GROUP BY status"
    );
    $countStmt->execute($params);
    foreach ($countStmt->fetchAll() as $row) {
        $key = (string) $row['status'];
        if (isset($summary[$key])) {
            $summary[$key] = (int) $row['total'];
            $summary['total'] += (int) $row['total'];
        }
    }

    $listWhere = $where;
    $listParams = $params;
    if ($status !== '') {
        $listWhere .= ' AND status = ?';
        $listParams[] = $status;
    }
    $list = db()->prepare(
        "SELECT id, client_name, sender, receiver, message, segments, sms_cost, status, created_at, delivered_at
         FROM sms_queue
         WHERE $listWhere
         ORDER BY id DESC
         LIMIT 300"
    );
    $list->execute($listParams);
    $rows = $list->fetchAll();

    $costStmt = db()->prepare(
        "SELECT COALESCE(SUM(segments), 0) AS total_segments,
                COALESCE(SUM(sms_cost), 0) AS total_cost
         FROM sms_queue
         WHERE $listWhere"
    );
    $costStmt->execute($listParams);
    $totals = $costStmt->fetch() ?: [];
    $totalSegments = (int) ($totals['total_segments'] ?? 0);
    $totalCost = (float) ($totals['total_cost'] ?? 0);
} catch (Throwable $e) {
    $dbError = 'Database is not connected. Check host, database, user, and password in config.php.';
}

page_open('SMS Report', 'report');
?>
<?php if ($dbError !== ''): ?>
    <div class="alert alert-warning"><?= h($dbError) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="meter sent"><span class="meter-icon" aria-hidden="true"><i class="bi bi-send-check"></i></span><div><span class="label">Sent</span><strong><?= $summary['sent'] ?></strong></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="meter delivered"><span class="meter-icon" aria-hidden="true"><i class="bi bi-check2-all"></i></span><div><span class="label">Delivered</span><strong><?= $summary['sent'] ?></strong></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="meter failed"><span class="meter-icon" aria-hidden="true"><i class="bi bi-x-circle"></i></span><div><span class="label">Failed</span><strong><?= $summary['failed'] ?></strong></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="meter pending"><span class="meter-icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span><div><span class="label">Pending</span><strong><?= $summary['pending'] + $summary['sending'] ?></strong></div></div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="date_from" class="form-label">From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= h($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= h($dateTo) ?>">
            </div>
            <div class="col-md-3">
                <label for="client_name" class="form-label">Client</label>
                <select class="form-select" id="client_name" name="client_name">
                    <option value="">All clients</option>
                    <?php foreach ($clients as $clientName): ?>
                        <option value="<?= h((string) $clientName) ?>" <?= $client === (string) $clientName ? 'selected' : '' ?>><?= h((string) $clientName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="sending" <?= $status === 'sending' ? 'selected' : '' ?>>Sending</option>
                    <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent / Delivered</option>
                    <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5">Messages <span class="text-secondary fs-6"><?= (int) $summary['total'] ?> in range<?= $status !== '' ? ', filtered list below' : '' ?></span></h2>
            <table id="sms_report_table" class="table table-sm table-bordered align-middle mb-0 w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Sender</th>
                        <th>Receiver</th>
                        <th>Message</th>
                        <th>Segments</th>
                        <th>Cost</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Delivered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= (int) $row['id'] ?></td>
                                <td><?= h((string) $row['client_name']) ?></td>
                                <td><?= h((string) ($row['sender'] ?? '')) ?></td>
                                <td><?= h((string) $row['receiver']) ?></td>
                                <td><span class="d-none"><?= h((string) $row['message']) ?></span><span class="message-hover" tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="auto" data-bs-custom-class="sms-popover" data-bs-content="<?= h((string) $row['message']) ?>"><?= h(excerpt((string) $row['message'])) ?></span></td>
                                <td><?= (int) $row['segments'] ?></td>
                                <td><?= h(money($row['sms_cost'])) ?></td>
                                <td><?= h((string) $row['status']) ?></td>
                                <td><?= h((string) $row['created_at']) ?></td>
                                <td><?= h((string) ($row['delivered_at'] ?? '')) ?></td>
                            </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th class="text-end">Total</th>
                        <th><?= (int) $totalSegments ?></th>
                        <th><?= h(money($totalCost)) ?></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
    </div>
</div>
<?php
page_close(<<<'HTML'
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
const reportTable = document.getElementById("sms_report_table");
if (reportTable) {
    const api = $("#sms_report_table").DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, "desc"]],
        autoWidth: false,
        scrollX: true,
        language: {
            emptyTable: "এই ফিল্টারে কোনো SMS নেই।",
            zeroRecords: "এই সার্চে কোনো SMS নেই।",
            search: "Search",
            lengthMenu: "Show _MENU_",
            info: "Showing _START_ to _END_ of _TOTAL_",
            infoEmpty: "Showing 0 to 0 of 0",
            paginate: { previous: "Prev", next: "Next" }
        }
    });
    const bindPopovers = function () {
        document.querySelectorAll("[data-bs-toggle=popover]").forEach(function (el) {
            const existing = bootstrap.Popover.getInstance(el);
            if (existing) {
                existing.dispose();
            }
            new bootstrap.Popover(el, { container: "body", placement: "bottom" });
        });
    };
    api.on("draw", bindPopovers);
    bindPopovers();
}
</script>
HTML);
