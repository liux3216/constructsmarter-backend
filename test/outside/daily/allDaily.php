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
        `u`.`residenceState`,
        `u`.`residence`,
        `u`.`street`,
        `u`.`zipCode`,
        `u`.`phoneNumber`,
        `u`.`firstName`,
        `u`.`lastName`,
        CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `userName`,
        `d`.`data`
     FROM `users` `u`
     LEFT JOIN `outsideDaily` `d` ON `d`.`userId` = `u`.`id`
     WHERE `u`.`void` = 'no' AND `u`.`outside` IN ('locator', 'standby', 'qew')
     ORDER BY `u`.`firstName`, `u`.`lastName`, `u`.`id`",
    [],
    __FILE__,
    __LINE__
);

$out = [];
foreach ($rows as $row) {
    $weekMap = outsideDailyGetWeekMap($row["data"] ?? null);
    $weekValue = $weekMap[$week] ?? "";
    $out[] = [
        "email" => $row["email"] ?? "",
        "lanId" => $row["id"],
        "hireDate" => $row["hireDate"] ?? "",
        "quitDate" => $row["quitDate"] ?? "",
        "outside" => $row["outside"] ?? "",
        "userName" => $row["userName"] ?? "",
        "residenceState" => $row["residenceState"] ?? "",
        "residence" => $row["residence"] ?? "",
        "street" => $row["street"] ?? "",
        "zipCode" => $row["zipCode"] ?? "",
        "phoneNumber" => $row["phoneNumber"] ?? "",
        "firstName" => $row["firstName"] ?? "",
        "lastName" => $row["lastName"] ?? "",
        $week => $weekValue,
    ];
}

exit(json_encode($out));
