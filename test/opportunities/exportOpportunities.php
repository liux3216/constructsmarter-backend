<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $mainUrl
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
$columns = json_decode($_POST['columns'] ?? '[]', true);
if(!is_array($columns) || empty($columns)){
	http_response_code(407);
	echo "Invalid export parameters";
	exit;
}
$COLUMN_MAP = [
	"opportunityName" => ["sql" => "`opportunities`.`opportunityName`", "label" => "Opportunity Name"],
	"organizationName" => ["sql" => "`organizations`.`name`", "label" => "Organization Name"], 
	"probability" => ["sql" => "`opportunities`.`probability`", "label" => "Probability"],
	"bidAmount" => ["sql" => "`opportunities`.`bidAmount`", "label" => "Bid Amount"],
	"bidType" => ["sql" => "`opportunities`.`bidType`", "label" => "Bid Type"],
	"category" => ["sql" => "`opportunities`.`category`", "label" => "Category"],
	"state" => ["sql" => "`opportunities`.`state`", "label" => "State"],
	"location" => ["sql" => "`opportunities`.`location`", "label" => "Location"],
	"actualCloseDate" => ["sql" => "`opportunities`.`actualCloseDate`", "label" => "Actual Close Date"],
	// { key: "projectId", label: "Project Id" },
	"contactIds" => ["sql" => "(SELECT GROUP_CONCAT(CONCAT_WS(' ', `c`.`firstName`, `c`.`middleName`, `c`.`lastName`) SEPARATOR ', ') FROM `opportunities_contact` `oc` LEFT JOIN `contacts` `c` ON `c`.`id` = `oc`.`contactId` WHERE `oc`.`opportunityId` = `opportunities`.`id`)", "label" => "Contacts"],
	"userResponsibleIds" => ["sql" => "(SELECT GROUP_CONCAT(CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) SEPARATOR ', ') FROM `opportunities_userResponsible` `our` LEFT JOIN `users` `u` ON `u`.`id` = `our`.`userId` WHERE `our`.`opportunityId` = `opportunities`.`id`)", "label" => "Users Responsible"],
	"background" => ["sql" => "`opportunities`.`background`", "label" => "Background"], 
	"void" => ["sql" => "`opportunities`.`void`", "label" => "Void"], 
    "voidReason" => ["sql" => "`opportunities`.`voidReason`", "label" => "Void Reason"], 
    "validateReason" => ["sql" => "`opportunities`.`validateReason`", "label" => "Validate Reason"], 
    "creatorName" => ["sql" => "CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)", "label" => "Creator"], 
    "createdAt" => ["sql" => "`opportunities`.`createdAt`", "label" => "Created At"], 
    "updaterName" => ["sql" => "CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)", "label" => "Updater"], 
    "updatedAt" => ["sql" => "`opportunities`.`updatedAt`", "label" => "Updated At"], 
];
/* ---------- search builder ---------- */
$search = new SearchHelper("opportunities");
$likeFields = ["background", "opportunityName", "location", "voidReason", "validateReason"];
$equalFields = ["creatorId", "updaterId", "bidType", "category", "state"];
$betweenDateTimeFields = ["createdAt", "updatedAt"];
$betweenDateFields = ["actualCloseDate", "probability", "bidAmount"];
$search->equals("organizationId", requireInt($_POST, "organizationId", null, null, false));
$contactId = requireInt($_POST, "contactId", null, null, false);
if ($contactId !== null) {
    $search->raw(
        "EXISTS (SELECT 1 FROM `opportunities_contact` WHERE `opportunities_contact`.`opportunityId` = `opportunities`.`id` AND `opportunities_contact`.`contactId` = ?)",
        [$contactId]
    );
}
$userResponsibleId = array_key_exists("userResponsibleId", $_POST) ? $_POST["userResponsibleId"] : null;
if ($userResponsibleId !== null) {
    $search->raw(
        "EXISTS (SELECT 1 FROM `opportunities_userResponsible` WHERE `opportunities_userResponsible`.`opportunityId` = `opportunities`.`id` AND `opportunities_userResponsible`.`userId` = ?)",
        [$userResponsibleId]
    );
}
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
foreach($betweenDateFields as $field){
    $search->between($field);
}
$whereSql = $search->getWhereSql();
$params   = $search->getParams();
$selectFields = [];
$headers = [];
$columns = array_values(array_filter($columns, fn($key) => array_key_exists($key, $COLUMN_MAP)));
foreach($columns as $key){
	$selectFields[] = $COLUMN_MAP[$key]['sql'] . " AS `$key`";
	switch($key){
		case "organizationName":
			$selectFields[] = "`opportunities`.`organizationId`";
			break;
		case "creatorName":
			$selectFields[] = "`opportunities`.`creatorId`";
			break;
		case "updaterName":
			$selectFields[] = "`opportunities`.`updaterId`";
			break;
		default:
			break;
	}
	$headers[] = $COLUMN_MAP[$key]['label'];
}
if(empty($selectFields)){
	http_response_code(400);
	echo "No valid columns";
	exit();
}


