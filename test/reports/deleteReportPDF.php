<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$reportId = trim((string)($_POST["id"] ?? ""));
if ($reportId === "") {
    http_response_code(422);
    exit(json_encode(["msg" => "Report id is required."]));
}

$report = $db->one(
    "SELECT `id`, `pdfId` FROM `reports` WHERE `id` = ? LIMIT 1;",
    [$reportId],
    __FILE__,
    __LINE__
);
if (!$report) {
    http_response_code(404);
    exit(json_encode(["msg" => "Report not found."]));
}

$fileId = trim((string)($report["pdfId"] ?? ""));
if ($fileId === "") {
    exit(json_encode(["id" => $reportId, "pdfId" => ""]));
}

$file = $db->one(
    "SELECT `id` FROM `fileInfo` WHERE `id` = ? LIMIT 1;",
    [$fileId],
    __FILE__,
    __LINE__
);

if ($file) {
    deleteFile($privateBucket, $fileId);
    $db->exec(
        "DELETE FROM `fileInfo` WHERE `id` = ?;",
        [$fileId],
        __FILE__,
        __LINE__
    );
}

$db->exec(
    "UPDATE `reports`
     SET `pdfId` = NULL,
         `updaterId` = ?,
         `updatedAt` = NOW()
     WHERE `id` = ?;",
    [$userId, $reportId],
    __FILE__,
    __LINE__
);

exit(json_encode(["id" => $reportId, "pdfId" => ""]));
