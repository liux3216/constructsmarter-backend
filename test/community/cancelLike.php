<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = $_POST["id"];
$parentId = array_key_exists("parentId", $_POST)?$_POST["parentId"]:NULL;
$deleted = $db->exec("DELETE FROM postLikes WHERE postId = ? AND userId = ?", [$id, $userId], __FILE__, __LINE__);
if($deleted === 1) $stmt = $db->exec("UPDATE `posts` SET `likesCount` = `likesCount` - 1 WHERE `id` = ?", [$id], __FILE__, __LINE__);
$row = $db->one(
    "SELECT 
    `p`.`likesCount`, 
    CASE WHEN `pL`.userId IS NULL THEN 0 ELSE 1 END AS liked
    FROM `posts` `p`
    LEFT JOIN `postLikes` `pL` ON `pL`.`postId` = `p`.`id` AND `pL`.`userId` = ?
    WHERE `p`.`id` = ?
    ORDER BY `p`.`updatedAt` DESC;", [$userId, $id], __FILE__, __LINE__
);
exit(json_encode($row));