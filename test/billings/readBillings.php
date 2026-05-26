<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
/* ---------- params ---------- */
$start = array_key_exists("createdAtFrom", $_POST) ? $_POST["createdAtFrom"] : "";
$end = array_key_exists("createdAtTo", $_POST) ? $_POST["createdAtTo"] : "";
$page = array_key_exists("page", $_POST) ? (int)$_POST["page"] : 1;
$limit = array_key_exists("limit", $_POST) ? (int)$_POST["limit"] : 10;
if ($page < 1)  $page = 1;
if ($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;
/* ---------- validate ---------- */
if(!$start || !$end){
  http_response_code(407);
  exit(json_encode(["error" => "Invalid Schema"]));
}
/* ---------- search ---------- */
$search = new SearchHelper("billings");
$likeFields         = ["notes", "approverNotes", "voidReason", "validateReason"];
$equalFields        = ["contactId", "approverId", "status", "paid", "notifiedBy", "creatorId", "updaterId"];
$betweenDateFields  = ["fromDate", "toDate", "notifiedAt", "approvalTime", "createdAt", "updatedAt"];
if (!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if ($_POST["void"] !== "all")     $search->equals("void", $_POST["void"]);
$search->when(
    array_key_exists("department", $_POST),
    fn($q) => $q->raw("`u1`.`department` = ?", [$_POST["department"]])
);
foreach ($likeFields as $field) {
    $search->like($field, $_POST[$field] ?? null);
}
foreach ($equalFields as $field) {
    $search->equals($field, $_POST[$field] ?? null);
}
foreach ($betweenDateFields as $field) {
    $search->between($field, "datetime");
}
$whereSql = $search->getWhereSql();
$params   = $search->getParams();
/* ---------- count ---------- */
$countSql = "SELECT COUNT(*) AS `total` FROM `billings` $whereSql;";
$totalRow = $db->one($countSql, $params, __FILE__, __LINE__);
$total = (int)($totalRow["total"] ?? 0);
/* ---------- page overflow guard ---------- */
$maxPage = max(1, (int)ceil($total / $limit));
if ($page > $maxPage) {
  $page = $maxPage;
  $offset = ($page - 1) * $limit;
}
/* ---------- data ---------- */
$billings = $db->all(
    "SELECT 
    `billings`.`id`, 
    `billings`.`billingNumber`, 
    CONCAT_WS(' - ',
        NULLIF(TRIM(`p`.`projectNumber`), ''),
        NULLIF(TRIM(`org`.`name`), ''),
        NULLIF(TRIM(`p`.`clientProjectNumber`), '')
    ) AS `projectName`,
    `billings`.`projectId`, 
    `billings`.`fromDate`, 
    `billings`.`toDate`, 
    `billings`.`amount`, 
    `billings`.`submitterId`,
    CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `submitterName`,
    `billings`.`submitTime`,
    `billings`.`pdfId`,
    `billings`.`status`,
    `billings`.`billed`
    FROM `billings`
    LEFT JOIN `projects` `p` ON `p`.`id` = `billings`.`projectId`
    LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
    LEFT JOIN `users` `u1` ON `u1`.`id` = `billings`.`submitterId`
    $whereSql ORDER BY `billings`.`createdAt` DESC LIMIT $limit OFFSET $offset;", 
    $params, __FILE__, __LINE__
);
/* ---------- response ---------- */
exit(json_encode([
    "billings" => $billings,
    "page"     => $page,
    "limit"    => $limit,
    "total"    => $total,
    "start"    => $start,
    "end"      => $end
]));