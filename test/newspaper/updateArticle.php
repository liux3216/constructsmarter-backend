<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
assertCanEditNewspaper();
try{
    $fileId = newspaperRequireString("fileId");
    $parentFolderId = newspaperRequireString("parentFolderId");
    $subject = newspaperRequireString("subject");
    $data = (string)($_POST["data"] ?? "");
    assertFolderWithinRoot($parentFolderId);
    assertFileWithinRoot($fileId);
    if(!uploadFileWithBody($privateBucket, $fileId, $data, "text/html")){
        throw new RuntimeException("Failed to upload article.");
    }
    $size = strlen($data);
    $db->exec(
        "UPDATE `fileInfo` SET `name` = ?, `size` = ?, `parentId` = ?, `updaterId` = ?, `status` = 'uploaded' WHERE `id` = ?;",
        [$subject, $size, $parentFolderId, $userId, $fileId],
        __FILE__,
        __LINE__
    );
    exit(json_encode(["id" => $fileId]));
}catch(InvalidArgumentException $e){
    newspaperJsonResponse(422, ["msg" => $e->getMessage()]);
}catch(Throwable $e){
    error_log($e);
    newspaperJsonResponse(500, ["msg" => "Internal Server Error"]);
}
