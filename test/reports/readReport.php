<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$id = trim((string)($_POST["id"] ?? ""));
if ($id === "") {
    http_response_code(400);
    exit(json_encode(["error" => "Invalid schema. `id` is required."]));
}

$sql = "SELECT
`reports`.`id`,
`reports`.`projectId`,
CONCAT_WS(' - ',
    NULLIF(TRIM(`p`.`projectNumber`), ''),
    NULLIF(TRIM(`org`.`name`), ''),
    NULLIF(TRIM(`p`.`clientProjectNumber`), '')
) AS `projectName`,
`reports`.`startDate`,
`reports`.`endDate`,
`reports`.`pothole`,
`reports`.`ep`,
`reports`.`manhole`,
`reports`.`code`,
`reports`.`priority`,
`reports`.`pending`,
`reports`.`sup`,
`reports`.`pdfId`,
`reports`.`reportTechId`,
CONCAT_WS(' ', `rt`.`firstName`, `rt`.`middleName`, `rt`.`lastName`) AS `reportTech`,
`reports`.`status`,
`reports`.`notes`,
`p`.`projectManagerId`,
`p`.`projectManagerId` AS `approverId`,
CONCAT_WS(' ', `pm`.`firstName`, `pm`.`middleName`, `pm`.`lastName`) AS `projectManager`,
`reports`.`approverNotes`,
`reports`.`creatorId`,
CONCAT_WS(' ', `creatorUser`.`firstName`, `creatorUser`.`middleName`, `creatorUser`.`lastName`) AS `creatorName`,
`reports`.`createdAt`,
`reports`.`updaterId`,
CONCAT_WS(' ', `updaterUser`.`firstName`, `updaterUser`.`middleName`, `updaterUser`.`lastName`) AS `updaterName`,
`reports`.`updatedAt`,
`reports`.`requestorId`,
CONCAT_WS(' ', `requestorUser`.`firstName`, `requestorUser`.`middleName`, `requestorUser`.`lastName`) AS `requestor`,
`reports`.`decisionTime`,
`reports`.`notify`,
`reports`.`void`,
`reports`.`voidReason`,
`reports`.`validateReason`,
`reports`.`reportLocation`,
`reports`.`cadLocation`,
`reports`.`videoLocation`
FROM `reports`
LEFT JOIN `projects` `p` ON `p`.`id` = `reports`.`projectId`
LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
LEFT JOIN `users` `rt` ON `rt`.`id` = `reports`.`reportTechId`
LEFT JOIN `users` `pm` ON `pm`.`id` = `p`.`projectManagerId`
LEFT JOIN `users` `creatorUser` ON `creatorUser`.`id` = `reports`.`creatorId`
LEFT JOIN `users` `updaterUser` ON `updaterUser`.`id` = `reports`.`updaterId`
LEFT JOIN `contacts` `requestorUser` ON `requestorUser`.`id` = `reports`.`requestorId`
WHERE CAST(`reports`.`id` AS CHAR) = ?
LIMIT 1;";

$report = $db->one($sql, [$id], __FILE__, __LINE__);
if (!$report) {
    http_response_code(404);
    exit(json_encode(["msg" => "Report not found."]));
}

exit(json_encode(["report" => $report]));
