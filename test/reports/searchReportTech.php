<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$q = requireField($_POST, "q", 0, "max", true);
if (!$q) exit(json_encode([]));

$rows = $db->all(
    "SELECT `id` AS `value`, `name` AS `label`
    FROM (
        SELECT
            `id`,
            `email`,
            CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `name`
        FROM `users`
        WHERE `reports` = 'edit'
          AND `void` = 'no'
    ) `t`
    WHERE `name` LIKE ? OR `email` LIKE ?
    ORDER BY `name`
    LIMIT 20;",
    ["%{$q}%", "%{$q}%"]
);
exit(json_encode($rows));
