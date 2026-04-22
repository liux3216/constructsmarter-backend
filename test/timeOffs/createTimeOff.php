<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php"; // sendEmail
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $mainUrl
require_once "timeOffPDF.php"; // generateTimeOffPdf
//-----------------------------------------------------------------
$requesterId = $_POST["requesterId"];
$approverId = $_POST["approverId"];
$fromDate = $_POST["fromDate"];
$toDate = $_POST["toDate"];
$notes = $_POST["notes"];
$type = $_POST["type"];
$data = $_POST["data"];
$totalHours = $_POST["totalHours"];
$requestDates = $fromDate === $toDate?$fromDate:"from $fromDate to $toDate";
//-----------------------------------------------------------------
$requester = $db->one(
    "SELECT `email`, `department`, CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `name` FROM `users` WHERE `id` = ?;", 
    [$requesterId], __FILE__, __LINE__
);
if(!$requester){
    http_response_code(400);
    exit(json_encode(["msg" => "The requester is not found."]));
}
$requesterEmail = $requester["email"];
$requesterName = $requester["name"];
$department = $requester["department"];
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
try {
    $db->begin();
    $db->exec(
        "INSERT INTO timeOffs (
            `creatorId`,
            `fromDate`,
            `toDate`,
            `type`,
            `requesterId`,
            `approverId`,
            `notes`,
            `status`,
            `data`,
            `totalHours`
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?, 
            ?
        );", [$userId, $fromDate, $toDate, $type, $requesterId, $approverId, $notes, "Submitted", $data, $totalHours]
    );
    $id = (int)$db->lastInsertId();
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

        "creatorId" => $userId,
        "creatorName" => $userName,
        "createdAt" => date("Y-m-d H:i:s")
    ];
    $pdfId = generateTimeOffPdf($id, null, $timeOffRow);
    if($pdfId !== ""){
        $db->exec("UPDATE `timeOffs` SET `pdfId` = ? WHERE `id` = ?", [$pdfId, $id], __FILE__, __LINE__);
    }
    $db->commit();
}catch(Throwable $e){
    $db->rollback();
    error_log(__FILE__.":".$e->getMessage());
    http_response_code(500);
    exit(json_encode(["msg" => "Failed to create time off form"]));
}
//-----------------------------------------------------------------
$requesterWithDates = "$requesterName $requestDates";
$ccEmails = [$email];
$extraText = "";
if($requesterId !== $userId){
    $ccEmails[] = $requesterEmail;
    $extraText = " for $requesterName";
}
sendEmail([
    "path" => basename(__FILE__)." ".__LINE__, 
    "selfEmail" => $email, 
    "db" => $db, 
    "to" => $approverEmail, 
    "cc" => $ccEmails, 
    "summary" => "Time Off Form Submission Review", 
    "body" => "&nbsp;&nbsp;&nbsp;&nbsp;$userName submitted a time off Form$extraText, Please review and provide your decision below:<br>
    <br>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <a href = \"$mainUrl/TimeOffs/$id\">
        $requesterWithDates
    </a>"
]);
exit(json_encode([
    "id" => $id
]));