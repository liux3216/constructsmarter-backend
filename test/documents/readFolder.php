<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
$rootFolderId = documentRequireString("rootFolderId");
$folderId = documentOptionalString("folderId") ?: $rootFolderId;
if(!isRootAllowed($rootFolderId)) documentsJsonResponse(400, ["msg" => "Invalid root folder."]);
assertFolderWithinRoot($folderId, $rootFolderId);
$files = $db->all("SELECT * FROM `fileInfo` WHERE `parentId` <=> ? ORDER BY `type` = 'folder' DESC, `name` ASC;", [$folderId], __FILE__, __LINE__);
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
exit(json_encode([
    "files" => array_map("normalizeDocumentRow", $files),
    "parents" => array_reverse($parents),
]));
