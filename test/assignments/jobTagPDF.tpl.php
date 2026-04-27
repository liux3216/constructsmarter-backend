<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #1f2933;
            margin: 22px;
        }
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }
        .logoWrap,
        .titleWrap {
            display: table-cell;
            vertical-align: middle;
        }
        .logo {
            width: auto;
            height: auto;
            max-width: 120px;
            max-height: 52px;
        }
        .appName {
            margin-top: 5px;
            color: #475569;
            font-size: 10px;
            font-weight: bold;
            line-height: 1.2;
        }
        .titleWrap {
            text-align: right;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 0;
        }
        .subtitle {
            margin-top: 3px;
            color: #64748b;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 10px;
        }
        td {
            border: 1px solid #d7dde5;
            vertical-align: top;
            padding: 6px 8px;
            word-break: break-word;
        }
        .label {
            font-size: 9px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .value {
            min-height: 14px;
            white-space: pre-wrap;
        }
        .section {
            background: #111827;
            color: #fff;
            font-weight: bold;
            padding: 5px 8px;
            margin: 12px 0 0;
        }
        .signatureBox {
            height: 92px;
            text-align: center;
            vertical-align: middle;
        }
        .signature {
            max-width: 100%;
            max-height: 82px;
        }
        .emptySign {
            color: #94a3b8;
            padding-top: 34px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logoWrap">
            <img class="logo" src="/opt/bitnami/apache/htdocs/test/logo.png" alt="Construct Smarter">
            <?php if (!empty($data["appName"])): ?>
                <div class="appName"><?= jobTagH($data["appName"]) ?></div>
            <?php endif; ?>
        </div>
        <div class="titleWrap">
            <div class="title">JOB TAG</div>
            <div class="subtitle">Assignment #<?= jobTagH($data["id"] ?? "") ?></div>
        </div>
    </div>

    <table>
        <tr>
            <td>
                <div class="label">Project Manager</div>
                <div class="value"><?= jobTagH($data["projectManager"] ?? "") ?></div>
            </td>
            <td>
                <div class="label">Project Number</div>
                <div class="value"><?= jobTagH($data["projectNumber"] ?? "") ?></div>
            </td>
            <td>
                <div class="label">Site Contact</div>
                <div class="value"><?= jobTagH($data["requestor"] ?? "") ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Organization</div>
                <div class="value"><?= jobTagH($data["clientName"] ?? "") ?></div>
            </td>
            <td>
                <div class="label">Client PO</div>
                <div class="value"><?= jobTagH($data["clientPONumber"] ?? "") ?></div>
            </td>
            <td>
                <div class="label">Client Project / Job Number</div>
                <div class="value"><?= jobTagH($data["clientProjectNumber"] ?? "") ?></div>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <div class="label">Job Site</div>
                <div class="value"><?= jobTagH($data["jobSite"] ?? "") ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Technician Name</div>
                <div class="value"><?= jobTagH($data["memberName"] ?? "") ?></div>
            </td>
            <td>
                <div class="label">Date Submitted</div>
                <div class="value"><?= jobTagH(jobTagDate($data["submitDateTime"] ?? "")) ?> <?= jobTagH(jobTagTime($data["submitDateTime"] ?? "")) ?></div>
            </td>
            <td>
                <div class="label">Supervisor Name</div>
                <div class="value"><?= jobTagH($data["supervisor"] ?? "") ?></div>
            </td>
        </tr>
    </table>

    <div class="section">Time</div>
    <table>
        <tr>
            <td>
                <div class="label">PreWork Driver</div>
                <div class="value"><?= jobTagH(jobTagYesNo($data["preDriver"] ?? null)) ?></div>
                <div class="label" style="margin-top:8px;">PostWork Driver</div>
                <div class="value"><?= jobTagH(jobTagYesNo($data["postDriver"] ?? null)) ?></div>
            </td>
            <td>
                <div class="label">Travel Start</div>
                <div class="value"><?= jobTagH(jobTagDate($data["travelStartTime"] ?? "")) ?> <?= jobTagH(jobTagTime($data["travelStartTime"] ?? "")) ?></div>
            </td>
            <td>
                <div class="label">Work Start</div>
                <div class="value"><?= jobTagH(jobTagDate($data["workStartTime"] ?? "")) ?> <?= jobTagH(jobTagTime($data["workStartTime"] ?? "")) ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Had Lunch</div>
                <div class="value"><?= jobTagH(jobTagYesNo($data["hadLunch"] ?? null)) ?></div>
            </td>
            <td>
                <div class="label">Work End</div>
                <div class="value"><?= jobTagH(jobTagDate($data["workEndTime"] ?? "")) ?> <?= jobTagH(jobTagTime($data["workEndTime"] ?? "")) ?></div>
            </td>
            <td>
                <div class="label">Travel End</div>
                <div class="value"><?= jobTagH(jobTagDate($data["travelEndTime"] ?? "")) ?> <?= jobTagH(jobTagTime($data["travelEndTime"] ?? "")) ?></div>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <div class="label">Work Finished</div>
                <div class="value"><?= jobTagH(ucfirst((string)($data["workFinished"] ?? ""))) ?></div>
            </td>
        </tr>
    </table>

    <div class="section">Work</div>
    <table>
        <tr>
            <td>
                <div class="label">Description - Work Requested</div>
                <div class="value"><?= jobTagH($data["workRequired"] ?? "") ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Description - Work Performed</div>
                <div class="value"><?= jobTagH($data["workPerformed"] ?? "") ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Additional Information</div>
                <div class="value"><?= jobTagH($data["additionalInfo"] ?? "") ?></div>
            </td>
        </tr>
        <?php $coords = jobTagCoords($data["coords"] ?? null); ?>
        <?php if ($coords["latLong"] !== ""): ?>
            <tr>
                <td>
                    <div class="label">Current Coordinates</div>
                    <div class="value">
                        <a href="https://www.google.com/maps/search/<?= jobTagH($coords["latLong"]) ?>">
                            <?= jobTagH($coords["latLong"]) ?>
                        </a>
                    </div>
                    <?php if ($coords["accuracy"] !== ""): ?>
                        <div class="label" style="margin-top:8px;">Accuracy</div>
                        <div class="value"><?= jobTagH($coords["accuracy"]) ?> meters</div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="section">Signatures</div>
    <table>
        <tr>
            <td>
                <div class="label">Technician Signature</div>
                <div class="signatureBox">
                    <?php if (!empty($data["techSign"])): ?>
                        <img class="signature" src="<?= jobTagH($data["techSign"]) ?>" alt="Technician signature">
                    <?php else: ?>
                        <div class="emptySign">No signature provided</div>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <div class="label">Site Contact Signature</div>
                <div class="signatureBox">
                    <?php if (!empty($data["clientSign"])): ?>
                        <img class="signature" src="<?= jobTagH($data["clientSign"]) ?>" alt="Client supervisor signature">
                    <?php else: ?>
                        <div class="emptySign">No signature provided</div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
