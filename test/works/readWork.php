<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$id = trim((string)($_POST["id"] ?? $_POST["workId"] ?? ""));
if ($id === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing work id."]));
}

/*
 * This endpoint targets the newer id-based `works` table and aliases the row
 * into the field names expected by the current React `works` module.
 *
 * Current assumptions:
 *   or a user email, so both paths are supported.
 * - The current `works` table does not store `fieldSupervisorName`,
 *   `fieldSupervisorEmail`, `workFiles`, or `labors`, so empty defaults are
 *   returned for those fields.
 */
$sql = "SELECT
`p`.`organizationId`, 
`w`.`id`,
`w`.`projectId`,
CONCAT_WS(' - ',
    NULLIF(TRIM(`p`.`projectNumber`), ''),
    NULLIF(TRIM(`org`.`name`), ''),
    NULLIF(TRIM(`p`.`clientProjectNumber`), '')
) AS `projectName`,
`w`.`category`,
`w`.`subCategory`,
`w`.`location`,
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
COALESCE(`c`.`phoneNumber`, `c`.`directNumber`, '') AS `siteContactMobile`,
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


COALESCE(`w`.`folderId`, '') AS `attachmentFolderId`,
COALESCE(`w`.`folderId`, '') AS `folderId`,
'[]' AS `workFiles`,
'[]' AS `labors`


FROM `works` `w`
LEFT JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
LEFT JOIN `contacts` `c` ON `c`.`id` = `w`.`siteContactId`
LEFT JOIN `users` `supById` ON CAST(`supById`.`id` AS CHAR) = `w`.`supervisorId`
LEFT JOIN `users` `creatorById` ON CAST(`creatorById`.`id` AS CHAR) = `w`.`creatorId`
LEFT JOIN `users` `updaterById` ON CAST(`updaterById`.`id` AS CHAR) = `w`.`updaterId`
LEFT JOIN `users` `leadById` ON `leadById`.`id` = `w`.`leadId`
WHERE `w`.`id` = ?;";

$row = $db->one($sql, [$id], __FILE__, __LINE__);
if (!$row) {
    http_response_code(400);
    exit(json_encode(["msg" => "The work is not found."]));
}

exit(json_encode($row));
