<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
require_once __DIR__."/helpers.php";
$start = (string)($_POST["poDateFrom"] ?? "");
$end = (string)($_POST["poDateTo"] ?? "");
$page = max(1, (int)($_POST["page"] ?? 1));
$limit = (int)($_POST["limit"] ?? 10);
if($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;
if($start === "" || $end === ""){
    purchaseJsonResponse(400, ["msg" => "Invalid date range."]);
}
$user = $db->one("SELECT `purchases` FROM `users` WHERE `id` = ?;", [$userId], __FILE__, __LINE__);
$access = $user["purchases"] ?? "no";
$search = new SearchHelper("p");
$search->between("poDate")
    ->between("total")
    ->like("poNumber", $_POST["poNumber"] ?? null)
    ->like("notes", $_POST["notes"] ?? null)
    ->like("clientInvoiceNumber", $_POST["clientInvoiceNumber"] ?? null)
    ->equals("requesterId", $_POST["requesterId"] ?? null)
    ->equals("projectId", $_POST["projectId"] ?? null)
    ->equals("approverId", $_POST["approverId"] ?? null)
    ->equals("category", $_POST["category"] ?? null)
    ->equals("department", $_POST["department"] ?? null)
    ->equals("paymentMethod", $_POST["paymentMethod"] ?? null)
    ->equals("billable", $_POST["billable"] ?? null)
    ->equals("includedInProposal", $_POST["includedInProposal"] ?? null)
    ->equals("status", $_POST["status"] ?? null)
    ->equals("creatorId", $_POST["creatorId"] ?? null)
    ->equals("updaterId", $_POST["updaterId"] ?? null);
if(array_key_exists("paid", $_POST) && $_POST["paid"] !== "all") $search->equals("paid", $_POST["paid"]);
if(array_key_exists("billed", $_POST) && $_POST["billed"] !== "all") $search->equals("billed", $_POST["billed"]);
if(!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if($_POST["void"] !== "all") $search->equals("void", $_POST["void"]);
[$scopeSql, $scopeParams] = purchaseScope("p", $userId, $access);
$search->raw($scopeSql, $scopeParams);
$from = purchaseFromSql();
$whereSql = $search->getWhereSql();
$params = $search->getParams();
$totalRow = $db->one("SELECT COUNT(*) AS `total` $from $whereSql;", $params, __FILE__, __LINE__);
$total = (int)($totalRow["total"] ?? 0);
$maxPage = max(1, (int)ceil($total / $limit));
if($page > $maxPage){
    $page = $maxPage;
    $offset = ($page - 1) * $limit;
}
$projectLabel = purchaseProjectLabel();
$rows = $db->all(
    "SELECT
        `p`.`id`, `p`.`poNumber`, `p`.`poDate`, `p`.`category`, `p`.`projectId`, $projectLabel AS `projectName`,
        `p`.`requesterId`, `p`.`approverId`, `p`.`total`, `p`.`status`, `p`.`paid`, `p`.`billed`, `p`.`void`,
        CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`,
        CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `approverName`
    $from $whereSql
    ORDER BY `p`.`poDate` DESC, `p`.`createdAt` DESC LIMIT $limit OFFSET $offset;",
    $params,
    __FILE__,
    __LINE__
);
foreach($rows as &$row){
    $row["requester"] = $row["requesterId"] ? ["label" => $row["requesterName"], "value" => $row["requesterId"]] : null;
    $row["approver"] = $row["approverId"] ? ["label" => $row["approverName"], "value" => $row["approverId"]] : null;
}
unset($row);
exit(json_encode(["purchases" => $rows, "page" => $page, "limit" => $limit, "total" => $total, "start" => $start, "end" => $end]));
