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
$search = new SearchHelper("opportunities");
$likeFields = ["background", "opportunityName", "location", "voidReason", "validateReason"];
$equalFields = ["creatorId", "updaterId", "bidType", "category", "state"];
$betweenDateTimeFields = ["createdAt", "updatedAt"];
$betweenDateFields = ["actualCloseDate", "probability", "bidAmount"];
$search->equals("organizationId", requireInt($_POST, "organizationId", null, null, false));
$contactId = requireInt($_POST, "contactId", null, null, false);
if ($contactId !== null) {
    $search->raw(
        "EXISTS (SELECT 1 FROM `opportunities_contact` WHERE `opportunities_contact`.`opportunityId` = `opportunities`.`id` AND `opportunities_contact`.`contactId` = ?)",
        [$contactId]
    );
}
$userResponsibleId = requireField($_POST, "userResponsibleId");
if ($userResponsibleId !== null) {
    $search->raw(
        "EXISTS (SELECT 1 FROM `opportunities_userResponsible` WHERE `opportunities_userResponsible`.`opportunityId` = `opportunities`.`id` AND `opportunities_userResponsible`.`userId` = ?)",
        [$userResponsibleId]
    );
}
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
foreach($betweenDateFields as $field){
    $search->between($field);
}
$whereSql = $search->getWhereSql();
$params   = $search->getParams();
/* ---------- count ---------- */
$countSql = "SELECT COUNT(*) AS `total` FROM `opportunities` $whereSql;";
$totalRow = $db->one($countSql, $params, __FILE__, __LINE__);
$total = (int)($totalRow["total"] ?? 0);
/* ---------- page overflow guard ---------- */
$maxPage = max(1, (int)ceil($total / $limit));
if ($page > $maxPage) {
  $page = $maxPage;
  $offset = ($page - 1) * $limit;
}
/* ---------- data ---------- */
$sql = "SELECT 
`opportunities`.`id`, 
`opportunities`.`opportunityName`,
`opportunities`.`organizationId`,
`organizations`.`name` AS `organizationName`
FROM `opportunities`
LEFT JOIN `users` `u1` ON `u1`.`id` = `opportunities`.`creatorId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `opportunities`.`updaterId`
LEFT JOIN `organizations` ON `organizations`.`id` = `opportunities`.`organizationId`
$whereSql ORDER BY `opportunities`.`createdAt` DESC LIMIT $limit OFFSET $offset;";
$opportunities = $db->all($sql, $params, __FILE__, __LINE__);
/* ---------- response ---------- */
exit(json_encode([
    "opportunities" => $opportunities,
    "page"  => $page,
    "limit" => $limit,
    "total" => $total
]));