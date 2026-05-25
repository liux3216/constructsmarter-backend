<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
require_once __DIR__."/helpers.php";
$start = (string)($_POST["proposalDateFrom"] ?? "");
$end = (string)($_POST["proposalDateTo"] ?? "");
$page = max(1, (int)($_POST["page"] ?? 1));
$limit = (int)($_POST["limit"] ?? 10);
if($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;
if($start === "" || $end === ""){
    proposalJsonResponse(400, ["msg" => "Invalid date range."]);
}
$search = new SearchHelper("p");
$search->between("proposalDate")
    ->between("total")
    ->like("proposalNumber", $_POST["proposalNumber"] ?? null)
    ->like("notes", $_POST["notes"] ?? null)
    ->equals("requesterId", $_POST["requesterId"] ?? null)
    ->equals("projectId", $_POST["projectId"] ?? null)
    ->equals("approverId", $_POST["approverId"] ?? null)
    ->equals("department", $_POST["department"] ?? null)
    ->equals("status", $_POST["status"] ?? null)
    ->equals("creatorId", $_POST["creatorId"] ?? null)
    ->equals("updaterId", $_POST["updaterId"] ?? null);
if(!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if($_POST["void"] !== "all") $search->equals("void", $_POST["void"]);
$whereSql = $search->getWhereSql();
$params = $search->getParams();
$totalRow = $db->one("SELECT COUNT(*) AS `total` " . proposalFromSql() . " $whereSql;", $params, __FILE__, __LINE__);
$total = (int)($totalRow["total"] ?? 0);
$maxPage = max(1, (int)ceil($total / $limit));
if($page > $maxPage){
    $page = $maxPage;
    $offset = ($page - 1) * $limit;
}
$projectLabel = proposalProjectLabel();
$rows = $db->all(
    "SELECT
        `p`.`id`, `p`.`proposalNumber`, `p`.`proposalDate`, `p`.`projectId`, $projectLabel AS `projectName`,
        `p`.`requesterId`, `p`.`approverId`, `p`.`total`, `p`.`status`, `p`.`void`,
        CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`,
        CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `approverName`
    " . proposalFromSql() . " $whereSql
    ORDER BY `p`.`proposalDate` DESC, `p`.`createdAt` DESC LIMIT $limit OFFSET $offset;",
    $params,
    __FILE__,
    __LINE__
);
foreach($rows as &$row){
    $row["requester"] = $row["requesterId"] ? ["label" => $row["requesterName"], "value" => $row["requesterId"]] : null;
    $row["approver"] = $row["approverId"] ? ["label" => $row["approverName"], "value" => $row["approverId"]] : null;
}
unset($row);
exit(json_encode(["proposals" => $rows, "page" => $page, "limit" => $limit, "total" => $total, "start" => $start, "end" => $end]));
