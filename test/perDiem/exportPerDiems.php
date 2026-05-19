<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
require_once __DIR__."/helpers.php";
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
$start = array_key_exists("createdAtFrom", $_POST) ? $_POST["createdAtFrom"] : "";
$end = array_key_exists("createdAtTo", $_POST) ? $_POST["createdAtTo"] : "";
$columns = array_key_exists("columns", $_POST) ? json_decode($_POST["columns"], true) : [];
if(!$start || !$end || !is_array($columns) || empty($columns)){
    http_response_code(407);
    echo "Invalid export parameters";
    exit;
}
$projectLabel = perDiemProjectLabel("pr", "o");
$COLUMN_MAP = [
    "requesterId" => ["sql" => "CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)", "label" => "Requester"],
    "department" => ["sql" => "`u1`.`department`", "label" => "Department"],
    "projectId" => ["sql" => $projectLabel, "label" => "Project"],
    "startDate" => ["sql" => "`p`.`startDate`", "label" => "From Date"],
    "endDate" => ["sql" => "`p`.`endDate`", "label" => "To Date"],
    "hotelName" => ["sql" => "`p`.`hotelName`", "label" => "Hotel Name"],
    "hotelAddress" => ["sql" => "`p`.`hotelAddress`", "label" => "Hotel Address"],
    "notes" => ["sql" => "`p`.`notes`", "label" => "Notes"],
    "status" => ["sql" => "`p`.`status`", "label" => "Status"],
    "approvalTime" => ["sql" => "`p`.`approvalTime`", "label" => "Decision Time"],
    "approverId" => ["sql" => "CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)", "label" => "Approver"],
    "approverNotes" => ["sql" => "`p`.`approverNotes`", "label" => "Approver Notes"],
    "paid" => ["sql" => "`p`.`paid`", "label" => "Paid"],
    "void" => ["sql" => "`p`.`void`", "label" => "Void"],
    "voidReason" => ["sql" => "`p`.`voidReason`", "label" => "Void Reason"],
    "validateReason" => ["sql" => "`p`.`validateReason`", "label" => "Validate Reason"],
    "notifiedBy" => ["sql" => "CONCAT_WS(\" \", `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`)", "label" => "Notified By"],
    "notifiedAt" => ["sql" => "`p`.`notifiedAt`", "label" => "Notified At"],
    "creatorId" => ["sql" => "CONCAT_WS(\" \", `u4`.`firstName`, `u4`.`middleName`, `u4`.`lastName`)", "label" => "Creator"],
    "createdAt" => ["sql" => "`p`.`createdAt`", "label" => "Created At"],
    "updaterId" => ["sql" => "CONCAT_WS(\" \", `u5`.`firstName`, `u5`.`middleName`, `u5`.`lastName`)", "label" => "Updater"],
    "updatedAt" => ["sql" => "`p`.`updatedAt`", "label" => "Updated At"],
];
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
[$scopeSql, $scopeParams] = perDiemScope("p", $userId, getPerDiemAccess($db, $userId));
$search->raw($scopeSql, $scopeParams);
$whereSql = $search->getWhereSql();
$params = $search->getParams();
$selectFields = [];
$headers = [];
foreach($columns as $key){
    if(!isset($COLUMN_MAP[$key])) continue;
    $selectFields[] = $COLUMN_MAP[$key]["sql"] . " AS `$key`";
    $headers[] = $COLUMN_MAP[$key]["label"];
}
if(empty($selectFields)){
    http_response_code(400);
    echo "No valid columns";
    exit;
}
$rows = $db->all(
    "SELECT ".implode(", ", $selectFields)." FROM `perDiems` `p`
    LEFT JOIN `users` `u1` ON `u1`.`id` = `p`.`requesterId`
    LEFT JOIN `users` `u2` ON `u2`.`id` = `p`.`approverId`
    LEFT JOIN `users` `u3` ON `u3`.`id` = `p`.`notifiedBy`
    LEFT JOIN `users` `u4` ON `u4`.`id` = `p`.`creatorId`
    LEFT JOIN `users` `u5` ON `u5`.`id` = `p`.`updaterId`
    LEFT JOIN `projects` `pr` ON `pr`.`id` = `p`.`projectId`
    LEFT JOIN `organizations` `o` ON `o`.`id` = `pr`.`organizationId`
    $whereSql;",
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
        $sheet->setCellValue([$colIndex++, $rowIndex], $row[$key]);
    }
    $rowIndex++;
}
for($i = 1; $i <= count($headers); $i++){
    $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
}
$filename = "perdiems_export_".date("Ymd_His").".xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
