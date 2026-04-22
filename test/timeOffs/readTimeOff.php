<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/s3.php"; // getObjectUrl
//-------------------------------------------------
$id = $_POST["id"];
if(!$id) exit();
//-------------------------------------------------
$sql = "SELECT 
`t`.`id`, 
`t`.`pdfId`, 
`t`.`type`,
`t`.`status`, 
`t`.`fromDate`, 
`t`.`toDate`, 
`t`.`notes`, 
`t`.`data`, 
`t`.`totalHours`, 
`t`.`requesterId`, 
`t`.`creatorId`, 
`t`.`approverId`, 
`t`.`createdAt`, 
`t`.`notifiedAt`, 
`t`.`notifiedBy`, 
`t`.`paid`, 
`t`.`void`, 
`t`.`voidReason`,
`t`.`approverNotes`, 
`t`.`approvalTime`, 
`u1`.`department`, 
`u1`.`outside`, 
`u1`.`projects`, 
CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`,
CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `approverName`, 
CONCAT_WS(\" \", `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`) AS `creatorName`, 
CONCAT_WS(\" \", `u4`.`firstName`, `u4`.`middleName`, `u4`.`lastName`) AS `notifierName`
FROM `timeOffs` `t` 
LEFT JOIN `users` `u1` ON `u1`.`id` = `t`.`requesterId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `t`.`approverId`
LEFT JOIN `users` `u3` ON `u3`.`id` = `t`.`creatorId`
LEFT JOIN `users` `u4` ON `u4`.`id` = `t`.`notifiedBy`
WHERE (`t`.`creatorId` = ? OR `t`.`approverId` = ?) AND `t`.`id` = ?;";
$row = $db->one($sql, [$userId, $userId, $id], __FILE__, __LINE__);
if(!$row){
    http_response_code(400);
    exit(json_encode(["msg" => "the time off form is not found."]));
}
$row["requester"] = [
    "label" => $row["requesterName"], 
    "value" => $row["requesterId"], 
    "department" => $row["department"], 
    "outside" => $row["outside"],
    "projects" => $row["projects"]
];
$row["approver"] = [
    "label" => $row["approverName"], 
    "value" => $row["approverId"], 
];
unset($row["requesterName"]);
unset($row["requesterId"]);
unset($row["department"]);
unset($row["outside"]);
unset($row["projects"]);
unset($row["approverName"]);
unset($row["approverId"]);
$row["data"] = json_decode($row["data"]);
$pdfId = "";
if($row["pdfId"]) $row["pdfId"] = getObjectUrl($privateBucket, $row["pdfId"], "time off form $id.pdf");
exit(json_encode($row));