<?php
require_once __DIR__ . "/helpers.php";
header("Content-Type: application/json");

$hashKey = trim((string)($_POST["hashKey"] ?? ""));
$path = trim((string)($_POST["path"] ?? ""));
if ($hashKey === "") {
    jsonResponse(400, ["msg" => "Missing hashKey."]);
}
if (!isset($_FILES["profile"])) {
    jsonResponse(400, ["msg" => "Missing profile file."]);
}

$url = fleetSaveInventoryImage($db, $hashKey, $_FILES["profile"], $path, $userId);

$catalog = fleetReadInventoryCatalog($db);
if (!array_key_exists($hashKey, $catalog) || !is_array($catalog[$hashKey])) {
    $catalog[$hashKey] = ["title" => "", "note" => "", "path" => $url];
} else {
    $catalog[$hashKey]["path"] = $url;
}
fleetWriteInventoryCatalog($db, $catalog, $userId);

exit(json_encode($url, JSON_UNESCAPED_SLASHES));
