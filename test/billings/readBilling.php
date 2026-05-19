<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/s3.php"; // getObjectUrl
//-------------------------------------------------
$id = $_POST["id"] ?? "";
if(!$id) exit();
//-------------------------------------------------
$sql = "SELECT 
`b`.`id`, 
`b`.`billingNumber`,
`b`.`projectId`, 
CONCAT_WS(' - ',
    NULLIF(TRIM(`p`.`projectNumber`), ''),
    NULLIF(TRIM(`org`.`name`), ''),
    NULLIF(TRIM(`p`.`clientProjectNumber`), '')
) AS `projectName`,
`b`.`contactId`, 
`b`.`approverId`,
`b`.`fromDate`, 
`b`.`toDate`, 
`b`.`amount`, 
`b`.`billable`, 
`b`.`notes`, 
`b`.`submitterId`, 
`b`.`submitTime`, 
`b`.`status`, 
`b`.`pdfId`, 
`b`.`notifiedAt`, 
`b`.`notifiedBy`, 
`b`.`approverNotes`, 
`b`.`billed`, 
`b`.`creatorId`, 
`b`.`createdAt`, 
`b`.`updaterId`, 
`b`.`updatedAt`, 
`b`.`void`, 
`b`.`voidReason`,
`b`.`approvalTime`, 
CONCAT_WS(\" \", `c1`.`firstName`, `c1`.`middleName`, `c1`.`lastName`) AS `contactName`,
CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `creatorName`, 
CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `submitterName`,
CONCAT_WS(\" \", `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`) AS `updaterName`,
CONCAT_WS(\" \", `u4`.`firstName`, `u4`.`middleName`, `u4`.`lastName`) AS `notifierName`,
CONCAT_WS(\" \", `u5`.`firstName`, `u5`.`middleName`, `u5`.`lastName`) AS `approverName`
FROM `billings` `b` 
LEFT JOIN `users` `u1` ON `u1`.`id` = `b`.`creatorId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `b`.`submitterId`
LEFT JOIN `users` `u3` ON `u3`.`id` = `b`.`updaterId`
LEFT JOIN `users` `u4` ON `u4`.`id` = `b`.`notifiedBy`
LEFT JOIN `users` `u5` ON `u5`.`id` = `b`.`approverId`
LEFT JOIN `contacts` `c1` ON `c1`.`id` = `b`.`contactId`
LEFT JOIN `projects` `p` ON `p`.`id` = `b`.`projectId`
LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
WHERE `b`.`id` = ?;";
$row = $db->one($sql, [$id], __FILE__, __LINE__);
if(!$row){
    http_response_code(400);
    exit(json_encode(["msg" => "the billing form is not found."]));
}
$row["requester"] = [
    "label" => $row["requesterName"] ?? null,
    "value" => $row["requesterId"] ?? null,
    "department" => $row["department"] ?? null,
    "outside" => $row["outside"] ?? null,
    "projects" => $row["projects"] ?? null,
];
$row["approver"] = [
    "label" => $row["approverName"] ?? null,
    "value" => $row["approverId"] ?? null,
];
unset($row["requesterName"], $row["requesterId"], $row["department"], $row["outside"], $row["projects"], $row["approverName"]);
$row["data"] = json_decode($row["data"] ?? "null");
if($row["pdfId"]) {
    $row["pdfId"] = getObjectUrl($privateBucket, $row["pdfId"], "billing $id.pdf");
}
exit(json_encode($row));
