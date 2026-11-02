<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/sms_segments.php';
require_once __DIR__ . '/../lib/api_guard.php';

$data = api_require_body();
$rows = $data['assignments'] ?? null;
if (!is_array($rows)) {
    api_fail(422, 'assignments is required.');
}

$byClient = [];
$simNumbers = [];
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }
    $sim = preg_replace('/[^\d+]/', '', (string) ($row['sim_number'] ?? '')) ?? '';
    if (preg_match('/^\+?\d{8,20}$/', $sim) !== 1) {
        continue;
    }
    $simNumbers[$sim] = true;
    $names = [];
    if (isset($row['client_names']) && is_array($row['client_names'])) {
        foreach ($row['client_names'] as $name) {
            $name = trim((string) $name);
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
    }
    foreach ($names as $name) {
        $byClient[$name] = $sim;
    }
}

if ($simNumbers === []) {
    api_fail(422, 'SIM number is required.');
}

try {
    $pdo = db();
    if ($byClient !== []) {
        $unknown = unknown_client_names($pdo, array_keys($byClient));
        if ($unknown !== []) {
            api_fail(422, 'Unknown client: ' . implode(', ', $unknown) . '.');
        }
    }

    $pdo->beginTransaction();
    foreach (array_keys($simNumbers) as $sim) {
        $keep = [];
        foreach ($byClient as $name => $assignedSim) {
            if ($assignedSim === $sim) {
                $keep[] = $name;
            }
        }
        if ($keep === []) {
            $clear = $pdo->prepare('UPDATE client_list SET sim_number = NULL WHERE sim_number = ?');
            $clear->execute([$sim]);
            continue;
        }
        $placeholders = implode(',', array_fill(0, count($keep), '?'));
        $clear = $pdo->prepare(
            "UPDATE client_list SET sim_number = NULL WHERE sim_number = ? AND client_name NOT IN ($placeholders)"
        );
        $clear->execute(array_merge([$sim], $keep));
    }

    $update = $pdo->prepare('UPDATE client_list SET sim_number = ? WHERE client_name = ?');
    foreach ($byClient as $name => $sim) {
        $update->execute([$sim, $name]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_fail(500, 'Could not save the SIM assignment.');
}

api_ok([
    'clients' => $byClient,
]);
