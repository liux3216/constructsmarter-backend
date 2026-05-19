<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

function parseMonthRange(string $month): array {
    $date = DateTime::createFromFormat("Y-m-d", $month . "-01");
    if (!$date) {
        $date = new DateTime("first day of this month");
    }
    return [$date->format("Y-m-01"), $date->format("Y-m-t")];
}

function parseWeekRange(string $week): array {
    if (!preg_match('/^w(\d{4})-(\d{2})$/', $week, $matches)) {
        $today = new DateTime("today");
        $today->modify("monday this week");
        $start = $today->format("Y-m-d");
        $end = $today->modify("+6 days")->format("Y-m-d");
        return [$start, $end];
    }
    $date = new DateTime();
    $date->setISODate((int)$matches[1], (int)$matches[2], 1);
    $start = $date->format("Y-m-d");
    $end = $date->modify("+6 days")->format("Y-m-d");
    return [$start, $end];
}

function normalizeFleetNumbers($value): array {
    if (!is_array($value)) return [];
    $result = [];
    foreach ($value as $item) {
        $item = trim((string)$item);
        if ($item !== "") $result[] = $item;
    }
    return array_values(array_unique($result));
}

$mode = ($_POST["mode"] ?? "Month") === "Week" ? "Week" : "Month";
$month = trim((string)($_POST["month"] ?? date("Y-m")));
$week = trim((string)($_POST["week"] ?? ""));
[$start, $end] = $mode === "Week" ? parseWeekRange($week) : parseMonthRange($month);
$selectedFleetNumbers = normalizeFleetNumbers($_POST["fleetNumber"] ?? []);
$selectedTrendFleetNumbers = normalizeFleetNumbers($_POST["trendFleetNumber"] ?? []);

$fleetCatalogRows = $db->all(
    "SELECT `truckNumber`
    FROM `fleets`
    WHERE `void` = 'no'
    ORDER BY `truckNumber` ASC;",
    [],
    __FILE__,
    __LINE__
);

$fleetNumbers = array_values(array_filter(array_map(
    fn($row) => $row["truckNumber"],
    $fleetCatalogRows
)));

if (!$selectedFleetNumbers) {
    $selectedFleetNumbers = array_slice($fleetNumbers, 0, 5);
}
if (!$selectedTrendFleetNumbers) {
    $selectedTrendFleetNumbers = array_slice($selectedFleetNumbers ?: $fleetNumbers, 0, 4);
}

$usageBaseSql = "
    SELECT 
        `a`.`id` AS `assignmentId`,
        `a`.`workId`,
        `a`.`status` AS `assignmentStatus`,
        `w`.`startTime`,
        `w`.`category`,
        `a`.`userId`,
        `w`.`projectId`,
        `f`.`truckNumber` AS `fleetNumber`,
        'Pre' AS `formSource`
    FROM `assignments` `a`
    LEFT JOIN `works` `w` ON `w`.`id` = `a`.`workId`
    LEFT JOIN `fleets` `f` ON `f`.`id` = `a`.`preTruckId`
    WHERE `a`.`void` = 'no'
        AND `w`.`void` = 'no'
        AND `a`.`preTruckId` IS NOT NULL
        AND `f`.`truckNumber` IS NOT NULL

    UNION ALL

    SELECT 
        `a`.`id` AS `assignmentId`,
        `a`.`workId`,
        `a`.`status` AS `assignmentStatus`,
        `w`.`startTime`,
        `w`.`category`,
        `a`.`userId`,
        `w`.`projectId`,
        `f`.`truckNumber` AS `fleetNumber`,
        'Post' AS `formSource`
    FROM `assignments` `a`
    LEFT JOIN `works` `w` ON `w`.`id` = `a`.`workId`
    LEFT JOIN `fleets` `f` ON `f`.`id` = `a`.`postTruckId`
    WHERE `a`.`void` = 'no'
        AND `w`.`void` = 'no'
        AND `a`.`postTruckId` IS NOT NULL
        AND `f`.`truckNumber` IS NOT NULL
";

