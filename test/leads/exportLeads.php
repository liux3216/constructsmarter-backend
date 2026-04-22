<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
$columns = json_decode($_POST['columns'] ?? '[]', true);
if(!is_array($columns) || empty($columns)){
	http_response_code(407);
	echo "Invalid export parameters";
	exit;
}
$COLUMN_MAP = [
	"firstName" => ["sql" => "`leads`.`firstName`", "label" => "First Name"],
	"middleName" => ["sql" => "`leads`.`middleName`", "label" => "Middle Name"],
	"lastName" => ["sql" => "`leads`.`lastName`", "label" => "Last Name"], 
	"organizationName" => ["sql" => "`organizations`.`name`", "label" => "Organization Name"], 

	"website" => ["sql" => "`leads`.`website`", "label" => "Website"], 
	"industry" => ["sql" => "`leads`.`industry`", "label" => "Industry"], 
	"source" => ["sql" => "`leads`.`source`", "label" => "Source"], 
	"status" => ["sql" => "`leads`.`status`", "label" => "Status"], 
	"referredBy" => ["sql" => "CONCAT_WS(\" \", `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`)", "label" => "Referred By"], 
	"uerResponsible1" => ["sql" => "CONCAT_WS(\" \", `u4`.`firstName`, `u4`.`middleName`, `u4`.`lastName`)", "label" => "User Responsible 1"], 
	"uerResponsible2" => ["sql" => "CONCAT_WS(\" \", `u5`.`firstName`, `u5`.`middleName`, `u5`.`lastName`)", "label" => "User Responsible 2"], 

	"businessPhone" => ["sql" => "`leads`.`businessPhone`", "label" => "Business Phone"], 
	"extension" => ["sql" => "`leads`.`extension`", "label" => "Extension"], 
	"fax" => ["sql" => "`leads`.`fax`", "label" => "Fax"], 
	"mobile" => ["sql" => "`leads`.`mobile`", "label" => "Direct Number"], 
	"email" => ["sql" => "`leads`.`email`", "label" => "Email 1"], 
	"street" => ["sql" => "`leads`.`street`", "label" => "Street"], 
	"city" => ["sql" => "`leads`.`city`", "label" => "City"], 
	"state" => ["sql" => "`leads`.`state`", "label" => "State"], 
	"zipCode" => ["sql" => "`leads`.`zipCode`", "label" => "Zip Code"], 
	"overseaAddress" => ["sql" => "`leads`.`overseaAddress`", "label" => "Oversea Address"], 
	"background" => ["sql" => "`leads`.`background`", "label" => "Background"], 
	"void" => ["sql" => "`leads`.`void`", "label" => "Void"], 
    "voidReason" => ["sql" => "`leads`.`voidReason`", "label" => "Void Reason"], 
    "validateReason" => ["sql" => "`leads`.`validateReason`", "label" => "Validate Reason"], 
    "creatorId" => ["sql" => "CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)", "label" => "Creator"], 
    "createdAt" => ["sql" => "`leads`.`createdAt`", "label" => "Created At"], 
    "updaterId" => ["sql" => "CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)", "label" => "Updater"], 
    "updatedAt" => ["sql" => "`leads`.`updatedAt`", "label" => "Updated At"], 
];
/* ---------- search builder ---------- */
$search = new SearchHelper("leads");
$likeFields = ["businessPhone", "extension", "fax", "mobile", "background", "overseaAddress", "email", "role", "website", "industry", "voidReason", "validateReason"];
$equalFields = ["creatorId", "updaterId", "source", "status", "referredBy", "userResponsible1", "userResponsible2"];
$betweenDateTimeFields = ["createdAt", "updatedAt"];
$search->equals("organizationId", requireInt($_POST, "organizationId", null, null, false));
$search->when(
    array_key_exists("address", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `leads`.`street`, `leads`.`city`, `leads`.`state`, `leads`.`zipCode`) LIKE ?",
        ["%" . $_POST["address"] . "%"]
    )
);
$search->when(
    array_key_exists("name", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `leads`.`firstName`, `leads`.`middleName`, `leads`.`lastName`) LIKE ?",
        ["%".$_POST["name"]."%"]
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
	"SELECT $selectFieldsSQL FROM `leads`
	LEFT JOIN `users` `u1` ON `u1`.`id` = `leads`.`creatorId`
	LEFT JOIN `users` `u2` ON `u2`.`id` = `leads`.`updaterId`
	LEFT JOIN `users` `u3` ON `u3`.`id` = `leads`.`referredBy`
	LEFT JOIN `users` `u4` ON `u4`.`id` = `leads`.`userResponsible1`
	LEFT JOIN `users` `u5` ON `u5`.`id` = `leads`.`userResponsible2`
	LEFT JOIN `organizations` ON `organizations`.`id` = `leads`.`organizationId` $whereSql;", 
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
