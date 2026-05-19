<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
assertCanEditNewspaper();
try{
    $fileId = newspaperRequireString("fileId");
    $fileName = newspaperRequireString("fileName");
    assertFileWithinRoot($fileId);
    $db->exec("UPDATE `fileInfo` SET `name` = ?, `updaterId` = ? WHERE `id` = ?;", [$fileName, $userId, $fileId], __FILE__, __LINE__);
    exit(json_encode(["id" => $fileId]));
}catch(InvalidArgumentException $e){
    newspaperJsonResponse(422, ["msg" => $e->getMessage()]);
}
