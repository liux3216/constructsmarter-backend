<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$selectedDate = requireDate($_POST, "selectedDate", true);

$rows = $db->all(
    "SELECT
        CAST(`w`.`id` AS CHAR) AS `workId`,
        CAST(`p`.`id` AS CHAR) AS `projectId`,
        CAST(`a`.`id` AS CHAR) AS `assignmentId`,
        `p`.`projectNumber`,
        `org`.`name` AS `organizationName`,
        COALESCE(`p`.`clientProjectNumber`, '') AS `clientProjectNumber`,
        COALESCE(`w`.`coords`, `p`.`coords`, '') AS `coords`,
        `w`.`category`,
        `w`.`location`,
        `w`.`allDay`,
        `w`.`startTime`,
        `w`.`endTime`,
        CAST(`p`.`projectManagerId` AS CHAR) AS `projectManagerId`,
        CONCAT_WS(' ', `projectManager`.`firstName`, `projectManager`.`middleName`, `projectManager`.`lastName`) AS `projectManager`,
        CAST(`p`.`creatorId` AS CHAR) AS `projectCreatorId`,
        CONCAT_WS(' ', `projectCreator`.`firstName`, `projectCreator`.`middleName`, `projectCreator`.`lastName`) AS `projectCreator`,
        CAST(`w`.`creatorId` AS CHAR) AS `workCreatorId`,
        CONCAT_WS(' ', `workCreator`.`firstName`, `workCreator`.`middleName`, `workCreator`.`lastName`) AS `workCreator`,
        CAST(`w`.`updaterId` AS CHAR) AS `workUpdaterId`,
        CONCAT_WS(' ', `workUpdater`.`firstName`, `workUpdater`.`middleName`, `workUpdater`.`lastName`) AS `workUpdater`,
        CAST(`a`.`userId` AS CHAR) AS `userId`,
        CONCAT_WS(' ', `assignedUser`.`firstName`, `assignedUser`.`middleName`, `assignedUser`.`lastName`) AS `userName`
     FROM `works` `w`
     JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
     LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
     LEFT JOIN `assignments` `a` ON `a`.`workId` = `w`.`id` AND `a`.`void` = 'no'
     LEFT JOIN `users` `assignedUser` ON `assignedUser`.`id` = `a`.`userId`
     LEFT JOIN `users` `projectManager` ON `projectManager`.`id` = `p`.`projectManagerId`
     LEFT JOIN `users` `projectCreator` ON `projectCreator`.`id` = `p`.`creatorId`
     LEFT JOIN `users` `workCreator` ON `workCreator`.`id` = `w`.`creatorId`
     LEFT JOIN `users` `workUpdater` ON `workUpdater`.`id` = `w`.`updaterId`
     WHERE `w`.`void` = 'no'
       AND `p`.`void` = 'no'
       AND DATE(`w`.`startTime`) <= ?
       AND DATE(`w`.`endTime`) >= ?
     ORDER BY `w`.`startTime`, `p`.`projectNumber`, `assignedUser`.`lastName`, `assignedUser`.`firstName`",
    [$selectedDate, $selectedDate],
    __FILE__,
    __LINE__
);

$works = [];
$seenAssignments = [];

foreach ($rows as $row) {
    $workId = (string)($row["workId"] ?? "");
    if ($workId === "") {
        continue;
    }

    if (!isset($works[$workId])) {
        $works[$workId] = [
            "workId" => $workId,
            "projectId" => (string)($row["projectId"] ?? ""),
            "projectNumber" => (string)($row["projectNumber"] ?? ""),
            "organizationName" => (string)($row["organizationName"] ?? ""),
            "clientProjectNumber" => (string)($row["clientProjectNumber"] ?? ""),
            "coords" => (string)($row["coords"] ?? ""),
            "category" => (string)($row["category"] ?? ""),
            "location" => (string)($row["location"] ?? ""),
            "allDay" => (string)($row["allDay"] ?? "no"),
            "startTime" => (string)($row["startTime"] ?? ""),
            "endTime" => (string)($row["endTime"] ?? ""),
            "projectManagerId" => (string)($row["projectManagerId"] ?? ""),
            "projectManager" => (string)($row["projectManager"] ?? ""),
            "projectCreatorId" => (string)($row["projectCreatorId"] ?? ""),
            "projectCreator" => (string)($row["projectCreator"] ?? ""),
            "workCreatorId" => (string)($row["workCreatorId"] ?? ""),
            "workCreator" => (string)($row["workCreator"] ?? ""),
            "workUpdaterId" => (string)($row["workUpdaterId"] ?? ""),
            "workUpdater" => (string)($row["workUpdater"] ?? ""),
            "assignments" => [],
        ];
        $seenAssignments[$workId] = [];
    }

    $assignmentId = trim((string)($row["assignmentId"] ?? ""));
    $userId = trim((string)($row["userId"] ?? ""));
    $userName = trim((string)($row["userName"] ?? ""));

    if ($assignmentId === "" && $userId === "" && $userName === "") {
        continue;
    }

    $assignmentKey = $assignmentId !== "" ? $assignmentId : $workId . "-" . $userId;
    if (isset($seenAssignments[$workId][$assignmentKey])) {
        continue;
    }

    $works[$workId]["assignments"][] = [
        "assignmentId" => $assignmentId !== "" ? $assignmentId : null,
        "workId" => $workId,
        "userId" => $userId,
        "userName" => $userName,
    ];

    $seenAssignments[$workId][$assignmentKey] = true;
}

exit(json_encode(array_values($works)));
