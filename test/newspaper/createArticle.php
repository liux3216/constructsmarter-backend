<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
assertCanEditNewspaper();
try{
    $parentFolderId = newspaperRequireString("parentFolderId");
    $subject = newspaperRequireString("subject");
    $data = (string)($_POST["data"] ?? "");
    assertFolderWithinRoot($parentFolderId);
    $fileId = md5(rand());
    if(!uploadFileWithBody($privateBucket, $fileId, $data, "text/html")){
        throw new RuntimeException("Failed to upload article.");
    }
    $size = strlen($data);
    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`) VALUES (?, ?, 'text/html', ?, ?, ?, 'uploaded');",
        [$fileId, $subject, $size, $parentFolderId, $userId],
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
