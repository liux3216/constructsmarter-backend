<?php
// Each LiveSmarter entry point uses this bootstrap; business endpoints remain shared.
require_once '/opt/bitnami/apache/htdocs/db.php';
require_once __DIR__.'/database.php';
set_exception_handler(function(Throwable $error):void {
 error_log('LiveSmarter request failed: '.get_class($error).' code '.$error->getCode());
 http_response_code(500);header('Content-Type: application/json');echo json_encode(['msg'=>'Could not complete this request. Please try again.']);
});
require_once '/opt/bitnami/apache/htdocs/test/constants.php';
$configFile=getenv('LIVESMARTER_CONFIG')?:'/opt/bitnami/livesmarter/config.php';
$config=require $configFile;
if(($config['database']??'')!=='livesmarter')throw new RuntimeException('Invalid LiveSmarter database configuration.');
$sqlInfo=['hostname'=>$config['hostname'],'username'=>$config['username'],'password'=>$config['password'],'database'=>$config['database']];
$allowedOrigins=$config['origins'];
$publicBucket='constructsmarterpublic';$privateBucket='constructsmarter';
date_default_timezone_set('America/Los_Angeles');
if(PHP_SAPI!=='cli'){
 header('Content-Type: application/json');header('Access-Control-Allow-Methods: POST, OPTIONS');header('Access-Control-Allow-Headers: Content-Type, Authorization, X-App-Token');header('Access-Control-Expose-Headers: Authorization, X-Auth-Token');header('Access-Control-Allow-Credentials: true');header('Vary: Origin');header('Cache-Control: no-store');
 $origin=$_SERVER['HTTP_ORIGIN']??'';
 if($origin!==''&&!in_array($origin,$allowedOrigins,true)){http_response_code(403);exit(json_encode(['msg'=>'Origin not allowed.']));}
 if($origin!=='')header('Access-Control-Allow-Origin: '.$origin);
 if(($_SERVER['REQUEST_METHOD']??'')==='OPTIONS'){http_response_code(204);exit;}
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit(json_encode(['msg'=>'Use POST.']));}
}
$db=new LiveSmarterDB($sqlInfo['hostname'],$sqlInfo['username'],$sqlInfo['password'],$sqlInfo['database']);
function liveRespond($data,int $status=200):never {http_response_code($status);exit(json_encode($data,JSON_UNESCAPED_UNICODE));}
function liveFail(string $message,int $status=422):never{liveRespond(['msg'=>$message],$status);}
