<?php
require_once __DIR__ . '/helpers.php';
$division = trim((string)($_POST['division'] ?? ''));
if ($division === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'division is required.']));
}
$data = array_values(array_filter(
    outsideDivisionsEnsureSeed($db, $userId),
    static fn($row) => (string)($row['division'] ?? '') !== $division
));
outsideDivisionsWrite($db, outsideDivisionsSort($data), $userId);
exit(json_encode(['msg' => 'deleted']));
