<?php
//required headers
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $allowedOrigins
if(!array_key_exists('HTTP_ORIGIN', $_SERVER)) exit();
date_default_timezone_set('America/Los_Angeles');
$origin = $_SERVER['HTTP_ORIGIN'];
if(in_array($origin, $allowedOrigins)){
    header('Access-Control-Allow-Origin: '.$origin);
}
header("Access-Control-Allow-Credentials: true");
setcookie("jwt", "", [
    "expires" => time() - 3600, // 1 hour ago
    "path" => "/",
    "secure" => true,      // match original cookie
    "httponly" => true,    // match original cookie
    "samesite" => "None"   // match original cookie
]);
exit();
/*
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$redis->setex($userId, 3600, 'loggedOut');
$redis->get('name'); 
$redis->exists('key')
*/