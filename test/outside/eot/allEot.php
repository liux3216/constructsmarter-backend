<?php
require_once __DIR__ . "/helpers.php";

$week = trim((string)($_POST["week"] ?? ""));
if ($week === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "week is required."]));
}

$rows = $db->all(
    "SELECT
        `u`.`id`,
        `u`.`email`,
        `u`.`hireDate`,
        `u`.`quitDate`,
        `u`.`outside`,
        `u`.`residence`,
        `u`.`phoneNumber`,
        `u`.`lanId`,
        `u`.`firstName`,
        `u`.`lastName`,
        CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `userName`,
        `e`.`data`
     FROM `users` `u`
     LEFT JOIN `outsideEOT` `e` ON `e`.`userId` = `u`.`id`
     WHERE `u`.`void` = 'no' AND `u`.`outside` IN ('locator', 'standby', 'qew')
     ORDER BY `u`.`firstName`, `u`.`lastName`, `u`.`id`",
    [],
    __FILE__,
    __LINE__
);

$out = [];
foreach ($rows as $row) {
    $weekMap = outsideEotGetWeekMap($row["data"] ?? null);
    $weekValue = $weekMap[$week] ?? "";
    $out[] = [
        "email" => $row["email"] ?? "",
        "lanId" => $row["lanId"] ?? $row["id"],
        "hireDate" => $row["hireDate"] ?? "",
        "quitDate" => $row["quitDate"] ?? "",
        "outside" => $row["outside"] ?? "",
        "userName" => $row["userName"] ?? "",
        "residence" => $row["residence"] ?? "",
        "phoneNumber" => $row["phoneNumber"] ?? "",
        "firstName" => $row["firstName"] ?? "",
        "lastName" => $row["lastName"] ?? "",
        $week => $weekValue,
    ];
}

exit(json_encode($out));
