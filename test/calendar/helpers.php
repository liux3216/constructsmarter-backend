<?php
function getCalendarAccessScope(DB $db, string $userId, string $file, int $line): array {
    $accessRow = $db->one(
        "SELECT `calendar` FROM `users` WHERE `id` = ? LIMIT 1;",
        [$userId],
        $file,
        $line
    );
    $calendarAccess = (string)($accessRow["calendar"] ?? "no");
    return [
        "access" => $calendarAccess,
        "scope" => $calendarAccess === "all" ? "all" : "self",
    ];
}

function readCalendarAssignments(DB $db, string $userId, string $rangeStart, string $rangeEnd, string $file, int $line): array {
    $scope = getCalendarAccessScope($db, $userId, $file, $line);
    $params = [$rangeStart, $rangeEnd];
    $userFilterSql = "";
    if($scope["access"] !== "all"){
        $userFilterSql = " AND `a`.`userId` = ?";
        $params[] = $userId;
    }

    return $db->all(
        "SELECT
            CAST(`p`.`id` AS CHAR) AS `projectId`,
            `p`.`projectNumber`,
            `org`.`name` AS `organizationName`,
            `p`.`clientProjectNumber` AS `clientProjectNumber`,
            COALESCE(`p`.`pipeline`, '') AS `category`,
            CAST(`w`.`id` AS CHAR) AS `workId`,
            CAST(`a`.`id` AS CHAR) AS `assignmentId`,
            CAST(`a`.`userId` AS CHAR) AS `userId`,
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
         LEFT JOIN `fleets` `postTruck` ON `postTruck`.`id` = `a`.`postTruckId`
         WHERE `a`.`void` = 'no'
           AND `w`.`void` = 'no'
           AND DATE(`w`.`startTime`) >= ?
           AND DATE(`w`.`endTime`) <= ?" . $userFilterSql . "
         ORDER BY `w`.`startTime` ASC, `a`.`id` ASC;",
        $params,
        $file,
        $line
    );
}
