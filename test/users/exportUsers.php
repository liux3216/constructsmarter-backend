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
/* ---------- where builder ---------- */
$search = new SearchHelper("u");
$likeFields = [
    "email", "role", "phoneNumber", "workphone", "extension",
    "driverLicense", "ssn", "phaseLevel", "unionName",
    "invoiceNumber", "lanId", "residence", "residenceState",
    "street", "zipCode", "address", "background", "voidReason", "validateReason"
];
$equalFields = [
    "region", "department",
    "projects", "assignments", "purchases", "PerDiem", "reports", "forms",
    "personel", "fleets", "calendar", "timeOffs", "office", "allOffice",
    "outside", "outsideStatus", "metrics", "newspaper", "community",
    "training", "workOut", "assignmentNotification", "dispatch",
];
$betweenDateFields = ["birthDay", "hireDate", "quitDate"];
if (!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if ($_POST["void"] !== "all")     $search->equals("void", $_POST["void"]);
// "name" searches across all three name columns
$search->when(
    array_key_exists("name", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) LIKE ?",
        ["%" . $_POST["name"] . "%"]
    )
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
$whereSql = $search->getWhereSql();
$params   = $search->getParams();
$COLUMN_MAP = [
	"id"                  => ["sql" => "`u`.`id`",                  "label" => "User Id"],
    "firstName"           => ["sql" => "`u`.`firstName`",           "label" => "First Name"],
    "middleName"          => ["sql" => "`u`.`middleName`",          "label" => "Middle Name"],
    "lastName"            => ["sql" => "`u`.`lastName`",            "label" => "Last Name"],
	"email"               => ["sql" => "`u`.`email`",               "label" => "Email Address"],
    "phoneNumber"         => ["sql" => "`u`.`phoneNumber`",              "label" => "Mobile Phone"],
    "workphone"           => ["sql" => "`u`.`workphone`",           "label" => "Work Phone"],
    "extension"           => ["sql" => "`u`.`extension`",           "label" => "Office Extension"],
    "region"              => ["sql" => "`u`.`region`",              "label" => "Region"],
    "department"          => ["sql" => "`u`.`department`",          "label" => "Department"],
    "role"                => ["sql" => "`u`.`role`",                "label" => "Role"],
    "unionName"           => ["sql" => "`u`.`unionName`",           "label" => "Union Name"],
    "phaseLevel"          => ["sql" => "`u`.`phaseLevel`",          "label" => "Phase Level"],
    "hireDate"            => ["sql" => "`u`.`hireDate`",            "label" => "Hire Date"],
    "quitDate"            => ["sql" => "`u`.`quitDate`",            "label" => "Quit Date"],
    "ssn"                 => ["sql" => "`u`.`ssn`",                 "label" => "SSN"],
    "birthDay"            => ["sql" => "`u`.`birthDay`",            "label" => "Birthday"],
    "creatorId"           => ["sql" => "CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)", "label" => "Creator"],
    "createdAt"           => ["sql" => "`u`.`createdAt`",           "label" => "Created At"],
    "updaterId"           => ["sql" => "CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)", "label" => "Updater"],
    "updatedAt"           => ["sql" => "`u`.`updatedAt`",           "label" => "Updated At"],
    "projects"            => ["sql" => "`u`.`projects`",            "label" => "Projects"],
    "assignments"            => ["sql" => "`u`.`assignments`",            "label" => "Assignments"],
    "purchases"           => ["sql" => "`u`.`purchases`",           "label" => "Purchases"],
    "perDiem"             => ["sql" => "`u`.`PerDiem`",             "label" => "Per Diem"],
    "newspaper"           => ["sql" => "`u`.`newspaper`",           "label" => "Newspaper"],
    "fleets"              => ["sql" => "`u`.`fleets`",              "label" => "Fleets"],
    "reports"             => ["sql" => "`u`.`reports`",             "label" => "Reports"],
    "personel"            => ["sql" => "`u`.`personel`",            "label" => "Personnel"],
    "calendar"            => ["sql" => "`u`.`calendar`",            "label" => "Calendar"],
    "timeOffs"            => ["sql" => "`u`.`timeOffs`",            "label" => "Time Offs"],
    "office"              => ["sql" => "`u`.`office`",              "label" => "Office"],
    "allOffice"           => ["sql" => "`u`.`allOffice`",           "label" => "All Office"],
    "outside"             => ["sql" => "`u`.`outside`",             "label" => "Outside"],
    "outsideStatus"       => ["sql" => "`u`.`outsideStatus`",       "label" => "Outside Status"],
    "background"          => ["sql" => "`u`.`background`",          "label" => "Background"],
];

$selectFields = [];
$headers      = [];
foreach ($columns as $key) {
    if (!isset($COLUMN_MAP[$key])) continue;
    $selectFields[] = $COLUMN_MAP[$key]['sql'] . " AS `$key`";
    $headers[]      = $COLUMN_MAP[$key]['label'];
}

if (empty($selectFields)) {
    http_response_code(400);
    echo "No valid columns";
    exit;
}

$selectFieldsSQL = implode(", ", $selectFields);
$rows = $db->all(
	"SELECT $selectFieldsSQL
	FROM `users` `u`
	LEFT JOIN `users` `u1` ON `u1`.`id` = `u`.`creatorId`
	LEFT JOIN `users` `u2` ON `u2`.`id` = `u`.`updaterId`
	$whereSql", 
	$params, __FILE__, __LINE__
);
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->freezePane("A2");

$colIndex = 1;
foreach ($headers as $header) {
    $sheet->setCellValue([$colIndex++, 1], $header);
}

$rowIndex = 2;
foreach ($rows as $row) {
    $colIndex = 1;
    foreach ($columns as $key) {
        if (!array_key_exists($key, $row)) continue;
        $sheet->setCellValue([$colIndex++, $rowIndex], $row[$key]);
    }
    $rowIndex++;
}

$columnCount = count($headers);
for ($i = 1; $i <= $columnCount; $i++) {
    $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
}

$filename = "users_export_" . date("Ymd_His") . ".xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;