<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

function outsideDailyDefaultData(): array {
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

function outsideDailyGetWeekMap(?string $json): array {
    if (!$json) return [];
    $map = json_decode($json, true);
    return is_array($map) ? $map : [];
}

function outsideDailyEnsureRow($db, string $userId, string $actorId): array {
    $row = $db->one("SELECT `userId`, `data` FROM `outsideDaily` WHERE `userId` = ?", [$userId], __FILE__, __LINE__);
    if ($row) return $row;
    $db->exec(
        "INSERT INTO `outsideDaily` (`userId`, `data`, `creatorId`, `updaterId`) VALUES (?, NULL, ?, ?)",
        [$userId, $actorId, $actorId],
        __FILE__,
        __LINE__
    );
    return ["userId" => $userId, "data" => null];
}

function outsideDailySaveWeekMap($db, string $userId, array $weekMap, string $actorId): void {
    $db->exec(
        "UPDATE `outsideDaily` SET `data` = ?, `updaterId` = ? WHERE `userId` = ?",
        [json_encode($weekMap), $actorId, $userId],
        __FILE__,
        __LINE__
    );
}
