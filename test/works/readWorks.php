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
$search = new SearchHelper("works");
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
$fromSql = "FROM `works`
LEFT JOIN `projects` `p` ON `p`.`id` = `works`.`projectId`
LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
LEFT JOIN `users` `supervisorUser` ON `supervisorUser`.`id` = `works`.`supervisorId`
LEFT JOIN `contacts` `siteContact` ON `siteContact`.`id` = `works`.`siteContactId`
LEFT JOIN `users` `leadUser` ON `leadUser`.`id` = `works`.`leadId`
LEFT JOIN `users` `creatorUser` ON `creatorUser`.`id` = `works`.`creatorId`
LEFT JOIN `users` `updaterUser` ON `updaterUser`.`id` = `works`.`updaterId`";
$likeFields = [
    "location",
    "description",
    "voidReason",
    "validateReason",
];
$equalFields = [
    "category",
    "subCategory",
    "supervisorId",
    "siteContactId",
    "leadId",
    "creatorId",
    "updaterId",
];
$yesNoFields = [
    "allDay",
    "jobTagLocation",
    "cadRequired",
    "reportRequired",
    "waiveJSA",
];
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
if (!array_key_exists("void", $_POST)) {
    $search->equals("void", "no");
} else if ($_POST["void"] !== "all") {
    $search->equals("void", $_POST["void"]);
}
foreach ($likeFields as $field) {
    $search->like($field, $_POST[$field] ?? null);
}
foreach ($equalFields as $field) {
    $search->equals($field, $_POST[$field] ?? null);
}
foreach ($yesNoFields as $field) {
    $search->equals($field, yesNoFilterValue($_POST[$field] ?? null));
}
foreach (["startTime", "endTime", "createdAt", "updatedAt"] as $field) {
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
$works = $db->all(
    "SELECT
    `works`.`id`,
    `works`.`projectId`,
    CONCAT_WS(' - ',
        NULLIF(TRIM(`p`.`projectNumber`), ''),
        NULLIF(TRIM(`org`.`name`), ''),
        NULLIF(TRIM(`p`.`clientProjectNumber`), '')
    ) AS `projectName`,
    `works`.`category`,
    `works`.`subCategory`,
    `works`.`location`,
    `works`.`jobTagLocation`,
    `works`.`coords`,
    `works`.`startTime`,
    `works`.`endTime`,
    `works`.`allDay`,
    CONCAT_WS(' ', `supervisorUser`.`firstName`, `supervisorUser`.`middleName`, `supervisorUser`.`lastName`) AS `supervisorName`,
    `works`.`supervisorId`,
    `works`.`siteContactId`,
    CONCAT_WS(' ', `siteContact`.`firstName`, `siteContact`.`middleName`, `siteContact`.`lastName`) AS `siteContactName`,
    `works`.`cadRequired`,
    `works`.`reportRequired`,
    `works`.`waiveJSA`,
    `works`.`leadId`,
    CONCAT_WS(' ', `leadUser`.`firstName`, `leadUser`.`middleName`, `leadUser`.`lastName`) AS `leadName`,
    `works`.`description`,
    `works`.`void`,
    `works`.`voidReason`,
    `works`.`validateReason`,
    `works`.`creatorId`,
    CONCAT_WS(' ', `creatorUser`.`firstName`, `creatorUser`.`middleName`, `creatorUser`.`lastName`) AS `creatorName`,
    `works`.`createdAt`,
    `works`.`updaterId`,
    CONCAT_WS(' ', `updaterUser`.`firstName`, `updaterUser`.`middleName`, `updaterUser`.`lastName`) AS `updaterName`,
    `works`.`updatedAt`
    $fromSql
    $whereSql
    ORDER BY `works`.`createdAt` DESC
    LIMIT $limit OFFSET $offset;",
    $params,
    __FILE__,
    __LINE__
);

/* ---------- response ---------- */
exit(json_encode([
    "works" => $works,
    "page" => $page,
    "limit" => $limit,
    "total" => $total,
]));
