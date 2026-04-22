<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/s3.php"; // deleteFile
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$curUserId = array_key_exists("curUserId", $_POST)?$_POST["curUserId"]:NULL;
if(!isset($curUserId)) $curUserId = $userId;
//-------------------------------------------------------
$row = $db->one("SELECT `profileId` FROM `users` WHERE `id` = ?;", [$curUserId], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    error_log(basename(__FILE__)." ".__LINE__." ".$email." The user is not found.");
    exit(json_encode(["msg" => "The user is not found."]));
}
$profileId = $row["profileId"];
//-------------------------------------------------------
try{
    deleteFile($publicBucket, $profileId);
}catch(InvalidArgumentException $e){
    error_log("File Not Found. " . $e->getMessage());
}
$db->exec("DELETE FROM `fileInfo` WHERE `id` = ?;", [$profileId], __FILE__, __LINE__);
exit();