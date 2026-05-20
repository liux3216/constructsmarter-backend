<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

function outsidePotDefaultData(): array {
    return [
        "unlock" => false,
        "form" => array_fill(0, 7, [
            "inYard" => "",
            "outYard" => "",
            "yard" => "",
            "weekDay" => "",
            "date" => "",
        ]),
        "status" => "Created",
    ];
}

function outsidePotGetWeekMap(?string $json): array {
    if (!$json) return [];
    $map = json_decode($json, true);
    return is_array($map) ? $map : [];
}

function outsidePotEnsureRow($db, string $targetUserId, string $actorId): array {
    $row = $db->one("SELECT `userId`, `data` FROM `outsidePOT` WHERE `userId` = ?", [$targetUserId], __FILE__, __LINE__);
    if ($row) return $row;
    $db->exec(
        "INSERT INTO `outsidePOT` (`userId`, `data`, `creatorId`, `updaterId`) VALUES (?, NULL, ?, ?)",
        [$targetUserId, $actorId, $actorId],
        __FILE__,
        __LINE__
    );
    return ["userId" => $targetUserId, "data" => null];
}

function outsidePotSaveWeekMap($db, string $targetUserId, array $weekMap, string $actorId): void {
    $db->exec(
        "UPDATE `outsidePOT` SET `data` = ?, `updaterId` = ? WHERE `userId` = ?",
        [json_encode($weekMap), $actorId, $targetUserId],
        __FILE__,
        __LINE__
    );
}
