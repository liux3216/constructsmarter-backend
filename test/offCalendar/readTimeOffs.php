<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$start = trim((string)($_POST["start"] ?? ""));
$end = trim((string)($_POST["end"] ?? ""));

if ($start === "" || $end === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing start or end date."]));
}

$rows = $db->all(
    "SELECT
        CAST(`t`.`id` AS CHAR) AS `timeOffId`,
        `t`.`status`,
        `t`.`data`,
        COALESCE(`u`.`email`, '') AS `requesterEmail`,
        COALESCE(CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`), '') AS `requester`,
        COALESCE(`u`.`department`, '') AS `department`
     FROM `timeOffs` `t`
     LEFT JOIN `users` `u` ON `u`.`id` = `t`.`requesterId`
     WHERE `t`.`void` = 'no'
       AND `t`.`fromDate` <= ?
       AND `t`.`toDate` >= ?
     ORDER BY `t`.`fromDate` ASC, `t`.`id` ASC;",
    [$end, $start],
    __FILE__,
    __LINE__
);

exit(json_encode($rows));
