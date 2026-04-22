<?php
if(!isset($helpers) || !is_array($helpers)){
    http_response_code(403);
    exit("Forbidden");
}
$esc      = $helpers["esc"];
$fmtDate  = $helpers["fmtDateMDY"];
$fmtDay   = $helpers["fmtDayName"];
$fmtTime  = $helpers["fmtTime12"];
$fmtHours = $helpers["fmtHours"];
?>
<!doctype html>
<html>
<head>
    <meta charset = "utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 6px 0; letter-spacing: 0.4px; }
        h2 { font-size: 13px; margin: 10px 0 6px 0; }
        .meta { font-size: 10px; margin-bottom: 8px; }
        .meta span { margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; margin: 0 0 10px 0; }
        th, td { border: 1px solid #333; padding: 4px 6px; vertical-align: top; }
        th { background: #f2f2f2; font-weight: 700; text-align: left; }
        .muted { color: #555; }
        .summary th:nth-child(4), .summary td:nth-child(4),
        .summary th:nth-child(5), .summary td:nth-child(5) { text-align: right; }
        .detail-day-title { font-weight: 700; margin: 10px 0 4px 0; }
        .detail th:nth-child(1), .detail td:nth-child(1) { width: 6%; text-align: right; }
        .detail th:nth-child(4), .detail td:nth-child(4) { width: 14%; text-align: right; }
        .me-2 { margin-right: 0.5rem }
        .cssNotes {
            word-break: break-all;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <h1><?= $esc($title) ?></h1>
    <div class = "meta">
        <span>
            <strong class = "me-2">Name:</strong>
            <a href = "<?= $mainUrl ?>/Users/<?= htmlspecialchars($userId) ?>"><?= $esc($userName) ?></a>
        </span>
        <span><strong class = "me-2">Week:</strong><?= $esc($week) ?></span>
        <span><strong class = "me-2">Status:</strong>Approved</span>
        <p>
            <strong class = "me-2">Approval Comments:</strong>
            <div class = "cssNotes"><?= $esc($approvalComments) ?></div>
        </p>
    </div>
    <h2>SUMMARY</h2>
    <table class = "summary">
        <thead>
        <tr>
            <th>Week Day</th>
            <th>Date</th>
            <th>Regular</th>
            <th>OT</th>
            <th colspan = "2">Summary</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $dateIso = (string)($r["date"] ?? "");
            $notes   = (string)($r["notes"] ?? "");
            ?>
            <tr>
                <td><?= $esc($dateIso ? $fmtDay($dateIso) : "") ?></td>
                <td><?= $esc($dateIso ? $fmtDate($dateIso) : "") ?></td>
                <td style = "text-align:right;"><?= $esc($fmtHours($r["regular"] ?? 0)) ?></td>
                <td style = "text-align:right;"><?= $esc($fmtHours($r["ot"] ?? 0)) ?></td>
                <td colspan = "2" clas = "cssNotes"><?= $esc($notes) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan = "2"><strong>Total</strong></td>
            <td style = "text-align:right;"><strong><?= $esc($fmtHours($totals["regular"] ?? 0)) ?></strong></td>
            <td style = "text-align:right;"><strong><?= $esc($fmtHours($totals["ot"] ?? 0)) ?></strong></td>
            <td colspan = "2"></td>
        </tr>
        </tbody>
    </table>
    <h2>DETAIL</h2>
    <?php foreach ($rows as $r): ?>
        <?php
        $dateIso = (string)($r["date"] ?? "");
        $dayName = $dateIso ? $fmtDay($dateIso) : "";
        $inOut   = (isset($r["inOut"]) && is_array($r["inOut"])) ? $r["inOut"] : [];
        ?>
        <div class = "detail-day-title">
            <?= $esc($dayName) ?>
        </div>
        <?php if (count($inOut) === 0): ?>
            <div class = "muted" style="margin: 0 0 8px 0;">No Data</div>
            <?php continue; ?>
        <?php endif; ?>
        <table class = "detail">
            <thead>
            <tr>
                <th>#</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Duration</th>
                <th>Comments</th>
            </tr>
            </thead>
            <tbody>
            <?php $i = 1; foreach ($inOut as $sess): ?>
                <?php
                $start = $fmtTime($sess["in"] ?? null);
                $end   = $fmtTime($sess["out"] ?? null);
                $dur   = $fmtHours($sess["duration"] ?? 0);
                $cmt   = (string)($sess["notes"] ?? "");
                ?>
                <tr>
                    <td><?= $esc((string)$i) ?></td>
                    <td><?= $esc($start) ?></td>
                    <td><?= $esc($end) ?></td>
                    <td><?= $esc($dur) ?></td>
                    <td class = "cssNotes"><?= $esc($cmt) ?></td>
                </tr>
            <?php $i++; endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</body>
</html>