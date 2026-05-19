<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$page = array_key_exists("page", $_POST) ? (int)$_POST["page"] : 1;
$limit = array_key_exists("limit", $_POST) ? (int)$_POST["limit"] : 10;
if ($page < 1) $page = 1;
if ($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;

$countRow = $db->one("SELECT COUNT(*) AS `total` FROM `fleets`;", [], __FILE__, __LINE__);
$total = (int)($countRow["total"] ?? 0);
$maxPage = max(1, (int)ceil($total / $limit));
if ($page > $maxPage) {
    $page = $maxPage;
    $offset = ($page - 1) * $limit;
}

$fleetsSql = "SELECT
`f`.`id`,
COALESCE(`f`.`truckNumber`, '') AS `truckNumber`,
'' AS `department`,
'' AS `location`,
'' AS `functionality`,
'' AS `employee`,
'' AS `employeeName`,
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
`f`.`creatorId`,
`f`.`updaterId`,
'' AS `insuranceCard`,
'' AS `registrationCard`,
'' AS `gps`,
'' AS `gpsUnit`,
'' AS `ft`,
'' AS `ftUnit`,
'' AS `miles`,
'' AS `licenseNumber`,
'' AS `vin`,
'' AS `year`,
'' AS `make`,
'' AS `model`,
COALESCE(`creatorUser`.`email`, '') AS `creatorEmail`,
COALESCE(CONCAT_WS(' ', `creatorUser`.`firstName`, `creatorUser`.`middleName`, `creatorUser`.`lastName`), '') AS `creator`,
COALESCE(`f`.`createdAt`, '') AS `dateCreated`,
COALESCE(`updaterUser`.`email`, '') AS `updaterEmail`,
COALESCE(CONCAT_WS(' ', `updaterUser`.`firstName`, `updaterUser`.`middleName`, `updaterUser`.`lastName`), '') AS `updater`,
COALESCE(`f`.`updatedAt`, '') AS `dateUpdated`,
COALESCE(`f`.`voidReason`, '') AS `background`,
'{}' AS `inventory`,
`f`.`fleetType`,
COALESCE(`f`.`isHotPatch`, '') AS `isHotPatch`,
`f`.`void`,
COALESCE(`f`.`validateReason`, '') AS `validateReason`
FROM `fleets` `f`
LEFT JOIN `users` `creatorUser` ON `creatorUser`.`id` = `f`.`creatorId`
LEFT JOIN `users` `updaterUser` ON `updaterUser`.`id` = `f`.`updaterId`
ORDER BY `f`.`truckNumber`
LIMIT $limit OFFSET $offset;";

$data = [
    "fleets" => $db->all($fleetsSql, [], __FILE__, __LINE__),
    "page" => $page,
    "limit" => $limit,
    "total" => $total,
];

exit(json_encode($data));
