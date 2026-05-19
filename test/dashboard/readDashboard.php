<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$start = trim((string)($_POST['start'] ?? ''));
$end = trim((string)($_POST['end'] ?? ''));
if (!$start || !$end) {
    http_response_code(400);
    exit(json_encode(['msg' => 'Missing start/end.']));
}
$startDate = DateTime::createFromFormat('Y-m-d', $start);
$endDate = DateTime::createFromFormat('Y-m-d', $end);
if (!$startDate || !$endDate || $startDate->format('Y-m-d') !== $start || $endDate->format('Y-m-d') !== $end) {
    http_response_code(422);
    exit(json_encode(['msg' => 'Invalid start/end.']));
}
if ($start > $end) {
    http_response_code(422);
    exit(json_encode(['msg' => 'Start cannot be after end.']));
}
$endDateTime = $end . ' 23:59:59';

function countQuery($db, string $sql, array $params): int {
    return (int)($db->one($sql, $params, __FILE__, __LINE__)['total'] ?? 0);
}
function bucketQuery($db, string $sql, array $params, bool $hasTotal = false): array {
    $rows = $db->all($sql, $params, __FILE__, __LINE__);
    return array_map(function($row) use ($hasTotal) {
        $out = [
            'label' => (string)($row['label'] ?? ''),
            'count' => (int)($row['count'] ?? 0),
        ];
        if ($hasTotal) $out['total'] = (float)($row['total'] ?? 0);
        return $out;
    }, $rows ?: []);
}
$projectLabel = "CONCAT_WS(' - ', NULLIF(TRIM(`p`.`projectNumber`), ''), NULLIF(TRIM(`o`.`name`), ''), NULLIF(TRIM(`p`.`clientProjectNumber`), ''))";

$summary = [
    'projectsCreated' => countQuery($db, "SELECT COUNT(*) AS `total` FROM `projects` WHERE `void` = 'no' AND `createdAt` BETWEEN ? AND ?", [$start . ' 00:00:00', $endDateTime]),
    'worksCreated' => countQuery($db, "SELECT COUNT(*) AS `total` FROM `works` WHERE `void` = 'no' AND `createdAt` BETWEEN ? AND ?", [$start . ' 00:00:00', $endDateTime]),
    'purchasesCreated' => countQuery($db, "SELECT COUNT(*) AS `total` FROM `purchases` WHERE `void` = 'no' AND COALESCE(`submitTime`, `createdAt`) BETWEEN ? AND ?", [$start . ' 00:00:00', $endDateTime]),
    'purchasesTotal' => (float)($db->one("SELECT COALESCE(SUM(`total`), 0) AS `total` FROM `purchases` WHERE `void` = 'no' AND COALESCE(`submitTime`, `createdAt`) BETWEEN ? AND ?", [$start . ' 00:00:00', $endDateTime], __FILE__, __LINE__)['total'] ?? 0),
    'reportsCreated' => countQuery($db, "SELECT COUNT(*) AS `total` FROM `reports` WHERE `void` = 'no' AND `createdAt` BETWEEN ? AND ?", [$start . ' 00:00:00', $endDateTime]),
    'rentalsCreated' => countQuery($db, "SELECT COUNT(*) AS `total` FROM `rental_statuses` WHERE `void` = 'no' AND `createdAt` BETWEEN ? AND ?", [$start . ' 00:00:00', $endDateTime]),
    'activeRentals' => countQuery($db, "SELECT COUNT(*) AS `total` FROM `rental_statuses` WHERE `void` = 'no' AND `status` = 'rented'", []),
    'timeOffsCreated' => countQuery($db, "SELECT COUNT(*) AS `total` FROM `timeOffs` WHERE `void` = 'no' AND `createdAt` BETWEEN ? AND ?", [$start . ' 00:00:00', $endDateTime]),
    'perDiemsCreated' => countQuery($db, "SELECT COUNT(*) AS `total` FROM `perDiems` WHERE `void` = 'no' AND `createdAt` BETWEEN ? AND ?", [$start . ' 00:00:00', $endDateTime]),
];

