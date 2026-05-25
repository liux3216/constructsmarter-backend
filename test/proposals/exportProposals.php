<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
require_once __DIR__."/helpers.php";
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
$columns = json_decode($_POST['columns'] ?? '[]', true);
if(!is_array($columns) || empty($columns)){
    http_response_code(407);
    echo "Invalid export parameters";
    exit;
}
$COLUMN_MAP = [
    "proposalNumber" => ["sql" => "`p`.`proposalNumber`", "label" => "Proposal Number"],
    "proposalDate" => ["sql" => "`p`.`proposalDate`", "label" => "Proposal Date"],
    "projectId" => ["sql" => proposalProjectLabel(), "label" => "Project"],
    "requesterId" => ["sql" => "CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)", "label" => "Requester"],
    "approverId" => ["sql" => "CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)", "label" => "Approver"],
    "department" => ["sql" => "`p`.`department`", "label" => "Department"],
    "notes" => ["sql" => "`p`.`notes`", "label" => "Notes"],
    "subtotal" => ["sql" => "`p`.`subtotal`", "label" => "Subtotal"],
    "tax" => ["sql" => "`p`.`tax`", "label" => "Tax"],
    "discount" => ["sql" => "`p`.`discount`", "label" => "Discount"],
    "total" => ["sql" => "`p`.`total`", "label" => "Total"],
    "status" => ["sql" => "`p`.`status`", "label" => "Status"],
    "approvalTime" => ["sql" => "`p`.`approvalTime`", "label" => "Approval Time"],
    "approverNotes" => ["sql" => "`p`.`approverNotes`", "label" => "Approver Notes"],
    "notifiedAt" => ["sql" => "`p`.`notifiedAt`", "label" => "Notified At"],
    "notifiedBy" => ["sql" => "CONCAT_WS(' ', `u6`.`firstName`, `u6`.`middleName`, `u6`.`lastName`)", "label" => "Notified By"],
    "createdAt" => ["sql" => "`p`.`createdAt`", "label" => "Created At"],
    "creatorId" => ["sql" => "CONCAT_WS(' ', `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`)", "label" => "Creator"],
    "updatedAt" => ["sql" => "`p`.`updatedAt`", "label" => "Updated At"],
    "updaterId" => ["sql" => "CONCAT_WS(' ', `u4`.`firstName`, `u4`.`middleName`, `u4`.`lastName`)", "label" => "Updated By"],
    "void" => ["sql" => "`p`.`void`", "label" => "Void"],
    "voidReason" => ["sql" => "`p`.`voidReason`", "label" => "Void Reason"],
    "validateReason" => ["sql" => "`p`.`validateReason`", "label" => "Validate Reason"],
    "lineItems" => ["sql" => "`p`.`data`", "label" => "Line Items"],
];
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
$selectFields = [];
$headers = [];
foreach($columns as $key){
    if(!isset($COLUMN_MAP[$key])) continue;
    $selectFields[] = $COLUMN_MAP[$key]['sql'] . " AS `$key`";
    $headers[] = $COLUMN_MAP[$key]['label'];
}
if(empty($selectFields)){
    http_response_code(400);
    echo "No valid columns";
    exit();
}
$rows = $db->all(
    "SELECT ".implode(", ", $selectFields)." " . proposalFromSql() . " $whereSql;",
    $params,
    __FILE__,
    __LINE__
);
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->freezePane("A2");
$colIndex = 1;
foreach($headers as $header){
    $sheet->setCellValue([$colIndex++, 1], $header);
}
$rowIndex = 2;
foreach($rows as $row){
    $colIndex = 1;
    foreach($columns as $key){
        if(!array_key_exists($key, $row)) continue;
        $value = $row[$key];
        if($key === 'lineItems'){
            $decoded = json_decode((string)$value, true);
            $value = is_array($decoded) ? json_encode($decoded) : $value;
        }
        $sheet->setCellValue([$colIndex++, $rowIndex], $value);
    }
    $rowIndex++;
}
for($i = 1; $i <= count($headers); $i++){
    $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
}
$filename = "proposals_export_" . date("Ymd_His") . ".xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit();
