<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

function checkInDefaultData(): array {
    return [
        "form" => array_fill(0, 7, [
            "unlock" => false,
            "working" => "",
            "notWorkingReason" => "",
            "commuteOrHotel" => "",
            "division" => "",
            "workingOn" => "",
            "acknowledged" => false,
            "morning" => false,
            "afternoon" => false,
            "doOT" => "",
            "otDate" => "",
            "otTime" => "",
        ]),
        "status" => "Created",
    ];
}

function checkInGetWeekMap(?string $json): array {
    if (!$json) return [];
    $map = json_decode($json, true);
    return is_array($map) ? $map : [];
}

function checkInEnsureRow($db, string $targetUserId, string $actorId): array {
    $row = $db->one("SELECT `userId`, `data` FROM `outsideDaily` WHERE `userId` = ?", [$targetUserId], __FILE__, __LINE__);
    if ($row) return $row;
    $db->exec(
        "INSERT INTO `outsideDaily` (`userId`, `data`, `creatorId`, `updaterId`) VALUES (?, NULL, ?, ?)",
        [$targetUserId, $actorId, $actorId],
        __FILE__,
        __LINE__
    );
    return ["userId" => $targetUserId, "data" => null];
}

function checkInSaveWeekMap($db, string $targetUserId, array $weekMap, string $actorId): void {
    $db->exec(
        "UPDATE `outsideDaily` SET `data` = ?, `updaterId` = ? WHERE `userId` = ?",
        [json_encode($weekMap), $actorId, $targetUserId],
        __FILE__,
        __LINE__
    );
}


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
$row = checkInEnsureRow($db, $targetUserId, $userId);
$weekMap = checkInGetWeekMap($row["data"] ?? null);

if (isset($_POST["data"])) {
    $payload = json_decode((string)$_POST["data"], true);
    if (!is_array($payload)) {
        http_response_code(400);
        exit(json_encode(["msg" => "data is invalid."]));
    }
    $weekMap[$week] = json_encode($payload);
    checkInSaveWeekMap($db, $targetUserId, $weekMap, $userId);
}

exit(json_encode([$week => $weekMap[$week] ?? ""]));
