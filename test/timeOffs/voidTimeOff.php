<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php"; // sendEmail
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $mainUrl
//-------------------------------------------------
$id = $_POST["id"];
$voidReason = $_POST["voidReason"];
//-----------------------------------------------------------------
$timeOff = $db->one(
    "SELECT 
    `t`.`pdfId`, 
    `t`.`approverId`, 
    `t`.`updaterId`, 
    `t`.`fromDate`, 
    `t`.`toDate`, 
    `u1`.`email` AS `requesterEmail`, 
    `u2`.`email` AS `creatorEmail`, 
    `u3`.`email` AS `approverEmail`, 
    `u4`.`email` AS `updaterEmail`, 
    CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`
    FROM `timeOffs` `t`
    LEFT JOIN `users` `u1` ON `u1`.`id` = `t`.`requesterId`
    LEFT JOIN `users` `u2` ON `u2`.`id` = `t`.`creatorId`
    LEFT JOIN `users` `u3` ON `u3`.`id` = `t`.`approverId`
    LEFT JOIN `users` `u4` ON `u4`.`id` = `t`.`updaterId`
    WHERE `t`.`id` = ?;", [$id], __FILE__, __LINE__
);
if(!$timeOff){
    http_response_code(400);
    exit(json_encode(["msg" => "The time off form is not found."]));
}
$fromDate = $timeOff["fromDate"];
$toDate = $timeOff["toDate"];
$requesterEmail = $timeOff["requesterEmail"];
$requesterName = $timeOff["requesterName"];
$approverEmail = $timeOff["approverEmail"];
$creatorEmail = $timeOff["creatorEmail"];
$updaterEmail = $timeOff["updaterEmail"];
//-----------------------------------------------------------------
$requestDates = $fromDate === $toDate?$fromDate:"from $fromDate to $toDate";
//-----------------------------------------------------------------
$db->exec(
    "UPDATE `timeOffs` SET `void` = \"yes\", `voidReason` = ? WHERE `id` = ?;", 
    [$voidReason, $id], __FILE__, __LINE__
);
//-----------------------------------------------------------------
//send out email
$emails = [
    $approverEmail, 
    $requesterEmail, 
    $creatorEmail
];
if($updaterEmail){
    array_push($emails, $updaterEmail);
}
$uniqueEmails = array_unique($emails);
sort($uniqueEmails);
sendEmail([
    "path" => basename(__FILE__)." ".__LINE__, 
    "selfEmail" => $email, 
    "db" => $db, 
    "to" => $uniqueEmails, 
    "summary" => "Time Off Form Voidance", 
    "body" => "&nbsp;&nbsp;&nbsp;&nbsp;$userName void a time off form, Please see the reason below:<br>
    <br>
    &nbsp;&nbsp;&nbsp;&nbsp;$voidReason<br>
    <br>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <a href = \"$mainUrl/TimeOffs/$id\">
        $requesterName $requestDates
    </a>"
]);