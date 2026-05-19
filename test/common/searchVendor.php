<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = trim((string)($_POST["q"] ?? ""));
if($q === "") exit(json_encode([]));
$rows = $db->all(
    "SELECT `id` AS `value`, `vendorName` AS `label`
    FROM `vendors`
    WHERE `void` = 'no' AND `vendorName` LIKE ?
    ORDER BY `vendorName` ASC LIMIT 20;",
    ["%$q%"],
    __FILE__,
    __LINE__
);
exit(json_encode($rows));
