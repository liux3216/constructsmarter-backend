<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/locator/helpers.php";

$week = trim((string)($_POST['wk'] ?? ''));
$targetUserId = trim((string)($_POST['userId'] ?? ''));
$fromIndex = intval($_POST['fromIndex'] ?? -1);
$toIndex = intval($_POST['toIndex'] ?? -1);
if ($week === '' || $targetUserId === '' || $fromIndex < 0 || $fromIndex > 6 || $toIndex < 0 || $toIndex > 6) {
    http_response_code(400);
    exit(json_encode(['msg' => 'Invalid payload.']));
}
[$weekMap, $weekData] = locatorGetWeekData($db, $targetUserId, $week, $userId);
if (($weekData['status'] ?? '') === 'Submitted') {
    $weekData['status'] = 'Saved';
    unset($weekData['submitTime']);
}
if (($weekData['form'][$fromIndex]['status'] ?? '') === 'Submitted') {
    $weekData['form'][$fromIndex]['status'] = 'Saved';
    unset($weekData['form'][$fromIndex]['submitTime']);
}
if (($weekData['form'][$toIndex]['status'] ?? '') === 'Submitted') {
    $weekData['form'][$toIndex]['status'] = 'Saved';
    unset($weekData['form'][$toIndex]['submitTime']);
}
[$weekData['form'][$fromIndex], $weekData['form'][$toIndex]] = [$weekData['form'][$toIndex], $weekData['form'][$fromIndex]];
locatorSaveWeekData($db, $targetUserId, $week, $weekMap, $weekData, $userId);
exit(json_encode(['ok' => true]));
