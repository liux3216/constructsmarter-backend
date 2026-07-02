<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = $_POST["id"] ?? null;
$workOutSettingId = $_POST["workOutSettingId"] ?? null;
$setsJson = $_POST["sets"] ?? "[]";
$sets = json_decode($setsJson, true);
if (!$id || !$workOutSettingId) exit(json_encode(["error" => "Planned exercise and exercise type are required."]));
if (!is_array($sets) || count($sets) === 0) exit(json_encode(["error" => "At least one planned set is required."]));
$item = $db->one("SELECT * FROM `workOutListItems` WHERE `id` = ? AND `userId` = ?;", [$id, $userId], __FILE__, __LINE__);
if (!$item) exit(json_encode(["error" => "Planned exercise not found."]));
$first = $sets[0];
$setCount = count($sets);
$db->exec(
    "UPDATE `workOutListItems` SET `workOutSettingId` = ?, `setCount` = ?, `weight` = ?, `repetition` = ?, `duration` = ?, `calories` = ?, `comments` = ? WHERE `id` = ? AND `userId` = ?;",
    [$workOutSettingId, $setCount, $first["weight"] ?? null, $first["repetition"] ?? null, $first["duration"] ?? null, $first["calories"] ?? null, $first["comments"] ?? null, $id, $userId], __FILE__, __LINE__
);
$db->exec("DELETE FROM `workOutListItemSets` WHERE `listItemId` = ? AND `userId` = ?;", [$id, $userId], __FILE__, __LINE__);
$index = 1;
foreach ($sets as $set) {
    $db->exec(
        "INSERT INTO `workOutListItemSets` (`userId`, `listItemId`, `setIndex`, `weight`, `repetition`, `duration`, `calories`, `comments`) VALUES (?, ?, ?, ?, ?, ?, ?, ?);",
        [$userId, $id, $index, $set["weight"] ?? null, $set["repetition"] ?? null, $set["duration"] ?? null, $set["calories"] ?? null, $set["comments"] ?? null], __FILE__, __LINE__
    );
    $index++;
}
exit(json_encode(["success" => true]));
