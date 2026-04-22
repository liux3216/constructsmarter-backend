<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = $_POST["id"];
//-------------------------------------------------
$rows = $db->all(
    "SELECT `wl`.`forms`
    FROM `workLogs` `wl`
    LEFT JOIN `workInstructions` `wi` ON `wi`.`id` = `wl`.`workInstructionId`
    LEFT JOIN `projects` `p` ON `wi`.`projectId` = `p`.`id`
    WHERE `wi`.`id` = ? AND 
    `wi`.`void` <> ? AND 
    `wl`.`void` <> ?
    ORDER BY `wl`.`createdAt`;", [$id, "yes", "yes"], __FILE__, __LINE__
);
echo json_encode($rows);