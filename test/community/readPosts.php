<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$posts = $db->all(
    "SELECT 
    `p`.`id`, 
    `p`.`subject`, 
    `p`.`body`, 
    `p`.`createdAt`, 
    `p`.`updatedAt`, 
    `p`.`creatorId`, 
    CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `creatorName`,
    `u1`.`email` AS `creatorEmail`
    FROM `posts` `p`
    LEFT JOIN `users` `u1` ON `p`.`creatorId` = `u1`.`id`
    WHERE `p`.`parentId` IS NULL
    ORDER BY `p`.`updatedAt` DESC;", [], __FILE__, __LINE__
);
exit(json_encode($posts));