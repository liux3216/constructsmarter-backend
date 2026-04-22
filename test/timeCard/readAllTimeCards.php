<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "functions.php"; // isValidWeekNum, getDateFromWeekNum
$week = array_key_exists("week", $_POST) ? $_POST["week"] : null;
$status = array_key_exists("status", $_POST) ? $_POST["status"] : null;
if (!isValidWeekNum($week)) $week = date("oW");
if($status && !in_array($status, ["Not Created", "Created", "Submitted", "Approved", "Rejected"])) jsonResponse(407, ["msg" => "Invalid parameter."]);
/* ---------- params ---------- */
$page = array_key_exists("page", $_POST) ? (int)$_POST["page"] : 1;
$limit = array_key_exists("limit", $_POST) ? (int)$_POST["limit"] : 10;
if ($page < 1)  $page = 1;
if ($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;
/* ---------- params ---------- */
$timeCards = $db->all(
    "SELECT `week` FROM `timeCard` GROUP BY `week` ORDER BY `week` DESC;",
    [], __FILE__, __LINE__
);
$weeks = array_column($timeCards, "week");
if ($week && !in_array($week, $weeks)) {
    array_unshift($weeks, $week);
}
$dateStart = getDateFromWeekNum($week, 0);
$dateEnd = getDateFromWeekNum($week, 6);
$allUsers = [];
if(!$status || $status === "Not Created"){
    $allUsers = $db->all(
        "SELECT 
            `users`.`id` AS `userId`,
            CONCAT_WS(\" \", `users`.`firstName`, `users`.`middleName`, `users`.`lastName`) AS `userName`
        FROM `users`
        WHERE `users`.`office` = ? AND `users`.`hireDate` <= ? AND (`users`.`quitDate` IS NULL OR `users`.`quitDate` >= ?)
        ORDER BY `userName` ASC;",
        ["yes", $dateEnd, $dateStart], __FILE__, __LINE__
    );
}
$whereSql = "";
$params = [];
if($status && $status !== "Not Created"){
    $whereSql = " AND JSON_UNQUOTE(JSON_EXTRACT(`timeCard`.`data`, '$.status')) = ?";
    $params[] = $status;
}
$timeCards = $db->all(
    "SELECT 
        `timeCard`.`userId`,
        CONCAT_WS(\" \", `users`.`firstName`, `users`.`middleName`, `users`.`lastName`) AS `userName`, 
        JSON_UNQUOTE(JSON_EXTRACT(`timeCard`.`data`, '$.status')) AS `status`
    FROM `timeCard`
    LEFT JOIN `users` ON `users`.`id` = `timeCard`.`userId`
    WHERE `timeCard`.`week` = ?$whereSql
    ORDER BY `userName` ASC;",
    [$week, ...$params], __FILE__, __LINE__
);
$userIds = array_column($timeCards, "userId");
if(!$status){
    foreach($allUsers as $user){
        if(!in_array($user["userId"], $userIds)){
            $user["status"] = "Not Created";
            $timeCards[] = $user;
        }
    }
}
if($status === "Not Created"){
    foreach($timeCards as $timeCard){
        $allUsers = array_values(array_filter(
            $allUsers,
            fn($user) => $user["userId"] !== $timeCard["userId"]
        ));
    }
    foreach($allUsers as &$user){
        $user["status"] = "Not Created";
    }
    $timeCards = $allUsers;
}
/* ---------- count ---------- */
$total = count($timeCards);
/* ---------- page overflow guard ---------- */
$maxPage = max(1, (int)ceil($total / $limit));
if ($page > $maxPage) {
  $page = $maxPage;
  $offset = ($page - 1) * $limit;
}
/* ---------- slicing data ---------- */
$pagedTimeCards = array_slice($timeCards, $offset, $limit);
/* ---------- output data ---------- */
exit(json_encode([
    "timeCards" => $pagedTimeCards,
    "week"      => $week,
    "weeks"     => $weeks,
    "page"     => $page, // not used yet
    "limit"    => $limit, // not used yet
    "total"    => $total,
]));