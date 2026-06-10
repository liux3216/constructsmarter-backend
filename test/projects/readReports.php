<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
header("Content-Type: application/json");
$projectId = trim((string)($_POST["id"] ?? $_POST["projectId"] ?? ""));
if ($projectId === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing project id."]));
}
$rows = $db->all(
    "SELECT
        `r`.`id` AS `reportId`,
        `r`.`reportTechId`,
        CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `reportTechName`,
        `r`.`startDate`,
        `r`.`endDate`,
        `r`.`pdfId`,
        `r`.`status`
     FROM `reports` `r`
     LEFT JOIN `users` `u` ON `u`.`id` = `r`.`reportTechId`
     WHERE `r`.`projectId` = ? AND `r`.`void` = 'no'
     ORDER BY `r`.`startDate` DESC, `r`.`id` DESC;",
    [$projectId],
    __FILE__,
    __LINE__
);
exit(json_encode($rows));
