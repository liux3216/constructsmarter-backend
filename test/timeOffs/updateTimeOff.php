<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php"; // sendEmail
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $mainUrl
require_once "timeOffPDF.php"; // generateTimeOffPdf
//-----------------------------------------------------------------
$id = $_POST["id"];
//-----------------------------------------------------------------
$requesterId = $_POST["requesterId"];
$approverId = $_POST["approverId"];
$fromDate = $_POST["fromDate"];
$toDate = $_POST["toDate"];
$notes = $_POST["notes"];
$type = $_POST["type"];
$data = $_POST["data"];
$totalHours = $_POST["totalHours"];
//-----------------------------------------------------------------
$requestDates = $fromDate === $toDate?$fromDate:"from $fromDate to $toDate";
//-----------------------------------------------------------------
$timeOff = $db->one(
    "SELECT `t`.`pdfId`, `t`.`createdAt`, `t`.`creatorId`, CONCAT_WS(\" \", `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `creatorName` 
    FROM `timeOffs` `t`
    LEFT JOIN `users` `u` ON `u`.`id` = `t`.`creatorId`
    WHERE `t`.`id` = ?;", [$id], __FILE__, __LINE__);
if(!$timeOff){
    http_response_code(400);
    exit(json_encode(["msg" => "The time off form is not found."]));
}
$pdfId = $timeOff["pdfId"];
$createdAt = $timeOff["createdAt"];
$creatorName = $timeOff["creatorName"];
$creatorId = $timeOff["creatorId"];
//-----------------------------------------------------------------
$requester = $db->one(
    "SELECT `email`, `department`, CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `name` FROM `users` WHERE `id` = ?;", 
    [$requesterId], __FILE__, __LINE__
);
if(!$requester){
    http_response_code(400);
    exit(json_encode(["msg" => "The requester is not found."]));
}
$department = $requester["department"];
$requesterEmail = $requester["email"];
$requesterName = $requester["name"];
//-----------------------------------------------------------------
$approver = $db->one(
    "SELECT `email`, CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `name` FROM `users` WHERE `id` = ?;", 
    [$approverId], __FILE__, __LINE__
);
if(!$approver){
    http_response_code(400);
    exit(json_encode(["msg" => "The approver is not found."]));
}
$approverEmail = $approver["email"];
$approverName = $approver["name"];
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
    
    "approverId" => $approverId,
    "approverName" => $approverName,

    "creatorId" => $creatorId,
    "creatorName" => $creatorName,
    "createdAt" => $createdAt, 

    "updaterId" => $userId,
    "updaterName" => $userName,
    "updatedAt" => date("Y-m-d H:i:s")
];
$pdfId = generateTimeOffPdf($id, null, $timeOffRow);
$db->exec(
    "UPDATE `timeOffs` SET
    `fromDate` = ?,
    `toDate` = ?,
    `type` = ?,
    `requesterId` = ?,
    `approverId` = ?,
    `notes` = ?,
    `pdfId` =?,
    `updaterId` = ?,
    `status` = \"Re-Submitted\",
    `data` = ?, 
    `totalHours` = ?
    WHERE `id` = ?;",
    [$fromDate, $toDate, $type, $requesterId, $approverId, $notes, $pdfId, $userId, $data, $totalHours, $id], __FILE__, __LINE__
);
//-----------------------------------------------------------------
$requesterWithDates = "$requesterName $requestDates";
$ccEmails = [$email];
$extraText = "";
if($requesterEmail !== $email){
    $ccEmails[] = $requesterEmail;
    $extraText = " for $requesterName";
}
sendEmail([
    "path" => basename(__FILE__)." ".__LINE__, 
    "selfEmail" => $email, 
    "db" => $db, 
    "to" => $approverEmail, 
    "cc" => $ccEmails, 
    "summary" => "Time Off Form Re-Submission Review", 
    "body" => "&nbsp;&nbsp;&nbsp;&nbsp;$userName re-submitted a time off form$extraText, Please review and provide your decision below:<br>
    <br>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <a href = \"$mainUrl/TimeOffs/$id\">
        $requesterWithDates
    </a>"
]);