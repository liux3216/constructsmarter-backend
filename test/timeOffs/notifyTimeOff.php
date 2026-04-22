<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php"; // sendEmail
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $mainUrl
//-----------------------------------------------------------------
$id = $_POST["id"];
//-----------------------------------------------------------------
$timeOff = $db->one(
    "SELECT
    `t`.`status`, 
    `t`.`pdfId`, 
    `t`.`fromDate`, 
    `t`.`toDate`, 
    `u1`.`email` AS `requesterEmail`, 
    CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`, 
    `u2`.`email` AS `approverEmail`
    FROM `timeOffs` `t`
    LEFT JOIN `users` `u1` ON `u1`.`id` = `t`.`requesterId`
    LEFT JOIN `users` `u2` ON `u2`.`id` = `t`.`approverId`
    WHERE `t`.`id` = ?;", [$id], __FILE__, __LINE__
);
if(!$timeOff){
    http_response_code(400);
    exit(json_encode(["msg" => "The time off form is not found."]));
}
$fromDate = $timeOff["fromDate"];
$toDate = $timeOff["toDate"];
$pdfId = $timeOff["pdfId"];
$status = $result["status"];
$requesterEmail = $timeOff["requesterEmail"];
$requesterName = $timeOff["requesterName"];
$approverEmail = $result["approverEmail"];
//----------------------------------
$requestDates = $fromDate === $toDate?$fromDate:"from $fromDate to $toDate";
//----------------------------------
if($status !== "Approved"){
    sendEmail([
        "path" => basename(__FILE__)." ".__LINE__, 
        "selfEmail" => $email, 
        "db" => $db, 
        "to" => $approverEmail, 
        "cc" => [$requesterEmail, $email],
        "summary" => "Time Off Form Review", 
        "body" => "&nbsp;&nbsp;&nbsp;&nbsp;$userName notified you about a Time Off Form, Please review and provide your decision below:<br>
        <br>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <a href = \"$mainUrl/TimeOffs/$id\">
            $requesterName $requestDates
        </a>"
    ]);
}
//-----------------------------------------------------------------
$db->exec("UPDATE `timeOffs` SET `notifiedBy` = ?, `notifiedAt` = ? WHERE `id` = ?;", [$userId, date("Y-m-d H:i:s"), $id], __FILE__, __LINE__);