$usageCounts = [];
$recentUsages = [];
if ($selectedFleetNumbers) {
    $placeholders = implode(",", array_fill(0, count($selectedFleetNumbers), "?"));
    $usageCounts = $db->all(
        "SELECT `fleetNumber`, COUNT(DISTINCT CONCAT(`assignmentId`, ':', `fleetNumber`)) AS `count`
        FROM ($usageBaseSql) `u`
        WHERE `fleetNumber` IN ($placeholders)
            AND DATE(`startTime`) BETWEEN ? AND ?
        GROUP BY `fleetNumber`
        ORDER BY FIELD(`fleetNumber`, $placeholders);",
        array_merge($selectedFleetNumbers, [$start, $end], $selectedFleetNumbers),
        __FILE__,
        __LINE__
    );

    $recentUsages = $db->all(
        "SELECT 
            CAST(`u`.`assignmentId` AS CHAR) AS `assignmentId`,
            CAST(`u`.`workId` AS CHAR) AS `workId`,
            `u`.`fleetNumber`,
            `u`.`startTime`,
            CONCAT_WS(' ', `usr`.`firstName`, `usr`.`middleName`, `usr`.`lastName`) AS `technicianName`,
            CONCAT_WS(' - ', `p`.`projectNumber`, `o`.`name`, `p`.`clientProjectNumber`) AS `projectName`,
            `u`.`category`,
            `u`.`assignmentStatus`,
            `u`.`formSource`
        FROM ($usageBaseSql) `u`
        LEFT JOIN `users` `usr` ON `usr`.`id` = `u`.`userId`
        LEFT JOIN `projects` `p` ON `p`.`id` = `u`.`projectId`
        LEFT JOIN `organizations` `o` ON `o`.`id` = `p`.`organizationId`
        WHERE `u`.`fleetNumber` IN ($placeholders)
            AND DATE(`u`.`startTime`) BETWEEN ? AND ?
        GROUP BY `u`.`assignmentId`, `u`.`fleetNumber`, `u`.`formSource`
        ORDER BY `u`.`startTime` DESC, `u`.`assignmentId` DESC
        LIMIT 50;",
        array_merge($selectedFleetNumbers, [$start, $end]),
        __FILE__,
        __LINE__
    );
}

$monthlyTrend = [];
if ($selectedTrendFleetNumbers) {
    $placeholders = implode(",", array_fill(0, count($selectedTrendFleetNumbers), "?"));
    $monthlyTrend = $db->all(
        "SELECT 
            `fleetNumber`,
            DATE_FORMAT(`startTime`, '%Y-%m') AS `month`,
            COUNT(DISTINCT CONCAT(`assignmentId`, ':', `fleetNumber`)) AS `count`
        FROM ($usageBaseSql) `u`
        WHERE `fleetNumber` IN ($placeholders)
        GROUP BY `fleetNumber`, DATE_FORMAT(`startTime`, '%Y-%m')
        ORDER BY `month` ASC;",
        $selectedTrendFleetNumbers,
        __FILE__,
        __LINE__
    );
}

$summary = $db->one(
    "SELECT
        COUNT(*) AS `totalFleetCount`,
        SUM(CASE WHEN `void` = 'no' THEN 1 ELSE 0 END) AS `activeFleetCount`,
        SUM(CASE WHEN `void` = 'no' AND `fleetType` = 'truck' THEN 1 ELSE 0 END) AS `truckCount`,
        SUM(CASE WHEN `void` = 'no' AND `fleetType` = 'trailer' THEN 1 ELSE 0 END) AS `trailerCount`,
        SUM(CASE WHEN `void` = 'no' AND `isHotPatch` = 'yes' THEN 1 ELSE 0 END) AS `hotPatchCount`
    FROM `fleets`;",
    [],
    __FILE__,
    __LINE__
);

$usedFleetRow = $db->one(
    "SELECT COUNT(DISTINCT `fleetNumber`) AS `usedFleetCount`
    FROM ($usageBaseSql) `u`
    WHERE DATE(`startTime`) BETWEEN ? AND ?;",
    [$start, $end],
    __FILE__,
    __LINE__
);

$output = [
    "mode" => $mode,
    "month" => $month,
    "week" => $week,
    "start" => $start,
    "end" => $end,
    "fleetOptions" => array_map(fn($fleetNumber) => ["label" => $fleetNumber, "value" => $fleetNumber], $fleetNumbers),
    "selectedFleetNumbers" => $selectedFleetNumbers,
    "selectedTrendFleetNumbers" => $selectedTrendFleetNumbers,
    "summary" => [
        "totalFleetCount" => (int)($summary["totalFleetCount"] ?? 0),
        "activeFleetCount" => (int)($summary["activeFleetCount"] ?? 0),
        "truckCount" => (int)($summary["truckCount"] ?? 0),
        "trailerCount" => (int)($summary["trailerCount"] ?? 0),
        "hotPatchCount" => (int)($summary["hotPatchCount"] ?? 0),
        "usedFleetCount" => (int)($usedFleetRow["usedFleetCount"] ?? 0),
        "selectedUsageCount" => array_sum(array_map(fn($row) => (int)($row["count"] ?? 0), $usageCounts)),
    ],
    "usageCounts" => array_map(fn($row) => [
        "fleetNumber" => $row["fleetNumber"],
        "count" => (int)($row["count"] ?? 0),
    ], $usageCounts),
    "monthlyTrend" => array_map(fn($row) => [
        "fleetNumber" => $row["fleetNumber"],
        "month" => $row["month"],
        "count" => (int)($row["count"] ?? 0),
    ], $monthlyTrend),
    "recentUsages" => $recentUsages,
];

exit(json_encode($output));
