<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/standby/helpers.php";

$fromWeek = trim((string)($_POST['fromWk'] ?? ''));
$toWeek = trim((string)($_POST['toWk'] ?? ''));
$targetUserId = trim((string)($_POST['userId'] ?? ''));
if ($fromWeek === '' || $toWeek === '' || $targetUserId === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'Invalid payload.']));
}
$row = standbyEnsureRow($db, $targetUserId, $userId);
$weekMap = standbyGetWeekMap($row['data'] ?? null);
$fromData = $weekMap[$fromWeek] ?? json_encode(standbyDefaultWeekData());
$toData = $weekMap[$toWeek] ?? json_encode(standbyDefaultWeekData());
$weekMap[$fromWeek] = $toData;
$weekMap[$toWeek] = $fromData;
$db->exec(
    "UPDATE `outsideStandby` SET `data` = ?, `updaterId` = ? WHERE `userId` = ?",
    [json_encode($weekMap), $userId, $targetUserId],
    __FILE__,
    __LINE__
);
exit(json_encode(['ok' => true]));
