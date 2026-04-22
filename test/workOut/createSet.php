<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$workOutGroupId = $_POST["groupId"];
$repetition = $_POST["repetition"];
$weight = $_POST["weight"];
$duration = $_POST["duration"];
$calories = array_key_exists("calories", $_POST)?$_POST["calories"]:null;
$uuid = md5(rand());
$db->exec("INSERT INTO `workOutSets` (`id`, `userId`, `workOutGroupId`, `repetition`, `weight`, `duration`, `calories`) VALUES (\"$uuid\", \"$userId\",?, ?, ?, ?, ?);", [$workOutGroupId, $repetition, $weight, $duration, $calories], __FILE__, __LINE__);
exit($uuid);