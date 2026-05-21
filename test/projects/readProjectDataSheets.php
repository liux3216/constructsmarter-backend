<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = $_POST["id"];
//-------------------------------------------------
$rows = $db->all(
    "SELECT `a`.`forms`
    FROM `assignments` `a`
    LEFT JOIN `works` `w` ON `a`.`id` = `a`.`workId`
    LEFT JOIN `projects` `p` ON `w`.`projectId` = `p`.`id`
    WHERE `w`.`id` = ? AND 
    `w`.`void` <> ? AND 
    `a`.`void` <> ?
    ORDER BY `a`.`createdAt`;", [$id, "yes", "yes"], __FILE__, __LINE__
);
echo json_encode($rows);