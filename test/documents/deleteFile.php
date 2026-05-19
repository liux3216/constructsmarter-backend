<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
assertCanEditDocuments();
$rootFolderId = documentRequireString("rootFolderId");
$fileId = documentRequireString("fileId");
if(!isRootAllowed($rootFolderId)) documentsJsonResponse(400, ["msg" => "Invalid root folder."]);
assertFileWithinRoot($fileId, $rootFolderId);
$rows = $db->all(
    "WITH RECURSIVE `nodes` AS (
        SELECT `id`, `type` FROM `fileInfo` WHERE `id` = ?
        UNION ALL
        SELECT `f`.`id`, `f`.`type` FROM `fileInfo` `f`
        JOIN `nodes` `n` ON `f`.`parentId` = `n`.`id`
    )
    SELECT `id`, `type` FROM `nodes`;",
    [$fileId],
    __FILE__,
    __LINE__
);
$ids = [];
foreach($rows as $row){
    $ids[] = $row["id"];
    if(!in_array($row["type"], ["folder", "link"], true)){
        try{
            deleteFile($privateBucket, $row["id"]);
        }catch(Throwable $e){
            error_log($e->getMessage());
        }
    }
}
if(count($ids)){
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $db->exec(
        "DELETE FROM `fileInfo` WHERE `id` IN ($placeholders);",
        $ids,
        __FILE__,
        __LINE__
    );
}
exit(json_encode(["id" => $fileId]));
