<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$start = trim((string)($_POST["start"] ?? ""));
$end = trim((string)($_POST["end"] ?? ""));
$page = array_key_exists("page", $_POST) ? (int)$_POST["page"] : 1;
$limit = array_key_exists("limit", $_POST) ? (int)$_POST["limit"] : 10;
if ($page < 1) $page = 1;
if ($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;

if ($start === "" || $end === "") {
    http_response_code(400);
    exit(json_encode(["error" => "Invalid schema. `start` and `end` are required."]));
}

$where = [];
$params = [];

/*
 * The current production `reports` table is much smaller than the frontend's
 * legacy report shape. This endpoint maps the available id-based schema into
 * the keys the React reports screens expect, and returns empty strings for
 * fields that do not exist in the current database yet.
 */

// Match reports that overlap the selected inclusive date range.
$where[] = "`reports`.`endDate` >= ? AND `reports`.`startDate` <= ?";
$params[] = $start;
$params[] = $end;

if (!array_key_exists("void", $_POST)) {
    $where[] = "`reports`.`void` = 'no'";
} elseif ($_POST["void"] !== "All") {
    $where[] = "`reports`.`void` = ?";
    $params[] = $_POST["void"] === "" ? "no" : $_POST["void"];
}

if (($reportHashKey = trim((string)($_POST["reportHashKey"] ?? ""))) !== "") {
    $where[] = "CAST(`reports`.`id` AS CHAR) = ?";
    $params[] = $reportHashKey;
}

if (($projectHashKey = trim((string)($_POST["projectHashKey"] ?? $_POST["projectId"] ?? ""))) !== "") {
    $where[] = "CAST(`reports`.`projectId` AS CHAR) = ?";
    $params[] = $projectHashKey;
}

if (($projectName = trim((string)($_POST["projectName"] ?? ""))) !== "") {
    $where[] = "CONCAT_WS(' - ',
        NULLIF(TRIM(`p`.`projectNumber`), ''),
        NULLIF(TRIM(`org`.`name`), ''),
        NULLIF(TRIM(`p`.`clientProjectNumber`), '')
    ) LIKE ?";
    $params[] = "%" . $projectName . "%";
}

if (($reportTechEmail = trim((string)($_POST["reportTechEmail"] ?? ""))) !== "") {
    $where[] = "`rt`.`email` = ?";
    $params[] = $reportTechEmail;
}

if (($projectManagerEmail = trim((string)($_POST["projectManagerEmail"] ?? ""))) !== "") {
    $where[] = "`pm`.`email` = ?";
    $params[] = $projectManagerEmail;
}

if (($startDateFrom = trim((string)($_POST["startDateFrom"] ?? ""))) !== "") {
    $where[] = "`reports`.`startDate` >= ?";
    $params[] = $startDateFrom;
}

if (($startDateTo = trim((string)($_POST["startDateTo"] ?? ""))) !== "") {
    $where[] = "`reports`.`startDate` <= ?";
    $params[] = $startDateTo;
}

if (($endDateFrom = trim((string)($_POST["endDateFrom"] ?? ""))) !== "") {
    $where[] = "`reports`.`endDate` >= ?";
    $params[] = $endDateFrom;
}

if (($endDateTo = trim((string)($_POST["endDateTo"] ?? ""))) !== "") {
    $where[] = "`reports`.`endDate` <= ?";
    $params[] = $endDateTo;
}

if (array_key_exists("hasFile", $_POST) && $_POST["hasFile"] !== "") {
    if ($_POST["hasFile"] === "Yes") {
        $where[] = "COALESCE(`reports`.`pdfId`, '') <> ''";
    } elseif ($_POST["hasFile"] === "No") {
        $where[] = "COALESCE(`reports`.`pdfId`, '') = ''";
    }
}

$impossibleEquals = [
];

foreach ($impossibleEquals as $field) {
    if (trim((string)($_POST[$field] ?? "")) !== "") {
        $where[] = "1 = 0";
    }
}

$impossibleTextFilters = [];

foreach ($impossibleTextFilters as $field) {
    if (trim((string)($_POST[$field] ?? "")) !== "") {
        $where[] = "1 = 0";
    }
}

if (($status = trim((string)($_POST["status"] ?? ""))) !== "") {
    $where[] = "`reports`.`status` = ?";
    $params[] = $status;
}

if (($notes = trim((string)($_POST["notes"] ?? ""))) !== "") {
    $where[] = "`reports`.`notes` LIKE ?";
    $params[] = "%" . $notes . "%";
}

if (($approverNotes = trim((string)($_POST["approverNotes"] ?? ""))) !== "") {
    $where[] = "`reports`.`approverNotes` LIKE ?";
    $params[] = "%" . $approverNotes . "%";
}

