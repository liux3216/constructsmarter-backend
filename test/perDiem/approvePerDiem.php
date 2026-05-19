<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php";
require_once "/opt/bitnami/apache/htdocs/test/constants.php";
require_once __DIR__."/helpers.php";
$id = perDiemRequirePost("id");
$approverDecision = perDiemRequirePost("approverDecision");
$approverNotes = array_key_exists("approverNotes", $_POST) ? trim((string)$_POST["approverNotes"]) : "";
if(!in_array($approverDecision, ["Approved", "Rejected"], true)){
    http_response_code(400);
    exit(json_encode(["msg" => "Invalid approver decision."]));
}
$access = getPerDiemAccess($db, $userId);
$row = $db->one(
    "SELECT `requesterId`, `approverId`, `creatorId`, `startDate`, `endDate`,
    `u1`.`email` AS `requesterEmail`, CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`,
    `u2`.`email` AS `creatorEmail`, `u3`.`email` AS `updaterEmail`
    FROM `perDiems` `p`
    LEFT JOIN `users` `u1` ON `u1`.`id` = `p`.`requesterId`
    LEFT JOIN `users` `u2` ON `u2`.`id` = `p`.`creatorId`
    LEFT JOIN `users` `u3` ON `u3`.`id` = `p`.`updaterId`
    WHERE `p`.`id` = ?;",
    [$id],
    __FILE__,
    __LINE__
);
if(!$row || !perDiemCanApproveRow($row, $userId, $access)){
    http_response_code(403);
    exit(json_encode(["msg" => "You are not allowed to approve this per diem form."]));
}
$db->exec(
    "UPDATE `perDiems` SET `status` = ?, `approverNotes` = ?, `approvalTime` = ? WHERE `id` = ?;",
    [$approverDecision, $approverNotes, date("Y-m-d H:i:s"), $id],
    __FILE__,
    __LINE__
);
$requestDates = $row["startDate"] === $row["endDate"] ? $row["startDate"] : "from {$row["startDate"]} to {$row["endDate"]}";
$comments = $approverNotes ? "<br><br>&nbsp;&nbsp;&nbsp;&nbsp;Comments:<br><br><pre style = \"margin-left:20px;font-family: Calibri, sans-serif;font-size:15\">$approverNotes</pre>" : "";
$toEmails = [$row["requesterEmail"]];
if($row["creatorEmail"] && $row["creatorEmail"] !== $row["requesterEmail"]) $toEmails[] = $row["creatorEmail"];
if($row["updaterEmail"] && $row["updaterEmail"] !== $row["requesterEmail"]) $toEmails[] = $row["updaterEmail"];
sendEmail([
    "path" => basename(__FILE__)." ".__LINE__,
    "selfEmail" => $email,
    "db" => $db,
    "to" => array_values(array_unique($toEmails)),
    "cc" => $email,
    "summary" => "Per Diem Form Confirmation",
    "body" => "&nbsp;&nbsp;&nbsp;&nbsp;a per diem form $requestDates is ".strtolower($approverDecision)." by $userName:<br><br>&nbsp;&nbsp;&nbsp;&nbsp;<a href = \"$mainUrl/PerDiem/$id\">Form Link</a>$comments"
]);
exit(json_encode(["id" => (int)$id]));
