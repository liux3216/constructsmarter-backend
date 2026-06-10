<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/s3.php"; // getObjectUrl
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$row = $db->one("SELECT `trainingId` FROM `users` WHERE `id` = ?;", [$userId], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    exit("The user is not found.");
}
$uuid = $row["trainingId"];
if($uuid === NULL) exit("");
//-------------------------------------------------------
$row = $db->one("SELECT `name` FROM `fileInfo` WHERE `id` = ?;", [$uuid], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    exit(json_encode(["msg" => "The file is not found."]));
}
$fileName = $row["name"];
//-------------------------------------------------
exit(getObjectUrl($privateBucket, $uuid, $fileName));