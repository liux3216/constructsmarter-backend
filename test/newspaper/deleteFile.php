<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
assertCanEditNewspaper();
$fileId = newspaperRequireString("fileId");
assertFileWithinRoot($fileId);
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
    if($row["type"] !== "folder"){
        try{
            deleteFile($privateBucket, $row["id"]);
        }catch(Throwable $e){
            error_log($e->getMessage());
        }
    }
}
if(count($ids)){
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $db->exec("DELETE FROM `fileInfo` WHERE `id` IN ($placeholders);", $ids, __FILE__, __LINE__);
}
$db->exec("UPDATE `entities` SET `textValue` = '' WHERE `entityKey` = ? AND `textValue` = ?;", [CURRENT_NEWSPAPER_KEY, $fileId], __FILE__, __LINE__);
exit(json_encode(["id" => $fileId]));
