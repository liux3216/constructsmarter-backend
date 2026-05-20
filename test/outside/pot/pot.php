<?php
require_once __DIR__ . "/helpers.php";

$week = trim((string)($_POST["week"] ?? $_POST["currentWeek"] ?? ""));
if ($week === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "week is required."]));
}

$row = outsidePotEnsureRow($db, $userId, $userId);
$weekMap = outsidePotGetWeekMap($row["data"] ?? null);

if (isset($_POST["data"])) {
    $payload = json_decode((string)$_POST["data"], true);
    if (!is_array($payload)) {
        http_response_code(400);
        exit(json_encode(["msg" => "data is invalid."]));
    }
    $weekMap[$week] = json_encode($payload);
    outsidePotSaveWeekMap($db, $userId, $weekMap, $userId);
}

exit(json_encode([$week => $weekMap[$week] ?? ""]));
