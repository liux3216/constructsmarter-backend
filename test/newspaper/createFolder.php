<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
assertCanEditNewspaper();
try{
    $parentFolderId = newspaperRequireString("parentFolderId");
    $folderName = newspaperRequireString("folderName");
    assertFolderWithinRoot($parentFolderId);
    $uuid = md5(rand());
    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`) VALUES (?, ?, 'folder', 0, ?, ?, 'uploaded');",
        [$uuid, $folderName, $parentFolderId, $userId],
        __FILE__,
        __LINE__
    );
    exit(json_encode(["id" => $uuid]));
}catch(InvalidArgumentException $e){
    newspaperJsonResponse(422, ["msg" => $e->getMessage()]);
}
