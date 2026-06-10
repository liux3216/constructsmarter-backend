<?php
require_once __DIR__ . "/helpers.php";
header("Content-Type: application/json");

$data = json_decode((string)($_POST["data"] ?? "{}"), true);
if (!is_array($data)) {
    jsonResponse(400, ["msg" => "Invalid yard inventory payload."]);
}

$normalized = [];
foreach ($data as $yardKey => $yard) {
    if (!is_array($yard)) continue;
    $inventory = [];
    foreach (($yard["inventory"] ?? []) as $itemKey => $item) {
        if (!is_array($item)) continue;
        $inventory[(string)$itemKey] = [
            "qty" => trim((string)($item["qty"] ?? "")),
            "yardItemNote" => trim((string)($item["yardItemNote"] ?? "")),
        ];
    }
    $normalized[(string)$yardKey] = [
        "title" => trim((string)($yard["title"] ?? "")),
        "yardNote" => trim((string)($yard["yardNote"] ?? "")),
        "inventory" => $inventory,
        "createdTime" => trim((string)($yard["createdTime"] ?? "")),
        "updateTime" => date("Y-m-d H:i:s"),
    ];
}

fleetWriteInventoryYards($db, $normalized, $userId);
exit(json_encode(["ok" => true], JSON_UNESCAPED_SLASHES));
