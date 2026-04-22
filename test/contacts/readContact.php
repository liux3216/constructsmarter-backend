<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = $_POST["id"];
if(!$id) exit();
//-------------------------------------------------
$sql = "SELECT 
`contacts`.`id`, 
`contacts`.`organizationId`, 
`contacts`.`firstName`, 
`contacts`.`middleName`, 
`contacts`.`lastName`, 
`contacts`.`role`, 
`contacts`.`email1`, 
`contacts`.`email2`, 
`contacts`.`directNumber`, 
`contacts`.`phoneNumber`, 
`contacts`.`extension`, 
`contacts`.`fax`, 
`contacts`.`street`, 
`contacts`.`city`, 
`contacts`.`state`, 
`contacts`.`zipCode`, 
`contacts`.`overseaAddress`, 
`contacts`.`background`, 
`contacts`.`void`, 
`contacts`.`voidReason`,
`contacts`.`validateReason`, 
`contacts`.`creatorId`, 
`contacts`.`createdAt`, 
`contacts`.`updaterId`, 
`contacts`.`updatedAt`, 
CONCAT_WS(\" \", `contacts`.`firstName`, `contacts`.`middleName`, `contacts`.`lastName`) AS `name`, 
CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `creatorName`, 
CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `updaterName`, 
`org`.`name` AS `organizationName`
FROM `contacts`
LEFT JOIN `organizations` `org` ON `org`.`id` = `contacts`.`organizationId`
LEFT JOIN `users` `u1` ON `u1`.`id` = `contacts`.`creatorId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `contacts`.`updaterId`
WHERE `contacts`.`id` = ?;";
$row = $db->one($sql, [$id], __FILE__, __LINE__);
if(!$row){
    http_response_code(400);
    exit(json_encode(["msg" => "the contact is not found."]));
}
$row["projects"] = [];
$row["opportunities"] = [];
exit(json_encode($row));