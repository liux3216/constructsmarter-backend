<?php
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/s3.php"; // uploadFileWithBody
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $mainUrl
use Dompdf\Dompdf;
use Dompdf\Options;
function generateTimeOffPdf(int $timeOffId, string | null $pdfId, array $timeOff): string {
	global $db, $userId, $privateBucket;
	$options = new Options();
	$options->set("defaultFont", "DejaVu Sans");
	$options->set("isRemoteEnabled", true);
	$dompdf = new Dompdf($options);
	$html = renderTimeOffTemplate($timeOffId, $timeOff);
	$dompdf->loadHtml($html);
	$dompdf->setPaper("A4", "portrait");
	$dompdf->render();
	$output = $dompdf->output();
	$size = strlen($output);
    if($pdfId === null){
        $pdfId = md5(rand());
        uploadFileWithBody($privateBucket, $pdfId, $output, "application/pdf");
        $db->exec(
            "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?);",
            [$pdfId, "timeOff_$timeOffId", "application/pdf", $size,
             "df1a2fb5d204c08d6d9cd724b152283b", $userId, "uploaded"],
            __FILE__, __LINE__
        );
    }else{
        uploadFileWithBody($privateBucket, $pdfId, $output, "application/pdf");
        $db->exec("UPDATE `fileInfo` SET `size` = ? WHERE `id` = ?;", [$size, $pdfId]);
    }
    return $pdfId;
}
function renderTimeOffTemplate(int $timeOffId, array $data): string {
	ob_start();
	global $mainUrl;
	$statusColor = match ($data["status"] ?? "") {
		"Approved" => "#198754", // green
		"Rejected" => "#dc3545", // red
		"Void"     => "#6c757d", // gray
		default    => "#000"
	};
	$data["stampColor"] = $statusColor;
	require "timeOffPDF.tpl.php";
	return ob_get_clean();
}
function h($v): string {
	return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}
function yesNo($v): string {
	if ($v === null) return "";
	return $v ? "Yes" : "No";
}
function fmtDate(?string $v): string {
	if (!$v) return "";
	return date("Y-m-d", strtotime($v));
}
function fmtDateTime(?string $v): string {
	if (!$v) return "";
	return date("Y-m-d H:i", strtotime($v));
}
