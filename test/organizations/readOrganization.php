<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = requireInt($_POST, "id", null, null, true);
if(!$id) exit();
//-------------------------------------------------
$sql = "SELECT 
`org`.`id`, 
`org`.`name`, 
`org`.`website`, 
`org`.`phoneNumber`, 
`org`.`extension`, 
`org`.`fax`, 
`org`.`street`, 
`org`.`city`, 
`org`.`state`, 
`org`.`zipCode`, 
`org`.`overseaAddress`, 
`org`.`background`, 
`org`.`void`, 
`org`.`voidReason`,
`org`.`validateReason`, 
`org`.`creatorId`, 
`org`.`createdAt`, 
`org`.`updaterId`, 
`org`.`updatedAt`, 
CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `creatorName`, 
CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `updaterName`
FROM `organizations` `org` 
LEFT JOIN `users` `u1` ON `u1`.`id` = `org`.`creatorId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `org`.`updaterId`
WHERE `org`.`id` = ?;";
$row = $db->one($sql, [$id], __FILE__, __LINE__);
if(!$row){
    http_response_code(400);
    exit(json_encode(["msg" => "the organization is not found."]));
}
$contacts = $db->all(
    "SELECT
    id,
    CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `name`
FROM contacts WHERE organizationId = ? AND void = ? ORDER BY createdAt DESC LIMIT 6;", [$id, "no"], __FILE__, __LINE__);
$row["contacts"] = $contacts;
$projects = $db->all(
    "SELECT
    id,
    CONCAT_WS(\" - \", `projectNumber`, (SELECT `name` FROM `organizations` WHERE `organizations`.`id` = `projects`.`organizationId`), `clientProjectNumber`) AS `name`
FROM projects WHERE organizationId = ? AND void = ? ORDER BY createdAt DESC LIMIT 6;", [$id, "no"], __FILE__, __LINE__);
$row["projects"] = $projects;
$row["opportunities"] = [];
exit(json_encode($row));