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

$mode = ($_POST["mode"] ?? "Month") === "Week" ? "Week" : "Month";
$month = trim((string)($_POST["month"] ?? date("Y-m")));
$week = trim((string)($_POST["week"] ?? ""));
[$start, $end] = $mode === "Week" ? parseWeekRange($week) : parseMonthRange($month);

$departmentRows = $db->all(
    "SELECT DISTINCT `department`
    FROM `users`
    WHERE `void` = 'no'
        AND `department` IS NOT NULL
        AND `department` <> ''
    ORDER BY `department` ASC;",
    [],
    __FILE__,
    __LINE__
);
$departmentOptions = array_map(fn($row) => ["label" => $row["department"], "value" => $row["department"]], $departmentRows);
$department = trim((string)($_POST["department"] ?? ($departmentOptions[0]["value"] ?? "")));

$workedRows = [];
if ($department !== "") {
    $workedRows = $db->all(
        "SELECT DATE(`w`.`startTime`) AS `date`, COUNT(DISTINCT `a`.`userId`) AS `worked`
        FROM `assignments` `a`
        LEFT JOIN `works` `w` ON `w`.`id` = `a`.`workId`
        LEFT JOIN `users` `u` ON `u`.`id` = `a`.`userId`
        WHERE `a`.`void` = 'no'
            AND `w`.`void` = 'no'
            AND `u`.`void` = 'no'
            AND `u`.`department` = ?
            AND DATE(`w`.`startTime`) BETWEEN ? AND ?
        GROUP BY DATE(`w`.`startTime`)
        ORDER BY `date` ASC;",
        [$department, $start, $end],
        __FILE__,
        __LINE__
    );
}

$workedMap = [];
foreach ($workedRows as $row) {
    $workedMap[$row["date"]] = (int)$row["worked"];
}

$points = [];
$averageWorked = 0;
$averageTotal = 0;
$averageUtilization = 0;
$totalAssignments = 0;
$maxWorked = 0;
if ($department !== "") {
    $current = new DateTime($start);
    $endDate = new DateTime($end);
    while ($current <= $endDate) {
        $date = $current->format("Y-m-d");
        $totalRow = $db->one(
            "SELECT COUNT(*) AS `total`
            FROM `users`
            WHERE `void` = 'no'
                AND `department` = ?
                AND (`hireDate` IS NULL OR `hireDate` < ?)
                AND (`quitDate` IS NULL OR `quitDate` > ?);",
            [$department, $date, $date],
            __FILE__,
            __LINE__
        );
        $worked = $workedMap[$date] ?? 0;
        $total = (int)($totalRow["total"] ?? 0);
        $utilization = $total > 0 ? $worked / $total : 0;
        $points[] = [
            "date" => $date,
            "worked" => $worked,
            "total" => $total,
            "utilization" => $utilization,
        ];
        $averageWorked += $worked;
        $averageTotal += $total;
        $averageUtilization += $utilization;
        $totalAssignments += $worked;
        if ($worked > $maxWorked) $maxWorked = $worked;
        $current->modify("+1 day");
    }
}

$pointCount = count($points) ?: 1;

$output = [
    "mode" => $mode,
    "month" => $month,
    "week" => $week,
    "start" => $start,
    "end" => $end,
    "department" => $department,
    "departmentOptions" => $departmentOptions,
    "points" => $points,
    "summary" => [
        "averageWorked" => $averageWorked / $pointCount,
        "averageTotal" => $averageTotal / $pointCount,
        "averageUtilization" => $averageUtilization / $pointCount,
        "maxWorked" => $maxWorked,
        "totalAssignments" => $totalAssignments,
    ],
];

exit(json_encode($output));
