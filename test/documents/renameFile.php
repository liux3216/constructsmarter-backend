<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
assertCanEditDocuments();
try{
    $rootFolderId = documentRequireString("rootFolderId");
    $fileId = documentRequireString("fileId");
    $fileName = documentRequireString("fileName");
    if(!isRootAllowed($rootFolderId)) documentsJsonResponse(400, ["msg" => "Invalid root folder."]);
    assertFileWithinRoot($fileId, $rootFolderId);
    $db->exec("UPDATE `fileInfo` SET `name` = ?, `updaterId` = ? WHERE `id` = ?;", [$fileName, $userId, $fileId], __FILE__, __LINE__);
    exit(json_encode(["id" => $fileId]));
}catch(InvalidArgumentException $e){
    documentsJsonResponse(422, ["msg" => $e->getMessage()]);
}
