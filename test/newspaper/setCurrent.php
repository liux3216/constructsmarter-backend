<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
assertCanEditNewspaper();
$fileId = newspaperOptionalString("fileId");
if($fileId !== ""){
    $row = assertFileWithinRoot($fileId);
    if(($row["type"] ?? "") === "folder"){
        newspaperJsonResponse(400, ["msg" => "Current newspaper must be an article."]);
    }
}
$db->exec("UPDATE `entities` SET `textValue` = ?, `updaterId` = ? WHERE `entityKey` = ?;", [$fileId, $userId, CURRENT_NEWSPAPER_KEY], __FILE__, __LINE__);
exit(json_encode(["fileId" => $fileId]));
