<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
//authorization: todo
/* ---------- params ---------- */
$page = array_key_exists("page", $_POST) ? (int)$_POST["page"] : 1;
$limit = array_key_exists("limit", $_POST) ? (int)$_POST["limit"] : 10;
if ($page < 1)  $page = 1;
if ($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;
/* ---------- search builder ---------- */
$search = new SearchHelper("leads");
$likeFields = ["businessPhone", "extension", "fax", "mobile", "background", "overseaAddress", "email", "role", "website", "industry", "voidReason", "validateReason"];
$equalFields = ["creatorId", "updaterId", "source", "status", "referredBy", "userResponsible1", "userResponsible2", "sent"];
$betweenDateTimeFields = ["createdAt", "updatedAt"];
$search->equals("organizationId", requireInt($_POST, "organizationId", null, null, false));
$search->when(
    array_key_exists("address", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `leads`.`street`, `leads`.`city`, `leads`.`state`, `leads`.`zipCode`) LIKE ?",
        ["%" . $_POST["address"] . "%"]
    )
);
$search->when(
    array_key_exists("name", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `leads`.`firstName`, `leads`.`middleName`, `leads`.`lastName`) LIKE ?",
        ["%".$_POST["name"]."%"]
    )
);
$search->when(
    array_key_exists("noOrganizationAssociated", $_POST) && $_POST["noOrganizationAssociated"] === "1", 
    fn($q) => $q->raw(
        "`leads`.`organizationId` IS NULL",
        []
    )
);
if(!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if($_POST["void"] !== "all") $search->equals("void", $_POST["void"]);
foreach($likeFields as $field){
    $search->like($field, $_POST[$field] ?? null);
}
foreach($equalFields as $field){
    $search->equals($field, $_POST[$field] ?? null);
}
foreach($betweenDateTimeFields as $field){
    $search->between($field, "datetime");
}
$whereSql = $search->getWhereSql();
$params   = $search->getParams();
/* ---------- count ---------- */
$countSql = "SELECT COUNT(*) AS `total` FROM `leads` $whereSql;";
$totalRow = $db->one($countSql, $params, __FILE__, __LINE__);
$total = (int)($totalRow["total"] ?? 0);
/* ---------- page overflow guard ---------- */
$maxPage = max(1, (int)ceil($total / $limit));
if ($page > $maxPage) {
  $page = $maxPage;
  $offset = ($page - 1) * $limit;
}
/* ---------- data ---------- */
$leads = $db->all(
    "SELECT 
    `leads`.`id`, 
    CONCAT_WS(\" \", `leads`.`firstName`, `leads`.`middleName`, `leads`.`lastName`) AS `name`, 
    `leads`.`email`,
    `leads`.`mobile`, 
    `leads`.`businessPhone`, 
    `leads`.`extension`, 
    `leads`.`fax`,
    `leads`.`role`,
    `leads`.`sent`,
    `leads`.`organizationId`,
    `organizations`.`name` AS `organizationName`
    FROM `leads`
    LEFT JOIN `organizations` ON `organizations`.`id` = `leads`.`organizationId`
    $whereSql ORDER BY `leads`.`createdAt` DESC LIMIT $limit OFFSET $offset;", 
    $params, __FILE__, __LINE__
);
/* ---------- response ---------- */
exit(json_encode([
    "leads" => $leads,
    "page"  => $page,
    "limit" => $limit,
    "total" => $total
]));