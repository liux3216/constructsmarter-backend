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
	"firstName" => ["sql" => "`contacts`.`firstName`", "label" => "First Name"],
	"middleName" => ["sql" => "`contacts`.`middleName`", "label" => "Middle Name"],
	"lastName" => ["sql" => "`contacts`.`lastName`", "label" => "Last Name"], 
	"organizationName" => ["sql" => "`organizations`.`name`", "label" => "Organization Name"], 
	"role" => ["sql" => "`contacts`.`role`", "label" => "Role"], 
	"website" => ["sql" => "`contacts`.`website`", "label" => "Website"], 
	"phoneNumber" => ["sql" => "`contacts`.`phoneNumber`", "label" => "Business Phone"], 
	"extension" => ["sql" => "`contacts`.`extension`", "label" => "Extension"], 
	"fax" => ["sql" => "`contacts`.`fax`", "label" => "Fax"], 
	"directNumber" => ["sql" => "`contacts`.`directNumber`", "label" => "Direct Number"], 
	"email1" => ["sql" => "`contacts`.`email1`", "label" => "Email 1"], 
	"email2" => ["sql" => "`contacts`.`email2`", "label" => "Email 2"], 
	"street" => ["sql" => "`contacts`.`street`", "label" => "Street"], 
	"city" => ["sql" => "`contacts`.`city`", "label" => "City"], 
	"state" => ["sql" => "`contacts`.`state`", "label" => "State"], 
	"zipCode" => ["sql" => "`contacts`.`zipCode`", "label" => "Zip Code"], 
	"overseaAddress" => ["sql" => "`contacts`.`overseaAddress`", "label" => "Oversea Address"], 
	"background" => ["sql" => "`contacts`.`background`", "label" => "Background"], 
	"void" => ["sql" => "`contacts`.`void`", "label" => "Void"], 
    "voidReason" => ["sql" => "`contacts`.`voidReason`", "label" => "Void Reason"], 
    "validateReason" => ["sql" => "`contacts`.`validateReason`", "label" => "Validate Reason"], 
    "creatorId" => ["sql" => "CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)", "label" => "Creator"], 
    "createdAt" => ["sql" => "`contacts`.`createdAt`", "label" => "Created At"], 
    "updaterId" => ["sql" => "CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)", "label" => "Updater"], 
    "updatedAt" => ["sql" => "`contacts`.`updatedAt`", "label" => "Updated At"], 
];
/* ---------- search builder ---------- */
$search = new SearchHelper("contacts");
$likeFields = ["phoneNumber", "extension", "fax", "directNumber", "background", "overseaAddress", "role", "voidReason", "validateReason"];
$equalFields = ["creatorId", "updaterId"];
$betweenDateTimeFields = ["createdAt", "updatedAt"];
$search->equals("organizationId", requireInt($_POST, "organizationId", null, null, false));
$search->when(
    array_key_exists("address", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `contacts`.`street`, `contacts`.`city`, `contacts`.`state`, `contacts`.`zipCode`) LIKE ?",
        ["%" . $_POST["address"] . "%"]
    )
);
$search->when(
    array_key_exists("name", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `contacts`.`firstName`, `contacts`.`middleName`, `contacts`.`lastName`) LIKE ?",
        ["%".$_POST["name"]."%"]
    )
);
$search->when(
    array_key_exists("email", $_POST),
    fn($q) => $q->raw(
        "(`contacts`.`email1` LIKE ? OR `contacts`.`email2` LIKE ?)",
        ["%".$_POST["email"]."%", "%".$_POST["email"]."%"]
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
	"SELECT $selectFieldsSQL FROM `contacts`
	LEFT JOIN `users` `u1` ON `u1`.`id` = `contacts`.`creatorId`
	LEFT JOIN `users` `u2` ON `u2`.`id` = `contacts`.`updaterId`
	LEFT JOIN `organizations` ON `organizations`.`id` = `contacts`.`organizationId` $whereSql;", 
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
