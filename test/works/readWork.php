<?php
require_once "/opt/bitnami/apache/htdocs/test/common/attachment_helpers.php";

header("Content-Type: application/json");

$id = trim((string)($_POST["id"] ?? $_POST["workId"] ?? ""));
if ($id === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing work id."]));
}

$sql = "SELECT
`p`.`organizationId`, 
`w`.`id`,
`w`.`projectId`,
`p`.`proposalId`,
`prop`.`proposalNumber`,
`w`.`serviceId`,
COALESCE(CONCAT_WS(' - ', NULLIF(TRIM(`svc`.`code`), ''), NULLIF(TRIM(`svc`.`name`), '')), `svc`.`name`, '') AS `serviceName`,
CONCAT_WS(' - ',
    NULLIF(TRIM(`p`.`projectNumber`), ''),
    NULLIF(TRIM(`org`.`name`), ''),
    NULLIF(TRIM(`p`.`clientProjectNumber`), '')
) AS `projectName`,
`w`.`category`,
`w`.`subCategory`,
`w`.`location`,
`w`.`jobTagLocation`,
`w`.`coords`,
`p`.`location` AS `projectLocation`,
`p`.`coords` AS `projectCoords`,
`w`.`startTime`,
`w`.`endTime`,
`w`.`allDay`,
`w`.`supervisorId`,
CONCAT_WS(' ', `supById`.`firstName`, `supById`.`middleName`, `supById`.`lastName`) AS `supervisorName`,
'' AS `fieldSupervisorName`,
'' AS `fieldSupervisorEmail`,
`w`.`siteContactId`,
CONCAT_WS(' ', `c`.`firstName`, `c`.`middleName`, `c`.`lastName`) AS `siteContactName`,
COALESCE(`c`.`phoneNumber`, `c`.`directNumber`) AS `siteContactMobile`,
`w`.`cadRequired`,
`w`.`reportRequired`,
`w`.`waiveJSA`,
CONCAT_WS(' ', `leadById`.`firstName`, `leadById`.`middleName`, `leadById`.`lastName`) AS `leadName`,
`w`.`leadId`,
`w`.`description`,
`w`.`void`,
`w`.`voidReason`,
`w`.`validateReason`,
`w`.`creatorId`,
CONCAT_WS(' ', `creatorById`.`firstName`, `creatorById`.`middleName`, `creatorById`.`lastName`) AS `creatorName`,
`w`.`createdAt`,
`w`.`updaterId`,
CONCAT_WS(' ', `updaterById`.`firstName`, `updaterById`.`middleName`, `updaterById`.`lastName`) AS `updaterName`,
`w`.`updatedAt`,
`w`.`folderId`
FROM `works` `w`
LEFT JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
LEFT JOIN `proposals` `prop` ON `prop`.`id` = `p`.`proposalId`
LEFT JOIN `services` `svc` ON `svc`.`id` = `w`.`serviceId`
LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
LEFT JOIN `contacts` `c` ON `c`.`id` = `w`.`siteContactId`
LEFT JOIN `users` `supById` ON `supById`.`id` = `w`.`supervisorId`
LEFT JOIN `users` `creatorById` ON `creatorById`.`id` = `w`.`creatorId`
LEFT JOIN `users` `updaterById` ON `updaterById`.`id` = `w`.`updaterId`
LEFT JOIN `users` `leadById` ON `leadById`.`id` = `w`.`leadId`
WHERE `w`.`id` = ?;";

$row = $db->one($sql, [$id], __FILE__, __LINE__);
if (!$row) {
    http_response_code(400);
    exit(json_encode(["msg" => "The work is not found."]));
}

$target = attachmentResolveTarget($db, 'works', (int)$id, $userId);
$row['folderId'] = $target['folderId'];
$row['workFiles'] = $target['files'];

$technicians = $db->all(
    "SELECT
        `a`.`id` AS `assignmentId`,
        `a`.`id` AS `id`,
        `a`.`userId`,
        CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `userName`,
        `a`.`laborCategory`,
        `a`.`fleetNumber`,
        `a`.`perDiem`
    FROM `assignments` `a`
    LEFT JOIN `users` `u` ON `u`.`id` = `a`.`userId`
    WHERE `a`.`workId` = ? AND `a`.`void` = 'no'
    ORDER BY `a`.`createdAt` ASC;",
    [$id],
    __FILE__,
    __LINE__
);

$row["technicians"] = $technicians;

exit(json_encode($row));
