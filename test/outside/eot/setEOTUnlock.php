<?php
require_once __DIR__ . "/helpers.php";

$userEmail = trim((string)($_POST["userEmail"] ?? ""));
$week = trim((string)($_POST["wk"] ?? ""));
$unlock = trim((string)($_POST["unlock"] ?? "")) === "yes";

if ($userEmail === "" || $week === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "invalid request."]));
}

$target = $db->one("SELECT `id`, `email` FROM `users` WHERE `email` = ? OR `id` = ?", [$userEmail, $userEmail], __FILE__, __LINE__);
if (!$target) {
    http_response_code(404);
    exit(json_encode(["msg" => "user not found."]));
}

$row = outsideEotEnsureRow($db, $target["id"], $userId);
$weekMap = outsideEotGetWeekMap($row["data"] ?? null);
$weekData = json_decode($weekMap[$week] ?? json_encode(outsideEotDefaultData()), true);
if (!is_array($weekData) || !isset($weekData["form"]) || !is_array($weekData["form"])) {
    $weekData = outsideEotDefaultData();
}
$weekData["unlock"] = $unlock;
$weekMap[$week] = json_encode($weekData);
outsideEotSaveWeekMap($db, $target["id"], $weekMap, $userId);

exit(json_encode(["msg" => "ok", "email" => $target["email"], "week" => $week, "unlock" => $unlock]));
