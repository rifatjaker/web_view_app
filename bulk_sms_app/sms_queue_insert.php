<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/panel.php';

$apiKey = (string) (app_config()['api_key'] ?? '');
$smsRate = (float) (app_config()['sms_rate'] ?? 0.48);
$dbError = '';
$recent = [];
$clients = [];

try {
    $clients = db()->query(
        'SELECT client_name FROM client_list ORDER BY client_name ASC'
    )->fetchAll(PDO::FETCH_COLUMN);
    $recentStmt = db()->query(
        'SELECT id, client_name, receiver, message, segments, sms_cost, status, created_at
         FROM sms_queue
         ORDER BY id DESC
         LIMIT 20'
    );
    $recent = $recentStmt->fetchAll();
} catch (Throwable $e) {
    $dbError = 'Database is not connected. Check host, database, user, and password in config.php.';
}

page_open('Insert SMS', 'insert');
?>
    <p class="text-secondary">অন্য প্রজেক্ট <code>client_name</code>, <code>receiver</code>, আর <code>message</code> পাঠাবে। <code>segments</code> ক্যারেক্টার দৈর্ঘ্য থেকে, <code>sms_cost</code> সেগমেন্ট × <?= h(number_format($smsRate, 2, '.', '')) ?> হিসাব হয়ে সেভ হবে।</p>

    <?php if ($dbError !== ''): ?>
        <div class="alert alert-warning"><?= h($dbError) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="sms_form" autocomplete="off">
                <div class="mb-3">
                    <label for="client_name" class="form-label">Client name</label>
                    <select class="form-select" id="client_name" name="client_name" required>
                        <option value="">Select client</option>
                        <?php foreach ($clients as $clientName): ?>
                            <option value="<?= h((string) $clientName) ?>"><?= h((string) $clientName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">নতুন ক্লায়েন্ট `client_list` টেবিলে যোগ করুন।</div>
                </div>
                <div class="mb-3">
                    <label for="receiver" class="form-label">Receiver</label>
                    <input type="text" class="form-control" id="receiver" name="receiver" inputmode="tel" placeholder="01712345678" required>
                </div>
                <div class="mb-3">
                    <label for="message" class="form-label">Message</label>
                    <textarea class="form-control" id="message" name="message" placeholder="মেসেজ লিখুন" required></textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <span class="text-secondary small">Encoding</span>
                            <strong id="encoding_text">—</strong>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <span class="text-secondary small">Characters</span>
                            <strong id="length_text">0</strong>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <span class="text-secondary small">Segments</span>
                            <strong id="segments_text">0</strong>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <span class="text-secondary small">SMS cost</span>
                            <strong id="cost_text">0.00</strong>
                        </div>
                    </div>
                </div>
                <p class="small text-secondary">GSM-7: ১৬০ পর্যন্ত ১ সেগমেন্ট, তারপর প্রতি ১৫৩। বাংলা বা ইমোজি থাকলে Unicode: ৭০ পর্যন্ত ১ সেগমেন্ট, তারপর প্রতি ৬৭।</p>
                <button type="submit" class="btn btn-primary" id="save_btn">
                    <span id="save_spinner" class="d-none" role="status">
                        <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                        <span class="visually-hidden">Loading...</span>
                    </span>
                    Save to queue
                </button>
            </form>
            <div id="result" class="mt-3"></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">REST API</h2>
            <p class="mb-2">অন্য প্রজেক্ট এই ঠিকানায় POST করবে। <code>segments</code>, <code>sms_cost</code>, <code>status</code>, <code>created_at</code> ক্লায়েন্ট থেকে নেওয়া হয় না।</p>
            <p class="mb-1"><code>POST https://soft.a2technologiesbd.com/bulk_sms/api/insert_sms.php</code></p>
            <p class="mb-2"><code>X-Api-Key: <?= h($apiKey) ?></code></p>
            <pre class="bg-light border rounded p-3 mb-0"><code>{
  "client_name": "mamunsir",
  "receiver": "01712345678",
  "message": "Hello"
}</code></pre>
            <p class="small text-secondary mt-2 mb-0">একসাথে অনেকগুলো পাঠাতে একই দুটি ফিল্ডের অ্যারে পাঠান। এক রিকোয়েস্টে সর্বোচ্চ ১০০টি।</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5">Recent queue</h2>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Receiver</th>
                            <th>Message</th>
                            <th>Segments</th>
                            <th>Cost</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody id="queue_body">
                        <?php if ($recent === []): ?>
                            <tr id="empty_row"><td colspan="8" class="text-secondary">কিউ খালি।</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent as $row): ?>
                                <tr>
                                    <td><?= (int) $row['id'] ?></td>
                                    <td><?= h((string) $row['client_name']) ?></td>
                                    <td><?= h((string) $row['receiver']) ?></td>
                                    <td><?= h(excerpt((string) $row['message'])) ?></td>
                                    <td><?= (int) $row['segments'] ?></td>
                                    <td><?= h(number_format((float) $row['sms_cost'], 2, '.', '')) ?></td>
                                    <td><?= h((string) $row['status']) ?></td>
                                    <td><?= h((string) $row['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="assets/js/sms_segments.js"></script>
<script>
const apiKey = <?= json_encode($apiKey, JSON_UNESCAPED_UNICODE) ?>;
const smsRateCents = <?= (int) round($smsRate * 100) ?>;
const form = document.getElementById("sms_form");
const clientNameInput = document.getElementById("client_name");
const receiverInput = document.getElementById("receiver");
const messageInput = document.getElementById("message");
const saveBtn = document.getElementById("save_btn");
const saveSpinner = document.getElementById("save_spinner");
const resultBox = document.getElementById("result");

function refreshStats() {
    const stats = window.smsTextStats(messageInput.value.trim());
    document.getElementById("encoding_text").textContent = messageInput.value.trim() === "" ? "—" : (stats.encoding === "gsm" ? "GSM-7" : "Unicode");
    document.getElementById("length_text").textContent = String(stats.length);
    document.getElementById("segments_text").textContent = String(stats.segments);
    const cents = stats.segments * smsRateCents;
    document.getElementById("cost_text").textContent = Math.floor(cents / 100) + "." + String(cents % 100).padStart(2, "0");
}

messageInput.addEventListener("input", refreshStats);

form.addEventListener("submit", async (event) => {
    event.preventDefault();
    resultBox.innerHTML = "";
    saveBtn.disabled = true;
    saveSpinner.classList.remove("d-none");

    try {
        const response = await fetch("api/insert_sms.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-Api-Key": apiKey
            },
            body: JSON.stringify({
                client_name: clientNameInput.value.trim(),
                receiver: receiverInput.value.trim(),
                message: messageInput.value.trim()
            })
        });
        const payload = await response.json();
        if (!response.ok || payload.status !== "success") {
            const detail = Array.isArray(payload.errors) ? " " + payload.errors.map((item) => item.message).join(" ") : "";
            throw new Error((payload.message || "Save failed.") + detail);
        }

        const row = payload.data;
        const alert = document.createElement("div");
        alert.className = "alert alert-success mb-0";
        alert.textContent = "সেভ হয়েছে। ID " + row.id + ", " + row.client_name + ", segments " + row.segments + ", cost " + row.sms_cost + " (" + row.encoding + ", " + row.length + ").";
        resultBox.appendChild(alert);
        prependRow(row);
        messageInput.value = "";
        refreshStats();
        receiverInput.focus();
    } catch (error) {
        resultBox.innerHTML = '<div class="alert alert-danger mb-0"></div>';
        resultBox.querySelector(".alert").textContent = error.message || "Save failed.";
    } finally {
        saveBtn.disabled = false;
        saveSpinner.classList.add("d-none");
    }
});

function prependRow(row) {
    const empty = document.getElementById("empty_row");
    if (empty) {
        empty.remove();
    }
    const tr = document.createElement("tr");
    const message = row.message.length > 80 ? row.message.slice(0, 80) + "…" : row.message;
    [String(row.id), row.client_name, row.receiver, message, String(row.segments), row.sms_cost, row.status, row.created_at].forEach((value) => {
        const td = document.createElement("td");
        td.textContent = value;
        tr.appendChild(td);
    });
    document.getElementById("queue_body").prepend(tr);
}

refreshStats();
</script>
<?php
page_close();
