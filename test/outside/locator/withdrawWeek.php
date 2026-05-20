<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/locator/helpers.php";

$week = trim((string)($_POST['wk'] ?? ''));
$targetUserId = trim((string)($_POST['userId'] ?? ''));
if ($week === '' || $targetUserId === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'wk and userId are required.']));
}
[$weekMap, $weekData] = locatorGetWeekData($db, $targetUserId, $week, $userId);
$weekData['status'] = 'Saved';
unset($weekData['submitTime']);
$weekData['form'] = array_map(function ($day) {
    if (($day['status'] ?? '') === 'Submitted') {
        $day['status'] = 'Saved';
        unset($day['submitTime']);
    }
    return $day;
}, $weekData['form'] ?? []);
$weekData['emergency'] = array_map(function ($day) {
    if (($day['status'] ?? '') === 'Submitted') {
        $day['status'] = 'Saved';
        unset($day['submitTime']);
    }
    return $day;
}, $weekData['emergency'] ?? []);
locatorSaveWeekData($db, $targetUserId, $week, $weekMap, $weekData, $userId);
exit(json_encode(['ok' => true]));
