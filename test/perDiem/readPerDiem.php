<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once __DIR__."/helpers.php";
$id = perDiemRequirePost("id");
$access = getPerDiemAccess($db, $userId);
[$scopeSql, $scopeParams] = perDiemScope("p", $userId, $access);
$projectLabel = perDiemProjectLabel("pr", "o");
$sql = "SELECT
`p`.`id`,
`p`.`pdfId`,
`p`.`projectId`,
$projectLabel AS `projectName`,
`p`.`requesterId`,
`p`.`approverId`,
`p`.`startDate`,
`p`.`endDate`,
`p`.`hotelName`,
`p`.`hotelAddress`,
`p`.`notes`,
`p`.`status`,
`p`.`paid`,
`p`.`void`,
`p`.`voidReason`,
`p`.`validateReason`,
`p`.`notifiedAt`,
`p`.`notifiedBy`,
`p`.`approvalTime`,
`p`.`approverNotes`,
`p`.`creatorId`,
`p`.`createdAt`,
`p`.`updaterId`,
`p`.`updatedAt`,
`u1`.`department`,
`u1`.`outside`,
`u1`.`projects`,
CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`,
CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `approverName`,
CONCAT_WS(\" \", `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`) AS `creatorName`,
CONCAT_WS(\" \", `u4`.`firstName`, `u4`.`middleName`, `u4`.`lastName`) AS `updaterName`,
CONCAT_WS(\" \", `u5`.`firstName`, `u5`.`middleName`, `u5`.`lastName`) AS `notifierName`
FROM `perDiems` `p`
LEFT JOIN `users` `u1` ON `u1`.`id` = `p`.`requesterId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `p`.`approverId`
LEFT JOIN `users` `u3` ON `u3`.`id` = `p`.`creatorId`
LEFT JOIN `users` `u4` ON `u4`.`id` = `p`.`updaterId`
LEFT JOIN `users` `u5` ON `u5`.`id` = `p`.`notifiedBy`
LEFT JOIN `projects` `pr` ON `pr`.`id` = `p`.`projectId`
LEFT JOIN `organizations` `o` ON `o`.`id` = `pr`.`organizationId`
WHERE `p`.`id` = ? AND $scopeSql;";
$row = $db->one($sql, array_merge([$id], $scopeParams), __FILE__, __LINE__);
if(!$row){
    http_response_code(400);
    exit(json_encode(["msg" => "the per diem form is not found."]));
}
$row["requester"] = [
    "label" => $row["requesterName"],
    "value" => $row["requesterId"],
    "department" => $row["department"],
    "outside" => $row["outside"],
    "projects" => $row["projects"],
];
$row["approver"] = [
    "label" => $row["approverName"],
    "value" => $row["approverId"],
];
if($row["pdfId"]) $row["pdfId"] = getObjectUrl($privateBucket, $row["pdfId"], "per diem form $id.pdf");
exit(json_encode($row));
