<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";

use Dompdf\Dompdf;
use Dompdf\Options;

function locatorGenerateId(): string {
    return md5(uniqid((string)mt_rand(), true));
}

function locatorDefaultDayData(): array {
    return [
        "data" => [],
        "status" => "Created",
        "overnight" => false,
    ];
}

function locatorDefaultWeekData(): array {
    return [
        "form" => array_fill(0, 7, locatorDefaultDayData()),
        "emergency" => [],
        "status" => "Created",
    ];
}

function locatorGetWeekMap(?string $json): array {
    if (!$json) return [];
    $map = json_decode($json, true);
    return is_array($map) ? $map : [];
}

function locatorEnsureRow(DB $db, string $targetUserId, string $actorId): array {
    $row = $db->one("SELECT `userId`, `data` FROM `outsideML` WHERE `userId` = ?", [$targetUserId], __FILE__, __LINE__);
    if ($row) return $row;
    $db->exec(
        "INSERT INTO `outsideML` (`userId`, `data`, `creatorId`, `updaterId`) VALUES (?, NULL, ?, ?)",
        [$targetUserId, $actorId, $actorId],
        __FILE__,
        __LINE__
    );
    return ["userId" => $targetUserId, "data" => null];
}

function locatorGetWeekData(DB $db, string $targetUserId, string $week, string $actorId): array {
    $row = locatorEnsureRow($db, $targetUserId, $actorId);
    $weekMap = locatorGetWeekMap($row["data"] ?? null);
    $weekDataRaw = $weekMap[$week] ?? null;
    $weekData = is_string($weekDataRaw) && $weekDataRaw !== ""
        ? json_decode($weekDataRaw, true)
        : null;
    if (!is_array($weekData)) {
        $weekData = locatorDefaultWeekData();
    }
    if (!isset($weekData["form"]) || !is_array($weekData["form"])) {
        $weekData["form"] = locatorDefaultWeekData()["form"];
    }
    while (count($weekData["form"]) < 7) {
        $weekData["form"][] = locatorDefaultDayData();
    }
    if (!isset($weekData["emergency"]) || !is_array($weekData["emergency"])) {
        $weekData["emergency"] = [];
    }
    if (!isset($weekData["status"]) || !is_string($weekData["status"])) {
        $weekData["status"] = "Created";
    }
    return [$weekMap, $weekData];
}

function locatorSaveWeekData(DB $db, string $targetUserId, string $week, array $weekMap, array $weekData, string $actorId): void {
    $weekMap[$week] = json_encode($weekData);
    $db->exec(
        "UPDATE `outsideML` SET `data` = ?, `updaterId` = ? WHERE `userId` = ?",
        [json_encode($weekMap), $actorId, $targetUserId],
        __FILE__,
        __LINE__
    );
}

function locatorDeletePdf(?string $fileId): void {
    global $db, $privateBucket;
    if (!$fileId || strlen($fileId) !== 32) return;
    deleteFile($privateBucket, $fileId);
    $db->exec("DELETE FROM `fileInfo` WHERE `id` = ?", [$fileId], __FILE__, __LINE__);
}

function locatorSyncFileInfo(string $fileId, string $name, int $size): void {
    global $db, $userId;
    $existing = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ?", [$fileId], __FILE__, __LINE__);
    if ($existing) {
        $db->exec(
            "UPDATE `fileInfo` SET `name` = ?, `type` = ?, `size` = ?, `updaterId` = ?, `status` = 'uploaded' WHERE `id` = ?",
            [$name, "application/pdf", $size, $userId, $fileId],
            __FILE__,
            __LINE__
        );
        return;
    }
    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `creatorId`, `updaterId`, `status`) VALUES (?, ?, ?, ?, ?, ?, 'uploaded')",
        [$fileId, $name, "application/pdf", $size, $userId, $userId],
        __FILE__,
        __LINE__
    );
}

