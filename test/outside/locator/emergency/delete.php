<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/locator/helpers.php";

$week = trim((string)($_POST['wk'] ?? ''));
$targetUserId = trim((string)($_POST['userId'] ?? ''));
$index = intval($_POST['index'] ?? -1);
if ($week === '' || $targetUserId === '' || $index < 0) {
    http_response_code(400);
    exit(json_encode(['msg' => 'Invalid payload.']));
}
[$weekMap, $weekData] = locatorGetWeekData($db, $targetUserId, $week, $userId);
if (!isset($weekData['emergency'][$index])) {
    http_response_code(404);
    exit(json_encode(['msg' => 'Emergency entry is not found.']));
}
$existingId = $weekData['emergency'][$index]['id'] ?? null;
if (is_string($existingId) && strlen($existingId) === 32) locatorDeletePdf($existingId);
array_splice($weekData['emergency'], $index, 1);
locatorSaveWeekData($db, $targetUserId, $week, $weekMap, $weekData, $userId);
exit(json_encode(['ok' => true]));
