<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/s3.php"; // putObjectUrl
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$mvrFolderId = "4dcaea1d96fcb3fb99d760830b5b165a";
//-------------------------------------------------
$curUserId = $_POST["curUserId"];
$fileName = $_POST["fileName"];
$fileType = $_POST["fileType"];
$fileSize = $_POST["fileSize"];
$lastModifiedAt = $_POST["lastModifiedAt"];
//-------------------------------------------------------
$row = $db->one(
    "SELECT 
    `mvrId` 
    FROM `users` 
    WHERE `id` = ?;", [$curUserId], __FILE__, __LINE__
);
if(!$row){
    http_response_code(404);
    exit(json_encode(["msg" => "The user is not found."]));
}
$uuid = $row["mvrId"];
//-------------------------------------------------------
if($uuid){
    $db->exec(
        "UPDATE `fileInfo` SET 
        `name` = ?,
        `type` =  ?,  
        `size` =  ?, 
        `lastModifiedAt` = ?, 
        `updaterId` = ?
        WHERE  id = ?;", [
            $fileName, 
            $fileType, 
            $fileSize, 
            $lastModifiedAt, 
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
            `parentId`,
            `creatorId`
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?
        );", [
            $uuid, 
            $fileName, 
            $fileType, 
            $fileSize, 
            $lastModifiedAt, 
            $mvrFolderId, 
            $userId
        ], __FILE__, __LINE__
    );
    $db->exec("UPDATE `users` SET `mvrId` = ? WHERE `userId` = ?;", [$uuid, $curUserId], __FILE__, __LINE__);
}
exit(json_encode(["id" => $uuid, "url" => putObjectUrl([
    "bucket" => $privateBucket, 
    "key" => $uuid, 
    "mime" => $fileType
])]));