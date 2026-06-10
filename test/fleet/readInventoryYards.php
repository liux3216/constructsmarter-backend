<?php
require_once __DIR__ . "/helpers.php";
header("Content-Type: application/json");
exit(json_encode(fleetReadInventoryYards($db), JSON_UNESCAPED_SLASHES));
