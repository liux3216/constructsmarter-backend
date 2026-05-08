<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$page = array_key_exists("page", $_POST) ? (int)$_POST["page"] : 1;
$limit = array_key_exists("limit", $_POST) ? (int)$_POST["limit"] : 10;
if ($page < 1) $page = 1;
if ($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;

$currentUser = $db->one(
    "SELECT `projects` FROM `users` WHERE `id` = ? LIMIT 1;",
    [$userId],
    __FILE__,
    __LINE__
);
$projectsPermission = $currentUser["projects"] ?? "no";

$fromSql = "FROM `assignments` `a`
LEFT JOIN `works` `w` ON `w`.`id` = `a`.`workId`
LEFT JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
LEFT JOIN `users` `assignedUser` ON `assignedUser`.`id` = `a`.`userId`
WHERE `a`.`void` = 'no'
  AND `a`.`status` = 'Submitted'
  AND (`w`.`supervisorId` = ? OR `p`.`projectManagerId` = ? OR ? <> 'no')";
$params = [$userId, $userId, $projectsPermission];

$totalRow = $db->one(
    "SELECT COUNT(*) AS `total` $fromSql;",
    $params,
    __FILE__,
    __LINE__
);
$total = (int)($totalRow["total"] ?? 0);
$maxPage = max(1, (int)ceil($total / $limit));
if ($page > $maxPage) {
    $page = $maxPage;
    $offset = ($page - 1) * $limit;
}

$approvals = $db->all(
    "SELECT
        CAST(`a`.`id` AS CHAR) AS `id`,
        CAST(`a`.`workId` AS CHAR) AS `workId`,
        CAST(`w`.`projectId` AS CHAR) AS `projectId`,
        CONCAT_WS(' - ',
            NULLIF(TRIM(`p`.`projectNumber`), ''),
            NULLIF(TRIM(`org`.`name`), ''),
            NULLIF(TRIM(`p`.`clientProjectNumber`), '')
        ) AS `projectName`,
        `p`.`pipeline` AS `projectCategory`,
        `w`.`category` AS `workCategory`,
        `a`.`laborCategory`,
        `a`.`userId`,
        CONCAT_WS(' ', `assignedUser`.`firstName`, `assignedUser`.`middleName`, `assignedUser`.`lastName`) AS `userName`,
        `assignedUser`.`department`,
        COALESCE(`a`.`updatedAt`, `a`.`createdAt`) AS `submitTime`,
        `w`.`startTime`,
        `w`.`endTime`,
        `w`.`allDay`
    $fromSql
    ORDER BY COALESCE(`a`.`updatedAt`, `a`.`createdAt`) DESC
    LIMIT $limit OFFSET $offset;",
    $params,
    __FILE__,
    __LINE__
);

exit(json_encode([
    "approvals" => $approvals,
    "page" => $page,
    "limit" => $limit,
    "total" => $total,
]));
