<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/s3.php"; // deleteFile
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$uuid = $_POST["fileId"];
$db->exec("DELETE FROM `fileInfo` WHERE `id` = ?;", [$uuid], __FILE__, __LINE__);
try{
    deleteFile($privateBucket, $uuid);
}catch(InvalidArgumentException $e){
    error_log("File Not Found. ".$e->getMessage());
}
exit();