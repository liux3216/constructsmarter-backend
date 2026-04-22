<?php
//required headers
header("Access-Control-Allow-Methods: GET");
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $allowedOrigins, $sqlInfo
if(!array_key_exists("HTTP_ORIGIN", $_SERVER)) return;
$origin = $_SERVER['HTTP_ORIGIN'];
if(in_array($origin, $allowedOrigins)){
    header('Access-Control-Allow-Origin: '.$origin);
}
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/db.php"; // DB
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php"; // sendEmail

try {
    if($_SERVER["REQUEST_METHOD"] !== "GET"){
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }
    $email = strtolower(trim($_GET["email"] ?? ""));
    $code  = trim($_GET["code"] ?? "");
    if($email === "" || $code === ""){
        jsonResponse(409, ["msg" => "Missing email or verification code"]);
    }
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        jsonResponse(407, ["msg" => "Invalid email"]);
    }
    $db = new DB(
        $sqlInfo["hostname"],
        $sqlInfo["username"],
        $sqlInfo["password"],
        $sqlInfo["database"]
    );
    $user = $db->one(
        "SELECT `verificationCode` FROM `users` WHERE `email` = ? AND (`quitDate` IS NULL OR `quitDate` > NOW());",
        [$email], __FILE__, __LINE__
    );
    if(!$user){
        jsonResponse(404, ["msg" => "User not found"]);
    }
    if($user["verificationCode"] === null){
        jsonResponse(409, ["msg" => "Email already verified"]);
    }
    if($user["verificationCode"] !== $code){
        jsonResponse(407, ["msg" => "Invalid verification code"]);
    }
    $db->begin();
    $rawPassword = md5(rand());
    $password = addslashes(password_hash($rawPassword, PASSWORD_ARGON2ID));
    $db->exec(
        "UPDATE `users` SET `password` = ?, `verificationCode` = NULL WHERE `users`.`email` = ?;",
        [$password, $email], __FILE__, __LINE__
    );
    sendEmail([
        "to"      => $email,
        "summary" => "Construct Smarter APP Email Verified",
        "body"    =>
            "&nbsp;&nbsp;&nbsp;&nbsp;Now you can start using Construct Smarter App.your temperary password is =>
            <span style = \"background-color:coral\"> <strong> &nbsp;$rawPassword&nbsp;</strong></span>"
    ]);
    $db->commit();
    jsonResponse(200, ["ok" => true]);

} catch (Throwable $e) {
    if(isset($db) && $db->inTransaction()){
        $db->rollBack();
    }
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}