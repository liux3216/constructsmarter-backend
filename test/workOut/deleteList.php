<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = $_POST["id"] ?? null;
if (!$id) exit(json_encode(["error" => "Session id is required."]));
$sessions = $db->all("SELECT * FROM `workOutListSessions` WHERE `listId` = ? AND `userId` = ?;", [$id, $userId], __FILE__, __LINE__);
foreach ($sessions as $session) {
    $completions = $db->all("SELECT * FROM `workOutListCompletions` WHERE `sessionId` = ? AND `userId` = ?;", [$session["id"], $userId], __FILE__, __LINE__);
    $groupIds = [];
    foreach ($completions as $completion) {
        if (!empty($completion["workOutSetId"])) {
            $db->exec("DELETE FROM `workOutSets` WHERE `id` = ? AND `userId` = ?;", [$completion["workOutSetId"], $userId], __FILE__, __LINE__);
        }
        if (!empty($completion["workOutGroupId"])) {
            $groupIds[(string)$completion["workOutGroupId"]] = $completion["workOutGroupId"];
        }
    }
    $db->exec("DELETE FROM `workOutListCompletions` WHERE `sessionId` = ? AND `userId` = ?;", [$session["id"], $userId], __FILE__, __LINE__);
    foreach ($groupIds as $groupId) {
        $db->exec("DELETE FROM `workOutGroups` WHERE `id` = ? AND `userId` = ?;", [$groupId, $userId], __FILE__, __LINE__);
    }
}
$db->exec("DELETE FROM `workOutListSessions` WHERE `listId` = ? AND `userId` = ?;", [$id, $userId], __FILE__, __LINE__);
$items = $db->all("SELECT `id` FROM `workOutListItems` WHERE `listId` = ? AND `userId` = ?;", [$id, $userId], __FILE__, __LINE__);
foreach ($items as $item) {
    $db->exec("DELETE FROM `workOutListItemSets` WHERE `listItemId` = ? AND `userId` = ?;", [$item["id"], $userId], __FILE__, __LINE__);
}
$db->exec("DELETE FROM `workOutListItems` WHERE `listId` = ? AND `userId` = ?;", [$id, $userId], __FILE__, __LINE__);
$db->exec("DELETE FROM `workOutLists` WHERE `id` = ? AND `userId` = ?;", [$id, $userId], __FILE__, __LINE__);
exit(json_encode(["success" => true]));
