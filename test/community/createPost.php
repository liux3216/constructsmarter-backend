<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$parentId = array_key_exists("parentId", $_POST) ? $_POST["parentId"] : NULL;
$replyTo = array_key_exists("replyTo", $_POST) ? $_POST["replyTo"] : NULL;
$subject = array_key_exists("subject", $_POST) ? $_POST["subject"] : NULL;
$body = $_POST["body"];
$db->exec(
    "INSERT INTO `posts` (`parentId`, `replyTo`, `subject`, `body`, `creatorId`) VALUES (?, ?, ?, ?, ?);",
    [$parentId, $replyTo, $subject, $body, $userId],
    __FILE__,
    __LINE__
);
if($parentId){
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
        ORDER BY `p`.`updatedAt` DESC;",
        [$userId, $parentId], __FILE__, __LINE__
    );
    exit(json_encode(["replies" => $replies]));
}else{
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
