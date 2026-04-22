<?php
//required headers
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
header("Access-Control-Expose-Headers: Authorization");
require_once "/opt/bitnami/apache/htdocs/db.php"; // DB
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $allowedOrigins, $sqlInfo
require_once "/opt/bitnami/apache/htdocs/jwt.php"; // JWTHandler
require_once "/opt/bitnami/apache/htdocs/test/functions.php"; // *getTextData
if(!array_key_exists('HTTP_ORIGIN', $_SERVER)) exit();
$origin = $_SERVER['HTTP_ORIGIN'];
if(in_array($origin, $allowedOrigins)){
    header('Access-Control-Allow-Origin: '.$origin);
}
// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}
if(!array_key_exists("email", $_POST) || !array_key_exists("password", $_POST)) exit();
date_default_timezone_set('America/Los_Angeles');
$inputEmail = $_POST["email"];
$inputPassword = $_POST["password"];
$versions = json_decode(file_get_contents("/opt/bitnami/apache/htdocs/test/appVersion.json"));
$latestVersion = $versions[count($versions) - 1];
if(!isset($inputEmail)){
    error_log(basename(__FILE__)." ".__LINE__." No email is provided.");
    http_response_code(404);
    exit(json_encode([
        "title" => "Error",
        "msg" => "No email is provided."
    ]));
}
if(!isset($inputPassword)){
    error_log(basename(__FILE__)." ".__LINE__." ".$inputEmail." No password is provided.");
    http_response_code(404);
    exit(json_encode([
        "title" => "Error",
        "msg" => "No password is provided."
    ]));
}
//-------------------------------------------------------------------------------------------
//connect to DB
$db = new DB(
    $sqlInfo["hostname"], 
    $sqlInfo["username"], 
    $sqlInfo["password"], 
    $sqlInfo["database"]
);
$user = $db->one(
    "SELECT `u`.*, 
    CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `userName`, 
    `f`.`name` AS `profileFileName`
    FROM `users` `u` LEFT JOIN `fileInfo` `f` ON `u`.`profileId` = `f`.`id`
    WHERE `email` = ?;", [$inputEmail], __FILE__, __LINE__
);
if(!$user){
    http_response_code(404);
    exit(json_encode([
        "title" => "Error",
        "msg" => "Wrong email."
    ]));
}

if($user["fav"]) $user["fav"] = json_decode($user["fav"], true);

$existingVerificationCode = $user["verificationCode"];
if($existingVerificationCode !== null){
    http_response_code(403);
    exit(json_encode([
        "title" => "Error",
        "msg" => "Verify your Email first."
    ]));
}
$existingPassword = $user["password"];
if(!password_verify($inputPassword, $existingPassword)){
    http_response_code(401);
    exit(json_encode([
        "title" => "Error",
        "msg" => "Wrong password."
    ]));
}
$isTmpPassword = $user["isTmpPassword"];
if($isTmpPassword){
    http_response_code(403);
    exit(json_encode([
        "title" => "Error",
        "msg" => "Temperary password, please change."
    ]));
}
$userId = $user["id"];
$userName = $user["userName"];
$existingVersion = $user["version"];
$quitDate = $user["quitDate"];
$popId = $user["popId"];
$profileId = $user["profileId"];
//-----------------------
// check valid
if($quitDate && $quitDate < date("Y-m-d")){
    http_response_code(403);
    exit(json_encode([
        "title" => "Error",
        "msg" => "You no longer have access to this app."
    ]));
}
$publicBucket = "constructsmarterpublic";
//-----------------------
if($profileId) $user["profileUrl"] = "https://$publicBucket.s3.us-west-1.amazonaws.com/$profileId";
//-----------------------
$res = (object)[
    "user" => $user
];   
//-----------------------
// get pop
$currentNewspaperId = $db->one("SELECT `textValue` FROM `entities` WHERE `entityKey` = ?", ["currentNewspaper"], __FILE__, __LINE__);
if(!$currentNewspaperId){
    http_response_code(500);
    error_log("not find row with `entityKey` = \"currentNewspaper\" in table `entities`");
    exit("Internal Error.");
}
$currentNewspaperId = $currentNewspaperId["textValue"];
if($currentNewspaperId && $currentNewspaperId !== $popId){
    // **** need work on this whole block ****
    try{
        // $res->popName = readDriveFolderName($currentNewspaperId);
        // $res->pop = getTextData($currentNewspaperId);
    }catch(\Google_Service_Exception $e){
        $myfile = fopen("/opt/bitnami/apache/htdocs/test/currentNewspaper.txt", "w") or die("Unable to open file."); // *
        fwrite($myfile, "");
        fclose($myfile);
        $currentNewspaperId = "";
        $res->popName = "";
        $res->pop = "";
    }
    $db->exec(
        "UPDATE `users` SET `popId` = \"$currentNewspaperId\" WHERE `id` = ?;", [$userId], __FILE__, __LINE__
    );
}
//-----------------------
// update version
if(
    $latestVersion && 
    $latestVersion->version && 
    $existingVersion !== $latestVersion->version
){
    $db->exec(
        "UPDATE `users` 
        SET `version` = ? 
        WHERE `id` = ?;", [$latestVersion->version, $userId], __FILE__, __LINE__
    );
    $res->user["version"] = $latestVersion->version;
    $res->user["versionMessage"] = $latestVersion->message;
    $res->update = "yes";
}
//-----------------------
$jwt = new JWTHandler();
$token = $jwt->generateToken(
    $userId, 
    $inputEmail, 
    $userName, 
    $latestVersion->version, 
    3600
);
/*
header("Authorization: Bearer ".$token);
*/
setcookie("jwt", "Bearer ".$token, [
    "expires" => time() + 3600,   // 1 hour
    "path" => "/",                  // cookie valid for whole domain
    "secure" => true,               // MUST be HTTPS
    "httponly" => true,             // JS cannot access
    "samesite" => "None"            // MUST be None for cross-origin
]);
exit(json_encode($res));
