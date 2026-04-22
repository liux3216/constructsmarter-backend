<?php
require_once "/opt/bitnami/apache/htdocs/s3.php"; // uploadFileWithBody
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $mainUrl, $privateBucket
use Dompdf\Dompdf;
use Dompdf\Options;
function generateTimeCardPDF(int $timeCardId, string | null $pdfId, array $payload): string {
    global $privateBucket, $db, $userId;
    $data = normalizeTimeCardPayload($payload);
    $html = renderPhpTemplate("timeCardPDF.tpl.php", $data);
    $options = new Options();
    $options->set("isRemoteEnabled", false);
    $options->set("isHtml5ParserEnabled", true);
    $options->set("defaultFont", "DejaVu Sans");
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, "UTF-8");
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();
    $output = $dompdf->output();
	$size = strlen($output);
    if($pdfId === null){
        $pdfId = md5(rand());
        uploadFileWithBody($privateBucket, $pdfId, $output, "application/pdf");
        $db->exec(
            "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?);",
            [$pdfId, "timeCard_$timeCardId", "application/pdf", $size, "c40eb41e555e87353db48a08242034b4", $userId, "uploaded"],
            __FILE__, __LINE__
        );
    }else{
        uploadFileWithBody($privateBucket, $pdfId, $output, "application/pdf");
        $db->exec("UPDATE `fileInfo` SET `size` = ? WHERE `id` = ?;", [$size, $pdfId]);
    }
    return $pdfId;
}
function renderPhpTemplate(string $path, array $vars): string {
    global $mainUrl;
    extract($vars, EXTR_SKIP);
    ob_start();
    require $path;
    return (string)ob_get_clean();
}
function normalizeTimeCardPayload(array $payload): array {
    $rows = isset($payload["form"]) && is_array($payload["form"]) ? $payload["form"] : [];
    $totalRegular = 0.0;
    $totalOT      = 0.0;
    foreach ($rows as $r) {
        $totalRegular += (float)($r["regular"] ?? 0);
        $totalOT      += (float)($r["ot"] ?? 0);
    }
    return [
        "title"   => "TIME SHEET",
        "week"      => $payload["week"] ?? "",
        "approvalComments" => $payload["comments"] ?? "",
        "rows"    => $rows,
        "totals"  => ["regular" => $totalRegular, "ot" => $totalOT],
        "helpers" => timeCardTemplateHelpers(), 
        "userName" => $payload["userName"] ?? "", 
        "userId" => $payload["userId"] ?? ""
    ];
}
function timeCardTemplateHelpers(): array {
    $esc = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $fmtDateMDY = static function (string $iso): string {
        $dt = DateTimeImmutable::createFromFormat("Y-m-d", $iso);
        return $dt ? $dt->format("n/j/Y") : $iso;
    };
    $fmtDayName = static function (string $iso): string {
        $dt = DateTimeImmutable::createFromFormat("Y-m-d", $iso);
        return $dt ? $dt->format("l") : "";
    };
    $fmtTime12 = static function (?string $hms): string {
        if ($hms === null || trim($hms) === "") return "";
        $dt = DateTimeImmutable::createFromFormat("H:i:s", $hms)
            ?: DateTimeImmutable::createFromFormat("H:i", $hms);
        return $dt ? $dt->format("g:i:s A") : $hms;
    };
    $fmtHours = static function ($num): string {
        $f = (float)($num ?? 0);
        if (abs($f - round($f)) < 0.00001) return (string)(int)round($f);
        $s = rtrim(rtrim(number_format($f, 2, ".", ""), "0"), ".");
        return $s === "" ? "0" : $s;
    };
    return compact("esc", "fmtDateMDY", "fmtDayName", "fmtTime12", "fmtHours");
}