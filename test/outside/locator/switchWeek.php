<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/locator/helpers.php";

$fromWeek = trim((string)($_POST['fromWk'] ?? ''));
$toWeek = trim((string)($_POST['toWk'] ?? ''));
$targetUserId = trim((string)($_POST['userId'] ?? ''));
if ($fromWeek === '' || $toWeek === '' || $targetUserId === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'Invalid payload.']));
}
$row = locatorEnsureRow($db, $targetUserId, $userId);
$weekMap = locatorGetWeekMap($row['data'] ?? null);
$fromData = $weekMap[$fromWeek] ?? json_encode(locatorDefaultWeekData());
$toData = $weekMap[$toWeek] ?? json_encode(locatorDefaultWeekData());
$weekMap[$fromWeek] = $toData;
$weekMap[$toWeek] = $fromData;
$db->exec(
    "UPDATE `outsideML` SET `data` = ?, `updaterId` = ? WHERE `userId` = ?",
    [json_encode($weekMap), $userId, $targetUserId],
    __FILE__,
    __LINE__
);
exit(json_encode(['ok' => true]));
