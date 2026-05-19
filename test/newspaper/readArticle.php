<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
$fileId = newspaperRequireString("fileId");
$row = assertFileWithinRoot($fileId);
exit(json_encode([
    "id" => $row["id"],
    "name" => $row["name"],
    "data" => readNewspaperBody($fileId),
]));
