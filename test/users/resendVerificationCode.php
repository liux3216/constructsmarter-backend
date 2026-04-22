<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php"; // sendEmail
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$userEmail = $_POST["userEmail"];
//-------------------------------------------------
$row = $db->one("SELECT `verificationCode` FROM `users` WHERE `email` = ?;", [$userEmail], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    exit(json_encode(["msg" => "The user is not found."]));
}
$verificationCode = $row["verificationCode"];
if($verificationCode === null){
    http_response_code(409);
    exit(json_encode(["msg" => "Already Verified. Please Refresh."]));
}
// $newVerificationCode = md5(rand());
// $db->exec("UPDATE `users` SET `verificationCode` = ? WHERE `users`.`id` = ?;", [newVerificationCode, $userId], __FILE__, __LINE__);
sendEmail([
    "path" => basename(__FILE__)." ".__LINE__, 
    "selfEmail" => $email, 
    "db" => $db, 
    "to" => $userEmail, 
    "summary" => "Construct Smarter App Verification", 
    "body" => "&nbsp;&nbsp;&nbsp;&nbsp;Click link below to finish verification procss.<br/><br/>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <a href = \"http://$mainIP/$rootName/users/verification.php?email=$userEmail&code=$verificationCode\">
        link
    </a>"
]);