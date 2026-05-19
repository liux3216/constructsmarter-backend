<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
require_once __DIR__."/helpers.php";
$start = array_key_exists("createdAtFrom", $_POST) ? $_POST["createdAtFrom"] : "";
$end = array_key_exists("createdAtTo", $_POST) ? $_POST["createdAtTo"] : "";
$page = array_key_exists("page", $_POST) ? (int)$_POST["page"] : 1;
$limit = array_key_exists("limit", $_POST) ? (int)$_POST["limit"] : 10;
if($page < 1) $page = 1;
if($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;
if(!$start || !$end){
    http_response_code(407);
    exit(json_encode(["error" => "Invalid Schema"]));
}
$access = getPerDiemAccess($db, $userId);
$search = new SearchHelper("p");
$search->between("createdAt", "datetime");
$search->between("startDate", "datetime");
$search->between("endDate", "datetime");
$search->between("notifiedAt", "datetime");
$search->between("approvalTime", "datetime");
$search->between("updatedAt", "datetime");
foreach(["hotelName", "hotelAddress", "notes", "approverNotes", "voidReason", "validateReason"] as $field){
    $search->like($field, $_POST[$field] ?? null);
}
foreach(["requesterId", "projectId", "approverId", "status", "paid", "notifiedBy", "creatorId", "updaterId"] as $field){
    $search->equals($field, $_POST[$field] ?? null);
}
if(!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if($_POST["void"] !== "all") $search->equals("void", $_POST["void"]);
$search->when(
    array_key_exists("department", $_POST) && $_POST["department"] !== "",
    fn($q) => $q->raw("`u1`.`department` = ?", [$_POST["department"]])
);
[$scopeSql, $scopeParams] = perDiemScope("p", $userId, $access);
$search->raw($scopeSql, $scopeParams);
$whereSql = $search->getWhereSql();
$params = $search->getParams();
$from = "FROM `perDiems` `p`
LEFT JOIN `users` `u1` ON `u1`.`id` = `p`.`requesterId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `p`.`approverId`
LEFT JOIN `projects` `pr` ON `pr`.`id` = `p`.`projectId`
LEFT JOIN `organizations` `o` ON `o`.`id` = `pr`.`organizationId`";
$totalRow = $db->one("SELECT COUNT(*) AS `total` $from $whereSql;", $params, __FILE__, __LINE__);
$total = (int)($totalRow["total"] ?? 0);
$maxPage = max(1, (int)ceil($total / $limit));
if($page > $maxPage){
    $page = $maxPage;
    $offset = ($page - 1) * $limit;
}
$projectLabel = perDiemProjectLabel("pr", "o");
$rows = $db->all(
    "SELECT
    `p`.`id`,
    `p`.`projectId`,
    $projectLabel AS `projectName`,
    `p`.`requesterId`,
    `p`.`approverId`,
    `p`.`startDate`,
    `p`.`endDate`,
    `p`.`hotelName`,
    `p`.`hotelAddress`,
    `p`.`status`,
    `p`.`paid`,
    `p`.`createdAt`,
    `p`.`void`,
    `u1`.`department`,
    `u1`.`outside`,
    `u1`.`projects`,
    CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`,
    CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `approverName`
    $from $whereSql
    ORDER BY `p`.`createdAt` DESC LIMIT $limit OFFSET $offset;",
    $params,
    __FILE__,
    __LINE__
);
foreach($rows as &$row){
    $row["requester"] = [
        "label" => $row["requesterName"],
        "value" => $row["requesterId"],
        "department" => $row["department"],
        "outside" => $row["outside"],
        "projects" => $row["projects"],
    ];
}
unset($row);
exit(json_encode([
    "perDiems" => $rows,
    "page" => $page,
    "limit" => $limit,
    "total" => $total,
    "start" => $start,
    "end" => $end,
]));
