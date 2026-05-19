<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$startWeek = trim((string)($_POST["startWeek"] ?? ""));
$endWeek = trim((string)($_POST["endWeek"] ?? ""));
if (!preg_match('/^w\d{4}-\d{2}$/', $startWeek)) {
    http_response_code(409);
    exit(json_encode(["msg" => "Invalid start week."]));
}
if (!preg_match('/^w\d{4}-\d{2}$/', $endWeek)) {
    http_response_code(409);
    exit(json_encode(["msg" => "Invalid end week."]));
}
if ($startWeek > $endWeek) {
    http_response_code(409);
    exit(json_encode(["msg" => "Start week cannot be after end week."]));
}

$rows = $db->all(
    "SELECT CAST(`projectId` AS CHAR) AS `projectId`, `week`, `amount`
     FROM `labor_costs`
     WHERE `week` >= ? AND `week` <= ?
     ORDER BY `week`, `projectId`;
    ",
    [$startWeek, $endWeek],
    __FILE__,
    __LINE__
);

$output = [];
foreach ($rows as $row) {
    $week = $row["week"];
    if (!array_key_exists($week, $output)) {
        $output[$week] = [];
    }
    $output[$week][$row["projectId"]] = (float)$row["amount"];
}

exit(json_encode($output));
