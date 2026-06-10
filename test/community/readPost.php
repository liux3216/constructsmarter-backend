<?php
require_once "/opt/bitnami/apache/htdocs/test/common/attachment_helpers.php";
$id = $_POST["id"];
$obj = $db->one(
    "SELECT 
    `p`.`id`, 
    `p`.`subject`, 
    `p`.`body`, 
    `p`.`createdAt`, 
    `p`.`updatedAt`, 
    `p`.`creatorId`, 
    `p`.`picFolderId`,
    CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `creatorName`, 
    `p`.`likesCount`, 
    CASE WHEN `pL`.userId IS NULL THEN 0 ELSE 1 END AS liked
    FROM `posts` `p`
    LEFT JOIN `users` `u1` ON `p`.`creatorId` = `u1`.`id`
    LEFT JOIN `postLikes` `pL` ON `pL`.`postId` = `p`.`id` AND `pL`.`userId` = ?
    WHERE `p`.`id` = ?
    ORDER BY `p`.`updatedAt` DESC;", [$userId, $id], __FILE__, __LINE__
);
$replies = $db->all(
    "SELECT 
    `p`.`id`, 
    `p`.`body`, 
    `p`.`createdAt`, 
    `p`.`updatedAt`, 
    `p`.`likesCount`, 
    `p`.`creatorId`, 
    `p`.`replyTo`, 
    CASE WHEN `pL`.userId IS NULL THEN 0 ELSE 1 END AS liked, 
    CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `replyToName`, 
    `u2`.`id` AS `replyToId`, 
    CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `creatorName`
    FROM `posts` `p`
    LEFT JOIN `users` `u1` ON `p`.`creatorId` = `u1`.`id`
    LEFT JOIN `users` `u2` ON `p`.`replyTo` = `u2`.`id`
    LEFT JOIN `postLikes` `pL` ON `pL`.`postId` = `p`.`id` AND `pL`.`userId` = ?
    WHERE `p`.`parentId` = ?
    ORDER BY `p`.`updatedAt` DESC;", [$userId, $id], __FILE__, __LINE__
);
$photos = [];
$picFolderId = trim((string)($obj['picFolderId'] ?? ''));
if ($picFolderId !== '') {
    foreach (attachmentReadFiles($db, $picFolderId) as $file) {
        $photos[] = [
            'id' => $file['id'],
            'name' => $file['name'],
            'url' => $file['webViewLink'],
        ];
    }
}
$obj['photos'] = $photos;
$obj["replies"] = $replies;
exit(json_encode($obj));
