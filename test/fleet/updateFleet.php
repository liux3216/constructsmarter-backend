<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$id = trim((string)($_POST["id"] ?? ""));
$truckNumber = trim((string)($_POST["truckNumber"] ?? ""));
$trailer = trim((string)($_POST["trailer"] ?? "no"));
$open = trim((string)($_POST["open"] ?? "Yes"));
$voidReason = trim((string)($_POST["background"] ?? ""));

if ($id === "" && $truckNumber === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing fleet id."]));
}

$fleetType = in_array($trailer, ["t", "hp"], true) ? "trailer" : "truck";
$isHotPatch = $trailer === "hp" ? "yes" : ($fleetType === "trailer" ? "no" : null);
$void = $open === "No" ? "yes" : "no";

$db->exec(
    "UPDATE `fleets`
    SET `fleetType` = ?,
        `isHotPatch` = ?,
        `updaterId` = ?,
        `void` = ?,
        `voidReason` = ?
    WHERE `id` = ? OR `truckNumber` = ?;",
    [$fleetType, $isHotPatch, $userId, $void, $voidReason, $id, $truckNumber],
    __FILE__,
    __LINE__
);

$row = $db->one(
    "SELECT `id`, `truckNumber` FROM `fleets` WHERE `id` = ? OR `truckNumber` = ? LIMIT 1;",
    [$id, $truckNumber],
    __FILE__,
    __LINE__
);

if (!$row) {
    http_response_code(404);
    exit(json_encode(["msg" => "The fleet is not found."]));
}

exit(json_encode($row));
