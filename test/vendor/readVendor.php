<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = requireField($_POST, "id", 1, 32, true);
if(!$id) exit();
$sql = "SELECT
`v`.`id`,
`v`.`vendorName`,
`v`.`website`,
`v`.`phoneNumber`,
`v`.`extension`,
`v`.`fax`,
`v`.`country`,
`v`.`state`,
`v`.`city`,
`v`.`street`,
`v`.`zipCode`,
`v`.`background`,
`v`.`void`,
`v`.`voidReason`,
`v`.`validateReason`,
`v`.`creatorId`,
`v`.`createdAt`,
`v`.`updaterId`,
`v`.`updatedAt`,
CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `creatorName`,
CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `updaterName`
FROM `vendors` `v`
LEFT JOIN `users` `u1` ON `u1`.`id` = `v`.`creatorId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `v`.`updaterId`
WHERE `v`.`id` = ?;";
$row = $db->one($sql, [$id], __FILE__, __LINE__);
if(!$row){
    http_response_code(400);
    exit(json_encode(["msg" => "the vendor is not found."]));
}
exit(json_encode($row));
