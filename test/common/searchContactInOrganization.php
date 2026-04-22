<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = requireField($_POST, "q", 0, "max", true);
if (!$q) exit(json_encode([]));
$limit = "";
$params = [];
if(array_key_exists("organizationId", $_POST) && $_POST["organizationId"]){
    $limit = "`organizationId` = ? AND ";
    $params[] = (int)$_POST["organizationId"];
}else{
    exit("[]");
}
$rows = $db->all(
    "SELECT `id` AS `value`, CONCAT_WS(\" \", `contacts`.`firstName`, `contacts`.`middleName`, `contacts`.`lastName`) AS `label`
     FROM `contacts`
     WHERE $limit CONCAT_WS(\" \", `contacts`.`firstName`, `contacts`.`middleName`, `contacts`.`lastName`) LIKE ?
     ORDER BY `label`
     LIMIT 20;",
    array_merge($params, ["%{$q}%"])
);
exit(json_encode($rows));