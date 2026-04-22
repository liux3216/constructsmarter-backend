<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "functions.php"; // getDateFromWeekNum
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="time_card_export_' . date('Y-m-d_His') . '.xlsx"');
header('Cache-Control: max-age=0');
// ── Helpers ──────────────────────────────────────────────────────────────────
function roundVal(float $val, int $precision = 2): float {
    return round($val, $precision);
}
function getReg(float $val): float {
    return $val > 8 ? 8.0 : $val;
}
function getOT(float $val): float {
    return $val > 8 ? $val - 8 : 0.0;
}
function sumDurations(array $inOut): float {
    if (empty($inOut)) return 0.0;
    return array_reduce($inOut, fn($carry, $item) => $carry + roundVal((float)($item['duration'] ?? 0), 2), 0.0);
}

$weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
// ── Input ─────────────────────────────────────────────────────────────────────
$week = $_POST["week"] ?? date("oW");
$forms = $db->all(
    "SELECT `data`, CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `userName` FROM `timeCard` 
    LEFT JOIN `users` ON `users`.`id` = `timeCard`.`userId`
    WHERE `week` = ? AND JSON_UNQUOTE(JSON_EXTRACT(`timeCard`.`data`, '$.status')) = ?;", 
    [$week, "Approved"], __FILE__, __LINE__
);
foreach($forms as &$form){
    $tmp = json_decode($form["data"], true);
    $tmp["userName"] = $form["userName"];
    $form = $tmp;
}
unset($form);
if(empty($forms)){
    jsonResponse(400, ['msg' => 'No data to export.']);
}
// ── Filter & enrich approved forms ────────────────────────────────────────────
$mainData = [];
foreach ($forms as $form) {
    $totalReg = 0.0;
    $totalOT  = 0.0;
    foreach ($form['form'] as $day) {
        $hours     = sumDurations($day['inOut'] ?? []);
        $totalReg += getReg($hours);
        $totalOT  += getOT($hours);
    }
    $mainData[] = array_merge($form, [
        'name'     => $form['userName'] ?? '',
        'totalReg' => $totalReg,
        'totalOT'  => $totalOT,
    ]);
}
if(empty($mainData)){
    jsonResponse(400, ['msg' => 'Nobody got approved.']);
}
// ── Build spreadsheet ─────────────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator($userName)
    ->setCreated(date('c'));
$spreadsheet->setActiveSheetIndex(0);
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Week $week");
$sheet->setShowGridlines(false);
// Default column width
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setWidth(18);
}
// Style definitions
$headerFill = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF00B0F0']],
    'font' => ['bold' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THICK]],
];
$evenRowFill = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9E1F2']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THICK]],
];
$oddRowStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THICK]],
];
$boldFont = ['font' => ['bold' => true]];
$headers = array_merge($weekDays, ['Total']); // 8 elements
foreach ($mainData as $idx => $record) {
    $baseRow = 1 + $idx * 9; // rows: 1, 10, 19 …
    // ── Row 1: Name / header row (cols A–F) ──────────────────────────────────
    $sheet->getRowDimension($baseRow)->setRowHeight(-1);
    $sheet->getStyle("A{$baseRow}:F{$baseRow}")->applyFromArray($headerFill);
    // ── Rows 2–9: Data rows (cols B–F) ───────────────────────────────────────
    for ($r = 0; $r < 8; $r++) {
        $dataRow = $baseRow + 1 + $r;
        $style   = ($r % 2 === 1) ? $evenRowFill : $oddRowStyle;
        $sheet->getStyle("B{$dataRow}:F{$dataRow}")->applyFromArray($style);
        $sheet->getStyle("B{$dataRow}")->applyFromArray($boldFont);
    }
    // ── Column A: Employee name (merging 9 rows visually via single value) ───
    $sheet->setCellValue("A{$baseRow}", $record['name']);
    // ── Column B: Labels ──────────────────────────────────────────────────────
    $sheet->setCellValue("B{$baseRow}", 'Week Day');
    foreach ($headers as $hi => $h) {
        $sheet->setCellValue("B" . ($baseRow + 1 + $hi), $h);
    }
    // ── Column C: Dates ───────────────────────────────────────────────────────
    $sheet->setCellValue("C{$baseRow}", 'Date');
    for ($d = 0; $d < 7; $d++) {
        $sheet->setCellValue("C" . ($baseRow + 1 + $d), getDateFromWeekNum($week, $d));
    }
    // row 9 (Total row) — date cell left blank
    $sheet->setCellValue("C" . ($baseRow + 8), '');
    // ── Column D: Regular hours ───────────────────────────────────────────────
    $sheet->setCellValue("D{$baseRow}", 'Regular');
    foreach ($record['form'] as $fi => $day) {
        $hours = sumDurations($day['inOut'] ?? []);
        $sheet->setCellValue("D" . ($baseRow + 1 + $fi), getReg($hours));
    }
    $sheet->setCellValue("D" . ($baseRow + 8), $record['totalReg']);
    // ── Column E: OT hours ────────────────────────────────────────────────────
    $sheet->setCellValue("E{$baseRow}", 'OT');
    foreach ($record['form'] as $fi => $day) {
        $hours = sumDurations($day['inOut'] ?? []);
        $sheet->setCellValue("E" . ($baseRow + 1 + $fi), getOT($hours));
    }
    $sheet->setCellValue("E" . ($baseRow + 8), $record['totalOT']);
    // ── Column F: Notes / Summary ─────────────────────────────────────────────
    $sheet->setCellValue("F{$baseRow}", 'Summary');
    foreach ($record['form'] as $fi => $day) {
        $sheet->setCellValue("F" . ($baseRow + 1 + $fi), $day['notes'] ?? '');
    }
    $sheet->setCellValue("F" . ($baseRow + 8), '');
}
// ── Stream to browser ─────────────────────────────────────────────────────────
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;