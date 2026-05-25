<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = trim((string)($_POST["q"] ?? ""));
$serviceId = requireInt($_POST, "serviceId", 1, null, true);
$startTime = requireField($_POST, "startTime", 1, 32, true);
$endTime = requireField($_POST, "endTime", 1, 32, true);
$workId = requireInt($_POST, "workId", null, null, false);
if(!$serviceId || !$startTime || !$endTime || !$q) exit(json_encode([]));
$start = DateTime::createFromFormat("Y-m-d H:i", $startTime);
$end = DateTime::createFromFormat("Y-m-d H:i", $endTime);
if(!$start || !$end) exit(json_encode([]));
$startSql = $start->format("Y-m-d H:i:s");
$endSql = $end->format("Y-m-d H:i:s");
$params = [$serviceId, "%{$q}%", $startSql, $workId ?? 0, $workId ?? 0, $startSql, $endSql];
$rows = $db->all(
    "SELECT DISTINCT
        `u`.`id` AS `value`,
        CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `label`
    FROM `users` `u`
    INNER JOIN `users_competency` `uc` ON `uc`.`userId` = `u`.`id` AND `uc`.`serviceId` = ?
    WHERE `u`.`void` = 'no'
      AND CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) LIKE ?
      AND (`u`.`quitDate` IS NULL OR `u`.`quitDate` = '' OR `u`.`quitDate` >= ?)
      AND NOT EXISTS (
        SELECT 1
        FROM `assignments` `a`
        INNER JOIN `works` `w` ON `w`.`id` = `a`.`workId`
        WHERE `a`.`userId` = `u`.`id`
          AND `a`.`void` = 'no'
          AND `w`.`void` = 'no'
          AND (? = 0 OR `w`.`id` <> ?)
          AND NOT (`w`.`endTime` < ? OR `w`.`startTime` > ?)
      )
    ORDER BY `label` ASC
    LIMIT 20;",
    $params,
    __FILE__,
    __LINE__
);
exit(json_encode($rows));
