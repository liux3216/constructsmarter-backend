<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = requireField($_POST, "q", 0, "max", true);
if (!$q) exit(json_encode([]));
$rows = $db->all(
    "SELECT `id` AS `value`, `proposalNumber` AS `label`
     FROM `proposals`
     WHERE `void` = 'no' AND `proposalNumber` LIKE ?
     ORDER BY `proposalNumber` DESC
     LIMIT 20;",
    ["%{$q}%"]
);
exit(json_encode($rows));
