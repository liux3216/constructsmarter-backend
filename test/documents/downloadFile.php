<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
$rootFolderId = documentRequireString("rootFolderId");
$fileId = documentRequireString("fileId");
$fileName = documentRequireString("fileName");
if(!isRootAllowed($rootFolderId)) documentsJsonResponse(400, ["msg" => "Invalid root folder."]);
assertFileWithinRoot($fileId, $rootFolderId);
exit(json_encode(getObjectUrl($privateBucket, $fileId, $fileName)));
