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
    "id" => ["sql" => "`services`.`id`", "label" => "Service ID"],
    "code" => ["sql" => "`services`.`code`", "label" => "Code"],
    "name" => ["sql" => "`services`.`name`", "label" => "Name"],
    "category" => ["sql" => "`services`.`category`", "label" => "Category"],
    "price" => ["sql" => "`services`.`price`", "label" => "Price"],
    "costType" => ["sql" => "`services`.`costType`", "label" => "Cost Type"],
    "notes" => ["sql" => "`services`.`notes`", "label" => "Notes"],
    "voidReason" => ["sql" => "`services`.`voidReason`", "label" => "Void Reason"],
    "validateReason" => ["sql" => "`services`.`validateReason`", "label" => "Validate Reason"],
    "creatorId" => ["sql" => "CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)", "label" => "Creator"],
    "createdAt" => ["sql" => "`services`.`createdAt`", "label" => "Created At"],
    "updaterId" => ["sql" => "CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)", "label" => "Updater"],
    "updatedAt" => ["sql" => "`services`.`updatedAt`", "label" => "Updated At"],
];
$search = new SearchHelper("services");
$likeFields = ["code", "name", "category", "costType", "notes", "voidReason", "validateReason"];
$equalFields = ["id", "creatorId", "updaterId"];
$betweenFields = ["price"];
$betweenDateTimeFields = ["createdAt", "updatedAt"];
if(!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if($_POST["void"] !== "all") $search->equals("void", $_POST["void"]);
foreach($likeFields as $field){
    $search->like($field, $_POST[$field] ?? null);
}
foreach($equalFields as $field){
    $search->equals($field, $_POST[$field] ?? null);
}
foreach($betweenFields as $field){
    $search->between($field, "number");
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
    "SELECT ".implode(", ", $selectFields)." FROM `services`
    LEFT JOIN `users` `u1` ON `u1`.`id` = `services`.`creatorId`
    LEFT JOIN `users` `u2` ON `u2`.`id` = `services`.`updaterId` $whereSql;",
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
$filename = "services_export_" . date("Ymd_His") . ".xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit();
