<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "functions.php"; // isValidWeekNum, defaultEmptyData
if (
    !array_key_exists("week", $_POST) ||
    !array_key_exists("employeeId", $_POST)
) jsonResponse(409, ["msg" => "Missing parameters"]);
$week = $_POST["week"];
$employeeId = $_POST["employeeId"];
if (!isValidWeekNum($week)) jsonResponse(422, ["msg" => "Invalid week."]);
$existing = $db->one(
    "SELECT 
        `timeCard`.`data`,
        CONCAT_WS(\" \", `users`.`firstName`, `users`.`middleName`, `users`.`lastName`) AS `userName`, 
        `users`.`hireDate`,
        `users`.`quitDate`,
        `users`.`outside`
    FROM `timeCard`
    LEFT JOIN `users` ON `users`.`id` = `timeCard`.`userId`
    WHERE `timeCard`.`userId` = ? AND `timeCard`.`week` = ?",
    [$employeeId, $week], __FILE__, __LINE__
);
if(!$existing){
    // return default empty data with user info
    $user = $db->one(
        "SELECT 
            CONCAT_WS(\" \", `users`.`firstName`, `users`.`middleName`, `users`.`lastName`) AS `userName`, 
            `hireDate`, `quitDate`, `outside`
        FROM `users` WHERE `id` = ?",
        [$employeeId], __FILE__, __LINE__
    );
    if (!$user) jsonResponse(404, ["msg" => "User not found."]);
    exit(json_encode([
        "userId"   => $employeeId,
        "userName" => $user["userName"],
        "hireDate" => $user["hireDate"],
        "quitDate" => $user["quitDate"],
        "outside"  => $user["outside"],
        "week"     => $week,
        ...defaultEmptyData(),
    ]));
}
$data = json_decode($existing["data"], true);
exit(json_encode([
    "userId"   => $employeeId,
    "userName" => $existing["userName"],
    "hireDate" => $existing["hireDate"],
    "quitDate" => $existing["quitDate"],
    "outside"  => $existing["outside"],
    "week"     => $week,
    ...$data,
]));