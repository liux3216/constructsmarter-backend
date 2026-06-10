<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$fileIds = $_POST["fileId"] ?? [];
$fileNames = $_POST["fileName"] ?? [];
if (!is_array($fileIds)) $fileIds = [$fileIds];
if (!is_array($fileNames)) $fileNames = [$fileNames];
$fileIds = array_values(array_filter(array_map('strval', $fileIds)));
if (!$fileIds) jsonResponse(422, ["msg" => "No file ids provided."]);
$placeholders = implode(',', array_fill(0, count($fileIds), '?'));
$rows = $db->all("SELECT `id`, `name`, `type` FROM `fileInfo` WHERE `id` IN ($placeholders);", $fileIds, __FILE__, __LINE__);
$rowsById = [];
foreach ($rows as $row) $rowsById[$row['id']] = $row;
if (count($fileIds) === 1) {
    $row = $rowsById[$fileIds[0]] ?? null;
    if (!$row) jsonResponse(404, ["msg" => "File not found."]);
    $bytes = @file_get_contents(getObjectUrl($privateBucket, $row['id'], $row['name'], '+5 minutes'));
    if ($bytes === false) jsonResponse(500, ["msg" => "Failed to read file."]);
    header('Content-Type: ' . ($row['type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . addslashes($fileNames[0] ?? $row['name']) . '"');
    exit($bytes);
}
$tempZip = tempnam(sys_get_temp_dir(), 'project-files-');
$zip = new ZipArchive();
$zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
foreach ($fileIds as $index => $fileId) {
    $row = $rowsById[$fileId] ?? null;
    if (!$row) continue;
    $bytes = @file_get_contents(getObjectUrl($privateBucket, $row['id'], $row['name'], '+5 minutes'));
    if ($bytes === false) continue;
    $zip->addFromString($fileNames[$index] ?? $row['name'], $bytes);
}
$zip->close();
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="attachments.zip"');
readfile($tempZip);
@unlink($tempZip);
exit();
