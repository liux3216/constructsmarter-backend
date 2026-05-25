<?php
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";
use Dompdf\Dompdf;
use Dompdf\Options;

function generateProposalPdf(string $proposalId, string $proposalNumber, ?string $pdfId, array $proposal): string {
    global $privateBucket;
    $options = new Options();
    $options->set("defaultFont", "DejaVu Sans");
    $options->set("isRemoteEnabled", true);
    $dompdf = new Dompdf($options);
    $html = renderProposalTemplate($proposalId, $proposal);
    $dompdf->loadHtml($html);
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();
    $output = $dompdf->output();
    if($pdfId === null || $pdfId === ""){
        $pdfId = proposalGenerateId();
    }
    if(!uploadFileWithBody($privateBucket, $pdfId, $output, "application/pdf")){
        throw new RuntimeException("Failed to upload proposal PDF.");
    }
    return $pdfId;
}

function renderProposalTemplate(string $proposalId, array $proposal): string {
    $statusColor = match ($proposal["status"] ?? "") {
        "Approved" => "#198754",
        "Rejected" => "#dc3545",
        default => "#0d6efd",
    };
    $lines = is_array($proposal["data"] ?? null) ? $proposal["data"] : [];
    ob_start();
    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
            h1 { margin: 0 0 8px; font-size: 22px; }
            .meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
            .meta th, .meta td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
            .meta th { width: 160px; background: #f5f5f5; }
            .status { display: inline-block; padding: 4px 10px; color: #fff; background: <?= ph($statusColor) ?>; border-radius: 12px; }
            .notes { white-space: pre-wrap; }
            .lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
            .lines th, .lines td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
            .lines th { background: #f5f5f5; }
            .totals { margin-top: 12px; width: 280px; margin-left: auto; border-collapse: collapse; }
            .totals th, .totals td { border: 1px solid #ccc; padding: 6px 8px; text-align: right; }
            .totals th { background: #f5f5f5; text-align: left; }
        </style>
    </head>
    <body>
        <h1>Proposal Form</h1>
        <table class="meta">
            <tr><th>Status</th><td><span class="status"><?= ph($proposal["status"] ?? "Submitted") ?></span></td></tr>
            <tr><th>Proposal Number</th><td><?= ph($proposal["proposalNumber"] ?? "") ?></td></tr>
            <tr><th>Proposal Date</th><td><?= ph($proposal["proposalDate"] ?? "") ?></td></tr>
            <tr><th>Requester</th><td><?= ph($proposal["requesterName"] ?? "") ?></td></tr>
            <tr><th>Approver</th><td><?= ph($proposal["approverName"] ?? "") ?></td></tr>
            <tr><th>Project</th><td><?= ph($proposal["projectName"] ?? "") ?></td></tr>
            <tr><th>Department</th><td><?= ph($proposal["department"] ?? "") ?></td></tr>
            <tr><th>Notes</th><td class="notes"><?= ph($proposal["notes"] ?? "") ?></td></tr>
        </table>
        <table class="lines">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Description</th>
                    <th>Unit Price</th>
                    <th>Quantity</th>
                    <th>Line Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($lines as $line): ?>
                <tr>
                    <td><?= ph($line["serviceName"] ?? "") ?></td>
                    <td><?= ph($line["description"] ?? "") ?></td>
                    <td><?= ph($line["unitPrice"] ?? "") ?></td>
                    <td><?= ph($line["quantity"] ?? "") ?></td>
                    <td><?= ph($line["lineTotal"] ?? "") ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table class="totals">
            <tr><th>Subtotal</th><td><?= ph($proposal["subtotal"] ?? "0.00") ?></td></tr>
            <tr><th>Tax</th><td><?= ph($proposal["tax"] ?? "0.00") ?></td></tr>
            <tr><th>Discount</th><td><?= ph($proposal["discount"] ?? "0.00") ?></td></tr>
            <tr><th>Total</th><td><?= ph($proposal["total"] ?? "0.00") ?></td></tr>
        </table>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function ph($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}
