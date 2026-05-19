<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$weekStart = requireDate($_POST, "weekStart", true);
$weekEnd = requireDate($_POST, "weekEnd", true);
$emptyDateTime = "1900-01-01 00:00:00";

$rows = $db->all(
    "SELECT
        CAST(`a`.`id` AS CHAR) AS `id`,
        CAST(`a`.`id` AS CHAR) AS `assignmentId`,
        CAST(`w`.`id` AS CHAR) AS `workId`,
        CAST(`p`.`id` AS CHAR) AS `projectId`,
        CONCAT_WS(' - ',
            NULLIF(TRIM(`p`.`projectNumber`), ''),
            NULLIF(TRIM(`org`.`name`), ''),
            NULLIF(TRIM(`p`.`clientProjectNumber`), '')
        ) AS `projectName`,
        `p`.`projectNumber`,
        `org`.`name` AS `organizationName`,
        COALESCE(`p`.`clientProjectNumber`, '') AS `clientProjectNumber`,
        COALESCE(`a`.`status`, 'Created') AS `AssignmentStatus`,
        `a`.`jobTagStatus`,
        'No' AS `billed`,
        `a`.`userId`,
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
     LEFT JOIN `fleets` `preTruck` ON `preTruck`.`id` = `a`.`preTruckId`
     LEFT JOIN `fleets` `postTruck` ON `postTruck`.`id` = `a`.`postTruckId`
     WHERE `a`.`void` = 'no'
       AND `w`.`void` = 'no'
       AND `a`.`userId` = ?
       AND DATE(COALESCE(`a`.`travelStartTime`, `w`.`startTime`)) >= ?
       AND DATE(COALESCE(`a`.`travelStartTime`, `w`.`startTime`)) < ?
     ORDER BY COALESCE(`a`.`travelStartTime`, `w`.`startTime`), `a`.`id`;",
    [$emptyDateTime, $emptyDateTime, $emptyDateTime, $emptyDateTime, $userId, $weekStart, $weekEnd],
    __FILE__,
    __LINE__
);

exit(json_encode($rows));
