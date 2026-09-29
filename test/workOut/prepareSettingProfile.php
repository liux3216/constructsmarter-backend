<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/constants.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";

$fileName = $_POST['fileName'] ?? '';
$fileType = $_POST['fileType'] ?? '';
$fileSize = filter_var($_POST['fileSize'] ?? null, FILTER_VALIDATE_INT);
$modified = $_POST['lastModifiedAt'] ?? '';
$date = is_string($modified) ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $modified) : false;
if (!is_string($fileName) || $fileName === '' || strlen($fileName) > 255 || !in_array($fileType, ['image/png', 'image/jpeg'], true) || !$fileSize || $fileSize < 1 || $fileSize > 10 * 1024 * 1024 || !$date || $date->format('Y-m-d H:i:s') !== $modified) {
    http_response_code(422);
    exit(json_encode(['msg' => 'Choose a PNG or JPEG image up to 10 MB.']));
}
// A fresh key preserves the saved picture until the exercise is successfully saved.
$id = bin2hex(random_bytes(16));
$url = putObjectUrl(['bucket' => $publicBucket, 'key' => $id, 'mime' => $fileType]);
if (!$url) {
    http_response_code(500);
    exit(json_encode(['msg' => 'Could not prepare picture upload.']));
}
$db->exec(
    "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `lastModifiedAt`, `parentId`, `creatorId`, `public`) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE);",
    [$id, $fileName, $fileType, $fileSize, $modified, $profileFolderId, $userId], __FILE__, __LINE__
);
exit(json_encode(['id' => $id, 'url' => $url]));
