<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
$page = max(1, (int)($_POST["page"] ?? 1));
$limit = (int)($_POST["limit"] ?? 10);
$limit = ($limit < 1 || $limit > 100) ? 10 : $limit;
$offset = ($page - 1) * $limit;
$search = new SearchHelper("services");
$likeFields = ["code", "name", "category", "costType", "notes", "voidReason", "validateReason"];
$equalFields = ["id", "creatorId", "updaterId"];
$betweenFields = ["price"];
$betweenDateTimeFields = ["createdAt", "updatedAt"];
if(!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if($_POST["void"] !== "all") $search->equals("void", $_POST["void"]);
foreach($likeFields as $field){
    $search->like($field, $_POST[$field] ?? null);
}
foreach($equalFields as $field){
    $search->equals($field, $_POST[$field] ?? null);
}
foreach($betweenFields as $field){
    $search->between($field, "number");
}
foreach($betweenDateTimeFields as $field){
    $search->between($field, "datetime");
}
$whereSql = $search->getWhereSql();
$params = $search->getParams();
$total = (int)($db->one(
    "SELECT COUNT(*) AS `total` FROM `services` $whereSql",
    $params, __FILE__, __LINE__
)["total"] ?? 0);
$maxPage = max(1, ceil($total / $limit));
$page = min($page, $maxPage);
$offset = ($page - 1) * $limit;
$rows = $db->all(
    "SELECT `id`, `code`, `name`, `category`, `price`, `costType`, `void`
     FROM `services`
     $whereSql
     ORDER BY `createdAt` DESC
     LIMIT $limit OFFSET $offset",
    $params, __FILE__, __LINE__
);
exit(json_encode([
    "services" => $rows,
    "page" => $page,
    "limit" => $limit,
    "total" => $total,
]));
