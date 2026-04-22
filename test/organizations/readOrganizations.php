<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
/* ---------- pagination ---------- */
$page  = max(1, (int)($_POST["page"]  ?? 1));
$limit = (int)($_POST["limit"] ?? 10);
$limit = ($limit < 1 || $limit > 100) ? 10 : $limit;
$offset = ($page - 1) * $limit;
/* ---------- search builder ---------- */
$search = new SearchHelper("organizations");
$likeFields = ["name", "website", "phoneNumber", "extension", "fax", "background", "overseaAddress", "voidReason", "validateReason"];
$equalFields = ["creatorId", "updaterId"];
$betweenDateTimeFields = ["createdAt", "updatedAt"];
$search->when(
    array_key_exists("address", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `street`, `city`, `state`, `zipCode`) LIKE ?",
        ["%" . $_POST["address"] . "%"]
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
/* ---------- total ---------- */
$total = (int)($db->one(
    "SELECT COUNT(*) AS `total` FROM `organizations` $whereSql",
    $params, __FILE__, __LINE__
)["total"] ?? 0);
/* ---------- page overflow guard ---------- */
$maxPage = max(1, ceil($total / $limit));
$page    = min($page, $maxPage);
$offset  = ($page - 1) * $limit;
/* ---------- data ---------- */
$rows = $db->all(
    "SELECT 
        `id`,
        `name`,
        `website`,
        `phoneNumber`,
        `extension`,
        `fax`
     FROM `organizations`
     $whereSql
     ORDER BY `createdAt` DESC
     LIMIT $limit OFFSET $offset",
    $params, __FILE__, __LINE__
);
/* ---------- response ---------- */
exit(json_encode([
    "organizations" => $rows,
    "page"          => $page,
    "limit"         => $limit,
    "total"         => $total,
]));