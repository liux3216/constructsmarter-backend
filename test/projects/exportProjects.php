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

$columns = json_decode($_POST['columns'] ?? '[]', true);
if (!is_array($columns) || empty($columns)) {
    http_response_code(407);
    echo "Invalid export parameters";
    exit;
}

$COLUMN_MAP = [
    "projectNumber"           => ["sql" => "`projects`.`projectNumber`",                                                                                                                                                         "label" => "Project Number"],
    "organizationName"        => ["sql" => "`organizations`.`name`",                                                                                                                                                             "label" => "Client Name"],
    "clientProjectNumber"     => ["sql" => "`projects`.`clientProjectNumber`",                                                                                                                                                   "label" => "Client Project Name / Job Number / PM Number"],
    "projectManager"          => ["sql" => "CONCAT_WS(' ', `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`)",                                                                                                               "label" => "Project Manager"],
    "pipeline"                => ["sql" => "`projects`.`pipeline`",                                                                                                                                                              "label" => "Pipeline"],
    "subPipeline"             => ["sql" => "`projects`.`subPipeline`",                                                                                                                                                           "label" => "Sub Pipeline"],
    "stage"                   => ["sql" => "`projects`.`stage`",                                                                                                                                                                 "label" => "Stage"],
    "reportNeeded"            => ["sql" => "`projects`.`reportNeeded`",                                                                                                                                                          "label" => "Report Needed"],
    "prevailing"              => ["sql" => "`projects`.`prevailing`",                                                                                                                                                            "label" => "Prevailing Job"],
    "cpr"                     => ["sql" => "`projects`.`cpr`",                                                                                                                                                                   "label" => "Certified Payroll"],
    "dirNumber"               => ["sql" => "`projects`.`dirNumber`",                                                                                                                                                             "label" => "DIR Number"],
    "location"                => ["sql" => "`projects`.`location`",                                                                                                                                                              "label" => "Location"],
    "nearestMedicalFacility"  => ["sql" => "`projects`.`nearestMedicalFacility`",                                                                                                                                                "label" => "Nearest Medical Facility"],
    "opportunityName"         => ["sql" => "`opportunities`.`opportunityName`",                                                                                                                                                  "label" => "Opportunity Name"],
    "proposalNumber"          => ["sql" => "`proposals`.`proposalNumber`",                                                                                                                                                       "label" => "Proposal Number"],
    "contactIds"              => ["sql" => "(SELECT GROUP_CONCAT(CONCAT_WS(' ', `c`.`firstName`, `c`.`middleName`, `c`.`lastName`) SEPARATOR ', ') FROM `projects_contact` `pc` LEFT JOIN `contacts` `c` ON `c`.`id` = `pc`.`contactId` WHERE `pc`.`projectId` = `projects`.`id`)", "label" => "Contacts"],
    "clientPONumber"          => ["sql" => "`projects`.`clientPONumber`",                                                                                                                                                        "label" => "Client PO"],
    "region"                  => ["sql" => "`projects`.`region`",                                                                                                                                                                "label" => "Region"],
    "usaTicketNumber"         => ["sql" => "`projects`.`usaTicketNumber`",                                                                                                                                                       "label" => "USA Ticket Number"],
    "usaTicketDate"           => ["sql" => "`projects`.`usaTicketDate`",                                                                                                                                                         "label" => "USA Ticket Date"],
    "billingType"             => ["sql" => "`projects`.`billingType`",                                                                                                                                                           "label" => "Billing Type"],
    "days"                    => ["sql" => "`projects`.`days`",                                                                                                                                                                  "label" => "Estimate Days"],
    "laborHours"              => ["sql" => "`projects`.`laborHours`",                                                                                                                                                            "label" => "Estimate Labor Hours"],
    "materialCost"            => ["sql" => "`projects`.`materialCost`",                                                                                                                                                          "label" => "Estimate Material Cost"],
    "budget"                  => ["sql" => "`projects`.`budgets`",                                                                                                                                                               "label" => "Estimate Budget"],
    "description"             => ["sql" => "`projects`.`description`",                                                                                                                                                           "label" => "Description"],
    "notes"                   => ["sql" => "`projects`.`notes`",                                                                                                                                                                 "label" => "Notes"],
    "accurateTime"            => ["sql" => "`projects`.`accurateTime`",                                                                                                                                                          "label" => "Accurate Time"],
    "clientSignatureRequired" => ["sql" => "`projects`.`clientSignatureRequired`",                                                                                                                                               "label" => "Client Signature Required"],
    "sendToClient"            => ["sql" => "`projects`.`sendToClient`",                                                                                                                                                          "label" => "Send To Client"],
    "creator"                 => ["sql" => "CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)",                                                                                                               "label" => "Creator"],
    "projectCreationDate"     => ["sql" => "`projects`.`createdAt`",                                                                                                                                                             "label" => "Date Created"],
    "updater"                 => ["sql" => "CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)",                                                                                                               "label" => "Updater"],
    "projectDateUpdated"      => ["sql" => "`projects`.`updatedAt`",                                                                                                                                                             "label" => "Date Updated"],
];

