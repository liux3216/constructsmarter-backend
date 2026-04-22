<?php
//required headers:
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
require_once "/opt/bitnami/apache/htdocs/db.php"; // DB
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $allowedOrigins, $sqlInfo, $appEmail
if(!array_key_exists('HTTP_ORIGIN', $_SERVER)) exit();
$origin = $_SERVER['HTTP_ORIGIN'];
if(in_array($origin, $allowedOrigins)){
    header('Access-Control-Allow-Origin: '.$origin);
}
// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}
date_default_timezone_set('America/Los_Angeles');
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php"; // sendEmail
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // 
//-------------------------------------------------------------------------------------------
$email = $_POST["email"];
//-------------------------------------------------------------------------------------------
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
    http_response_code(401);
    exit(json_encode([
        "title" => "Error",
        "msg" => "Wrong email."
    ]));
}
$newPassword = md5(rand());
$newPasswordHashed = password_hash($newPassword, PASSWORD_ARGON2ID);
$db->exec("UPDATE `users` SET `password` = ?, isTmpPassword = TRUE WHERE `users`.`email` = ?;", [$newPasswordHashed, $email], __FILE__, __LINE__);
sendEmail([
    "path" => basename(__FILE__)." ".__LINE__, 
    "selfEmail" => $email, 
    "db" => $db, 
    "to" => $email,
    "summary" => "Password on Construct Smarter App", 
    "body" => "&nbsp;&nbsp;&nbsp;&nbsp;your temperary password is => 
    <span style = \"background-color:coral\">
        <strong>
            $newPassword
        </strong>
    </span>"
]);
exit(json_encode([
    "title" => "Success",
    "msg" => "Password sent to you from $appEmail."
]));