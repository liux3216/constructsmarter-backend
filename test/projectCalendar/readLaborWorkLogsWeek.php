<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$today = date("Y-m-d");

$row = $db->one(
    "SELECT
        COALESCE(MIN(DATE(`w`.`startTime`)), ?) AS `minTime`,
        COALESCE(MAX(DATE(`w`.`startTime`)), ?) AS `maxTime`
     FROM `assignments` `a`
     JOIN `works` `w` ON `w`.`id` = `a`.`workId`
     WHERE `a`.`void` = 'no'
       AND `w`.`void` = 'no';",
    [$today, $today],
    __FILE__,
    __LINE__
);

if (!$row) {
    $row = [
        "minTime" => $today,
        "maxTime" => $today,
    ];
}

exit(json_encode($row));
