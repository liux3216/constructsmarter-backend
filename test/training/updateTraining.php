<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/s3.php"; // putObjectUrl
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $trainingProblemFolderId
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$status = $_POST["status"];
$uuid = $_POST["fileId"];
$fileName = $_POST["fileName"];
$fileSize = $_POST["fileSize"];
$lastModifiedAt = $_POST["lastModifiedAt"];
//-------------------------------------------------------
if($uuid){
    $db->exec(
        "UPDATE `fileInfo` SET 
        `name` = ?,
        `type` = \"text\/plain\",  
        `size` = ?, 
        `lastModifiedAt` = ?, 
        `description` = ?, 
        `updaterId` = ?
        WHERE  id = ?;", [
            $fileName,
            $fileSize, 
            $lastModifiedAt, 
            $status, 
            $userId, 
            $uuid
        ], __FILE__, __LINE__
    );
}else{
    $uuid = md5(rand());
    $db->exec(
        "INSERT INTO `fileInfo` (
            `id`, 
            `name`, 
            `type`, 
            `size`, 
            `lastModifiedAt`, 
            `description`, 
            `parentId`, 
            `creatorId`
        ) VALUES (
            ?, 
            ?, 
            \"text\/plain\", 
            ?, 
            ?, 
            ?, 
            ?, 
            ?
        );", [
            $uuid, 
            $fileName, 
            $fileSize, 
            $lastModifiedAt, 
            $status, 
            $trainingProblemFolderId, 
            $userId
        ], __FILE__, __LINE__
    );
}
exit(json_encode(["id" => $uuid, "url" => putObjectUrl([
    "bucket" => $privateBucket, 
    "key" => $uuid, 
    "mime" => "text/plain"
])]));