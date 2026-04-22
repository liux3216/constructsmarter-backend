<?php
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    http_response_code(403);
    exit('Forbidden');
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 12px;
                color: #222;
            }
            h1 {
                font-size: 18px;
                margin-bottom: 12px;
            }
            .section {
                margin-bottom: 14px;
            }
            .row {
                margin-bottom: 6px;
            }
            .label {
                display: inline-block;
                width: 160px;
                font-weight: bold;
            }
            .value {
                display: inline-block;
            }
            .box {
                border: 1px solid #ccc;
                padding: 8px;
                white-space: pre-wrap;
            }
            .hr {
                border-top: 1px solid #ddd;
                margin: 16px 0;
            }
            .stamp {
                position: fixed;
                top: 35%;
                left: 10%;
                width: 80%;
                text-align: center;
                transform: rotate(-20deg);
                opacity: 0.15;
                z-index: 9999;
                color: <?= h($data["stampColor"] ?? "#000") ?>;
            }
            .stamp .company {
                font-size: 26px;
                font-weight: bold;
                letter-spacing: 2px;
            }
            .stamp .status {
                font-size: 64px;
                font-weight: bold;
                margin: 10px 0;
            }
            .stamp .by {
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <?php if (array_key_exists("approvalTime", $data)): ?>
        <div class="stamp">
            <div class="company">
                Construct Smarter
            </div>
            <div class="status">
                <?= strtoupper(h($data["status"])) ?>
            </div>
            <div class="by">
                by <?= h($data["approverName"]) ?>
            </div>
        </div>
        <?php endif; ?>
        <h1>
            <a href="<?= h($mainUrl."/TimeOffs/".$timeOffId) ?>" >
                Time Off Request <?= $timeOffId ?>
            </a>
        </h1>
        <div class="section">
            <div class="row">
                <span class="label">Requester:</span>
                <span class="value">
                    <a href="<?= h($mainUrl."/Users/".$data["requesterId"]) ?>">
                        <?= h($data["requesterName"] ?? "") ?>
                    </a>
                </span>
            </div>
            <div class="row">
                <span class="label">Department:</span>
                <span class="value"><?= h($data["department"] ?? "") ?></span>
            </div>
            <div class="row">
                <span class="label">Type:</span>
                <span class="value"><?= h($data["type"] ?? "") ?></span>
            </div>
        </div>
        <div class="section">
            <div class="row">
                <span class="label">From Date:</span>
                <span class="value"><?= fmtDate($data["fromDate"] ?? null) ?></span>
            </div>
            <div class="row">
                <span class="label">To Date:</span>
                <span class="value"><?= fmtDate($data["toDate"] ?? null) ?></span>
            </div>
            <div class="row">
                <span class="label">Total Hours:</span>
                <span class="value"><?= h($data["totalHours"] ?? "") ?></span>
            </div>
        </div>
        <?php if ($data["notes"]): ?>
        <div class="section">
            <div class="row label">Notes:</div>
            <div class="box"><?= h($data["notes"]) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($data["detail"]): ?>
        <div class="hr"></div>
        <div class="section">
            <div class="row label">Detail Hours <span style="font-weight:normal">(2-hour increment)</span></div>
            <ul style="margin-top:8px;">
            <?php foreach ($data["detail"] as $d): ?>
                <li style="margin-bottom:6px;">
                    <strong><?= h($d["date"]) ?></strong>
                    <?php if ($d["holiday"]): ?>
                    <span style="color:#0dcaf0;">
                        (<?= h($d["holidayName"] ?? "Holiday") ?>)
                    </span>
                    <?php elseif ($d["weekend"]): ?>
                    <span style="color:#198754;">
                        (Weekend)
                    </span>
                    <?php else: ?>
                    —
                    <?php if ($d["startTime"] && (int)$d["hours"] < 8): ?>
                    start <?= h($d["startTime"]) ?>,
                    <?php endif; ?>
                    <?= h($d["hours"]) ?> hours
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
            <div style="margin-top:8px;font-weight:bold;">
                Total:
                <span style="color:green;">
                    <?= h($data["totalHours"]) ?> hours
                </span>
            </div>
        </div>
        <?php endif; ?>
        <div class="hr"></div>
        <div class="section">
            <div class="row">
                <span class="label">Approver:</span>
                <span class="value">
                    <a href="<?= h($mainUrl."/Users/".$data["approverId"]) ?>">
                        <?= h($data["approverName"]) ?>
                    </a>
                </span>
            </div>
            <?php if (array_key_exists("approvalTime", ($data))): ?>
            <div class="row">
                <span class="label">Decision Time:</span>
                <span class="value"><?= fmtDateTime($data["approvalTime"]) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php if (array_key_exists("approverNotes", $data)): ?>
        <div class="section">
            <div class="row label">Approver Notes:</div>
            <div class="box"><?= $data["approverNotes"] ?></div>
        </div>
        <?php endif; ?>
        <div class="section">
            <div class="row">
                <span class="label">Created By:</span>
                <span class="value">
                    <a href="<?= h($mainUrl."/Users/".$data["creatorId"]) ?>">
                        <?= h($data["creatorName"] ?? "") ?>
                    </a>
                </span>
            </div>
            <div class="row">
                <span class="label">Created At:</span>
                <span class="value"><?= fmtDateTime($data["createdAt"] ?? null) ?></span>
            </div>
            <?php if (array_key_exists("updaterId", $data)): ?>
            <div class="row">
                <span class="label">Updated By:</span>
                <span class="value">
                    <a href="<?= h($mainUrl."/Users/".$data["updaterId"]) ?>">
                        <?= h($data["updaterName"] ?? "") ?>
                    </a>
                </span>
            </div>
            <div class="row">
                <span class="label">Updated At:</span>
                <span class="value"><?= fmtDateTime($data["updatedAt"] ?? null) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </body>
</html>