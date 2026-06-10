<?php
require_once __DIR__ . "/helpers.php";
header("Content-Type: application/json");

$hashKey = trim((string)($_POST["hashKey"] ?? ""));
$path = trim((string)($_POST["path"] ?? ""));
if ($path === "") {
    exit(json_encode(["ok" => true]));
}

fleetDeleteInventoryImage($db, $path);
$catalog = fleetReadInventoryCatalog($db);
if ($hashKey !== "" && array_key_exists($hashKey, $catalog) && is_array($catalog[$hashKey])) {
    $catalog[$hashKey]["path"] = "";
    fleetWriteInventoryCatalog($db, $catalog, $userId);
}
exit(json_encode(["ok" => true], JSON_UNESCAPED_SLASHES));
