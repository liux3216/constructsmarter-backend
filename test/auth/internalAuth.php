<?php 
//required headers
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
require_once "/opt/bitnami/apache/htdocs/db.php"; // DB
require_once "/opt/bitnami/apache/htdocs/jwt.php"; // JWTHandler
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
date_default_timezone_set('America/Los_Angeles');
//-------------------------------------------------------------------------------------------
$jwt = new JWTHandler();
$token = $jwt->getCookieToken();
// $token = $jwt->getAuthorizationHeader();
if(!$token){
    http_response_code(401);
    exit(json_encode(["msg" => "The token is expired or invalid"]));
}
$result = $jwt->verifyToken($token);
if(!$result['valid']){
    http_response_code(401);
    exit(json_encode(["msg" => "The token is invalid or expired"]));
}
if($result['data']->exp <= time() + 5 * 60){
     // will expire in 5 minutes
     // Generate new token
    $newToken = $jwt->generateToken(
        $result['data']->userId,
        $result['data']->email,
        $result['data']->userName, 
        $result['data']->version, 
        3600
    );
    // set new JWT
    // header("Authorization: Bearer ".$newToken);
    setcookie("jwt", "Bearer ".$newToken, [
        "expires" => time() + 3600,   // 1 hour
        "path" => "/",                  // cookie valid for whole domain
        "secure" => true,               // MUST be HTTPS
        "httponly" => true,             // JS cannot access
        "samesite" => "None"            // MUST be None for cross-origin
    ]);
};
$userId = $result['data']->userId;
$userName = $result['data']->userName;
$email = $result['data']->email;
$version = $result['data']->version;
//connect to DB
$db = new DB(
    $sqlInfo["hostname"], 
    $sqlInfo["username"], 
    $sqlInfo["password"], 
    $sqlInfo["database"]
);
//------------------------------------------
$versions = $db->one("SELECT `jsonValue` FROM `entities` WHERE `entityKey` = ?", ["appversion"], __FILE__, __LINE__);
if(!$versions){
    http_response_code(404);
    exit(json_encode([
        "title" => "Error",
        "msg" => "Internal Error."
    ]));
    error_log(basename(__FILE__)." ".__LINE__." No row with `entityKey` = \"appversion\" in table `entities`.");
}
if($versions["jsonValue"]) $appVersions = json_decode($versions["jsonValue"], true);
$latestVersion = $appVersions[count($appVersions) - 1];
//------------------------------------------
if($version && $latestVersion["version"] !== $version){
    http_response_code(409);
    exit(json_encode(["msg" => "App updated. Please refresh (or log out and log back in)."]));
}
$publicBucket = "constructsmarterpublic";
$privateBucket = "constructsmarter";
/* continue */