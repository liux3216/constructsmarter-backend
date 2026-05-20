<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/locator/helpers.php";

$week = trim((string)($_POST['wk'] ?? ''));
$targetUserId = trim((string)($_POST['userId'] ?? ''));
if ($week === '' || $targetUserId === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'Invalid payload.']));
}
[$weekMap, $weekData] = locatorGetWeekData($db, $targetUserId, $week, $userId);
$weekData['emergency'][] = locatorDefaultDayData();
locatorSaveWeekData($db, $targetUserId, $week, $weekMap, $weekData, $userId);
exit(json_encode(['ok' => true, 'index' => count($weekData['emergency']) - 1]));
