<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = requireField($_POST, "q", 0, "max", true);
if (!$q) exit(json_encode([]));
$rows = $db->all(
    "SELECT `id` AS `value`, CONCAT_WS(\" \", `contacts`.`firstName`, `contacts`.`middleName`, `contacts`.`lastName`) AS `label`
     FROM `contacts`
     WHERE CONCAT_WS(\" \", `contacts`.`firstName`, `contacts`.`middleName`, `contacts`.`lastName`) LIKE ?
     ORDER BY `label`
     LIMIT 20;",
    ["%{$q}%"]
);
exit(json_encode($rows));