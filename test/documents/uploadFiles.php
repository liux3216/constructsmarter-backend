<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
assertCanEditDocuments();
$rootFolderId = documentRequireString("rootFolderId");
$folderId = documentRequireString("folderId");
if(!isRootAllowed($rootFolderId)) documentsJsonResponse(400, ["msg" => "Invalid root folder."]);
assertFolderWithinRoot($folderId, $rootFolderId);
$fileNames = $_POST["fileName"] ?? $_POST["fileName[]"] ?? [];
$fileSizes = $_POST["fileSize"] ?? $_POST["fileSize[]"] ?? [];
$fileTypes = $_POST["fileType"] ?? $_POST["fileType[]"] ?? [];
$lastModifiedAts = $_POST["lastModifiedAt"] ?? $_POST["lastModifiedAt[]"] ?? [];
if(!is_array($fileNames) || !count($fileNames)) documentsJsonResponse(400, ["msg" => "No files."]);
$ids = [];
$urls = [];
for($i = 0; $i < count($fileNames); $i++){
    $uuid = md5(rand());
    $name = trim((string)$fileNames[$i]);
    $type = trim((string)($fileTypes[$i] ?? "application/octet-stream"));
    $size = (int)($fileSizes[$i] ?? 0);
    $lastModifiedAt = trim((string)($lastModifiedAts[$i] ?? date("Y-m-d H:i:s")));
    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `lastModifiedAt`, `parentId`, `creatorId`) VALUES (?, ?, ?, ?, ?, ?, ?);",
        [$uuid, $name, $type, $size, $lastModifiedAt, $folderId, $userId],
        __FILE__,
        __LINE__
    );
    $ids[] = $uuid;
    $urls[] = putObjectUrl([
        "bucket" => $privateBucket,
        "key" => $uuid,
        "mime" => $type,
    ]);
}
exit(json_encode(["ids" => $ids, "urls" => $urls]));
