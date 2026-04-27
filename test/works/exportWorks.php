<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
require_once "/opt/bitnami/apache/htdocs/test/constants.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$columns = json_decode($_POST["columns"] ?? "[]", true);
if (!is_array($columns) || empty($columns)) {
    http_response_code(407);
    echo "Invalid export parameters";
    exit;
}

$projectNameSql = "CONCAT_WS(' - ',
    NULLIF(TRIM(`p`.`projectNumber`), ''),
    NULLIF(TRIM(`org`.`name`), ''),
    NULLIF(TRIM(`p`.`clientProjectNumber`), '')
)";

$COLUMN_MAP = [
    "projectName"     => ["sql" => $projectNameSql, "label" => "Project Name"],
    "category"        => ["sql" => "`works`.`category`", "label" => "Category"],
    "subCategory"     => ["sql" => "`works`.`subCategory`", "label" => "Sub Category"],
    "location"        => ["sql" => "`works`.`location`", "label" => "Location"],
    "jobTagLocation"  => ["sql" => "`works`.`jobTagLocation`", "label" => "Job Tag Location"],
    "startTime"       => ["sql" => "`works`.`startTime`", "label" => "Start Time"],
    "endTime"         => ["sql" => "`works`.`endTime`", "label" => "End Time"],
    "allDay"          => ["sql" => "`works`.`allDay`", "label" => "All Day"],
    "supervisorName"  => ["sql" => "CONCAT_WS(' ', `supervisorUser`.`firstName`, `supervisorUser`.`middleName`, `supervisorUser`.`lastName`)", "label" => "Supervisor"],
    "siteContactName" => ["sql" => "CONCAT_WS(' ', `siteContact`.`firstName`, `siteContact`.`middleName`, `siteContact`.`lastName`)", "label" => "Site Contact"],
    "cadRequired"     => ["sql" => "`works`.`cadRequired`", "label" => "CAD Required"],
    "reportRequired"  => ["sql" => "`works`.`reportRequired`", "label" => "Report Required"],
    "waiveJSA"        => ["sql" => "`works`.`waiveJSA`", "label" => "Waive JSA"],
    "leadName"        => ["sql" => "CONCAT_WS(' ', `leadUser`.`firstName`, `leadUser`.`middleName`, `leadUser`.`lastName`)", "label" => "Lead"],
    "description"     => ["sql" => "`works`.`description`", "label" => "Description"],
    "void"            => ["sql" => "`works`.`void`", "label" => "Void"],
    "voidReason"      => ["sql" => "`works`.`voidReason`", "label" => "Void Reason"],
    "creatorName"     => ["sql" => "CONCAT_WS(' ', `creatorUser`.`firstName`, `creatorUser`.`middleName`, `creatorUser`.`lastName`)", "label" => "Creator"],
    "createdAt"       => ["sql" => "`works`.`createdAt`", "label" => "Created At"],
    "updaterName"     => ["sql" => "CONCAT_WS(' ', `updaterUser`.`firstName`, `updaterUser`.`middleName`, `updaterUser`.`lastName`)", "label" => "Updater"],
    "updatedAt"       => ["sql" => "`works`.`updatedAt`", "label" => "Updated At"],
];

function yesNoFilterValue($value): ?string
{
    if ($value === null || $value === "") {
        return null;
    }

    $value = strtolower(trim((string)$value));
    if ($value === "1") {
        return "yes";
    }
    if ($value === "0") {
        return "no";
    }

    return $value;
}

function setLinkedCell($sheet, array $cellptr, $value, string $url): void
{
    $sheet->setCellValueExplicit($cellptr, $value, DataType::TYPE_STRING);
    if ($value !== "" && substr($url, -1) !== "/") {
        $sheet->getCell($cellptr)->getHyperlink()->setUrl($url);
        $sheet->getStyle($cellptr)->getFont()->setUnderline(Font::UNDERLINE_SINGLE);
        $sheet->getStyle($cellptr)->getFont()->setColor(new Color("FF0563C1"));
    }
}

