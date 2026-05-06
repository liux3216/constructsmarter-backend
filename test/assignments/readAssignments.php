<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";

header("Content-Type: application/json");

/* ---------- params ---------- */
$page = array_key_exists("page", $_POST) ? (int)$_POST["page"] : 1;
$limit = array_key_exists("limit", $_POST) ? (int)$_POST["limit"] : 10;
if ($page < 1) $page = 1;
if ($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;

/* ---------- search builder ---------- */
$search = new SearchHelper("assignments");
function yesNoFilterValue($value): ?string {
    if ($value === null || $value === "") {
        return null;
    }
    $value = strtolower(trim((string)$value));
    if ($value === "1") {
        return "yes";
    }
    if ($value === "0") {
        return "no";
    }
    return $value;
}

$fromSql = "FROM `assignments`
LEFT JOIN `works` `w` ON `w`.`id` = `assignments`.`workId`
LEFT JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
LEFT JOIN `users` `assignedUser` ON `assignedUser`.`id` = `assignments`.`userId`
LEFT JOIN `users` `supervisorUser` ON `supervisorUser`.`id` = `w`.`supervisorId`
LEFT JOIN `users` `leadUser` ON `leadUser`.`id` = `w`.`leadId`
LEFT JOIN `users` `creatorUser` ON `creatorUser`.`id` = `assignments`.`creatorId`
LEFT JOIN `users` `updaterUser` ON `updaterUser`.`id` = `assignments`.`updaterId`
";

$search->when(
    array_key_exists("projectName", $_POST) && $_POST["projectName"] !== "",
    fn($q) => $q->raw(
        "CONCAT_WS(' - ',
            NULLIF(TRIM(`p`.`projectNumber`), ''),
            NULLIF(TRIM(`org`.`name`), ''),
            NULLIF(TRIM(`p`.`clientProjectNumber`), '')
        ) LIKE ?",
        ["%" . $_POST["projectName"] . "%"]
    )
);
$search->when(
    array_key_exists("userName", $_POST) && $_POST["userName"] !== "",
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `assignedUser`.`firstName`, `assignedUser`.`middleName`, `assignedUser`.`lastName`) LIKE ?",
        ["%" . $_POST["userName"] . "%"]
    )
);

if (!array_key_exists("void", $_POST)) {
    $search->equals("void", "no");
} else if ($_POST["void"] !== "all") {
    $search->equals("void", $_POST["void"]);
}

$likeFields = [
    "laborCategory",
    "fleetNumber",
    "coords",
    "voidReason",
    "validateReason",
];
$equalFields = [
    "workId",
    "userId",
    "creatorId",
    "updaterId",
];
$yesNoFields = [
    "perDiem",
];
foreach ($likeFields as $field) {
    $search->like($field, $_POST[$field] ?? null);
}
foreach ($equalFields as $field) {
    $search->equals($field, $_POST[$field] ?? null);
}
foreach ($yesNoFields as $field) {
    $search->equals($field, yesNoFilterValue($_POST[$field] ?? null));
}
foreach (["createdAt", "updatedAt"] as $field) {
    $search->between($field, "datetime");
}

$whereSql = $search->getWhereSql();
$params = $search->getParams();

/* ---------- count ---------- */
$countSql = "SELECT COUNT(*) AS `total` $fromSql $whereSql;";
$totalRow = $db->one($countSql, $params, __FILE__, __LINE__);
$total = (int)($totalRow["total"] ?? 0);

/* ---------- page overflow guard ---------- */
$maxPage = max(1, (int)ceil($total / $limit));
if ($page > $maxPage) {
    $page = $maxPage;
    $offset = ($page - 1) * $limit;
}

/* ---------- data ---------- */
$assignments = $db->all(
    "SELECT
    `assignments`.`id`,
    `assignments`.`workId`,
    `w`.`projectId`,
    CONCAT_WS(' - ',
        NULLIF(TRIM(`p`.`projectNumber`), ''),
        NULLIF(TRIM(`org`.`name`), ''),
        NULLIF(TRIM(`p`.`clientProjectNumber`), '')
    ) AS `projectName`,
    `w`.`category` AS `workCategory`,
    `w`.`subCategory` AS `workSubCategory`,
    `w`.`location` AS `workLocation`,
    `w`.`coords` AS `workCoords`,
    `w`.`startTime`,
    `w`.`endTime`,
    `w`.`allDay`,
    `assignments`.`userId`,
    CONCAT_WS(' ', `assignedUser`.`firstName`, `assignedUser`.`middleName`, `assignedUser`.`lastName`) AS `userName`,
    `assignments`.`laborCategory`,
    `assignments`.`fleetNumber`,
    `assignments`.`perDiem`,
    `assignments`.`coords`,
    CONCAT_WS(' ', `supervisorUser`.`firstName`, `supervisorUser`.`middleName`, `supervisorUser`.`lastName`) AS `supervisorName`,
    `w`.`supervisorId`,
    `w`.`leadId`,
    CONCAT_WS(' ', `leadUser`.`firstName`, `leadUser`.`middleName`, `leadUser`.`lastName`) AS `leadName`, 

    `assignments`.`void`,
    `assignments`.`voidReason`,
    `assignments`.`validateReason`,
    `assignments`.`creatorId`,
    CONCAT_WS(' ', `creatorUser`.`firstName`, `creatorUser`.`middleName`, `creatorUser`.`lastName`) AS `creatorName`,
    `assignments`.`createdAt`,
    `assignments`.`updaterId`,
    CONCAT_WS(' ', `updaterUser`.`firstName`, `updaterUser`.`middleName`, `updaterUser`.`lastName`) AS `updaterName`,
    `assignments`.`updatedAt`
    $fromSql
    $whereSql
    ORDER BY `assignments`.`createdAt` DESC
    LIMIT $limit OFFSET $offset;",
    $params,
    __FILE__,
    __LINE__
);

/* ---------- response ---------- */
exit(json_encode([
    "assignments" => $assignments,
    "page" => $page,
    "limit" => $limit,
    "total" => $total,
]));
