<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$id = trim((string)($_POST["id"] ?? $_POST["assignmentId"] ?? ""));
if ($id === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing assignment id."]));
}

$sql = "SELECT
`a`.`id`,
`a`.`workId`,
CONCAT_WS(' - ',
    NULLIF(TRIM(`p`.`projectNumber`), ''),
    NULLIF(TRIM(`org`.`name`), ''),
    NULLIF(TRIM(`p`.`clientProjectNumber`), '')
) AS `projectName`,
`p`.`pipeline` AS `projectCategory`,
`p`.`subPipeline` AS `projectSubCategory`,
`w`.`projectId`,
`w`.`category` AS `workCategory`,
`w`.`subCategory` AS `workSubCategory`,
`w`.`location`,
`a`.`coords`,
`w`.`startTime`,
`w`.`endTime`,
`w`.`allDay`,
`a`.`userId`,
CONCAT_WS(' ', `assignedUser`.`firstName`, `assignedUser`.`middleName`, `assignedUser`.`lastName`) AS `userName`,
`a`.`laborCategory`,
`a`.`fleetNumber`,
`a`.`perDiem`,
`a`.`travelStartTime`,
`a`.`workStartTime`,
`a`.`hadLunch`,
`a`.`workEndTime`,
`a`.`travelEndTime`,
`a`.`workFinished`,
`a`.`workRequired`,
`a`.`workPerformed`,
`a`.`additionalInfo`,
`a`.`jobTagFileId`,
`w`.`jobTagLocation`,
CONCAT_WS(' ', `supervisorUser`.`firstName`, `supervisorUser`.`middleName`, `supervisorUser`.`lastName`) AS `supervisorName`,
`w`.`supervisorId`,
`w`.`leadId`,
`w`.`waiveJSA`, 
CONCAT_WS(' ', `leadUser`.`firstName`, `leadUser`.`middleName`, `leadUser`.`lastName`) AS `leadName`, 
`p`.`description` AS `projectDescription`,
`w`.`description` AS `workDescription`,
`p`.`projectManagerId`, 
`p`.`clientPONumber`, 
CONCAT_WS(' ', `pm`.`firstName`, `pm`.`middleName`, `pm`.`lastName`) AS `projectManagerName`,
`w`.`siteContactId`, 
CONCAT_WS(' ', `siteContact`.`firstName`, `siteContact`.`middleName`, `siteContact`.`lastName`) AS `siteContactName`,
COALESCE(`siteContact`.`directNumber`, `siteContact`.`phoneNumber`) AS `siteContactPhone`,
`a`.`void`,
`a`.`voidReason`,
`a`.`validateReason`,
`a`.`creatorId`,
CONCAT_WS(' ', `creatorUser`.`firstName`, `creatorUser`.`middleName`, `creatorUser`.`lastName`) AS `creatorName`,
`a`.`createdAt`,
`a`.`updaterId`,
CONCAT_WS(' ', `updaterUser`.`firstName`, `updaterUser`.`middleName`, `updaterUser`.`lastName`) AS `updaterName`,
`a`.`updatedAt`,

`a`.`travelStartTime`, `a`.`workStartTime`, `a`.`hadLunch`, `a`.`workEndTime`, `a`.`travelEndTime`, `a`.`workFinished`, `a`.`workRequired`, `a`.`workPerformed`, `a`.`additionalInfo`, 
`a`.`jobTagFileId`

FROM `assignments` `a`
LEFT JOIN `works` `w` ON `w`.`id` = `a`.`workId`
LEFT JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
LEFT JOIN `users` `assignedUser` ON `assignedUser`.`id` = `a`.`userId`
LEFT JOIN `users` `supervisorUser` ON `supervisorUser`.`id` = `w`.`supervisorId`
LEFT JOIN `users` `leadUser` ON `leadUser`.`id` = `w`.`leadId`
LEFT JOIN `contacts` `siteContact` ON `siteContact`.`id` = `w`.`siteContactId`
LEFT JOIN `users` `pm` ON `pm`.`id` = `p`.`projectManagerId`
LEFT JOIN `users` `creatorUser` ON `creatorUser`.`id` = `a`.`creatorId`
LEFT JOIN `users` `updaterUser` ON `updaterUser`.`id` = `a`.`updaterId`
WHERE `a`.`id` = ?;";

$row = $db->one($sql, [$id], __FILE__, __LINE__);
if (!$row) {
    http_response_code(400);
    exit(json_encode(["msg" => "The assignment is not found."]));
}

exit(json_encode($row));