if (($createdBy = trim((string)($_POST["createdBy"] ?? ""))) !== "") {
    $where[] = "`creatorUser`.`email` = ?";
    $params[] = $createdBy;
}

if (($updatedBy = trim((string)($_POST["updatedBy"] ?? ""))) !== "") {
    $where[] = "`updaterUser`.`email` = ?";
    $params[] = $updatedBy;
}

foreach (["pending", "sup"] as $field) {
    $value = trim((string)($_POST[$field] ?? ""));
    if ($value !== "" && $value !== "All") {
        $where[] = "`reports`.`$field` = ?";
        $params[] = $value;
    }
}

foreach ([
    "createdAtFrom",
    "createdAtTo",
    "updatedAtFrom",
    "updatedAtTo",
] as $field) {
    $value = trim((string)($_POST[$field] ?? ""));
    if ($value === "") continue;
    if ($field === "createdAtFrom") {
        $where[] = "`reports`.`createdAt` >= ?";
        $params[] = $value . " 00:00:00";
    } elseif ($field === "createdAtTo") {
        $where[] = "`reports`.`createdAt` <= ?";
        $params[] = $value . " 23:59:59";
    } elseif ($field === "updatedAtFrom") {
        $where[] = "`reports`.`updatedAt` >= ?";
        $params[] = $value . " 00:00:00";
    } else {
        $where[] = "`reports`.`updatedAt` <= ?";
        $params[] = $value . " 23:59:59";
    }
}

if (($priority = trim((string)($_POST["priority"] ?? ""))) !== "") {
    $where[] = "`reports`.`priority` = ?";
    $params[] = $priority;
}

if (($code = trim((string)($_POST["code"] ?? ""))) !== "") {
    $where[] = "`reports`.`code` LIKE ?";
    $params[] = "%" . $code . "%";
}

foreach (["pothole", "ep", "manhole"] as $field) {
    $min = trim((string)($_POST[$field . "From"] ?? ""));
    $max = trim((string)($_POST[$field . "To"] ?? ""));
    if ($min !== "" && $max !== "") {
        $where[] = "`reports`.`$field` BETWEEN ? AND ?";
        $params[] = $min;
        $params[] = $max;
    } elseif ($min !== "") {
        $where[] = "`reports`.`$field` >= ?";
        $params[] = $min;
    } elseif ($max !== "") {
        $where[] = "`reports`.`$field` <= ?";
        $params[] = $max;
    }
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

$fromSql = "FROM `reports`
LEFT JOIN `projects` `p` ON `p`.`id` = `reports`.`projectId`
LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
LEFT JOIN `users` `rt` ON `rt`.`id` = `reports`.`reportTechId`
LEFT JOIN `users` `pm` ON `pm`.`id` = `p`.`projectManagerId`
LEFT JOIN `users` `creatorUser` ON `creatorUser`.`id` = `reports`.`creatorId`
LEFT JOIN `users` `updaterUser` ON `updaterUser`.`id` = `reports`.`updaterId`
LEFT JOIN `contacts` `requestorUser` ON `requestorUser`.`id` = `reports`.`requestorId`";

$countSql = "SELECT COUNT(*) AS `total` $fromSql $whereSql;";
$totalRow = $db->one($countSql, $params, __FILE__, __LINE__);
$total = (int)($totalRow["total"] ?? 0);

$maxPage = max(1, (int)ceil($total / $limit));
if ($page > $maxPage) {
    $page = $maxPage;
    $offset = ($page - 1) * $limit;
}

$sql = "SELECT
`reports`.`id`,
`reports`.`projectId`,
CONCAT_WS(' - ',
    NULLIF(TRIM(`p`.`projectNumber`), ''),
    NULLIF(TRIM(`org`.`name`), ''),
    NULLIF(TRIM(`p`.`clientProjectNumber`), '')
) AS `projectName`,
`reports`.`startDate`,
`reports`.`endDate`,
`reports`.`pothole`,
`reports`.`ep`,
`reports`.`manhole`,
`reports`.`code`,
`reports`.`priority`,
`reports`.`pending`,
`reports`.`sup`,
`reports`.`pdfId`,
`reports`.`reportTechId`,
CONCAT_WS(' ', `rt`.`firstName`, `rt`.`middleName`, `rt`.`lastName`) AS `reportTech`,
`reports`.`status`,
`p`.`projectManagerId` AS `approverId`

$fromSql
$whereSql
ORDER BY `reports`.`endDate` DESC, `reports`.`id` DESC
LIMIT $limit OFFSET $offset;";

$reports = $db->all($sql, $params, __FILE__, __LINE__);

exit(json_encode([
    "reports" => $reports,
    "page" => $page,
    "limit" => $limit,
    "total" => $total,
    "start" => $start,
    "end" => $end
]));
