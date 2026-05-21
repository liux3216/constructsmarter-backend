<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$workOutGroupId = $_POST["groupId"];
$repetition = $_POST["repetition"];
$weight = $_POST["weight"];
$duration = $_POST["duration"];
$calories = array_key_exists("calories", $_POST) ? $_POST["calories"] : null;
$db->exec("INSERT INTO `workOutSets` (`userId`, `workOutGroupId`, `repetition`, `weight`, `duration`, `calories`) VALUES (?, ?, ?, ?, ?, ?);", [$userId, $workOutGroupId, $repetition, $weight, $duration, $calories], __FILE__, __LINE__);
$id = (int)($db->one("SELECT LAST_INSERT_ID() AS `id`", [], __FILE__, __LINE__)["id"] ?? 0);
exit((string)$id);
