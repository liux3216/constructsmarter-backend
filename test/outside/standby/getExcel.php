<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$fileId = trim((string)($_POST['fileId'] ?? ''));
if ($fileId === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'fileId is required.']));
}
$row = $db->one("SELECT `name` FROM `fileInfo` WHERE `id` = ?", [$fileId], __FILE__, __LINE__);
if (!$row) {
    http_response_code(404);
    exit(json_encode(['msg' => 'The file is not found.']));
}
exit(getObjectUrl($privateBucket, $fileId, $row['name']));
