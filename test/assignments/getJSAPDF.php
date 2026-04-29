<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";

$id = requireInt($_POST, "assignmentId", 1, null, true);

$row = $db->one("SELECT `jsaFileId` FROM `assignments` WHERE `id` = ?;", [$id], __FILE__, __LINE__);
if(!$row){
    jsonResponse(404, ["msg" => "The assignment is not found."]);
}
if(!$row["jsaFileId"]){
    jsonResponse(404, ["msg" => "The JSA PDF is not found."]);
}

$fileName = "jsa_$id.pdf";
$file = $db->one("SELECT `name` FROM `fileInfo` WHERE `id` = ?;", [$row["jsaFileId"]], __FILE__, __LINE__);
if($file && $file["name"]){
    $fileName = $file["name"].".pdf";
}

exit(getObjectUrl($privateBucket, $row["jsaFileId"], $fileName));
