<?php
require_once __DIR__ . "/helpers.php";
header("Content-Type: application/json");

$data = json_decode((string)($_POST["data"] ?? "{}"), true);
if (!is_array($data)) {
    jsonResponse(400, ["msg" => "Invalid inventory catalog payload."]);
}

$normalized = [];
foreach ($data as $hashKey => $item) {
    if (!is_array($item)) continue;
    $normalized[(string)$hashKey] = [
        "title" => trim((string)($item["title"] ?? "")),
        "note" => trim((string)($item["note"] ?? "")),
        "path" => trim((string)($item["path"] ?? "")),
    ];
}

fleetWriteInventoryCatalog($db, $normalized, $userId);
exit(json_encode(["ok" => true], JSON_UNESCAPED_SLASHES));
