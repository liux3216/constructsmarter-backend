<?php
require_once __DIR__ . '/helpers.php';
$division = trim((string)($_POST['division'] ?? ''));
if ($division === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'division is required.']));
}
$data = outsideDivisionsEnsureSeed($db, $userId);
foreach ($data as $row) {
    if (strcasecmp((string)($row['division'] ?? ''), $division) === 0) {
        http_response_code(400);
        exit(json_encode(['msg' => 'Division already exists.']));
    }
}
$data[] = [
    'division' => $division,
    'supervisorName' => trim((string)($_POST['supervisorName'] ?? '')),
    'supervisorEmail' => strtoupper(trim((string)($_POST['supervisorEmail'] ?? ''))),
    'role' => trim((string)($_POST['role'] ?? '')),
    'supervisorPhone' => trim((string)($_POST['supervisorPhone'] ?? '')),
    'region' => trim((string)($_POST['region'] ?? '')),
];
outsideDivisionsWrite($db, outsideDivisionsSort($data), $userId);
exit(json_encode(['msg' => 'created']));
