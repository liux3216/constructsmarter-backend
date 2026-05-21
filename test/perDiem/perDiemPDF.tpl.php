<?php
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
if (PHP_SAPI !== 'cli' && $remoteAddr !== '127.0.0.1' && $remoteAddr !== '::1') {
    http_response_code(403);
    exit('Forbidden');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 12px; }
        .section { margin-bottom: 14px; }
        .row { margin-bottom: 6px; }
        .label { display: inline-block; width: 160px; font-weight: bold; }
        .value { display: inline-block; }
        .box { border: 1px solid #ccc; padding: 8px; white-space: pre-wrap; }
        .stamp {
            position: fixed; top: 35%; left: 10%; width: 80%; text-align: center;
            transform: rotate(-20deg); opacity: 0.15; z-index: 9999; color: <?= h($data['stampColor'] ?? '#000') ?>;
        }
        .stamp .company { font-size: 26px; font-weight: bold; letter-spacing: 2px; }
        .stamp .status { font-size: 64px; font-weight: bold; margin: 10px 0; }
        .stamp .by { font-size: 14px; }
        .hr { border-top: 1px solid #ddd; margin: 16px 0; }
    </style>
</head>
<body>
    <?php if (!empty($data['approvalTime'])): ?>
    <div class="stamp">
        <div class="company">Construct Smarter</div>
        <div class="status"><?= strtoupper(h($data['status'] ?? '')) ?></div>
        <div class="by">by <?= h($data['approverName'] ?? '') ?></div>
    </div>
    <?php endif; ?>
    <h1><a href="<?= h($mainUrl . '/PerDiem/' . $perDiemId) ?>">Per Diem Request <?= $perDiemId ?></a></h1>
    <div class="section">
        <div class="row"><span class="label">Requester:</span><span class="value"><a href="<?= h($mainUrl . '/Users/' . ($data['requesterId'] ?? '')) ?>"><?= h($data['requesterName'] ?? '') ?></a></span></div>
        <div class="row"><span class="label">Department:</span><span class="value"><?= h($data['department'] ?? '') ?></span></div>
        <div class="row"><span class="label">Project:</span><span class="value"><a href="<?= h($mainUrl . '/Projects/' . ($data['projectId'] ?? '')) ?>"><?= h($data['projectName'] ?? '') ?></a></span></div>
    </div>
    <div class="section">
        <div class="row"><span class="label">Start Date:</span><span class="value"><?= fmtDate($data['startDate'] ?? null) ?></span></div>
        <div class="row"><span class="label">End Date:</span><span class="value"><?= fmtDate($data['endDate'] ?? null) ?></span></div>
        <div class="row"><span class="label">Hotel Name:</span><span class="value"><?= h($data['hotelName'] ?? '') ?></span></div>
        <div class="row"><span class="label">Hotel Address:</span><span class="value"><?= h($data['hotelAddress'] ?? '') ?></span></div>
    </div>
    <?php if (!empty($data['notes'])): ?>
    <div class="section">
        <div class="row label">Notes:</div>
        <div class="box"><?= h($data['notes']) ?></div>
    </div>
    <?php endif; ?>
    <div class="hr"></div>
    <div class="section">
        <div class="row"><span class="label">Approver:</span><span class="value"><a href="<?= h($mainUrl . '/Users/' . ($data['approverId'] ?? '')) ?>"><?= h($data['approverName'] ?? '') ?></a></span></div>
        <?php if (!empty($data['approvalTime'])): ?>
        <div class="row"><span class="label">Decision Time:</span><span class="value"><?= fmtDateTime($data['approvalTime'] ?? null) ?></span></div>
        <?php endif; ?>
    </div>
    <?php if (array_key_exists('approverNotes', $data) && $data['approverNotes'] !== null && $data['approverNotes'] !== ''): ?>
    <div class="section">
        <div class="row label">Approver Notes:</div>
        <div class="box"><?= h($data['approverNotes']) ?></div>
    </div>
    <?php endif; ?>
    <div class="section">
        <div class="row"><span class="label">Created By:</span><span class="value"><a href="<?= h($mainUrl . '/Users/' . ($data['creatorId'] ?? '')) ?>"><?= h($data['creatorName'] ?? '') ?></a></span></div>
        <div class="row"><span class="label">Created At:</span><span class="value"><?= fmtDateTime($data['createdAt'] ?? null) ?></span></div>
        <?php if (!empty($data['updaterId'])): ?>
        <div class="row"><span class="label">Updated By:</span><span class="value"><a href="<?= h($mainUrl . '/Users/' . ($data['updaterId'] ?? '')) ?>"><?= h($data['updaterName'] ?? '') ?></a></span></div>
        <div class="row"><span class="label">Updated At:</span><span class="value"><?= fmtDateTime($data['updatedAt'] ?? null) ?></span></div>
        <?php endif; ?>
    </div>
</body>
</html>
