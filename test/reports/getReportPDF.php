<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$reportId = trim((string)($_POST["reportHashKey"] ?? $_POST["id"] ?? ""));
if ($reportId === "") {
    jsonResponse(422, ["msg" => "reportHashKey is required."]);
}

$report = $db->one(
    "SELECT `pdfId` FROM `reports` WHERE `id` = ? LIMIT 1;",
    [$reportId],
    __FILE__,
    __LINE__
);
if (!$report || !$report["pdfId"]) {
    jsonResponse(404, ["msg" => "Report file not found."]);
}

$file = $db->one(
    "SELECT `name` FROM `fileInfo` WHERE `id` = ? LIMIT 1;",
    [$report["pdfId"]],
    __FILE__,
    __LINE__
);
if (!$file) {
    exit(json_encode("https://drive.google.com/file/d/" . $report["pdfId"] . "/view?usp=drivesdk"));
}

exit(json_encode(getObjectUrl($privateBucket, $report["pdfId"], $file["name"])));
