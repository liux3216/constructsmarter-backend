<?php
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";
use Dompdf\Dompdf;
use Dompdf\Options;

function generatePurchasePdf(string $purchaseId, string $poNumber, ?string $pdfId, array $purchase): string {
    global $privateBucket;
    $options = new Options();
    $options->set("defaultFont", "DejaVu Sans");
    $options->set("isRemoteEnabled", true);
    $dompdf = new Dompdf($options);
    $html = renderPurchaseTemplate($purchaseId, $purchase);
    $dompdf->loadHtml($html);
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();
    $output = $dompdf->output();
    $size = strlen($output);
    if($pdfId === null || $pdfId === ""){
        $pdfId = purchaseGenerateId();
    }
    if(!uploadFileWithBody($privateBucket, $pdfId, $output, "application/pdf")){
        throw new RuntimeException("Failed to upload purchase PDF.");
    }
    purchaseSyncFileInfo($pdfId, ($poNumber ?: "purchase") . ".pdf", "application/pdf", $size);
    return $pdfId;
}

function renderPurchaseTemplate(string $purchaseId, array $purchase): string {
    $statusColor = match ($purchase["status"] ?? "") {
        "Approved" => "#198754",
        "Rejected" => "#dc3545",
        default => "#0d6efd",
    };
    $lines = is_array($purchase["data"] ?? null) ? $purchase["data"] : [];
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
            .status { display: inline-block; padding: 4px 10px; color: #fff; background: <?= h($statusColor) ?>; border-radius: 12px; }
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
        <h1>Purchase Form</h1>
        <table class="meta">
            <tr><th>Status</th><td><span class="status"><?= h($purchase["status"] ?? "Submitted") ?></span></td></tr>
            <tr><th>PO Number</th><td><?= h($purchase["poNumber"] ?? "") ?></td></tr>
            <tr><th>PO Date</th><td><?= h($purchase["poDate"] ?? "") ?></td></tr>
            <tr><th>Requester</th><td><?= h($purchase["requesterName"] ?? "") ?></td></tr>
            <tr><th>Approver</th><td><?= h($purchase["approverName"] ?? "") ?></td></tr>
            <tr><th>Project</th><td><?= h($purchase["projectName"] ?? "") ?></td></tr>
            <tr><th>Category</th><td><?= h($purchase["category"] ?? "") ?></td></tr>
            <tr><th>Department</th><td><?= h($purchase["department"] ?? "") ?></td></tr>
            <tr><th>Payment Method</th><td><?= h($purchase["paymentMethod"] ?? "") ?><?= ($purchase["last4"] ?? "") !== "" ? " / Last 4: " . h($purchase["last4"]) : "" ?></td></tr>
            <tr><th>Billable</th><td><?= h($purchase["billable"] ?? "") ?></td></tr>
            <tr><th>Included In Proposal</th><td><?= h($purchase["includedInProposal"] ?? "") ?></td></tr>
            <tr><th>Client Invoice Number</th><td><?= h($purchase["clientInvoiceNumber"] ?? "") ?></td></tr>
            <tr><th>Notes</th><td class="notes"><?= h($purchase["notes"] ?? "") ?></td></tr>
        </table>
        <table class="lines">
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>Description</th>
                    <th>Unit Price</th>
                    <th>Quantity</th>
                    <th>Line Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($lines as $line): ?>
                <tr>
                    <td><?= h($line["vendorName"] ?? "") ?></td>
                    <td><?= h($line["description"] ?? "") ?></td>
                    <td><?= h($line["unitPrice"] ?? "") ?></td>
                    <td><?= h($line["quantity"] ?? "") ?></td>
                    <td><?= h($line["lineTotal"] ?? "") ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table class="totals">
            <tr><th>Subtotal</th><td><?= h($purchase["subtotal"] ?? "0.00") ?></td></tr>
            <tr><th>Tax</th><td><?= h($purchase["tax"] ?? "0.00") ?></td></tr>
            <tr><th>Discount</th><td><?= h($purchase["discount"] ?? "0.00") ?></td></tr>
            <tr><th>Total</th><td><?= h($purchase["total"] ?? "0.00") ?></td></tr>
        </table>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}
