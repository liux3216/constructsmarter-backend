<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$userId = trim((string)($_POST["userId"] ?? ""));
$start = trim((string)($_POST["start"] ?? ""));
$end = trim((string)($_POST["end"] ?? ""));

if ($userId === "") {
    http_response_code(409);
    exit(json_encode(["msg" => "Technician is required."]));
}
if (!preg_match("/^\\d{4}-\\d{2}-\\d{2}$/", $start)) {
    http_response_code(409);
    exit(json_encode(["msg" => "Start date is invalid."]));
}
if (!preg_match("/^\\d{4}-\\d{2}-\\d{2}$/", $end)) {
    http_response_code(409);
    exit(json_encode(["msg" => "End date is invalid."]));
}
if ($start > $end) {
    http_response_code(409);
    exit(json_encode(["msg" => "Start date cannot be after end date."]));
}

$selectedUser = $db->one(
    "SELECT `id`
     FROM `users`
     WHERE `void` = 'no'
       AND `id` = ?
     LIMIT 1;",
    [$userId],
    __FILE__,
    __LINE__
);
if (!$selectedUser) {
    http_response_code(404);
    exit(json_encode(["msg" => "Technician not found."]));
}

$rows = $db->all(
    "SELECT
        `a`.`jobTagFileId`,
        COALESCE(`fi`.`name`, '') AS `fileName`,
        CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `laborName`,
        `w`.`startTime`,
        `w`.`endTime`
     FROM `assignments` `a`
     JOIN `works` `w` ON `w`.`id` = `a`.`workId`
     JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
     LEFT JOIN `users` `u` ON `u`.`id` = `a`.`userId`
     LEFT JOIN `fileInfo` `fi` ON `fi`.`id` = `a`.`jobTagFileId`
     WHERE `a`.`void` = 'no'
       AND `w`.`void` = 'no'
       AND `p`.`void` = 'no'
       AND `u`.`void` = 'no'
       AND `a`.`userId` = ?
       AND `a`.`jobTagStatus` = 'Submitted'
       AND `a`.`jobTagFileId` IS NOT NULL
       AND `a`.`jobTagFileId` <> ''
       AND DATE(COALESCE(`a`.`travelStartTime`, `a`.`workStartTime`, `w`.`startTime`)) >= ?
       AND DATE(COALESCE(`a`.`travelStartTime`, `a`.`workStartTime`, `w`.`startTime`)) <= ?
     ORDER BY COALESCE(`a`.`updatedAt`, `a`.`createdAt`) DESC, `a`.`id` DESC;",
    [$selectedUser["id"], $start, $end],
    __FILE__,
    __LINE__
);

if (!$rows) {
    http_response_code(404);
    exit(json_encode(["msg" => "No job tags found."]));
}

function buildDownloadName(array $row): string {
    $startDate = substr((string)$row["startTime"], 0, 10);
    $endDate = substr((string)$row["endTime"], 0, 10);
    $duration = $startDate === $endDate ? $startDate : "from {$startDate} to {$endDate}";
    $original = (string)($row["fileName"] ?? "");
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $suffix = $ext ? ".{$ext}" : ".pdf";
    $base = trim((string)($row["laborName"] ?? "Job Tag"));
    return trim("{$base} {$duration}") . $suffix;
}

$urls = [];
foreach ($rows as $row) {
    $fileName = $row["fileName"] ?: buildDownloadName($row);
    $url = getObjectUrl($privateBucket, $row["jobTagFileId"], $fileName);
    if (!$url) {
        http_response_code(404);
        exit(json_encode(["msg" => "One or more files are not found."]));
    }
    $urls[] = $url;
}

exit(json_encode($urls));
