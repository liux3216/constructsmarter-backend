<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
$folderId = newspaperOptionalString("folderId") ?: NEWSPAPER_ROOT_ID;
assertFolderWithinRoot($folderId);
$items = $db->all("SELECT * FROM `fileInfo` WHERE `parentId` <=> ? ORDER BY `type` = 'folder' DESC, `name` ASC;", [$folderId], __FILE__, __LINE__);
$parents = $db->all(
    "WITH RECURSIVE `chain` AS (
        SELECT `id`, `name`, `parentId` FROM `fileInfo` WHERE `id` = ?
        UNION ALL
        SELECT `f`.`id`, `f`.`name`, `f`.`parentId` FROM `fileInfo` `f`
        JOIN `chain` `c` ON `f`.`id` = `c`.`parentId`
    )
    SELECT `id`, `name` FROM `chain`;",
    [$folderId],
    __FILE__,
    __LINE__
);
$current = $db->one("SELECT `textValue` FROM `entities` WHERE `entityKey` = ?;", [CURRENT_NEWSPAPER_KEY], __FILE__, __LINE__);
exit(json_encode([
    "items" => array_map("newspaperNormalizeRow", $items),
    "parents" => array_reverse($parents),
    "currentNewspaperId" => $current["textValue"] ?? "",
]));
