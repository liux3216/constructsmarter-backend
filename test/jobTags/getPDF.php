<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/s3.php"; // getObjectUrl
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = $_POST["id"];
//-------------------------------------------------------
$row = $db->one("SELECT `jobTagFileId` FROM `assignments` WHERE `id` = ?;", [$id], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    exit(json_encode(["msg" => "The assignment is not found."]));
}
$fileId = $row["jobTagFileId"];
//-------------------------------------------------------
$row = $db->one("SELECT `name` FROM `fileInfo` WHERE `id` = ?;", [$fileId], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    exit(json_encode(["msg" => "The file is not found."]));
}
$fileName = $row["name"];
//-------------------------------------------------
exit(getObjectUrl($privateBucket, $fileId, $fileName));