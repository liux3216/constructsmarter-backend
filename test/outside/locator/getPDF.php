<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$id = trim((string)($_POST['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'id is required.']));
}
$row = $db->one("SELECT `name` FROM `fileInfo` WHERE `id` = ?", [$id], __FILE__, __LINE__);
if (!$row) {
    http_response_code(404);
    exit(json_encode(['msg' => 'The file is not found.']));
}
exit(getObjectUrl($privateBucket, $id, $row['name']));
