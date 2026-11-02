<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/panel.php';

function sms_delay_seconds(string $created, string $delivered): ?int
{
    if ($created === '' || $delivered === '') {
        return null;
    }
    $start = strtotime($created);
    $end = strtotime($delivered);
    if ($start === false || $end === false || $end < $start) {
        return null;
    }

    return $end - $start;
}

function sms_delay_label(?int $seconds): string
{
    if ($seconds === null) {
        return '';
    }
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remain = $seconds % 60;
    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm ' . $remain . 's';
    }
    if ($minutes > 0) {
        return $minutes . 'm ' . $remain . 's';
    }

    return $remain . 's';
}

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

function sms_report_date(string $value, string $fallback): string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : $fallback;
}

function sms_retry_failed(string $dateFrom, string $dateTo, string $client, int $id): int
{
    $pdo = db();
    if ($id > 0) {
        $where = "status = 'failed' AND id = ?";
        $params = [$id];
    } else {
        $where = "status = 'failed' AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $params = [$dateFrom . ' 00:00:00', $dateTo];
        if ($client !== '') {
            $where .= ' AND client_name = ?';
            $params[] = $client;
        }
    }

    $namesStmt = $pdo->prepare("SELECT DISTINCT client_name FROM sms_queue WHERE $where");
    $namesStmt->execute($params);
    $names = $namesStmt->fetchAll(PDO::FETCH_COLUMN);
    if ($names === []) {
        return 0;
    }

    $update = $pdo->prepare(
        "UPDATE sms_queue
         SET status = 'pending',
             claimed_at = NULL,
             delivered_at = NULL,
             fail_reason = NULL
         WHERE $where"
    );
    $update->execute($params);
    $count = $update->rowCount();
    if ($count > 0) {
        try {
            require_once __DIR__ . '/lib/fcm.php';
            fcm_notify_clients($pdo, $names);
        } catch (Throwable $e) {
            // The row is already pending. The phone still picks it up on the backup poll.
        }
    }

    return $count;
}

