<?php
if(!isset($recordId)){
    http_response_code(403);
    exit("Forbidden");
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset = "utf-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 11px;
        color: #1a1a1a;
        padding: 24px;
        background: #fff;
    }
    .doc-header {
        border-bottom: 2px solid #2563eb;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }
    .doc-title {
        font-size: 20px;
        font-weight: bold;
        color: #111;
        margin-bottom: 4px;
    }
    .doc-subtitle {
        font-size: 10px;
        color: #666;
    }
    .section-heading {
        font-size: 12px;
        font-weight: bold;
        color: #fff;
        background: #2563eb;
        padding: 5px 10px;
        margin: 14px 0 6px;
        border-radius: 3px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6px;
        table-layout: fixed;
    }
    td {
        border: 1px solid #dde1e7;
        padding: 5px 8px;
        vertical-align: top;
        word-wrap: break-word;
        overflow-wrap: break-word;
        word-break: break-word;
        white-space: normal;
    }
    .label {
        font-weight: bold;
        width: 32%;
        background: #f4f6fa;
        color: #374151;
    }
    tr:nth-child(even) td:not(.label) {
        background: #fafbfc;
    }
    .display-row td {
        background: #fffbe6;
        border-left: 3px solid #f59e0b;
        font-size: 10.5px;
        color: #444;
    }
    .cssNotes {
        white-space: pre-wrap;
        word-break: break-all;
        font-size: 10.5px;
        line-height: 1.5;
    }
    .signature {
        border: 1px solid #bbb;
        border-radius: 4px;
        padding: 3px;
        background: #fff;
        max-width: 100%;
        max-height: 100px;
    }
    .figuresContainer {
        display: block;
        width: 100%;
    }
    .figure {
        display: inline-block;
        vertical-align: top;
        margin: 4px 6px 4px 0;
        max-width: 110px;
        text-align: center;
    }
    .img {
        display: block;
        width: 100%;
        height: auto;
        border: 1px solid #dde1e7;
        border-radius: 3px;
    }
    .figcaption {
        display: block;
        width: 100%;
        background: #1e293b;
        color: #fff;
        font-size: 9px;
        padding: 2px 4px;
        word-break: break-word;
        border-radius: 0 0 3px 3px;
    }
    .sub-table {
        margin: 0 0 8px 12px;
        border-left: 3px solid #2563eb;
        padding-left: 8px;
    }
    .sub-divider {
        border: none;
        border-top: 1px dashed #dde1e7;
        margin: 6px 0;
    }
</style>
</head>
<body>
<div class = "doc-header">
    <div class = "doc-title"><?= htmlspecialchars($form["name"]) ?></div>
    <div class = "doc-subtitle">
        Record #<?= $recordId ?><?= $recordName ? " &mdash; " . htmlspecialchars($recordName) : "" ?>
    </div>
</div>
<?php renderFields($data); ?>
<?php
function renderFields(array $fields): void {
    echo "<table>";
    foreach ($fields as $f) {
        $type  = $f["type"]  ?? "text";
        $label = htmlspecialchars($f["label"] ?? "");
        $value = $f["value"] ?? "";
        if ($type === "sub_form") {
            echo "</table>";
            echo "<div class=\"section-heading\">$label</div>";
            foreach ($f["records"] as $i => $sub) {
                if ($i > 0) echo "<hr class=\"sub-divider\"/>";
                echo "<div class=\"sub-table\">";
                renderFields($sub["fields"]);
                echo "</div>";
            }
            echo "<table>";
        } else if ($type === "picture" && $value) {
            global $db, $privateBucket;
            $files = $db->all(
                "SELECT `id`, `name`, `description` AS `caption` FROM `fileInfo` WHERE `parentId` = ?;",
                [$value], __FILE__, __LINE__
            );
            echo "<tr>";
            echo "<td class=\"label\">$label</td>";
            echo "<td><div class=\"figuresContainer\">";
            foreach ($files as $file) {
                $url = getObjectUrl($privateBucket, $file["id"], $file["name"]);
                echo "<figure class=\"figure\">";
                echo "<img class=\"img\" src=\"$url\"/>";
                if ($file["caption"]) {
                    echo "<figcaption class=\"figcaption\">" . htmlspecialchars($file["caption"]) . "</figcaption>";
                }
                echo "</figure>";
            }
            echo "</div></td>";
            echo "</tr>";
        } else if ($type === "display") {
            echo "<tr class=\"display-row\">";
            echo "<td colspan=\"2\">" . sanitizeHtml($f["label"]) . "</td>";
            echo "</tr>";
        } else if ($type === "textarea") {
            echo "<tr>";
            echo "<td class=\"label\">$label</td>";
            echo "<td class=\"cssNotes\">" . htmlspecialchars($value) . "</td>";
            echo "</tr>";
        } else if ($type === "signature" && $value) {
            echo "<tr>";
            echo "<td class=\"label\">$label</td>";
            echo "<td><img class=\"signature\" src=\"" . htmlspecialchars($value) . "\"/></td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td class=\"label\">$label</td>";
            echo "<td>" . htmlspecialchars($value) . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
}
?>
</body>
</html>