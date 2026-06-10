<?php
require_once __DIR__ . "/helpers.php";
header("Content-Type: application/json");
exit(json_encode(fleetReadInventoryCatalog($db), JSON_UNESCAPED_SLASHES));
