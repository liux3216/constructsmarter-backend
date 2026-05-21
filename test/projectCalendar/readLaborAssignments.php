<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
header("Content-Type: application/json");
$weekStart = requireDate($_POST, "weekStart", true);
$weekEnd = requireDate($_POST, "weekEnd", true);
$emptyDateTime = "0000-00-00 00:00:00";
$rows = $db->all(
    "SELECT
        CAST(`p`.`id` AS CHAR) AS `projectId`,
        `p`.`projectNumber`,
        `org`.`name` AS `organizationName`,
        `p`.`clientProjectNumber` AS `clientProjectNumber`,
        COALESCE(`p`.`pipeline`, '') AS `category`,
        CAST(`w`.`id` AS CHAR) AS `workId`,
        CAST(`a`.`id` AS CHAR) AS `assignmentId`,
        COALESCE(`a`.`status`, 'Created') AS `assignmentStatus`,

        'No' AS `billed`,

        CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `userName`,
        `a`.`laborCategory`,
        `postTruck`.`truckNumber`,
        `a`.`travelStartTime`,
        `a`.`workStartTime`,
        `a`.`workEndTime`,
        `a`.`travelEndTime`,
        `w`.`startTime`,
        `w`.`endTime`
     FROM `assignments` `a`
     JOIN `works` `w` ON `w`.`id` = `a`.`workId`
     JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
     LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
     LEFT JOIN `users` `u` ON `u`.`id` = `a`.`userId`
     LEFT JOIN `fleets` `preTruck` ON `preTruck`.`id` = `a`.`preTruckId`
     LEFT JOIN `fleets` `postTruck` ON `postTruck`.`id` = `a`.`postTruckId`
     WHERE `a`.`void` = 'no'
       AND `w`.`void` = 'no'
       AND DATE(`w`.`startTime`) >= ?
       AND DATE(`w`.`endTime`) < ?
     ORDER BY `p`.`projectNumber`, `a`.`id`;",
    [
        $weekStart,
        $weekEnd,
    ],
    __FILE__,
    __LINE__
);

exit(json_encode($rows));
