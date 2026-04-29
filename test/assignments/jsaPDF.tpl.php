<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #1f2933;
            margin: 20px;
        }
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 14px;
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
            font-size: 22px;
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
            margin-bottom: 9px;
        }
        td,
        th {
            border: 1px solid #d7dde5;
            vertical-align: top;
            padding: 5px 7px;
            word-break: break-word;
        }
        th {
            background: #eef2f7;
            color: #334155;
            font-size: 9px;
            text-align: left;
            text-transform: uppercase;
        }
        .label {
            font-size: 8px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .value {
            min-height: 13px;
            white-space: pre-wrap;
        }
        .section {
            background: #111827;
            color: #fff;
            font-weight: bold;
            padding: 5px 8px;
            margin: 11px 0 0;
        }
        .subsection {
            color: #111827;
            font-size: 12px;
            font-weight: bold;
            margin: 8px 0 4px;
        }
        .muted {
            color: #64748b;
        }
        .signature {
            max-width: 100%;
            max-height: 82px;
        }
        .signatureCell {
            height: 88px;
            text-align: center;
            vertical-align: middle;
        }
        .pageBreak {
            page-break-before: always;
        }
    </style>
</head>
<body>
<?php $content = $data["content"] ?? []; ?>
<?php $others = is_array($content["others"] ?? null) ? $content["others"] : []; ?>
    <div class="header">
        <div class="logoWrap">
            <img class="logo" src="/opt/bitnami/apache/htdocs/test/logo.png" alt="Construct Smarter">
            <?php if(!empty($data["appName"])): ?>
                <div class="appName"><?= jsaPdfH($data["appName"]) ?></div>
            <?php endif; ?>
        </div>
        <div class="titleWrap">
            <div class="title">JOB SAFETY ANALYSIS</div>
            <div class="subtitle">Assignment #<?= jsaPdfH($data["id"] ?? "") ?></div>
        </div>
    </div>

    <table>
        <tr>
            <td>
                <div class="label">Project</div>
                <div class="value"><?= jsaPdfH($data["projectName"] ?? "") ?></div>
            </td>
            <td>
                <div class="label">Organization</div>
                <div class="value"><?= jsaPdfH($data["clientName"] ?? "") ?></div>
            </td>
            <td>
                <div class="label">Submitted</div>
                <div class="value"><?= jsaPdfH(jsaPdfDateTime($data["submitDateTime"] ?? "")) ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Member</div>
                <div class="value"><?= jsaPdfH($content["memberName"] ?? $data["memberName"] ?? "") ?></div>
            </td>
            <td>
                <div class="label">Role</div>
                <div class="value"><?= jsaPdfH($data["laborCategory"] ?? "") ?></div>
            </td>
            <td>
                <div class="label">Supervisor / Lead</div>
                <div class="value"><?= jsaPdfH(trim(($data["supervisor"] ?? "")." ".($data["leadName"] ?? ""))) ?></div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <div class="label">Job Location</div>
                <div class="value"><?= jsaPdfH($content["loc"] ?? $data["jobSite"] ?? "") ?></div>
            </td>
            <td>
                <div class="label">Site Contact</div>
                <div class="value"><?= jsaPdfH(trim(($data["siteContactName"] ?? "")." ".($data["siteContactPhone"] ?? ""))) ?></div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Nearest Medical Facility</div>
                <div class="value"><?= jsaPdfH($content["loc2"] ?? "") ?></div>
            </td>
            <td>
                <div class="label">Muster / Evacuation Point</div>
                <div class="value"><?= jsaPdfH($content["loc3"] ?? "") ?></div>
            </td>
            <td>
                <div class="label">Emergency Equipment</div>
                <div class="value"><?= jsaPdfH($content["loc4"] ?? "") ?></div>
            </td>
        </tr>
    </table>

    <div class="section">Pre-Job Questions</div>
    <table>
        <tr>
            <td>
                <div class="label">911 Availability Identified</div>
                <div class="value"><?= jsaPdfH(jsaPdfYesNo($content["check1"] ?? "")) ?></div>
            </td>
            <td>
                <div class="label">Water Runoff Controlled</div>
                <div class="value"><?= jsaPdfH(jsaPdfYesNo($content["sel1"] ?? "")) ?></div>
            </td>
            <td>
                <div class="label">Site Examined for Hazards</div>
                <div class="value"><?= jsaPdfH(jsaPdfYesNo($content["sel2"] ?? "")) ?></div>
            </td>
            <td>
                <div class="label">Confined Space / Hot Work</div>
                <div class="value"><?= jsaPdfH(jsaPdfYesNo($content["sel3"] ?? "")) ?></div>
            </td>
        </tr>
    </table>

    <?php if(($content["sel3"] ?? "") === "Yes"): ?>
        <div class="section">Confined Space / Hot Work</div>
        <table>
            <tr>
                <th>Question</th>
                <th style="width:18%;">Answer</th>
            </tr>
            <?php
                $confined = [
                    "confinedSpaceHazard" => "Does Task Present Confined Space Hazards?",
                    "airMonitoring" => "Do We need air Monitoring?",
                    "hotWork" => "Will there be Hot Work?",
                    "permit" => "Do we need a permit?",
                    "fireWatch" => "Is Fire Watch required?",
                    "respirators" => "Are respirators needed?",
                    "attendant" => "Do we need attendant?",
                ];
            ?>
            <?php foreach($confined as $key => $label): ?>
                <tr>
                    <td><?= jsaPdfH($label) ?></td>
                    <td><?= jsaPdfH(jsaPdfYesNo($content[$key] ?? "")) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <div class="section">Potential Serious Injury and Fatality Topics</div>
    <?php $sif = is_array($content["sif"] ?? null) ? $content["sif"] : []; ?>
    <?php $hasSif = false; ?>
    <?php foreach($sif as $title => $item): ?>
        <?php if(!empty($item["selected"])): ?>
            <?php $hasSif = true; ?>
            <div class="subsection"><?= jsaPdfH($title) ?></div>
            <table>
                <tr>
                    <td>
                        <div class="label">Hazard</div>
                        <div class="value"><?= jsaPdfH($item["inputHazard"] ?? "") ?></div>
                    </td>
                    <td>
                        <div class="label">Other Controls</div>
                        <div class="value"><?= jsaPdfH($item["inputControls"] ?? "") ?></div>
                    </td>
                </tr>
                <?php foreach(($item["content"] ?? []) as $hazard => $controls): ?>
                    <?php if(is_array($controls) && count($controls) > 0): ?>
                        <tr>
                            <td>
                                <div class="label">Topic</div>
                                <div class="value"><?= jsaPdfH($hazard) ?></div>
                            </td>
                            <td>
                                <div class="label">Selected Controls</div>
                                <div class="value"><?= jsaPdfList($controls) ?></div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if(!$hasSif): ?>
        <table><tr><td class="muted">No SIF topics selected.</td></tr></table>
    <?php endif; ?>

    <div class="section">Secondary Hazard Control</div>
    <?php $hazardControls = is_array($content["hazardControls"] ?? null) ? $content["hazardControls"] : []; ?>
    <?php if(count($hazardControls) > 0): ?>
        <table>
            <tr>
                <th style="width:28%;">Hazard</th>
                <th>Control</th>
            </tr>
            <?php foreach($hazardControls as $hazard): ?>
                <tr>
                    <td><?= jsaPdfH(($hazard["hazard"] ?? "") === "Others" ? ($hazard["otherHazard"] ?? "Others") : ($hazard["hazard"] ?? "")) ?></td>
                    <td><?= jsaPdfH($hazard["control"] ?? "") ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <table><tr><td class="muted">No secondary hazards entered.</td></tr></table>
    <?php endif; ?>

    <div class="section">PPE Checklist</div>
    <table>
        <?php
            $ppeRows = [
                "Eye & Face" => ["key" => "eyeFace", "other" => "eyeFace"],
                "Hand Gloves" => ["key" => "handGloves", "other" => "handGloves"],
                "Foot" => ["key" => "foot", "other" => "foot"],
                "Head" => ["key" => "head", "other" => "head"],
                "Gas Detector" => ["key" => "gasDetector", "other" => "gasDetector"],
                "Respiratory Protection" => ["key" => "respiratoryProtection", "other" => "respiratoryProtection"],
                "Protective Clothing" => ["key" => "protectiveClothing", "other" => "protectiveClothing"],
                "Hearing" => ["key" => "hearing", "other" => "hearing"],
                "Fall Protection" => ["key" => "fallProtectionList", "other" => "fallProtectionList"],
            ];
        ?>
        <?php foreach($ppeRows as $label => $meta): ?>
            <?php $other = jsaPdfOther($others, $meta["other"]); ?>
            <tr>
                <td style="width:28%;"><div class="label"><?= jsaPdfH($label) ?></div></td>
                <td>
                    <div class="value"><?= jsaPdfList($content[$meta["key"]] ?? []) ?></div>
                    <?php if($other !== ""): ?>
                        <div class="value"><strong>Other:</strong> <?= jsaPdfH($other) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td><div class="label">Additional PPE</div></td>
            <td><div class="value"><?= jsaPdfH($content["additionalPPE"] ?? "") ?></div></td>
        </tr>
    </table>

    <div class="section">Job Scope Change / Additional Hazard / Notes</div>
    <table>
        <tr>
            <td><div class="value"><?= jsaPdfH($content["notes"] ?? "") ?></div></td>
        </tr>
    </table>

    <div class="section">Signatures</div>
    <table>
        <tr>
            <th>Name</th>
            <th style="width:20%;">Role</th>
            <th style="width:18%;">Date</th>
            <th style="width:30%;">Signature</th>
        </tr>
        <?php $mainSign = jsaPdfSignatureSrc($content["sign"] ?? ($content["jobSafetyAnalysisSign"] ?? [])); ?>
        <tr>
            <td><?= jsaPdfH($content["memberName"] ?? $data["memberName"] ?? "") ?></td>
            <td><?= jsaPdfH($data["laborCategory"] ?? "") ?></td>
            <td><?= jsaPdfH($content["submitDate"] ?? "") ?></td>
            <td class="signatureCell">
                <?php if($mainSign !== ""): ?>
                    <img class="signature" src="<?= jsaPdfH($mainSign) ?>" alt="Signature">
                <?php endif; ?>
            </td>
        </tr>
        <?php foreach(($content["jobSafetyAnalysisInternalSigns"] ?? []) as $index => $sign): ?>
            <?php $signData = jsaPdfSignatureSrc($content["iSigns"][$index] ?? ($sign["data"] ?? [])); ?>
            <tr>
                <td><?= jsaPdfH($sign["laborName"] ?? "") ?></td>
                <td><?= jsaPdfH($sign["laborCategory"] ?? "") ?></td>
                <td><?= jsaPdfH($content["submitDate"] ?? "") ?></td>
                <td class="signatureCell">
                    <?php if($signData !== ""): ?>
                        <img class="signature" src="<?= jsaPdfH($signData) ?>" alt="Signature">
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
