<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "functions.php"; // defaultEmptyData, isValidWeekNum
$week = array_key_exists("week", $_POST) ? $_POST["week"] : null;
if(!isValidWeekNum($week)) $week = date("oW");
$timeCards = $db->all(
    "SELECT DISTINCT `week` FROM `timeCard`  
    WHERE `userId` = ?;", [$userId], __FILE__, __LINE__
);
$weeks = array_column($timeCards, "week");
$timeCard = $db->one(
    "SELECT `data` FROM `timeCard`  
    WHERE `userId` = ? AND `week` = ?;", [$userId, $week], __FILE__, __LINE__
);
if(!$timeCard) $data = defaultEmptyData();
else $data = json_decode($timeCard["data"], true);
if(!in_array($week, $weeks)) array_push($weeks, $week);
exit(json_encode(["data" => $data, "week" => $week, "weeks" => $weeks]));