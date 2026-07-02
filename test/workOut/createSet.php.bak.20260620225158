<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$workOutGroupId = $_POST["groupId"];
$repetition = array_key_exists("repetition", $_POST) ? $_POST["repetition"] : null;
$weight = array_key_exists("weight", $_POST) ? $_POST["weight"] : null;
$duration = array_key_exists("duration", $_POST) ? $_POST["duration"] : null;
$calories = array_key_exists("calories", $_POST) ? $_POST["calories"] : null;
$db->exec("INSERT INTO `workOutSets` (`userId`, `workOutGroupId`, `repetition`, `weight`, `duration`, `calories`) VALUES (?, ?, ?, ?, ?, ?);", [$userId, $workOutGroupId, $repetition, $weight, $duration, $calories], __FILE__, __LINE__);
$id = (int)($db->one("SELECT LAST_INSERT_ID() AS `id`", [], __FILE__, __LINE__)["id"] ?? 0);
exit((string)$id);
