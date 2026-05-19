<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$range = $db->one(
    "SELECT
        MIN(DATE(COALESCE(`a`.`travelStartTime`, `w`.`startTime`))) AS `minTime`,
        MAX(DATE(COALESCE(`a`.`travelStartTime`, `w`.`startTime`))) AS `maxTime`
     FROM `assignments` `a`
     JOIN `works` `w` ON `w`.`id` = `a`.`workId`
     WHERE `a`.`void` = 'no'
       AND `w`.`void` = 'no'
       AND `a`.`userId` = ?;",
    [$userId],
    __FILE__,
    __LINE__
) ?: [];

exit(json_encode([
    "minTime" => $range["minTime"] ?? null,
    "maxTime" => $range["maxTime"] ?? null,
]));
