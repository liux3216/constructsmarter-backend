<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$id = trim((string)($_POST["id"] ?? $_POST["truckNumber"] ?? ""));
if ($id === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing fleet id."]));
}

$sql = "SELECT
`f`.`id`,
COALESCE(`f`.`truckNumber`, '') AS `truckNumber`,
CASE WHEN `f`.`void` = 'yes' THEN 'No' ELSE 'Yes' END AS `open`,
CASE
    WHEN `f`.`fleetType` = 'trailer' AND `f`.`isHotPatch` = 'yes' THEN 'Hot Patch Trailer'
    WHEN `f`.`fleetType` = 'trailer' THEN 'Trailer'
    ELSE 'Truck'
END AS `type`,
CASE
    WHEN `f`.`fleetType` = 'trailer' AND `f`.`isHotPatch` = 'yes' THEN 'hp'
    WHEN `f`.`fleetType` = 'trailer' THEN 't'
    ELSE 'no'
END AS `trailer`,
`f`.`fleetType`,
COALESCE(`f`.`isHotPatch`, '') AS `isHotPatch`,
`f`.`creatorId`,
COALESCE(`creatorUser`.`email`, '') AS `creatorEmail`,
COALESCE(CONCAT_WS(' ', `creatorUser`.`firstName`, `creatorUser`.`middleName`, `creatorUser`.`lastName`), '') AS `creator`,
COALESCE(`f`.`createdAt`, '') AS `createdAt`,
COALESCE(`f`.`createdAt`, '') AS `dateCreated`,
`f`.`updaterId`,
COALESCE(`updaterUser`.`email`, '') AS `updaterEmail`,
COALESCE(CONCAT_WS(' ', `updaterUser`.`firstName`, `updaterUser`.`middleName`, `updaterUser`.`lastName`), '') AS `updater`,
COALESCE(`f`.`updatedAt`, '') AS `updatedAt`,
COALESCE(`f`.`updatedAt`, '') AS `dateUpdated`,
`f`.`void`,
COALESCE(`f`.`voidReason`, '') AS `voidReason`,
COALESCE(`f`.`validateReason`, '') AS `validateReason`
FROM `fleets` `f`
LEFT JOIN `users` `creatorUser` ON `creatorUser`.`id` = `f`.`creatorId`
LEFT JOIN `users` `updaterUser` ON `updaterUser`.`id` = `f`.`updaterId`
WHERE `f`.`id` = ? OR `f`.`truckNumber` = ?
LIMIT 1;";

$row = $db->one($sql, [$id, $id], __FILE__, __LINE__);
if (!$row) {
    http_response_code(404);
    exit(json_encode(["msg" => "The fleet is not found."]));
}

exit(json_encode($row));
