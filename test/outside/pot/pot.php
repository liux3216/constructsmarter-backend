<?php
require_once __DIR__ . "/helpers.php";


function resolveTargetUserId($db, string $currentUserId): string {
    $userEmail = trim((string)($_POST["userEmail"] ?? $_POST["targetUserEmail"] ?? ""));
    if ($userEmail === "") {
        return $currentUserId;
    }
    $target = $db->one("SELECT `id` FROM `users` WHERE `email` = ? OR `id` = ? LIMIT 1", [$userEmail, $userEmail], __FILE__, __LINE__);
    if (!$target || !isset($target["id"])) {
        http_response_code(404);
        exit(json_encode(["msg" => "user not found."]));
    }
    return (string)$target["id"];
}

$week = trim((string)($_POST["week"] ?? $_POST["currentWeek"] ?? ""));
if ($week === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "week is required."]));
}

$targetUserId = resolveTargetUserId($db, $userId);
$row = outsidePotEnsureRow($db, $targetUserId, $userId);
$weekMap = outsidePotGetWeekMap($row["data"] ?? null);

if (isset($_POST["data"])) {
    $payload = json_decode((string)$_POST["data"], true);
    if (!is_array($payload)) {
        http_response_code(400);
        exit(json_encode(["msg" => "data is invalid."]));
    }
    $weekMap[$week] = json_encode($payload);
    outsidePotSaveWeekMap($db, $targetUserId, $weekMap, $userId);
}

exit(json_encode([$week => $weekMap[$week] ?? ""]));