/* ---------- search builder ---------- */
$search = new SearchHelper("projects");
$search->equals("organizationId", requireInt($_POST, "organizationId", null, null, false));
$contactId = requireInt($_POST, "contactId", null, null, false);
if ($contactId !== null) {
    $search->raw(
        "EXISTS (SELECT 1 FROM `projects_contact` WHERE `projects_contact`.`projectId` = `projects`.`id` AND `projects_contact`.`contactId` = ?)",
        [$contactId]
    );
}
if (!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if ($_POST["void"] !== "all") $search->equals("void", $_POST["void"]);

$likeFields = ["projectNumber", "clientProjectNumber", "location", "nearestMedicalFacility", "usaTicketNumber", "clientPONumber", "description", "notes"];
foreach ($likeFields as $field) {
    $search->like($field, $_POST[$field] ?? null);
}

$joinedLikeFields = [
    "organizationName" => "`organizations`.`name`",
    "projectManager"   => "CONCAT_WS(' ', `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`)",
    "opportunityName"  => "`opportunities`.`opportunityName`",
    "proposalNumber"   => "`proposals`.`proposalNumber`",
    "requestor"        => "CONCAT_WS(' ', `u_req`.`firstName`, `u_req`.`middleName`, `u_req`.`lastName`)",
    "creator"          => "CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)",
    "updater"          => "CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)",
];
foreach ($joinedLikeFields as $key => $expr) {
    $val = $_POST[$key] ?? null;
    if ($val !== null && $val !== "") {
        $search->raw("$expr LIKE ?", ["%$val%"]);
    }
}

$equalFields = ["pipeline", "subPipeline", "stage", "reportNeeded", "prevailing", "cpr", "region", "billingType", "accurateTime", "clientSignatureRequired", "sendToClient"];
foreach ($equalFields as $field) {
    $search->equals($field, $_POST[$field] ?? null);
}

foreach (["usaTicketDate"] as $field) {
    $search->between($field, "date");
}
foreach (["projectCreationDate" => "createdAt", "projectDateUpdated" => "updatedAt"] as $postKey => $dbCol) {
    $search->between($dbCol, "datetime", $postKey);
}
foreach (["days", "laborHours", "materialCost"] as $field) {
    $search->between($field, "number");
}
/* ---------- fav filter ---------- */
if (array_key_exists("fav", $_POST) && $_POST["fav"] === "1"){
    $userRow = $db->one("SELECT `fav` FROM `users` WHERE `id` = ?;", [$userId], __FILE__,  __LINE__);
    $favJson = $userRow["fav"] ?? "[]";
    $favData = json_decode($favJson, true);
    $favProjectIds = [];
    if (is_array($favData) && isset($favData["projects"]) && is_array($favData["projects"])) {
        $favProjectIds = array_values(array_filter(
            array_map("intval", $favData["projects"]),
            fn($id) => $id > 0
        ));
    }
    if (count($favProjectIds) === 0) {
        $search->raw("1 = 0");
    } else {
        $placeholders = implode(", ", array_fill(0, count($favProjectIds), "?"));
        $search->raw("`projects`.`id` IN ($placeholders)", $favProjectIds);
    }
}

$whereSql = $search->getWhereSql();
$params   = $search->getParams();

/* ---------- build select ---------- */
$columns = array_values(array_filter($columns, fn($key) => array_key_exists($key, $COLUMN_MAP)));
if (empty($columns)) {
    http_response_code(400);
    echo "No valid columns";
    exit();
}

$selectFields = [];
$headers      = [];
foreach ($columns as $key) {
    $selectFields[] = $COLUMN_MAP[$key]['sql'] . " AS `$key`";
    switch ($key) {
        case "organizationName":
            $selectFields[] = "`projects`.`organizationId`";
            break;
        case "projectManager":
            $selectFields[] = "`projects`.`projectManagerId`";
            break;
        case "creator":
            $selectFields[] = "`projects`.`creatorId`";
            break;
        case "updater":
            $selectFields[] = "`projects`.`updaterId`";
            break;
    }
    $headers[] = $COLUMN_MAP[$key]['label'];
}

$selectFieldsSQL = implode(", ", $selectFields);
$rows = $db->all(
    "SELECT $selectFieldsSQL
     FROM `projects`
     LEFT JOIN `users` `u1`       ON `u1`.`id`           = `projects`.`creatorId`
     LEFT JOIN `users` `u2`       ON `u2`.`id`           = `projects`.`updaterId`
     LEFT JOIN `users` `u3`       ON `u3`.`id`           = `projects`.`projectManagerId`
     LEFT JOIN `organizations`    ON `organizations`.`id` = `projects`.`organizationId`
     LEFT JOIN `opportunities`    ON `opportunities`.`id` = `projects`.`opportunityId`
     LEFT JOIN `proposals`        ON `proposals`.`id` = `projects`.`proposalId`
     $whereSql;",
    $params, __FILE__, __LINE__
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
$rowOdd  = "FFFFFFFF";

/* ---------- build spreadsheet ---------- */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setShowGridlines(false);
$sheet->freezePane("A2");

// header row
$sheet->getRowDimension(1)->setRowHeight(28);
$colIndex = 1;
foreach ($headers as $header) {
    $cellptr = [$colIndex, 1];
    $sheet->setCellValue($cellptr, $header);
    $sheet->getStyle($cellptr)->applyFromArray($headerStyle);
    $colIndex++;
}

// data rows
$rowIndex = 2;
foreach ($rows as $row) {
    $colIndex   = 1;
    $rowBgColor = ($rowIndex % 2 === 0) ? $rowEven : $rowOdd;
    $sheet->getRowDimension($rowIndex)->setRowHeight(22);

    foreach ($columns as $key) {
        if (!array_key_exists($key, $row)) continue;
        $value   = $row[$key];
        $cellptr = [$colIndex, $rowIndex];

        switch ($key) {
            case "organizationName":
                $sheet->setCellValueExplicit($cellptr, $value, DataType::TYPE_STRING);
                $sheet->getCell($cellptr)->getHyperlink()->setUrl("$mainUrl/Organizations/" . $row["organizationId"]);
                $sheet->getStyle($cellptr)->getFont()->setUnderline(Font::UNDERLINE_SINGLE);
                $sheet->getStyle($cellptr)->getFont()->setColor(new Color("FF0563C1"));
                break;
            case "projectManager":
                $sheet->setCellValueExplicit($cellptr, $value, DataType::TYPE_STRING);
                $sheet->getCell($cellptr)->getHyperlink()->setUrl("$mainUrl/Users/" . $row["projectManagerId"]);
                $sheet->getStyle($cellptr)->getFont()->setUnderline(Font::UNDERLINE_SINGLE);
                $sheet->getStyle($cellptr)->getFont()->setColor(new Color("FF0563C1"));
                break;
            case "creator":
                $sheet->setCellValueExplicit($cellptr, $value, DataType::TYPE_STRING);
                $sheet->getCell($cellptr)->getHyperlink()->setUrl("$mainUrl/Users/" . $row["creatorId"]);
                $sheet->getStyle($cellptr)->getFont()->setUnderline(Font::UNDERLINE_SINGLE);
                $sheet->getStyle($cellptr)->getFont()->setColor(new Color("FF0563C1"));
                break;
            case "updater":
                $sheet->setCellValueExplicit($cellptr, $value, DataType::TYPE_STRING);
                $sheet->getCell($cellptr)->getHyperlink()->setUrl("$mainUrl/Users/" . $row["updaterId"]);
                $sheet->getStyle($cellptr)->getFont()->setUnderline(Font::UNDERLINE_SINGLE);
                $sheet->getStyle($cellptr)->getFont()->setColor(new Color("FF0563C1"));
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

$filename = "projects_export_" . date("Ymd_His") . ".xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit();
