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
`w`.`projectId`,
`w`.`category` AS `workCategory`,
`w`.`subCategory` AS `workSubCategory`,
`w`.`location` AS `workLocation`,
`w`.`startTime`,
`w`.`endTime`,
`a`.`userId`,
COALESCE(CONCAT_WS(' ', `assignedUser`.`firstName`, `assignedUser`.`middleName`, `assignedUser`.`lastName`), '') AS `userName`,
`a`.`laborCategory`,
`a`.`fleetNumber`,
`a`.`perDiem`,
`a`.`void`,
`a`.`voidReason`,
`a`.`validateReason`,
`a`.`creatorId`,
COALESCE(CONCAT_WS(' ', `creatorUser`.`firstName`, `creatorUser`.`middleName`, `creatorUser`.`lastName`), '') AS `creatorName`,
`a`.`createdAt`,
`a`.`updaterId`,
COALESCE(CONCAT_WS(' ', `updaterUser`.`firstName`, `updaterUser`.`middleName`, `updaterUser`.`lastName`), '') AS `updaterName`,
`a`.`updatedAt`
FROM `assignments` `a`
LEFT JOIN `works` `w` ON `w`.`id` = `a`.`workId`
LEFT JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
LEFT JOIN `users` `assignedUser` ON CAST(`assignedUser`.`id` AS CHAR) = `a`.`userId`
LEFT JOIN `users` `creatorUser` ON CAST(`creatorUser`.`id` AS CHAR) = `a`.`creatorId`
LEFT JOIN `users` `updaterUser` ON CAST(`updaterUser`.`id` AS CHAR) = `a`.`updaterId`
WHERE `a`.`id` = ?;";

$row = $db->one($sql, [$id], __FILE__, __LINE__);
if (!$row) {
    http_response_code(400);
    exit(json_encode(["msg" => "The assignment is not found."]));
}

exit(json_encode($row));
