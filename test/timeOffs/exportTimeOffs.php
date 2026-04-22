<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
$start = array_key_exists("createdAtFrom", $_POST) ? $_POST["createdAtFrom"] : "";
$end = array_key_exists("createdAtTo", $_POST) ? $_POST["createdAtTo"] : "";
if(array_key_exists("columns", $_POST)) $columns = json_decode($_POST['columns'] ?? '[]', true);
if(!$start || !$end || !is_array($columns) || empty($columns)){
	http_response_code(407);
	echo "Invalid export parameters";
	exit;
}
$COLUMN_MAP = [
	"requester" => ["sql" => "CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)", "label" => "Requester"], 
	"department" => ["sql" => "`u1`.`department`", "label" => "Department"], 
	"type" => ["sql" => "`t`.`type`", "label" => "Type"], 
	"totalHours" => ["sql" => "`t`.`totalHours`", "label" => "Total Hours"], 
	"fromDate" => ["sql" => "`t`.`fromDate`", "label" => "From Date"], 
	"toDate" => ["sql" => "`t`.`toDate`", "label" => "To Date"], 
    "notes" => ["sql" => "`t`.`notes`", "label" => "Notes"], 
	"status" => ["sql" => "`t`.`status`", "label" => "Status"], 
    "approvalTime" => ["sql" => "`t`.`approvalTime`", "label" => "Decision Time"], 
	"approverId" => ["sql" => "CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)", "label" => "Approver"], 
    "approverNotes" => ["sql" => "`t`.`approverNotes`", "label" => "Approver Notes"], 
    "paid" => ["sql" => "`t`.`paid`", "label" => "Paid"], 
    "void" => ["sql" => "`t`.`void`", "label" => "Void"], 
    "voidReason" => ["sql" => "`t`.`voidReason`", "label" => "Void Reason"], 
    "validateReason" => ["sql" => "`t`.`validateReason`", "label" => "Validate Reason"], 
    "notifiedBy" => ["sql" => "CONCAT_WS(\" \", `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`)", "label" => "Notified By"], 
    "notifiedAt" => ["sql" => "`t`.`notifiedAt`", "label" => "Notified At"], 
    "creatorId" => ["sql" => "CONCAT_WS(\" \", `u4`.`firstName`, `u4`.`middleName`, `u4`.`lastName`)", "label" => "Creator"], 
    "createdAt" => ["sql" => "`t`.`createdAt`", "label" => "Created At"], 
    "updaterId" => ["sql" => "CONCAT_WS(\" \", `u5`.`firstName`, `u5`.`middleName`, `u5`.`lastName`)", "label" => "Updater"], 
    "updatedAt" => ["sql" => "`t`.`updatedAt`", "label" => "Updated At"], 
];
/* ---------- search ---------- */
$search = new SearchHelper("timeOffs");
$likeFields         = ["notes", "approverNotes", "voidReason", "validateReason"];
$equalFields        = ["requesterId", "type", "approverId", "status", "paid", "notifiedBy", "updaterId"];
$betweenDateFields  = ["fromDate", "toDate", "notifiedAt", "approvalTime", "updatedAt"];
if (!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if ($_POST["void"] !== "all")     $search->equals("void", $_POST["void"]);
$search->when(
    array_key_exists("department", $_POST),
    fn($q) => $q->raw("`u1`.`department` = ?", [$_POST["department"]])
);
foreach ($likeFields as $field) {
    $search->like($field, $_POST[$field] ?? null);
}
foreach ($equalFields as $field) {
    $search->equals($field, $_POST[$field] ?? null);
}
foreach ($betweenDateFields as $field) {
    $search->between($field, "datetime");
}
$search->between("totalHours");  // number range, uses default type
$whereSql = $search->getWhereSql();
$params   = $search->getParams();
$selectFields = [];
$headers = [];
foreach($columns as $key){
	if (!isset($COLUMN_MAP[$key])) continue;
	$selectFields[] = $COLUMN_MAP[$key]['sql'] . " AS `$key`";
	$headers[] = $COLUMN_MAP[$key]['label'];
}
if(empty($selectFields)){
	http_response_code(400);
	echo "No valid columns";
	exit();
}
$selectFieldsSQL = implode(", ", $selectFields);
$rows = $db->all(
	"SELECT $selectFieldsSQL FROM `timeOffs`
	LEFT JOIN `users` `u1` ON `u1`.`id` = `timeOffs`.`requesterId`
	LEFT JOIN `users` `u2` ON `u2`.`id` = `timeOffs`.`approverId`
    LEFT JOIN `users` `u3` ON `u3`.`id` = `timeOffs`.`notifiedBy`
    LEFT JOIN `users` `u4` ON `u4`.`id` = `timeOffs`.`creatorId`
    LEFT JOIN `users` `u5` ON `u5`.`id` = `timeOffs`.`updaterId`
	$whereSql;", 
	$params, __FILE__, __LINE__
);
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->freezePane("A2");
$colIndex = 1;
foreach($headers as $header){
	$sheet->setCellValue([$colIndex++, 1], $header);
}
$rowIndex = 2;
foreach ($rows as $row) {
	$colIndex = 1;
	foreach ($columns as $key) {
		if(!array_key_exists($key, $row)) continue;
		$sheet->setCellValue([$colIndex++, $rowIndex], $row[$key]);
	}
	$rowIndex++;
}
$columnCount = count($headers);
for ($i = 1; $i <= $columnCount; $i++) {
	$sheet
		->getColumnDimensionByColumn($i)
		->setAutoSize(true);
}
$filename = "timeoffs_export_" . date("Ymd_His") . ".xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit();
