<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$truckNumber = trim((string)($_POST["truckNumber"] ?? ""));
$trailer = trim((string)($_POST["trailer"] ?? "no"));
$open = trim((string)($_POST["open"] ?? "Yes"));

if ($truckNumber === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing truck number."]));
}

$existing = $db->one(
    "SELECT `id` FROM `fleets` WHERE `truckNumber` = ? LIMIT 1;",
    [$truckNumber],
    __FILE__,
    __LINE__
);
if ($existing) {
    http_response_code(400);
    exit(json_encode(["msg" => "Truck number already exists."]));
}

$fleetType = in_array($trailer, ["t", "hp"], true) ? "trailer" : "truck";
$isHotPatch = $trailer === "hp" ? "yes" : ($fleetType === "trailer" ? "no" : null);
$void = $open === "No" ? "yes" : "no";

$db->exec(
    "INSERT INTO `fleets` (`truckNumber`, `fleetType`, `isHotPatch`, `creatorId`, `updaterId`, `void`)
    VALUES (?, ?, ?, ?, ?, ?);",
    [$truckNumber, $fleetType, $isHotPatch, $userId, $userId, $void],
    __FILE__,
    __LINE__
);

$row = $db->one(
    "SELECT `id`, `truckNumber` FROM `fleets` WHERE `truckNumber` = ? LIMIT 1;",
    [$truckNumber],
    __FILE__,
    __LINE__
);

exit(json_encode($row));
