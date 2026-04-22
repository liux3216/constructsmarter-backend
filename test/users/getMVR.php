<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/s3.php"; // getObjectUrl
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$curUserId = $_POST["curUserId"];
//-------------------------------------------------------
$row = $db->one("SELECT `mvrId` FROM `users` WHERE `id` = ?;", [$curUserId], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    exit(json_encode(["msg" => "The user is not found."]));
}
$mvrId = $row["mvrId"];
//-------------------------------------------------------
$row = $db->one("SELECT `name` FROM `fileInfo` WHERE `id` = ?;", [$mvrId], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    exit(json_encode(["msg" => "The file is not found."]));
}
$fileName = $row["name"];
//-------------------------------------------------
exit(getObjectUrl($privateBucket, $mvrId, $fileName));