<?php
require_once "/opt/bitnami/apache/htdocs/test/constants.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";
// Shared by create/update. Never accept a URL supplied by the client.
function validateSettingProfile($db, $userId, $profileId) {
    if ($profileId === "") return null;
    if (!is_string($profileId) || !preg_match('/^[a-f0-9]{32}$/', $profileId)) {
        http_response_code(422);
        exit(json_encode(["msg" => "Invalid exercise picture."]));
    }
    global $publicBucket, $s3Client, $profileFolderId;
    $file = $db->one("SELECT `type`, `size` FROM `fileInfo` WHERE `id` = ? AND `creatorId` = ? AND `parentId` = ?;", [$profileId, $userId, $profileFolderId], __FILE__, __LINE__);
    if (!$file || !in_array($file['type'], ['image/png', 'image/jpeg'], true) || (int)$file['size'] < 1 || (int)$file['size'] > 10 * 1024 * 1024) {
        http_response_code(422);
        exit(json_encode(["msg" => "Invalid exercise picture."]));
    }
    try {
        $object = $s3Client->headObject(["Bucket" => $publicBucket, "Key" => $profileId]);
        if ((int)$object['ContentLength'] !== (int)$file['size'] || $object['ContentType'] !== $file['type']) {
            throw new RuntimeException("Upload metadata mismatch");
        }
    } catch (Throwable $error) {
        http_response_code(422);
        exit(json_encode(["msg" => "The picture upload did not complete. Please try again."]));
    }
    $db->exec("UPDATE `fileInfo` SET `status` = 'uploaded' WHERE `id` = ? AND `creatorId` = ?;", [$profileId, $userId], __FILE__, __LINE__);
    return $profileId;
}
