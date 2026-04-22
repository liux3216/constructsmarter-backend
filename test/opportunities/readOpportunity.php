<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = requireInt($_POST, "id", null, null, true);
//-------------------------------------------------
$sql = "SELECT 
`opportunities`.`id`, 
`opportunities`.`opportunityName`, 
`opportunities`.`organizationId`, 
`org`.`name` AS `organizationName`, 
`opportunities`.`probability`, 
`opportunities`.`bidAmount`, 
`opportunities`.`bidType`,
`opportunities`.`category`,
`opportunities`.`state`,
`opportunities`.`location`,
`opportunities`.`projectId`,
`opportunities`.`actualCloseDate`,
`opportunities`.`background`, 
`opportunities`.`void`, 
`opportunities`.`voidReason`,
`opportunities`.`validateReason`, 
`opportunities`.`creatorId`, 
`opportunities`.`createdAt`, 
`opportunities`.`updaterId`, 
CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `creatorName`, 
`opportunities`.`updatedAt`, 
CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `updaterName`

FROM `opportunities`
LEFT JOIN `organizations` `org` ON `org`.`id` = `opportunities`.`organizationId`
LEFT JOIN `users` `u1` ON `u1`.`id` = `opportunities`.`creatorId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `opportunities`.`updaterId`
WHERE `opportunities`.`id` = ?;";
$row = $db->one($sql, [$id], __FILE__, __LINE__);
if(!$row){
    http_response_code(400);
    exit(json_encode(["msg" => "the opportunity is not found."]));
}
$row = $db->one($sql, [$id], __FILE__, __LINE__);
if(!$row){
    http_response_code(400);
    exit(json_encode(["msg" => "the opportunity is not found."]));
}

$contacts = $db->all(
    "SELECT `contactId` AS `value`, CONCAT_WS(\" \", `contacts`.`firstName`, `contacts`.`middleName`, `contacts`.`lastName`) AS `label`
    FROM `opportunities_contact` 
    LEFT JOIN `contacts` ON `contacts`.`id` = `opportunities_contact`.`contactId`
    WHERE `opportunityId` = ?",
    [$id],
    __FILE__, __LINE__
);
$row['contacts'] = $contacts;

$users = $db->all(
    "SELECT `userId` AS `value`, CONCAT_WS(\" \", `users`.`firstName`, `users`.`middleName`, `users`.`lastName`) AS `label`
    FROM `opportunities_userResponsible` 
    LEFT JOIN `users` ON `users`.`id` = `opportunities_userResponsible`.`userId`
    WHERE `opportunityId` = ?",
    [$id],
    __FILE__, __LINE__
);
$row['users'] = $users;

exit(json_encode($row));