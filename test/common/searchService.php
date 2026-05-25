<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = trim((string)($_POST["q"] ?? ""));
if($q === "") exit(json_encode([]));
$rows = $db->all(
    "SELECT `id` AS `value`, CONCAT_WS(' - ', `code`, `name`) AS `label`, `price`, `notes`
    FROM `services`
    WHERE `void` = 'no' AND CONCAT_WS(' - ', `code`, `name`, `category`) LIKE ?
    ORDER BY `name` ASC LIMIT 20;",
    ["%$q%"],
    __FILE__,
    __LINE__
);
foreach($rows as &$row){
    if(!$row['label']) $row['label'] = '';
}
unset($row);
exit(json_encode($rows));
