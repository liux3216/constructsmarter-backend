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
`w`.`coords`,
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
`a`.`jsaContent`,
`a`.`jsaSaveTime`,
`a`.`jsaSubmitTime`,
`a`.`jsaStatus`,
`a`.`jsaFileId`,
`w`.`jobTagLocation`,
`a`.`jobTagStatus`,
`a`.`status`, 
`a`.`isPreDriver`,
`a`.`preTruckId`,
`f1`.`truckNumber` AS `preTruckNumber`,
`a`.`preVehicleData`,

`a`.`isPostDriver`,
`a`.`postTruckId`,
`f2`.`truckNumber` AS `postTruckNumber`,
`a`.`postVehicleData`,

`a`.`hasPreTrailer`,
`a`.`preTrailerId`,
`f3`.`truckNumber` AS `preTrailerNumber`,
`a`.`preTrailerData`,

`a`.`hasPostTrailer`,
`a`.`postTrailerId`,
`f4`.`truckNumber` AS `postTrailerNumber`,
`a`.`postTrailerData`,

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
`a`.`status`,
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
LEFT JOIN `fleets` `f1` ON `f1`.`id` = `a`.`preTruckId`
LEFT JOIN `fleets` `f2` ON `f2`.`id` = `a`.`postTruckId`
LEFT JOIN `fleets` `f3` ON `f3`.`id` = `a`.`preTrailerId`
LEFT JOIN `fleets` `f4` ON `f4`.`id` = `a`.`postTrailerId`
LEFT JOIN `users` `pm` ON `pm`.`id` = `p`.`projectManagerId`
LEFT JOIN `users` `creatorUser` ON `creatorUser`.`id` = `a`.`creatorId`
LEFT JOIN `users` `updaterUser` ON `updaterUser`.`id` = `a`.`updaterId`
WHERE `a`.`id` = ?;";

$row = $db->one($sql, [$id], __FILE__, __LINE__);
if (!$row) {
    http_response_code(400);
    exit(json_encode(["msg" => "The assignment is not found."]));
}

$row["jobSafetyAnalysisContent"] = [];
if (!empty($row["jsaContent"])) {
    $decoded = json_decode($row["jsaContent"], true);
    $row["jobSafetyAnalysisContent"] = is_array($decoded) ? $decoded : [];
}

$assignmentForms = $db->all(
    "SELECT
    `formName`,
    `status`
    FROM `assignment_forms`
    WHERE `assignmentId` = ?
    ORDER BY `updatedAt` DESC;",
    [$id],
    __FILE__,
    __LINE__
);
$row["forms"] = $assignmentForms;

exit(json_encode($row));
