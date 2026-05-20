<?php
require_once __DIR__ . '/helpers.php';
$division = trim((string)($_POST['division'] ?? ''));
$key = trim((string)($_POST['key'] ?? ''));
$value = trim((string)($_POST['value'] ?? ''));
$allowed = ['division', 'supervisorName', 'supervisorEmail', 'role', 'supervisorPhone', 'region'];
if ($division === '' || !in_array($key, $allowed, true)) {
    http_response_code(400);
    exit(json_encode(['msg' => 'invalid payload.']));
}
$data = outsideDivisionsEnsureSeed($db, $userId);
foreach ($data as &$row) {
    if ((string)($row['division'] ?? '') !== $division) continue;
    if ($key === 'supervisorEmail') $value = strtoupper(str_replace(' ', '', $value));
    $row[$key] = $value;
    outsideDivisionsWrite($db, outsideDivisionsSort($data), $userId);
    exit(json_encode(['msg' => 'updated']));
}
unset($row);
http_response_code(404);
exit(json_encode(['msg' => 'Division not found.']));
