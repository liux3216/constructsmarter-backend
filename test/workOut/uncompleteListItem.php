<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$listItemId = $_POST["listItemId"] ?? null;
$sessionId = $_POST["sessionId"] ?? null;
$setIndex = max(1, (int)($_POST["setIndex"] ?? 1));
if (!$listItemId || !$sessionId) exit(json_encode(["error" => "List item and session are required."]));
$completion = $db->one("SELECT * FROM `workOutListCompletions` WHERE `userId` = ? AND `sessionId` = ? AND `listItemId` = ? AND `setIndex` = ?;", [$userId, $sessionId, $listItemId, $setIndex], __FILE__, __LINE__);
if (!$completion) exit(json_encode(["success" => true]));
$groupId = $completion["workOutGroupId"] ?? null;
if (!empty($completion["workOutSetId"])) {
    $db->exec("DELETE FROM `workOutSets` WHERE `id` = ? AND `userId` = ?;", [$completion["workOutSetId"], $userId], __FILE__, __LINE__);
}
$db->exec("DELETE FROM `workOutListCompletions` WHERE `id` = ? AND `userId` = ?;", [$completion["id"], $userId], __FILE__, __LINE__);
if ($groupId) {
    $remaining = $db->one("SELECT COUNT(*) AS `count` FROM `workOutListCompletions` WHERE `userId` = ? AND `sessionId` = ? AND `listItemId` = ? AND `workOutGroupId` = ?;", [$userId, $sessionId, $listItemId, $groupId], __FILE__, __LINE__);
    if ((int)($remaining["count"] ?? 0) === 0) {
        $db->exec("DELETE FROM `workOutGroups` WHERE `id` = ? AND `userId` = ?;", [$groupId, $userId], __FILE__, __LINE__);
    }
}
exit(json_encode(["success" => true]));
