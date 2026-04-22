<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = requireField($_POST, "q", 0, "max", true);
if (!$q) exit(json_encode([]));
$rows = $db->all(
    "SELECT `id` AS `value`, `name` AS `label`
    FROM (
        SELECT
            `id`,
            CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `name`
        FROM `users`
    ) `t`
    WHERE `name` LIKE ?
    ORDER BY `name`
    LIMIT 20;",
    ["%{$q}%"]
);
exit(json_encode($rows));