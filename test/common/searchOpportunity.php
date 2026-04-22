<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = requireField($_POST, "q", 0, "max", true);
if (!$q) exit(json_encode([]));
$rows = $db->all(
    "SELECT `id` AS `value`, `opportunityName` AS `label`
     FROM `opportunities`
     WHERE `opportunityName` LIKE ?
     ORDER BY `opportunityName`
     LIMIT 20;",
    ["%{$q}%"]
);
exit(json_encode($rows));