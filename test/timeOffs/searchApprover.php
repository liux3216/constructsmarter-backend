<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = requireField($_POST, "q", 0, "max", true);
if (!$q) exit(json_encode([]));
// limitation: `purchases`, `btlNumber`
$rows = $db->all(
    "SELECT `id` AS `value`, `name` AS `label`
    FROM (
        SELECT
            `timeOffs`, 
            `id`,
            CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `name`
        FROM `users`
    ) `t`
    WHERE `timeOffs` = ? AND `name` LIKE ?
    ORDER BY `name`
    LIMIT 20;",
    ["approver", "%{$q}%"]
);
exit(json_encode($rows));