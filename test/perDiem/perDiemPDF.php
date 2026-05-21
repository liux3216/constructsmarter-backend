<?php
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/constants.php";
use Dompdf\Dompdf;
use Dompdf\Options;

function generatePerDiemPdf(int $perDiemId, ?string $pdfId, array $perDiem): string {
    global $db, $userId, $privateBucket;
    $options = new Options();
    $options->set("defaultFont", "DejaVu Sans");
    $options->set("isRemoteEnabled", true);
    $dompdf = new Dompdf($options);
    $html = renderPerDiemTemplate($perDiemId, $perDiem);
    $dompdf->loadHtml($html);
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();
    $output = $dompdf->output();
    $size = strlen($output);
    if ($pdfId === null || $pdfId === "") {
        $pdfId = md5(rand());
        uploadFileWithBody($privateBucket, $pdfId, $output, "application/pdf");
        $db->exec(
            "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `creatorId`, `status`)
             VALUES (?, ?, ?, ?, ?, ?);",
            [$pdfId, "perDiem_$perDiemId", "application/pdf", $size, $userId, "uploaded"],
            __FILE__, __LINE__
        );
    } else {
        uploadFileWithBody($privateBucket, $pdfId, $output, "application/pdf");
        $db->exec(
            "UPDATE `fileInfo` SET `size` = ?, `updaterId` = ?, `status` = 'uploaded' WHERE `id` = ?;",
            [$size, $userId, $pdfId],
            __FILE__, __LINE__
        );
    }
    return $pdfId;
}

function renderPerDiemTemplate(int $perDiemId, array $data): string {
    ob_start();
    global $mainUrl;
    $statusColor = match ($data["status"] ?? "") {
        "Approved" => "#198754",
        "Rejected" => "#dc3545",
        "Void" => "#6c757d",
        default => "#000"
    };
    $data["stampColor"] = $statusColor;
    require __DIR__ . "/perDiemPDF.tpl.php";
    return ob_get_clean();
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}
function fmtDate(?string $v): string {
    if (!$v) return "";
    return date("Y-m-d", strtotime($v));
}
function fmtDateTime(?string $v): string {
    if (!$v) return "";
    return date("Y-m-d H:i", strtotime($v));
}