if(empty($selectFields)){
	http_response_code(400);
	echo "No valid columns";
	exit();
}
$selectFieldsSQL = implode(", ", $selectFields);
$rows = $db->all(
	"SELECT $selectFieldsSQL FROM `opportunities`
	LEFT JOIN `users` `u1` ON `u1`.`id` = `opportunities`.`creatorId`
	LEFT JOIN `users` `u2` ON `u2`.`id` = `opportunities`.`updaterId`
	LEFT JOIN `organizations` ON `organizations`.`id` = `opportunities`.`organizationId` $whereSql;", 
	$params, __FILE__, __LINE__
);
/* ---------- style helpers ---------- */
// Status color map
$statusColors = [
    "Complete"    => "FF70AD47", // green
    "In Progress" => "FF800080", // purrle
    "Created"     => "FF800080", // red
];

$thinBorder = [
    "borders" => [
        "allBorders" => [
            "borderStyle" => Border::BORDER_THIN,
            "color"       => ["argb" => "FFD0D0D0"],
        ],
    ],
];
$headerStyle = [
    "font" => [
        "bold"  => true,
        "color" => ["argb" => "FFFFFFFF"],
        "size"  => 11,
        "name"  => "Calibri",
    ],
    "fill" => [
        "fillType"   => Fill::FILL_SOLID,
        "startColor" => ["argb" => "FF2E75B6"], // deep blue
    ],
    "alignment" => [
        "horizontal" => Alignment::HORIZONTAL_CENTER,
        "vertical"   => Alignment::VERTICAL_CENTER,
    ],
    "borders" => [
        "allBorders" => [
            "borderStyle" => Border::BORDER_THIN,
            "color"       => ["argb" => "FFFFFFFF"],
        ],
    ],
];
$rowEven = "FFF2F7FB"; // very light blue
$rowOdd  = "FFFFFFFF"; // white
/* ---------- build spreadsheet ---------- */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setShowGridlines(false);
$sheet->freezePane("A2");
$columnCount = count($headers);
// ── Header row ────────────────────────────────────────────────────────────────
$sheet->getRowDimension(1)->setRowHeight(28);
$colIndex = 1;
foreach($headers as $header){
    $cellptr = [$colIndex, 1];
    $sheet->setCellValue($cellptr, $header);
    $sheet->getStyle($cellptr)->applyFromArray($headerStyle);
    $colIndex++;
}
// ── Data rows ─────────────────────────────────────────────────────────────────
$rowIndex = 2;
foreach ($rows as $row) {
	$colIndex   = 1;
	$isEven     = ($rowIndex % 2 === 0);
    $rowBgColor = $isEven ? $rowEven : $rowOdd;
	// Set default row height
    $sheet->getRowDimension($rowIndex)->setRowHeight(22);
    foreach ($columns as $key) {
        if(!array_key_exists($key, $row)) continue;
        $value   = $row[$key];
        $cellptr = [$colIndex, $rowIndex];
        switch($key){
            case "organizationName":
                $organizationId = $row["organizationId"];
                $sheet->setCellValueExplicit($cellptr, $row[$key], DataType::TYPE_STRING);
                $sheet->getCell($cellptr)->getHyperlink()->setUrl("$mainUrl/Organizations/$organizationId");
                $sheet->getStyle($cellptr)->getFont()->setUnderline(Font::UNDERLINE_SINGLE);
                $sheet->getStyle($cellptr)->getFont()->setColor(new Color("FF0563C1"));
                break;
            case "creatorName":
                $creatorId = $row["creatorId"];
                $sheet->setCellValueExplicit($cellptr, $row[$key], DataType::TYPE_STRING);
                $sheet->getCell($cellptr)->getHyperlink()->setUrl("$mainUrl/Users/$creatorId");
                $sheet->getStyle($cellptr)->getFont()->setUnderline(Font::UNDERLINE_SINGLE);
                $sheet->getStyle($cellptr)->getFont()->setColor(new Color("FF0563C1"));
                break;
            case "updaterName":
                $updaterId = $row["updaterId"];
                $sheet->setCellValueExplicit($cellptr, $row[$key], DataType::TYPE_STRING);
                $sheet->getCell($cellptr)->getHyperlink()->setUrl("$mainUrl/Users/$updaterId");
                $sheet->getStyle($cellptr)->getFont()->setUnderline(Font::UNDERLINE_SINGLE);
                $sheet->getStyle($cellptr)->getFont()->setColor(new Color("FF0563C1"));
                break;
            default:
                $sheet->setCellValue($cellptr, $value);
                break;
        }
        $sheet->getStyle($cellptr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($rowBgColor);
        // Alignment + border for every cell
        $sheet->getStyle($cellptr)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($cellptr)->applyFromArray($thinBorder);
        $colIndex++;
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
