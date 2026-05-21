<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$id = (int)($_POST["id"] ?? 0);
if (!$id) {
    http_response_code(422);
    exit(json_encode(["msg" => "Billing id is required."]));
}

$billing = $db->one(
    "SELECT `id`, `pdfId` FROM `billings` WHERE `id` = ? LIMIT 1;",
    [$id],
    __FILE__,
    __LINE__
);
if (!$billing) {
    http_response_code(404);
    exit(json_encode(["msg" => "Billing not found."]));
}

$fileId = trim((string)($billing["pdfId"] ?? ""));
if ($fileId === "") {
    exit(json_encode(["id" => $id, "pdfId" => ""]));
}
$fileInfo = $db->one(
    "SELECT `id` FROM `fileInfo` WHERE `id` = ? LIMIT 1;",
    [$fileId],
    __FILE__,
    __LINE__
);
if ($fileInfo) {
    deleteFile($privateBucket, $fileId);
    $db->exec("DELETE FROM `fileInfo` WHERE `id` = ?;", [$fileId], __FILE__, __LINE__);
}
$db->exec(
    "UPDATE `billings`
     SET `pdfId` = NULL, `updaterId` = ?, `updatedAt` = NOW()
     WHERE `id` = ?;",
    [$userId, $id],
    __FILE__,
    __LINE__
);

exit(json_encode(["id" => $id, "pdfId" => ""]));
