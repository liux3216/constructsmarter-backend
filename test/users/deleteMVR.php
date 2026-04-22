<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/s3.php"; // deleteFile
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$curUserId = $_POST["curUserId"];
//-------------------------------------------------------
$row = $db->one("SELECT `mvrId` FROM `users` WHERE `id` = ?;", [$curUserId], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    exit(json_encode(["msg" => "The user is not found."]));
}
$uuid = $row["mvrId"];
//-------------------------------------------------
try{
    deleteFile($privateBucket, $uuid);
}catch(InvalidArgumentException $e){
    error_log("File Not Found. " . $e->getMessage());
}
$db->exec("DELETE FROM `fileInfo` WHERE `id` = ?;",[$uuid], __FILE__, __LINE__);
exit();