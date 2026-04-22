<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php"; // sendEmail
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $mainUrl
require_once "/opt/bitnami/apache/htdocs/s3.php"; // deleteFile
require_once "timeOffPDF.php"; // generateTimeOffPdf
//-----------------------------------------------------------------
$id = $_POST["id"];
$approverDecision = $_POST["approverDecision"];
$approverNotes = $_POST["approverNotes"];
//-----------------------------------------------------------------
if(!in_array($approverDecision, ["Approved", "Rejected"])){
    http_response_code(400);
    exit(json_encode(["msg" => "You are not found."]));
}
//-----------------------------------------------------------------
$timeOff = $db->one(
    "SELECT
    `t`.`notes`, 
    `t`.`data`, 
    `t`.`pdfId`, 
    `t`.`fromDate`, 
    `t`.`toDate`, 
    `t`.`type`, 
    `t`.`totalHours`, 
    `t`.`requesterId`, 
    `u1`.`department`, 
    `u1`.`email` AS `requesterEmail`, 
    CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`, 
    `t`.`creatorId`, 
    `t`.`createdAt`, 
    `u2`.`email` AS `creatorEmail`, 
    CONCAT_WS(\" \", `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `creatorName`, 
    `t`.`updaterId`, 
    `t`.`updatedAt`, 
    `u3`.`email` AS `updaterEmail`, 
    CONCAT_WS(\" \", `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`) AS `updaterName` 
    FROM `timeOffs` `t`
    LEFT JOIN `users` `u1` ON `u1`.`id` = `t`.`requesterId`
    LEFT JOIN `users` `u2` ON `u2`.`id` = `t`.`creatorId`
    LEFT JOIN `users` `u3` ON `u3`.`id` = `t`.`updaterId`
    WHERE `t`.`id` = ?;", [$id], __FILE__, __LINE__
);
if(!$timeOff){
    http_response_code(400);
    exit(json_encode(["msg" => "The time off form is not found."]));
}
$requesterId = $timeOff["requesterId"];
$requesterEmail = $timeOff["requesterEmail"];
$requesterName = $timeOff["requesterName"];
$department = $timeOff["department"];
$creatorId = $timeOff["creatorId"];
$createdAt = $timeOff["createdAt"];
$creatorEmail = $timeOff["creatorEmail"];
$creatorName = $timeOff["creatorName"];
$updaterId = $timeOff["updaterId"];
$updatedAt = $timeOff["updatedAt"];
$updaterEmail = $timeOff["updaterEmail"];
$updaterName = $timeOff["updaterName"];
$fromDate = $timeOff["fromDate"];
$toDate = $timeOff["toDate"];
$type = $timeOff["type"];
$totalHours = $timeOff["totalHours"];
$notes = $timeOff["notes"];
$data = $timeOff["data"];
$pdfId = $timeOff["pdfId"];
//-----------------------------------------------------------------
$requestDates = $fromDate === $toDate?$fromDate:"from $fromDate to $toDate";
$requesterWithDates = "$requesterName $requestDates";
//-----------------------------------------------------------------
try{
    deleteFile($privateBucket, $pdfId);
}catch(InvalidArgumentException $e){
    error_log("File Not Found. " . $e->getMessage());
}
//-----------------------------------------------------------------
$timeOffRow = [
    "requesterName" => $requesterName,
    "requesterId" => $requesterId,
    "department" => $department,
    "type" => $type,
    "fromDate" => $fromDate,
    "toDate" => $toDate,
    "totalHours" => $totalHours,
    "detail" => json_decode($data, true), 
    "notes" => $notes,
    
    "approverId" => $userId,
    "approverName" => $userName,
    
    "creatorId" => $creatorId,
    "creatorName" => $creatorName,
    "createdAt" => $createdAt, 

    "updaterId" => $updaterId,
    "updaterName" => $updaterName,
    "updatedAt" => $updatedAt, 

    "status" => $approverDecision,
    "approverNotes" => $approverNotes,
    "approvalTime" => date("Y:m:d H:i:s")
];
$pdfId = generateTimeOffPdf($id, null, $timeOffRow);
//-----------------------------------------------------------------
$db->exec(
    "UPDATE `timeOffs` SET
    `status` = ?,
    `approverNotes` = ?,
    `approvalTime` = ?,
    `pdfId` = ?
    WHERE `id` = ?;", [$approverDecision, $approverNotes, date("Y-m-d H:i:s"), $pdfId, $id], __FILE__, __LINE__
);
//-----------------------------------------------------------------
//send email to approver and requester and submitter if different from reqeuster
$comments = "";
if($approverNotes){
    $comments .= "<br><br>&nbsp;&nbsp;&nbsp;&nbsp;Comments: <br><br><pre style = \"margin-left:20px;font-family: Calibri, sans-serif;font-size:15\">$approverNotes</pre>";
}
$toEmails = [$requesterEmail];
$lastSumbiterEmail = $updaterEmail?$updaterEmail:$submitterEmail;
if($lastSumbiterEmail !== $requesterEmail){
    $toEmails[] = $lastSumbiterEmail;
}
$approverDecisionLower = strtolower($approverDecision);
sendEmail([
    "path" => basename(__FILE__)." ".__LINE__, 
    "selfEmail" => $email, 
    "db" => $db, 
    "to" => $toEmails, 
    "cc" => $email, 
    "summary" => "Time Off Form Confirmation", 
    "body" => "&nbsp;&nbsp;&nbsp;&nbsp;a time off form $requestDates is $approverDecisionLower by $userName:<br>
    <br>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <a href = \"$mainUrl/TimeOffs/$id\">
        Form Link
    </a>
    $comments"
]);