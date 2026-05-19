<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
require_once __DIR__."/helpers.php";
$columns = json_decode((string)($_POST["columns"] ?? "[]"), true);
if(!is_array($columns) || !count($columns)){
    $columns = ["poNumber", "poDate", "requesterId", "projectId", "category", "total", "status"];
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
$projectLabel = purchaseProjectLabel();
$rows = $db->all(
    "SELECT `p`.*, $projectLabel AS `projectName`,
        CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`,
        CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `approverName`,
        CONCAT_WS(' ', `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`) AS `creatorName`,
        CONCAT_WS(' ', `u4`.`firstName`, `u4`.`middleName`, `u4`.`lastName`) AS `updaterName`,
        CONCAT_WS(' ', `u5`.`firstName`, `u5`.`middleName`, `u5`.`lastName`) AS `submitterName`,
        CONCAT_WS(' ', `u6`.`firstName`, `u6`.`middleName`, `u6`.`lastName`) AS `notifierName`
    ".purchaseFromSql()." ".$search->getWhereSql()." ORDER BY `p`.`poDate` DESC, `p`.`createdAt` DESC;",
    $search->getParams(),
    __FILE__,
    __LINE__
);
$labels = [
    "poNumber" => "PO Number",
    "poDate" => "PO Date",
    "poType" => "PO Type",
    "category" => "Category",
    "projectId" => "Project",
    "requesterId" => "Requester",
    "approverId" => "Approver",
    "department" => "Department",
    "paymentMethod" => "Payment Method",
    "last4" => "Last 4",
    "billable" => "Billable",
    "includedInProposal" => "Included In Proposal",
    "clientInvoiceNumber" => "Client Invoice Number",
    "notes" => "Notes",
    "subtotal" => "Subtotal",
    "tax" => "Tax",
    "discount" => "Discount",
    "total" => "Total",
    "status" => "Status",
    "paid" => "Paid",
    "billed" => "Billed",
    "approvalTime" => "Approval Time",
    "approverNotes" => "Approver Notes",
    "notifiedAt" => "Notified At",
    "notifiedBy" => "Notified By",
    "createdAt" => "Created At",
    "creatorId" => "Creator",
    "updatedAt" => "Updated At",
    "updaterId" => "Updated By",
    "void" => "Void",
    "voidReason" => "Void Reason",
    "validateReason" => "Validate Reason",
    "lineItems" => "Line Items",
];
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=Purchases.csv");
$output = fopen("php://output", "w");
fputcsv($output, array_map(fn($column) => $labels[$column] ?? $column, $columns));
foreach($rows as $row){
    $lineItems = [];
    $decoded = $row["data"] ? json_decode($row["data"], true) : [];
    if(is_array($decoded)){
        foreach($decoded as $line){
            $lineItems[] = trim((string)($line["vendorName"] ?? "")) . ": " . trim((string)($line["description"] ?? "")) . " (" . trim((string)($line["quantity"] ?? "")) . " x " . trim((string)($line["unitPrice"] ?? "")) . " = " . trim((string)($line["lineTotal"] ?? "")) . ")";
        }
    }
    $mapped = [
        "projectId" => $row["projectName"],
        "requesterId" => $row["requesterName"],
        "approverId" => $row["approverName"],
        "notifiedBy" => $row["notifierName"],
        "creatorId" => $row["creatorName"],
        "updaterId" => $row["updaterName"],
        "lineItems" => implode(" | ", $lineItems),
    ];
    $exportRow = [];
    foreach($columns as $column){
        $exportRow[] = $mapped[$column] ?? (is_scalar($row[$column] ?? null) ? $row[$column] : "");
    }
    fputcsv($output, $exportRow);
}
fclose($output);
exit();
