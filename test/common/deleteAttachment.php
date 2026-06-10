<?php
require_once "/opt/bitnami/apache/htdocs/test/common/attachment_helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(405, ["msg" => "Method Not Allowed"]);
}
$sqlTable = trim((string)($_POST["sqlTable"] ?? ""));
$idLabel = trim((string)($_POST["idLabel"] ?? "id"));
$filesKey = trim((string)($_POST["filesKey"] ?? "files"));
$recordId = trim((string)($_POST[$idLabel] ?? ""));
$fileIds = $_POST["fileId"] ?? [];
if (!is_array($fileIds)) $fileIds = [$fileIds];
$fileIds = array_values(array_filter(array_map('strval', $fileIds)));
if ($idLabel !== "id" || $recordId === "") {
    jsonResponse(422, ["msg" => "Unsupported attachment target."]);
}

$targetId = (int)$recordId;
$target = attachmentResolveTarget($db, $sqlTable, $targetId, $userId);
$folderId = $target['folderId'];
if ($fileIds) {
    $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
    $params = array_merge($fileIds, [$folderId]);
    $db->exec("DELETE FROM `fileInfo` WHERE `id` IN ($placeholders) AND `parentId` = ?;", $params, __FILE__, __LINE__);
    foreach ($fileIds as $fileId) {
        deleteFile($privateBucket, $fileId);
    }
}
$files = attachmentReadFiles($db, $folderId);
jsonResponse(200, [
    "folderId" => $folderId,
    $filesKey => $files,
    "files" => $files,
]);
