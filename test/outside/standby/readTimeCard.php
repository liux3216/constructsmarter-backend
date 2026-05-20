<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/standby/helpers.php";

$week = trim((string)($_POST['week'] ?? ''));
$prevWeek = trim((string)($_POST['prevWeek'] ?? ''));
if ($week === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'week is required.']));
}

[, $current] = standbyGetWeekData($db, $userId, $week, $userId);
$output = [$week => json_encode($current), 'email' => $email];
if ($prevWeek !== '') {
    [, $previous] = standbyGetWeekData($db, $userId, $prevWeek, $userId);
    $output[$prevWeek] = json_encode($previous);
}
exit(json_encode($output));
