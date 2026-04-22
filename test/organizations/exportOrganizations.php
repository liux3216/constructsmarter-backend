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
	"name" => ["sql" => "`organizations`.`name`", "label" => "Organization Name"], 
	"website" => ["sql" => "`organizations`.`website`", "label" => "Website"], 
	"phoneNumber" => ["sql" => "`organizations`.`phoneNumber`", "label" => "Business Phone"], 
	"extension" => ["sql" => "`organizations`.`extension`", "label" => "Extension"], 
	"fax" => ["sql" => "`organizations`.`fax`", "label" => "Fax"], 
	"street" => ["sql" => "`organizations`.`street`", "label" => "Billing Street"], 
	"city" => ["sql" => "`organizations`.`city`", "label" => "Billing City"], 
	"state" => ["sql" => "`organizations`.`state`", "label" => "Billing State"], 
	"zipCode" => ["sql" => "`organizations`.`zipCode`", "label" => "Billing Zip Code"], 
	"overseaAddress" => ["sql" => "`organizations`.`overseaAddress`", "label" => "Oversea Billing Address"], 
	"background" => ["sql" => "`organizations`.`background`", "label" => "Background"], 
	"void" => ["sql" => "`organizations`.`void`", "label" => "Void"], 
    "voidReason" => ["sql" => "`organizations`.`voidReason`", "label" => "Void Reason"], 
    "validateReason" => ["sql" => "`organizations`.`validateReason`", "label" => "Validate Reason"], 
    "creatorId" => ["sql" => "CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)", "label" => "Creator"], 
    "createdAt" => ["sql" => "`organizations`.`createdAt`", "label" => "Created At"], 
    "updaterId" => ["sql" => "CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)", "label" => "Updater"], 
    "updatedAt" => ["sql" => "`organizations`.`updatedAt`", "label" => "Updated At"], 
];
/* ---------- search builder ---------- */
$search = new SearchHelper("organizations");
$likeFields = ["name", "website", "phoneNumber", "extension", "fax", "background", "overseaAddress", "voidReason", "validateReason"];
$equalFields = ["creatorId", "updaterId"];
$betweenDateTimeFields = ["createdAt", "updatedAt"];
$search->when(
    array_key_exists("address", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `street`, `city`, `state`, `zipCode`) LIKE ?",
        ["%" . $_POST["address"] . "%"]
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
	"SELECT $selectFieldsSQL FROM `organizations`
	LEFT JOIN `users` `u1` ON `u1`.`id` = `organizations`.`creatorId`
	LEFT JOIN `users` `u2` ON `u2`.`id` = `organizations`.`updaterId` $whereSql;", 
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
