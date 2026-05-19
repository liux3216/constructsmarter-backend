<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
assertCanEditDocuments();
try{
    $rootFolderId = documentRequireString("rootFolderId");
    $parentFolderId = documentRequireString("parentFolderId");
    $folderName = documentRequireString("folderName");
    if(!isRootAllowed($rootFolderId)) documentsJsonResponse(400, ["msg" => "Invalid root folder."]);
    assertFolderWithinRoot($parentFolderId, $rootFolderId);
    $uuid = md5(rand());
    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`) VALUES (?, ?, 'folder', 0, ?, ?, 'uploaded');",
        [$uuid, $folderName, $parentFolderId, $userId],
        __FILE__,
        __LINE__
    );
    exit(json_encode(["id" => $uuid]));
}catch(InvalidArgumentException $e){
    documentsJsonResponse(422, ["msg" => $e->getMessage()]);
}
