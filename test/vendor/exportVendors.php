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
    "vendorName" => ["sql" => "`vendors`.`vendorName`", "label" => "Vendor Name"],
    "website" => ["sql" => "`vendors`.`website`", "label" => "Website"],
    "phoneNumber" => ["sql" => "`vendors`.`phoneNumber`", "label" => "Business Phone"],
    "extension" => ["sql" => "`vendors`.`extension`", "label" => "Extension"],
    "fax" => ["sql" => "`vendors`.`fax`", "label" => "Fax"],
    "street" => ["sql" => "`vendors`.`street`", "label" => "Street"],
    "city" => ["sql" => "`vendors`.`city`", "label" => "City"],
    "state" => ["sql" => "`vendors`.`state`", "label" => "State"],
    "zipCode" => ["sql" => "`vendors`.`zipCode`", "label" => "Zip Code"],
    "country" => ["sql" => "`vendors`.`country`", "label" => "Country"],
    "background" => ["sql" => "`vendors`.`background`", "label" => "Background"],
    "voidReason" => ["sql" => "`vendors`.`voidReason`", "label" => "Void Reason"],
    "validateReason" => ["sql" => "`vendors`.`validateReason`", "label" => "Validate Reason"],
    "creatorId" => ["sql" => "CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)", "label" => "Creator"],
    "createdAt" => ["sql" => "`vendors`.`createdAt`", "label" => "Created At"],
    "updaterId" => ["sql" => "CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)", "label" => "Updater"],
    "updatedAt" => ["sql" => "`vendors`.`updatedAt`", "label" => "Updated At"],
];
$search = new SearchHelper("vendors");
$likeFields = ["vendorName", "website", "phoneNumber", "extension", "fax", "country", "background", "voidReason", "validateReason"];
$equalFields = ["creatorId", "updaterId"];
$betweenDateTimeFields = ["createdAt", "updatedAt"];
$search->when(
    array_key_exists("address", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `street`, `city`, `state`, `zipCode`, `country`) LIKE ?",
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
    "SELECT ".implode(", ", $selectFields)." FROM `vendors`
    LEFT JOIN `users` `u1` ON `u1`.`id` = `vendors`.`creatorId`
    LEFT JOIN `users` `u2` ON `u2`.`id` = `vendors`.`updaterId` $whereSql;",
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
$filename = "vendors_export_" . date("Ymd_His") . ".xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit();
