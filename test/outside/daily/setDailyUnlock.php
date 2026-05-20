<?php
require_once __DIR__ . "/helpers.php";

$userEmail = trim((string)($_POST["userEmail"] ?? ""));
$week = trim((string)($_POST["wk"] ?? ""));
$weekDay = (int)($_POST["wkDay"] ?? -1);
$unlock = trim((string)($_POST["unlock"] ?? "")) === "yes";

if ($userEmail === "" || $week === "" || $weekDay < 0 || $weekDay > 6) {
    http_response_code(400);
    exit(json_encode(["msg" => "invalid request."]));
}

$target = $db->one("SELECT `id`, `email` FROM `users` WHERE `email` = ? OR `id` = ?", [$userEmail, $userEmail], __FILE__, __LINE__);
if (!$target) {
    http_response_code(404);
    exit(json_encode(["msg" => "user not found."]));
}

$row = outsideDailyEnsureRow($db, $target["id"], $userId);
$weekMap = outsideDailyGetWeekMap($row["data"] ?? null);
$weekData = json_decode($weekMap[$week] ?? json_encode(outsideDailyDefaultData()), true);
if (!is_array($weekData) || !isset($weekData["form"]) || !is_array($weekData["form"])) {
    $weekData = outsideDailyDefaultData();
}
if (!isset($weekData["form"][$weekDay]) || !is_array($weekData["form"][$weekDay])) {
    $weekData["form"][$weekDay] = outsideDailyDefaultData()["form"][$weekDay];
}
$weekData["form"][$weekDay]["unlock"] = $unlock;
$weekMap[$week] = json_encode($weekData);
outsideDailySaveWeekMap($db, $target["id"], $weekMap, $userId);

exit(json_encode(["msg" => "ok", "email" => $target["email"], "week" => $week, "weekDay" => $weekDay, "unlock" => $unlock]));