function locatorJsonArray(string $json): array {
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function locatorScalar($value): string {
    if (is_bool($value)) return $value ? "Yes" : "No";
    if ($value === null) return "";
    if (is_array($value)) return json_encode($value);
    return (string)$value;
}

function locatorH($value): string {
    return htmlspecialchars(locatorScalar($value), ENT_QUOTES, 'UTF-8');
}

function locatorRenderPdf(array $post, bool $emergency): string {
    $title = $emergency ? 'Emergency Locator Time Card' : 'Locator Time Card';
    $weekTitle = (string)($post['weekTitle'] ?? '');
    $formDate = (string)($post['formDate'] ?? '');
    $table = locatorJsonArray((string)($post['table'] ?? '[]'));
    $col1 = locatorJsonArray((string)($post['col1'] ?? '[]'));
    $col2 = locatorJsonArray((string)($post['col2'] ?? '[]'));
    $col3 = locatorJsonArray((string)($post['col3'] ?? '[]'));
    $dayData = json_decode((string)($post['dayData'] ?? '{}'), true);
    if (!is_array($dayData)) $dayData = [];

    ob_start();
    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
            h1 { margin: 0 0 8px; font-size: 22px; }
            h2 { margin: 18px 0 8px; font-size: 15px; }
            .meta { margin-bottom: 14px; }
            .meta div { margin: 3px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 8px; }
            th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
            th { background: #f4f4f4; }
            .kv { width: 100%; }
            .notes { white-space: pre-wrap; }
            .pill { display: inline-block; padding: 3px 8px; border: 1px solid #777; border-radius: 10px; }
        </style>
    </head>
    <body>
        <h1><?= locatorH($title) ?></h1>
        <div class="meta">
            <div><strong>Week:</strong> <?= locatorH($weekTitle) ?></div>
            <div><strong>Date:</strong> <?= locatorH($formDate) ?></div>
            <div><strong>Status:</strong> <span class="pill"><?= locatorH($dayData['status'] ?? 'Submitted') ?></span></div>
            <?php if (($dayData['workStatus'] ?? '') !== ''): ?>
                <div><strong>Work Status:</strong> <?= locatorH($dayData['workStatus']) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($col3): ?>
            <h2>Technician</h2>
            <table class="kv">
                <tbody>
                    <?php foreach ($col3 as $row): ?>
                        <tr><td><?= locatorH($row[0] ?? '') ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($col1 || $col2): ?>
            <h2>Summary</h2>
            <table>
                <tbody>
                    <?php $maxRows = max(count($col1), count($col2));
                    for ($i = 0; $i < $maxRows; $i++): ?>
                        <tr>
                            <td><?= locatorH($col1[$i][0] ?? '') ?></td>
                            <td><?= locatorH($col2[$i][0] ?? '') ?></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($table): ?>
            <h2>Entries</h2>
            <table>
                <tbody>
                    <?php foreach ($table as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?= locatorH($cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($dayData['data']) && is_array($dayData['data'])): ?>
            <h2>Raw Detail</h2>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Ticket / Event</th>
                        <th>Comment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dayData['data'] as $row): ?>
                        <tr>
                            <td><?= locatorH($row['category'] ?? '') ?></td>
                            <td><?= locatorH($row['start'] ?? '') ?></td>
                            <td><?= locatorH($row['end'] ?? '') ?></td>
                            <td><?= locatorH($row['ticketNumber'] ?? ($row['event'] ?? '')) ?></td>
                            <td class="notes"><?= locatorH($row['comment'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function locatorGeneratePdf(array $post, ?string $existingPdfId, bool $emergency): string {
    global $privateBucket;
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml(locatorRenderPdf($post, $emergency));
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $output = $dompdf->output();
    $size = strlen($output);
    $pdfId = $existingPdfId ?: locatorGenerateId();
    if (!uploadFileWithBody($privateBucket, $pdfId, $output, 'application/pdf')) {
        throw new RuntimeException('Failed to upload locator PDF.');
    }
    $formDate = preg_replace('/[^0-9A-Za-z_-]+/', '-', (string)($post['formDate'] ?? 'locator'));
    $fileName = ($emergency ? 'locator-emergency-' : 'locator-') . $formDate . '.pdf';
    locatorSyncFileInfo($pdfId, $fileName, $size);
    return $pdfId;
}
