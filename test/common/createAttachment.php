<?php
require_once "/opt/bitnami/apache/htdocs/test/common/attachment_helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(405, ["msg" => "Method Not Allowed"]);
}
$sqlTable = trim((string)($_POST["sqlTable"] ?? ""));
$idLabel = trim((string)($_POST["idLabel"] ?? "id"));
$filesKey = trim((string)($_POST["filesKey"] ?? "files"));
$recordId = trim((string)($_POST[$idLabel] ?? ""));
if ($idLabel !== "id" || $recordId === "") {
    jsonResponse(422, ["msg" => "Unsupported attachment target."]);
}
if (!isset($_FILES["files"])) {
    jsonResponse(422, ["msg" => "No files uploaded."]);
}

$targetId = (int)$recordId;
$db->begin();
$target = attachmentResolveTarget($db, $sqlTable, $targetId, $userId);
$folderId = $target['folderId'];
$names = $_FILES["files"]["name"] ?? [];
$types = $_FILES["files"]["type"] ?? [];
$sizes = $_FILES["files"]["size"] ?? [];
$tmpNames = $_FILES["files"]["tmp_name"] ?? [];
$errors = $_FILES["files"]["error"] ?? [];
$ids = [];
$now = date("Y-m-d H:i:s");

try {
    foreach ($names as $index => $name) {
        if (!$name) continue;
        $tmpName = $tmpNames[$index] ?? "";
        $errorCode = (int)($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK || $tmpName === "" || !is_uploaded_file($tmpName)) {
            throw new RuntimeException("Failed to receive uploaded file: " . $name);
        }
        $fileId = secureId();
        $mimeType = trim((string)($types[$index] ?? "application/octet-stream")) ?: "application/octet-stream";
        $size = (int)($sizes[$index] ?? 0);
        if (!uploadFile($privateBucket, $fileId, $tmpName)) {
            throw new RuntimeException("Failed to store uploaded file: " . $name);
        }
        $db->exec(
            "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `lastModifiedAt`, `parentId`, `creatorId`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, 'uploaded');",
            [$fileId, $name, $mimeType, $size, $now, $folderId, $userId],
            __FILE__,
            __LINE__
        );
        $ids[] = $fileId;
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    jsonResponse(500, ["msg" => $e->getMessage()]);
}

$files = attachmentReadFiles($db, $folderId);
jsonResponse(200, [
    "folderId" => $folderId,
    $filesKey => $files,
    "files" => $files,
    "ids" => $ids,
]);
