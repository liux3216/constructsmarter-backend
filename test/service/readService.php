<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = requireField($_POST, "id", 1, 32, true);
if(!$id) exit();
$sql = "SELECT
`s`.`id`,
`s`.`code`,
`s`.`name`,
`s`.`category`,
`s`.`price`,
`s`.`costType`,
`s`.`notes`,
`s`.`void`,
`s`.`voidReason`,
`s`.`validateReason`,
`s`.`creatorId`,
`s`.`createdAt`,
`s`.`updaterId`,
`s`.`updatedAt`,
CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `creatorName`,
CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `updaterName`
FROM `services` `s`
LEFT JOIN `users` `u1` ON `u1`.`id` = `s`.`creatorId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `s`.`updaterId`
WHERE `s`.`id` = ?;";
$row = $db->one($sql, [$id], __FILE__, __LINE__);
if(!$row){
    http_response_code(400);
    exit(json_encode(["msg" => "the service is not found."]));
}
exit(json_encode($row));