/* ---------- search builder ---------- */
$search = new SearchHelper("works");
$fromSql = "FROM `works`
LEFT JOIN `projects` `p` ON `p`.`id` = `works`.`projectId`
LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
LEFT JOIN `users` `supervisorUser` ON `supervisorUser`.`id` = `works`.`supervisorId`
LEFT JOIN `contacts` `siteContact` ON `siteContact`.`id` = `works`.`siteContactId`
LEFT JOIN `users` `leadUser` ON `leadUser`.`id` = `works`.`leadId`
LEFT JOIN `users` `creatorUser` ON `creatorUser`.`id` = `works`.`creatorId`
LEFT JOIN `users` `updaterUser` ON `updaterUser`.`id` = `works`.`updaterId`";
$likeFields = [
    "location",
    "description",
    "voidReason",
    "validateReason",
];
$equalFields = [
    "category",
    "subCategory",
    "supervisorId",
    "siteContactId",
    "leadId",
    "creatorId",
    "updaterId",
];
$yesNoFields = [
    "allDay",
    "jobTagLocation",
    "cadRequired",
    "reportRequired",
    "waiveJSA",
];
$search->when(
    array_key_exists("projectName", $_POST) && $_POST["projectName"] !== "",
    fn($q) => $q->raw(
        "CONCAT_WS(' - ',
            NULLIF(TRIM(`p`.`projectNumber`), ''),
            NULLIF(TRIM(`org`.`name`), ''),
            NULLIF(TRIM(`p`.`clientProjectNumber`), '')
        ) LIKE ?",
        ["%" . $_POST["projectName"] . "%"]
    )
);
if (!array_key_exists("void", $_POST)) {
    $search->equals("void", "no");
} else if ($_POST["void"] !== "all") {
    $search->equals("void", $_POST["void"]);
}
foreach ($likeFields as $field) {
    $search->like($field, $_POST[$field] ?? null);
}
foreach ($equalFields as $field) {
    $search->equals($field, $_POST[$field] ?? null);
}
foreach ($yesNoFields as $field) {
    $search->equals($field, yesNoFilterValue($_POST[$field] ?? null));
}
foreach (["startTime", "endTime", "createdAt", "updatedAt"] as $field) {
    $search->between($field, "datetime");
}
$whereSql = $search->getWhereSql();
$params = $search->getParams();

/* ---------- build select ---------- */
$columns = array_values(array_filter($columns, fn($key) => array_key_exists($key, $COLUMN_MAP)));
if (empty($columns)) {
    http_response_code(400);
    echo "No valid columns";
    exit();
}

$selectFields = [];
$headers = [];
foreach ($columns as $key) {
    $selectFields[] = $COLUMN_MAP[$key]["sql"] . " AS `$key`";
    switch ($key) {
        case "projectName":
            $selectFields[] = "`works`.`projectId`";
            break;
        case "supervisorName":
            $selectFields[] = "`works`.`supervisorId`";
            break;
        case "siteContactName":
            $selectFields[] = "`works`.`siteContactId`";
            break;
        case "leadName":
            $selectFields[] = "`works`.`leadId`";
            break;
        case "creatorName":
            $selectFields[] = "`works`.`creatorId`";
            break;
        case "updaterName":
            $selectFields[] = "`works`.`updaterId`";
            break;
    }
    $headers[] = $COLUMN_MAP[$key]["label"];
}

$selectFieldsSQL = implode(", ", $selectFields);
$rows = $db->all(
    "SELECT $selectFieldsSQL
    $fromSql
    $whereSql
    ORDER BY `works`.`createdAt` DESC;",
    $params,
    __FILE__,
    __LINE__
);

/* ---------- style helpers ---------- */
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
        "startColor" => ["argb" => "FF2E75B6"],
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
$rowEven = "FFF2F7FB";
$rowOdd = "FFFFFFFF";

/* ---------- build spreadsheet ---------- */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setShowGridlines(false);
$sheet->freezePane("A2");

$sheet->getRowDimension(1)->setRowHeight(28);
$colIndex = 1;
foreach ($headers as $header) {
    $cellptr = [$colIndex, 1];
    $sheet->setCellValue($cellptr, $header);
    $sheet->getStyle($cellptr)->applyFromArray($headerStyle);
    $colIndex++;
}

$rowIndex = 2;
foreach ($rows as $row) {
    $colIndex = 1;
    $rowBgColor = ($rowIndex % 2 === 0) ? $rowEven : $rowOdd;
    $sheet->getRowDimension($rowIndex)->setRowHeight(22);

    foreach ($columns as $key) {
        if (!array_key_exists($key, $row)) continue;

        $value = $row[$key];
        $cellptr = [$colIndex, $rowIndex];

        switch ($key) {
            case "projectName":
                setLinkedCell($sheet, $cellptr, $value, "$mainUrl/Projects/" . ($row["projectId"] ?? ""));
                break;
            case "supervisorName":
                setLinkedCell($sheet, $cellptr, $value, "$mainUrl/Users/" . ($row["supervisorId"] ?? ""));
                break;
            case "siteContactName":
                setLinkedCell($sheet, $cellptr, $value, "$mainUrl/Contacts/" . ($row["siteContactId"] ?? ""));
                break;
            case "leadName":
                setLinkedCell($sheet, $cellptr, $value, "$mainUrl/Users/" . ($row["leadId"] ?? ""));
                break;
            case "creatorName":
                setLinkedCell($sheet, $cellptr, $value, "$mainUrl/Users/" . ($row["creatorId"] ?? ""));
                break;
            case "updaterName":
                setLinkedCell($sheet, $cellptr, $value, "$mainUrl/Users/" . ($row["updaterId"] ?? ""));
                break;
            default:
                $sheet->setCellValue($cellptr, $value);
                break;
        }

        $sheet->getStyle($cellptr)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($rowBgColor);
        $sheet->getStyle($cellptr)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($cellptr)->applyFromArray($thinBorder);
        $colIndex++;
    }
    $rowIndex++;
}

for ($i = 1; $i <= count($headers); $i++) {
    $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
}

$filename = "works_export_" . date("Ymd_His") . ".xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit();