$retryNotice = '';
if (isset($_GET['retried']) && preg_match('/^\d+$/', (string) $_GET['retried']) === 1) {
    $retried = (int) $_GET['retried'];
    $retryNotice = $retried === 1
        ? '1 failed SMS is pending again.'
        : $retried . ' failed SMS are pending again.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $postedFrom = sms_report_date((string) ($_POST['date_from'] ?? ''), $dateFrom);
    $postedTo = sms_report_date((string) ($_POST['date_to'] ?? ''), $dateTo);
    if ($postedFrom > $postedTo) {
        [$postedFrom, $postedTo] = [$postedTo, $postedFrom];
    }
    $postedClient = trim((string) ($_POST['client_name'] ?? ''));
    $postedStatus = (string) ($_POST['status'] ?? '');
    if (!in_array($postedStatus, $allowedStatus, true)) {
        $postedStatus = '';
    }
    $postedId = (int) ($_POST['id'] ?? 0);

    if (!auth_csrf_ok(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $retryNotice = 'Retry was rejected. Reload the page and try again.';
    } else {
        try {
            if ($postedClient !== '' && !in_array($postedClient, client_names(), true)) {
                $postedClient = '';
            }
            $count = sms_retry_failed($postedFrom, $postedTo, $postedClient, $postedId);
            header('Location: sms_report.php?' . http_build_query([
                'date_from' => $postedFrom,
                'date_to' => $postedTo,
                'client_name' => $postedClient,
                'status' => $postedStatus,
                'retried' => $count,
            ]));
            exit;
        } catch (Throwable $e) {
            $retryNotice = 'Could not retry the failed SMS.';
        }
    }
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

    $where = 'sms_queue.created_at >= ? AND sms_queue.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $params = [$dateFrom . ' 00:00:00', $dateTo];
    if ($client !== '') {
        $where .= ' AND sms_queue.client_name = ?';
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
        $listWhere .= ' AND sms_queue.status = ?';
        $listParams[] = $status;
    }
    $list = db()->prepare(
        "SELECT sms_queue.id, sms_queue.client_name,
                COALESCE(NULLIF(sms_queue.sender, ''), client_list.sim_number) AS sender,
                sms_queue.receiver, sms_queue.message, sms_queue.segments, sms_queue.sms_cost,
                sms_queue.status, sms_queue.fail_reason, sms_queue.created_at, sms_queue.delivered_at
         FROM sms_queue
         LEFT JOIN client_list ON client_list.client_name = sms_queue.client_name
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
<?php if ($retryNotice !== ''): ?>
    <div class="alert alert-info"><?= h($retryNotice) ?></div>
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
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h5 mb-0">Messages <span class="text-secondary fs-6"><?= (int) $summary['total'] ?> in range<?= $status !== '' ? ', filtered list below' : '' ?></span></h2>
            <?php if ($summary['failed'] > 0): ?>
                <form method="post" action="sms_report.php">
                    <input type="hidden" name="csrf" value="<?= h(auth_csrf_token()) ?>">
                    <input type="hidden" name="date_from" value="<?= h($dateFrom) ?>">
                    <input type="hidden" name="date_to" value="<?= h($dateTo) ?>">
                    <input type="hidden" name="client_name" value="<?= h($client) ?>">
                    <input type="hidden" name="status" value="<?= h($status) ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('এই ফিল্টারের সব failed SMS আবার পাঠানো হবে।')">Retry failed (<?= (int) $summary['failed'] ?>)</button>
                </form>
            <?php endif; ?>
        </div>
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
                        <th>Reason</th>
                        <th>Created</th>
                        <th>Delivered</th>
                        <th>Delay</th>
                        <th>Retry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                            <?php $delaySeconds = sms_delay_seconds((string) $row['created_at'], (string) ($row['delivered_at'] ?? '')); ?>
                            <tr>
                                <td><?= (int) $row['id'] ?></td>
                                <td><?= h((string) $row['client_name']) ?></td>
                                <td><?= h((string) ($row['sender'] ?? '')) ?></td>
                                <td><?= h((string) $row['receiver']) ?></td>
                                <td><span class="d-none"><?= h((string) $row['message']) ?></span><span class="message-hover" tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="auto" data-bs-custom-class="sms-popover" data-bs-content="<?= h((string) $row['message']) ?>"><?= h(excerpt((string) $row['message'])) ?></span></td>
                                <td><?= (int) $row['segments'] ?></td>
                                <td><?= h(money($row['sms_cost'])) ?></td>
                                <td><?= h((string) $row['status']) ?></td>
                                <td><?= (string) $row['status'] === 'failed' ? h((string) ($row['fail_reason'] ?? '')) : '' ?></td>
                                <td><?= h((string) $row['created_at']) ?></td>
                                <td><?= h((string) ($row['delivered_at'] ?? '')) ?></td>
                                <td data-order="<?= $delaySeconds === null ? '' : (int) $delaySeconds ?>"><?= h(sms_delay_label($delaySeconds)) ?></td>
                                <td>
                                    <?php if ((string) $row['status'] === 'failed'): ?>
                                        <form method="post" action="sms_report.php">
                                            <input type="hidden" name="csrf" value="<?= h(auth_csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="date_from" value="<?= h($dateFrom) ?>">
                                            <input type="hidden" name="date_to" value="<?= h($dateTo) ?>">
                                            <input type="hidden" name="client_name" value="<?= h($client) ?>">
                                            <input type="hidden" name="status" value="<?= h($status) ?>">
                                            <button type="submit" class="btn btn-outline-primary btn-sm" onclick="return confirm('এই SMS আবার পাঠানো হবে।')">Retry</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
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
        columnDefs: [{ targets: -1, orderable: false, searchable: false }],
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
