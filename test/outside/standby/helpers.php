<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function standbyGenerateId(): string {
    return md5(uniqid((string)mt_rand(), true));
}

function standbyGetWeekMap(?string $json): array {
    if (!$json) return [];
    $map = json_decode($json, true);
    return is_array($map) ? $map : [];
}

function standbyEnsureRow(DB $db, string $targetUserId, string $actorId): array {
    $row = $db->one("SELECT `userId`, `data` FROM `outsideStandby` WHERE `userId` = ?", [$targetUserId], __FILE__, __LINE__);
    if ($row) return $row;
    $db->exec(
        "INSERT INTO `outsideStandby` (`userId`, `data`, `creatorId`, `updaterId`) VALUES (?, NULL, ?, ?)",
        [$targetUserId, $actorId, $actorId],
        __FILE__,
        __LINE__
    );
    return ["userId" => $targetUserId, "data" => null];
}

function standbyDefaultWeekData(): array {
    return [
        "data" => array_fill(0, 7, [[]]),
        "otherNotes" => "",
        "region" => "",
        "status" => "Created",
        "id" => null,
        "billableId" => null,
    ];
}

function standbyGetWeekData(DB $db, string $targetUserId, string $week, string $actorId): array {
    $row = standbyEnsureRow($db, $targetUserId, $actorId);
    $weekMap = standbyGetWeekMap($row['data'] ?? null);
    $weekDataRaw = $weekMap[$week] ?? null;
    $weekData = is_string($weekDataRaw) && $weekDataRaw !== '' ? json_decode($weekDataRaw, true) : null;
    if (!is_array($weekData)) $weekData = standbyDefaultWeekData();
    if (!isset($weekData['data']) || !is_array($weekData['data'])) $weekData['data'] = standbyDefaultWeekData()['data'];
    while (count($weekData['data']) < 7) $weekData['data'][] = [[]];
    if (!isset($weekData['status'])) $weekData['status'] = 'Created';
    return [$weekMap, $weekData];
}

function standbySaveWeekData(DB $db, string $targetUserId, string $week, array $weekMap, array $weekData, string $actorId): void {
    $weekMap[$week] = json_encode($weekData);
    $db->exec(
        "UPDATE `outsideStandby` SET `data` = ?, `updaterId` = ? WHERE `userId` = ?",
        [json_encode($weekMap), $actorId, $targetUserId],
        __FILE__,
        __LINE__
    );
}

function standbyDeleteFileIfExists(?string $fileId): void {
    global $db, $privateBucket;
    if (!$fileId || strlen($fileId) !== 32) return;
    deleteFile($privateBucket, $fileId);
    $db->exec("DELETE FROM `fileInfo` WHERE `id` = ?", [$fileId], __FILE__, __LINE__);
}

function standbySyncFileInfo(string $fileId, string $name, int $size): void {
    global $db, $userId;
    $existing = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ?", [$fileId], __FILE__, __LINE__);
    if ($existing) {
        $db->exec(
            "UPDATE `fileInfo` SET `name` = ?, `type` = ?, `size` = ?, `updaterId` = ?, `status` = 'uploaded' WHERE `id` = ?",
            [$name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $size, $userId, $fileId],
            __FILE__,
            __LINE__
        );
        return;
    }
    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `creatorId`, `updaterId`, `status`) VALUES (?, ?, ?, ?, ?, ?, 'uploaded')",
        [$fileId, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $size, $userId, $userId],
        __FILE__,
        __LINE__
    );
}

function standbyBuildWorkbook(string $week, array $data, bool $billableOnly): string {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($billableOnly ? 'Billable' : 'Payroll');
    $sheet->fromArray([
        ['Week', $week],
        ['Status', $data['status'] ?? ''],
        ['Region', $data['region'] ?? ''],
        ['Other Notes', $data['otherNotes'] ?? ''],
        [],
        ['Day', 'Task #', 'Category', 'Start', 'End', 'PM #', 'Division', 'Description', 'Expense Value'],
    ], null, 'A1');

    $row = 7;
    foreach (($data['data'] ?? []) as $dayIndex => $day) {
        if (!is_array($day)) continue;
        foreach ($day as $jobIndex => $job) {
            if ($jobIndex === 0 || !is_array($job) || empty($job['category'])) continue;
            if ($billableOnly && in_array($job['category'], ['Rest', 'Lunch', 'Non Billable', 'Non Production', 'Hotel Charges 3rd Party', 'Hotel Charges PGE', 'Toll Charges 3rd Party', 'Toll Charges PGE'], true)) {
                continue;
            }
            $sheet->fromArray([[
                $dayIndex + 1,
                $jobIndex,
                (string)($job['category'] ?? ''),
                (string)($job['start'] ?? ''),
                (string)($job['end'] ?? ''),
                (string)($job['pmNumber'] ?? ''),
                (string)($job['division'] ?? ''),
                (string)($job['description'] ?? ''),
                (string)($job['expenseValue'] ?? ''),
            ]], null, "A{$row}");
            $row++;
        }
    }

    foreach (range('A', 'I') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    ob_start();
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    return (string)ob_get_clean();
}

function standbyGenerateWorkbookFiles(string $week, array $data, ?string $allId, ?string $billableId): array {
    global $privateBucket;
    $allId = $allId ?: standbyGenerateId();
    $allBody = standbyBuildWorkbook($week, $data, false);
    uploadFileWithBody($privateBucket, $allId, $allBody, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    standbySyncFileInfo($allId, "{$week}-payroll.xlsx", strlen($allBody));

    $billableId = $billableId ?: standbyGenerateId();
    $billableBody = standbyBuildWorkbook($week, $data, true);
    uploadFileWithBody($privateBucket, $billableId, $billableBody, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    standbySyncFileInfo($billableId, "{$week}-billable.xlsx", strlen($billableBody));

    return ['all' => $allId, 'billable' => $billableId];
}
