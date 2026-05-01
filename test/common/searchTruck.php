<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = requireField($_POST, "q", 0, "max", true);
if (!$q) exit(json_encode([]));
$rows = $db->all(
    "SELECT `id` AS `value`, `truckNumber` AS `label`
     FROM `fleets`
     WHERE `truckNumber` LIKE ? AND fleetType = \"truck\"
     ORDER BY `truckNumber`
     LIMIT 20;",
    ["%{$q}%"]
);
exit(json_encode($rows));