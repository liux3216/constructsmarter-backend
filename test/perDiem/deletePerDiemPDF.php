<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__ . "/helpers.php";

$id = perDiemRequirePost("id");
$access = getPerDiemAccess($db, $userId);
$current = $db->one(
    "SELECT `id`, `pdfId`, `requesterId`, `approverId`, `creatorId` FROM `perDiems` WHERE `id` = ? LIMIT 1;",
    [$id],
    __FILE__,
    __LINE__
);
if (!$current) {
    http_response_code(404);
    exit(json_encode(["msg" => "Per diem form not found."]));
}
if (!perDiemCanEditRow($current, $userId, $access)) {
    http_response_code(403);
    exit(json_encode(["msg" => "You are not allowed to remove this per diem file."]));
}

$fileId = trim((string)($current["pdfId"] ?? ""));
if ($fileId === "") {
    exit(json_encode(["id" => (int)$id, "pdfId" => ""]));
}
$file = $db->one(
    "SELECT `id` FROM `fileInfo` WHERE `id` = ? LIMIT 1;",
    [$fileId],
    __FILE__,
    __LINE__
);
if ($file) {
    deleteFile($privateBucket, $fileId);
    $db->exec("DELETE FROM `fileInfo` WHERE `id` = ?;", [$fileId], __FILE__, __LINE__);
}
$db->exec(
    "UPDATE `perDiems` SET `pdfId` = NULL, `updaterId` = ?, `updatedAt` = NOW() WHERE `id` = ?;",
    [$userId, $id],
    __FILE__,
    __LINE__
);

exit(json_encode(["id" => (int)$id, "pdfId" => ""]));
