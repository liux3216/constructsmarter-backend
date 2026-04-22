<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
// also need to delete pics
$id = $_POST["id"];
$parentId = array_key_exists("parentId", $_POST)?$_POST["parentId"]:NULL;
$db->exec("DELETE FROM `posts` WHERE `id` = ?;", [$id], __FILE__, __LINE__);
//-------------------------------------------------
if($parentId){
    // sub post
    $replies = $db->all(
        "SELECT 
        `p`.`id`, 
        `p`.`body`, 
        `p`.`createdAt`, 
        `p`.`updatedAt`, 
        `p`.`likesCount`, 
        `p`.`creatorId`, 
        `p`.`replyTo`, 
        CASE WHEN `pL`.userId IS NULL THEN 0 ELSE 1 END AS `liked`, 
        CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `replyToName`, 
        `u2`.`id` AS `replyToId`, 
        CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `creatorName`
        FROM `posts` `p`
        LEFT JOIN `users` `u1` ON `p`.`creatorId` = `u1`.`id`
        LEFT JOIN `users` `u2` ON `p`.`replyTo` = `u2`.`id`
        LEFT JOIN `postLikes` `pL` ON `pL`.`postId` = `p`.`id` AND `pL`.`userId` = ?
        WHERE `p`.`parentId` <=> ?
        ORDER BY `p`.`updatedAt` DESC;", [$userId, $parentId], __FILE__, __LINE__
    );
    exit(json_encode(["replies" => $replies]));
}else{
    // main post
    $posts = $db->all(
        "SELECT 
        `p`.`id`, 
        `p`.`subject`, 
        `p`.`body`, 
        `p`.`createdAt`, 
        `p`.`updatedAt`, 
        `p`.`creatorId`, 
        CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `creatorName`
        FROM `posts` `p`
        LEFT JOIN `users` `u1` ON `p`.`creatorId` = `u1`.`id`
        WHERE `p`.`parentId` IS NULL
        ORDER BY `p`.`updatedAt` DESC;", [], __FILE__, __LINE__
    );
    exit(json_encode($posts));
}