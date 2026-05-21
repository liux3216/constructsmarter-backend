<?php
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
        CAST(`p`.`id` AS CHAR) AS `projectId`,
        CAST(`w`.`id` AS CHAR) AS `workId`,
        CAST(`a`.`id` AS CHAR) AS `assignmentId`,
        CONCAT_WS(' - ',
            NULLIF(TRIM(`p`.`projectNumber`), ''),
            NULLIF(TRIM(`org`.`name`), ''),
            NULLIF(TRIM(`p`.`clientProjectNumber`), '')
        ) AS `projectName`,
        COALESCE(`w`.`category`, '') AS `category`,
        COALESCE(`a`.`laborCategory`, '') AS `laborCategory`,
        COALESCE(`u`.`department`, '') AS `department`,
        COALESCE(`a`.`updatedAt`, `a`.`createdAt`) AS `assignmentSubmitTime`,
        `w`.`startTime`,
        `w`.`endTime`,
        CASE WHEN `w`.`allDay` = 'yes' THEN true ELSE false END AS `allDay`,
        `a`.`jobTagFileId`,
        CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `laborName`,
        COALESCE(`a`.`status`, 'Created') AS `assignmentStatus`
     FROM `assignments` `a`
     JOIN `works` `w` ON `w`.`id` = `a`.`workId`
     JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
     LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
     LEFT JOIN `users` `u` ON `u`.`id` = `a`.`userId`
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

exit(json_encode($rows));
