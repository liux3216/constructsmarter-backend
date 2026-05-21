<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }

    $reportId = trim((string)($_POST["id"] ?? $_POST["id"] ?? ""));
    if ($reportId === "") {
        jsonResponse(422, ["msg" => "Report id is required."]);
    }

    $fileName = requireField($_POST, "fileName", 1, 255, true);
    $fileType = requireField($_POST, "fileType", 0, 255, false) ?? "application/octet-stream";
    $fileSize = requireInt($_POST, "fileSize", 0, null, true);
    $lastModifiedAt = requireField($_POST, "lastModifiedAt", 1, 32, true);

    $report = $db->one(
        "SELECT `id`, `pdfId` FROM `reports` WHERE `id` = ? LIMIT 1;",
        [$reportId],
        __FILE__,
        __LINE__
    );
    if (!$report) {
        jsonResponse(404, ["msg" => "Report not found."]);
    }

    $fileId = $report["pdfId"];
    $file = $fileId ? $db->one(
        "SELECT `id` FROM `fileInfo` WHERE `id` = ? LIMIT 1;",
        [$fileId],
        __FILE__,
        __LINE__
    ) : null;

    if ($file) {
        $db->exec(
            "UPDATE `fileInfo`
             SET `name` = ?,
                 `type` = ?,
                 `size` = ?,
                 `lastModifiedAt` = ?,
                 `updaterId` = ?,
                 `status` = 'pending'
             WHERE `id` = ?;",
            [$fileName, $fileType, $fileSize, $lastModifiedAt, $userId, $fileId],
            __FILE__,
            __LINE__
        );
    } else {
        $fileId = md5(uniqid((string)mt_rand(), true));
        $db->exec(
            "INSERT INTO `fileInfo`
             (`id`, `name`, `type`, `size`, `lastModifiedAt`, `creatorId`)
             VALUES (?, ?, ?, ?, ?, ?);",
            [$fileId, $fileName, $fileType, $fileSize, $lastModifiedAt, $userId],
            __FILE__,
            __LINE__
        );
    }

    $db->exec(
        "UPDATE `reports`
         SET `pdfId` = ?,
             `updaterId` = ?,
             `updatedAt` = NOW()
         WHERE `id` = ?;",
        [$fileId, $userId, $reportId],
        __FILE__,
        __LINE__
    );

    exit(json_encode([
        "id" => $fileId,
        "url" => putObjectUrl([
            "bucket" => $privateBucket,
            "key" => $fileId,
            "mime" => $fileType,
        ]),
    ]));

} catch (Throwable $e) {
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
