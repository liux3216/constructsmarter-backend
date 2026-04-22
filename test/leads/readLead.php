<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = requireInt($_POST, "id", null, null, true);
//-------------------------------------------------
$sql = "SELECT 
`leads`.`id`, 
`leads`.`organizationId`, 
`leads`.`firstName`, 
`leads`.`middleName`, 
`leads`.`lastName`, 
`leads`.`role`, 
`leads`.`email`, 
`leads`.`mobile`, 
`leads`.`businessPhone`, 
`leads`.`extension`, 
`leads`.`fax`, 
`leads`.`street`, 
`leads`.`city`, 
`leads`.`state`, 
`leads`.`zipCode`, 
`leads`.`overseaAddress`, 

`leads`.`source`, 
`leads`.`status`, 
`leads`.`website`,
`leads`.`industry`, 
`leads`.`referredBy`, 
`leads`.`userResponsible1`, 
`leads`.`userResponsible2`, 

`leads`.`background`, 
`leads`.`void`, 
`leads`.`voidReason`,
`leads`.`validateReason`, 
`leads`.`creatorId`, 
`leads`.`createdAt`, 
`leads`.`updaterId`, 
`leads`.`updatedAt`, 
CONCAT_WS(\" \", `leads`.`firstName`, `leads`.`middleName`, `leads`.`lastName`) AS `name`, 
CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `creatorName`, 
CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `updaterName`, 
CONCAT_WS(\" \", `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`) AS `referredByName`, 
CONCAT_WS(\" \", `u4`.`firstName`, `u4`.`middleName`, `u4`.`lastName`) AS `userResponsible1Name`, 
CONCAT_WS(\" \", `u5`.`firstName`, `u5`.`middleName`, `u5`.`lastName`) AS `userResponsible2Name`, 
`org`.`name` AS `organizationName`
FROM `leads`
LEFT JOIN `organizations` `org` ON `org`.`id` = `leads`.`organizationId`
LEFT JOIN `users` `u1` ON `u1`.`id` = `leads`.`creatorId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `leads`.`updaterId`
LEFT JOIN `users` `u3` ON `u3`.`id` = `leads`.`referredBy`
LEFT JOIN `users` `u4` ON `u4`.`id` = `leads`.`userResponsible1`
LEFT JOIN `users` `u5` ON `u5`.`id` = `leads`.`userResponsible2`
WHERE `leads`.`id` = ?;";
$row = $db->one($sql, [$id], __FILE__, __LINE__);
if(!$row){
    http_response_code(400);
    exit(json_encode(["msg" => "the lead is not found."]));
}
exit(json_encode($row));