<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$weekStart = requireDate($_POST, "weekStart", true);
$weekEnd = requireDate($_POST, "weekEnd", true);
$emptyDateTime = "1900-01-01 00:00:00";

$range = $db->one(
    "SELECT
        MIN(DATE(COALESCE(`a`.`travelStartTime`, `w`.`startTime`))) AS `minTime`,
        MAX(DATE(COALESCE(`a`.`travelStartTime`, `w`.`startTime`))) AS `maxTime`
     FROM `assignments` `a`
     JOIN `works` `w` ON `w`.`id` = `a`.`workId`
     WHERE `a`.`void` = 'no'
       AND `w`.`void` = 'no';",
    [],
    __FILE__,
    __LINE__
) ?: [];

$userList = $db->all(
    "SELECT
        `email`,
        `id`,
        CONCAT_WS(' ', `firstName`, `middleName`, `lastName`) AS `userName`,
        `department`,
        `region`,
        `unionName`,
        `role`
     FROM `users`
     WHERE `void` = 'no'
     ORDER BY `userName`;",
    [],
    __FILE__,
    __LINE__
);

$labors = $db->all(
    "SELECT
        `a`.`id`,
        CAST(`p`.`id` AS CHAR) AS `projectId`,
        `p`.`projectNumber`,
        `org`.`name` AS `organizationName`,
        COALESCE(`p`.`clientProjectNumber`, '') AS `clientProjectNumber`,
        `a`.`status` AS `AssignmentStatus`,
        `a`.`jobTagStatus`,
        'No' AS `billed`,
        `u`.`email` AS `labor`,
        `a`.`userId`,
        CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `userName`,
        `u`.`department`,
        `u`.`region`,
        COALESCE(`u`.`unionName`, '') AS `unionName`,
        `u`.`role`,
        `a`.`laborCategory`,
        COALESCE(`postTruck`.`truckNumber`, `preTruck`.`truckNumber`, `a`.`fleetNumber`) AS `truckNumber`,
        `a`.`coords`,
        CASE WHEN `a`.`isPreDriver` = 'yes' THEN 'Yes' ELSE 'No' END AS `preDriver`,
        CASE WHEN `a`.`isPostDriver` = 'yes' THEN 'Yes' ELSE 'No' END AS `postDriver`,
        CASE WHEN `a`.`hadLunch` = 'yes' THEN 'Yes' ELSE 'No' END AS `hadLunch`,
        COALESCE(`a`.`travelStartTime`, ?) AS `travelStartTime`,
        COALESCE(`a`.`workStartTime`, ?) AS `workStartTime`,
        COALESCE(`a`.`workEndTime`, ?) AS `workEndTime`,
        COALESCE(`a`.`travelEndTime`, ?) AS `travelEndTime`,
        COALESCE(`a`.`travelStartTime`, `w`.`startTime`) AS `startTime`,
        COALESCE(`a`.`travelEndTime`, `w`.`endTime`) AS `endTime`
     FROM `assignments` `a`
     JOIN `works` `w` ON `w`.`id` = `a`.`workId`
     JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
     LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
     LEFT JOIN `users` `u` ON `u`.`id` = `a`.`userId`
     LEFT JOIN `fleets` `preTruck` ON `preTruck`.`id` = `a`.`preTruckId`
     LEFT JOIN `fleets` `postTruck` ON `postTruck`.`id` = `a`.`postTruckId`
     WHERE `a`.`void` = 'no'
       AND `w`.`void` = 'no'
       AND DATE(COALESCE(`a`.`travelStartTime`, `w`.`startTime`)) >= ?
       AND DATE(COALESCE(`a`.`travelStartTime`, `w`.`startTime`)) < ?
     ORDER BY `u`.`lastName`, `u`.`firstName`, COALESCE(`a`.`travelStartTime`, `w`.`startTime`);",
    [$emptyDateTime, $emptyDateTime, $emptyDateTime, $emptyDateTime, $weekStart, $weekEnd],
    __FILE__,
    __LINE__
);

exit(json_encode([
    "minTime" => $range["minTime"] ?? $weekStart,
    "maxTime" => $range["maxTime"] ?? $weekEnd,
    "userList" => $userList,
    "labors" => $labors,
]));
