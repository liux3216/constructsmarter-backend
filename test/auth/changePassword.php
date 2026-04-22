<?php
//required headers
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
require_once "/opt/bitnami/apache/htdocs/db.php"; // DB
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $allowedOrigins, $sqlInfo
if(!array_key_exists('HTTP_ORIGIN', $_SERVER)) exit();
$origin = $_SERVER['HTTP_ORIGIN'];
if(in_array($origin, $allowedOrigins)){
    header('Access-Control-Allow-Origin: '.$origin);
}
// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php"; // sendEmail
if(!array_key_exists("email", $_POST) || !array_key_exists("password", $_POST) || !array_key_exists("newPassword", $_POST)) exit();
date_default_timezone_set('America/Los_Angeles');
$email = $_POST["email"];
$inputPassword = $_POST["password"];
$newPassword = $_POST["newPassword"];
$newPasswordHashed = password_hash($newPassword, PASSWORD_ARGON2ID);
//connect to DB
$db = new DB(
    $sqlInfo["hostname"], 
    $sqlInfo["username"], 
    $sqlInfo["password"], 
    $sqlInfo["database"]
);
$today = date("Y-m-d");
$row = $db->one("SELECT `password` FROM `users` WHERE `email` = ? AND (`quitDate` IS NULL OR `quitDate` > \"$today\");", [$email], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    exit(json_encode([
        "title" => "Error",
        "msg" => "Wrong email."
    ]));
}
$existingPassword = $row["password"];
if (!password_verify($inputPassword, $existingPassword)) {
    http_response_code(401);
    exit(json_encode([
        "title" => "Error",
        "msg" => "Wrong password."
    ]));
}
!$db->exec("UPDATE `users` SET `password` = ?, `isTmpPassword` = FALSE WHERE `users`.`email` = ?;", [$newPasswordHashed, $email], __FILE__, __LINE__);
sendEmail([
    "path" => basename(__FILE__)." ".__LINE__, 
    "selfEmail" => $email, 
    "db" => $db, 
    "to" => $email,
    "summary" => "Password Changed on Construct Smarter App",
    "body" => "&nbsp;&nbsp;&nbsp;&nbsp;your password is changed."
]);
exit(json_encode([
    "title" => "Success",
    "msg" => "Password Changed."
]));