$projectPipelines = bucketQuery($db,
    "SELECT COALESCE(NULLIF(TRIM(`pipeline`), ''), 'Unspecified') AS `label`, COUNT(*) AS `count`
     FROM `projects`
     WHERE `void` = 'no' AND `createdAt` BETWEEN ? AND ?
     GROUP BY `label`
     ORDER BY `count` DESC, `label` ASC",
    [$start . ' 00:00:00', $endDateTime]
);
$projectStages = bucketQuery($db,
    "SELECT COALESCE(NULLIF(TRIM(`stage`), ''), 'Unspecified') AS `label`, COUNT(*) AS `count`
     FROM `projects`
     WHERE `void` = 'no' AND `createdAt` BETWEEN ? AND ?
     GROUP BY `label`
     ORDER BY `count` DESC, `label` ASC",
    [$start . ' 00:00:00', $endDateTime]
);
$workCategories = bucketQuery($db,
    "SELECT COALESCE(NULLIF(TRIM(`category`), ''), 'Unspecified') AS `label`, COUNT(*) AS `count`
     FROM `works`
     WHERE `void` = 'no' AND `createdAt` BETWEEN ? AND ?
     GROUP BY `label`
     ORDER BY `count` DESC, `label` ASC",
    [$start . ' 00:00:00', $endDateTime]
);
$purchaseCategories = bucketQuery($db,
    "SELECT COALESCE(NULLIF(TRIM(`category`), ''), 'Unspecified') AS `label`, COUNT(*) AS `count`, COALESCE(SUM(`total`), 0) AS `total`
     FROM `purchases`
     WHERE `void` = 'no' AND COALESCE(`submitTime`, `createdAt`) BETWEEN ? AND ?
     GROUP BY `label`
     ORDER BY `count` DESC, `label` ASC",
    [$start . ' 00:00:00', $endDateTime],
    true
);
$purchaseDepartments = bucketQuery($db,
    "SELECT COALESCE(NULLIF(TRIM(`department`), ''), 'Unspecified') AS `label`, COUNT(*) AS `count`, COALESCE(SUM(`total`), 0) AS `total`
     FROM `purchases`
     WHERE `void` = 'no' AND COALESCE(`submitTime`, `createdAt`) BETWEEN ? AND ?
     GROUP BY `label`
     ORDER BY `count` DESC, `label` ASC",
    [$start . ' 00:00:00', $endDateTime],
    true
);
$rentalStatuses = bucketQuery($db,
    "SELECT `status` AS `label`, COUNT(*) AS `count`
     FROM `rental_statuses`
     WHERE `void` = 'no' AND `createdAt` BETWEEN ? AND ?
     GROUP BY `status`
     ORDER BY `count` DESC, `label` ASC",
    [$start . ' 00:00:00', $endDateTime]
);
$reportStatuses = bucketQuery($db,
    "SELECT COALESCE(NULLIF(TRIM(`status`), ''), 'Unspecified') AS `label`, COUNT(*) AS `count`
     FROM `reports`
     WHERE `void` = 'no' AND `createdAt` BETWEEN ? AND ?
     GROUP BY `label`
     ORDER BY `count` DESC, `label` ASC",
    [$start . ' 00:00:00', $endDateTime]
);
$recentPurchases = $db->all(
    "SELECT `pu`.`id`, `pu`.`poNumber`, $projectLabel AS `projectName`, `pu`.`total`, `pu`.`status`
     FROM `purchases` `pu`
     LEFT JOIN `projects` `p` ON `p`.`id` = `pu`.`projectId`
     LEFT JOIN `organizations` `o` ON `o`.`id` = `p`.`organizationId`
     WHERE `pu`.`void` = 'no' AND COALESCE(`pu`.`submitTime`, `pu`.`createdAt`) BETWEEN ? AND ?
     ORDER BY COALESCE(`pu`.`submitTime`, `pu`.`createdAt`) DESC
     LIMIT 10",
    [$start . ' 00:00:00', $endDateTime],
    __FILE__,
    __LINE__
) ?: [];
$recentRentals = $db->all(
    "SELECT `r`.`id`, `r`.`equipmentName`, $projectLabel AS `projectName`, CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `renterName`, `r`.`status`, `r`.`rentalStartDate`, `r`.`rentalReturnDate`
     FROM `rental_statuses` `r`
     LEFT JOIN `projects` `p` ON `p`.`id` = `r`.`projectId`
     LEFT JOIN `organizations` `o` ON `o`.`id` = `p`.`organizationId`
     LEFT JOIN `users` `u` ON `u`.`id` = `r`.`renterId`
     WHERE `r`.`void` = 'no' AND `r`.`createdAt` BETWEEN ? AND ?
     ORDER BY `r`.`createdAt` DESC
     LIMIT 10",
    [$start . ' 00:00:00', $endDateTime],
    __FILE__,
    __LINE__
) ?: [];

exit(json_encode([
    'start' => $start,
    'end' => $end,
    'summary' => $summary,
    'projectPipelines' => $projectPipelines,
    'projectStages' => $projectStages,
    'workCategories' => $workCategories,
    'purchaseCategories' => $purchaseCategories,
    'purchaseDepartments' => $purchaseDepartments,
    'rentalStatuses' => $rentalStatuses,
    'reportStatuses' => $reportStatuses,
    'recentPurchases' => $recentPurchases,
    'recentRentals' => $recentRentals,
]));
