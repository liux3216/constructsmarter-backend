<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$id = $_POST["id"];
$repetition = array_key_exists("repetition", $_POST)?$_POST["repetition"]:null;
$weight = array_key_exists("weight", $_POST)?$_POST["weight"]:null;
$duration = array_key_exists("duration", $_POST)?$_POST["duration"]:null;
$calories = array_key_exists("calories", $_POST)?$_POST["calories"]:null;
$comments = array_key_exists("comments", $_POST)?$_POST["comments"]:null;
$db->exec(
    "UPDATE `workOutSets` SET 
    `repetition` = ?, 
    `weight` = ?, 
    `duration` = ?, 
    `calories` = ?,
    `comments` = ?
    WHERE `id` = ? AND `userId` = \"$userId\";", 
    [$repetition, $weight, $duration, $calories, $comments, $id], __FILE__, __LINE__
);
exit();
