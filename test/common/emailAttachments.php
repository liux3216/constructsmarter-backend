<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(405, ["msg" => "Method Not Allowed"]);
}
$toEmail = requireEmail($_POST, "toEmail", true, 255);
$fileIds = $_POST["fileId"] ?? [];
if (!is_array($fileIds)) $fileIds = [$fileIds];
$fileIds = array_values(array_filter(array_map('strval', $fileIds)));
if (!$fileIds) {
    jsonResponse(422, ["msg" => "No files selected."]);
}
$placeholders = implode(',', array_fill(0, count($fileIds), '?'));
$rows = $db->all("SELECT `id`, `name` FROM `fileInfo` WHERE `id` IN ($placeholders);", $fileIds, __FILE__, __LINE__);
if (!$rows) {
    jsonResponse(404, ["msg" => "Files not found."]);
}
$lines = [];
foreach ($rows as $row) {
    $url = getObjectUrl($privateBucket, $row['id'], $row['name'], '+1 day');
    $lines[] = '<li><a href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . htmlspecialchars($row['name']) . '</a></li>';
}
sendEmail([
    'to' => [$toEmail],
    'subject' => 'Construct Smarter Files',
    'body' => '<p>The requested files are available at the links below:</p><ul>' . implode('', $lines) . '</ul>',
]);
jsonResponse(200, ['msg' => 'Email sent.']);
