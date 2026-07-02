<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$listId = $_POST["listId"];
$workOutSettingId = $_POST["workOutSettingId"];
$sortOrder = $_POST["sortOrder"] ?? 0;
$setsJson = $_POST["sets"] ?? "[]";
$sets = json_decode($setsJson, true);
if (!is_array($sets) || count($sets) === 0) {
    $sets = [[
        "weight" => $_POST["weight"] ?? null,
        "repetition" => $_POST["repetition"] ?? null,
        "duration" => $_POST["duration"] ?? null,
        "calories" => $_POST["calories"] ?? null,
        "comments" => $_POST["comments"] ?? null,
    ]];
}
$first = $sets[0];
$setCount = count($sets);
$db->exec(
    "INSERT INTO `workOutListItems` (`userId`, `listId`, `workOutSettingId`, `sortOrder`, `setCount`, `weight`, `repetition`, `duration`, `calories`, `comments`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);",
    [$userId, $listId, $workOutSettingId, $sortOrder, $setCount, $first["weight"] ?? null, $first["repetition"] ?? null, $first["duration"] ?? null, $first["calories"] ?? null, $first["comments"] ?? null], __FILE__, __LINE__
);
$id = (int)($db->one("SELECT LAST_INSERT_ID() AS `id`", [], __FILE__, __LINE__)["id"] ?? 0);
$index = 1;
foreach ($sets as $set) {
    $db->exec(
        "INSERT INTO `workOutListItemSets` (`userId`, `listItemId`, `setIndex`, `weight`, `repetition`, `duration`, `calories`, `comments`) VALUES (?, ?, ?, ?, ?, ?, ?, ?);",
        [$userId, $id, $index, $set["weight"] ?? null, $set["repetition"] ?? null, $set["duration"] ?? null, $set["calories"] ?? null, $set["comments"] ?? null], __FILE__, __LINE__
    );
    $index++;
}
exit((string)$id);
