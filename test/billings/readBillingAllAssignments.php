<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$projectId = trim((string)($_POST["projectId"] ?? ""));
if ($projectId === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing projectId."]));
}
$emptyDateTime = "0000-00-00 00:00:00";
$rows = $db->all(
    "SELECT 
    `w`.`startTime`, 
    `w`.`endTime`, 
    `a`.`userId`, 
    `a`.`jobTagFileId`, 
    `a`.`id`, 
    `a`.`status`, 
    CONCAT_WS(\" \", `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `laborName`, 
    `a`.`travelStartTime`, 
    `a`.`workStartTime`, 
    `a`.`workEndTime`, 
    `a`.`travelEndTime` 
    FROM `assignments` `a`
    LEFT JOIN `works` `w` ON `a`.`workId` = `w`.`id` 
    LEFT JOIN `projects` `p` ON `p`.`id` = `w`.`projectId` 
    LEFT JOIN `users` `u` ON `a`.`userId` = `u`.`id` 
    WHERE `p`.`id` = ? AND 
    `a`.`laborCategory` <> \"Images\" AND 
    (`a`.`jobTagStatus` = \"Submitted\" OR `a`.`jobTagStatus` = \"Approved\") AND
    `p`.`void` <> \"yes\" AND 
    `w`.`void` <> \"yes\" AND 
    `a`.`void` <> \"yes\"
    ORDER BY `w`.`startTime` ASC;", 
    [$projectId],
    __FILE__,
    __LINE__
);
exit(json_encode($rows));