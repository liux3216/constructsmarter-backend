<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$row = $db->one(
    "SELECT
        COALESCE(MIN(`fromDate`), CURDATE()) AS `minTime`,
        COALESCE(MAX(`toDate`), CURDATE()) AS `maxTime`
     FROM `timeOffs`
     WHERE `void` = 'no';",
    [],
    __FILE__,
    __LINE__
);

if (!$row) {
    $today = date("Y-m-d");
    $row = [
        "minTime" => $today,
        "maxTime" => $today,
    ];
}

exit(json_encode($row));
