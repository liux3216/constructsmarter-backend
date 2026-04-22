<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/s3.php"; // putObjectUrl
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $profileFolderId
// require_once "/opt/bitnami/apache/htdocs/test/functions.php"; // compressImage
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$curUserId = array_key_exists("curUserId", $_POST)?$_POST["curUserId"]:$userId;
$fileName = $_POST["fileName"];
$fileType = $_POST["fileType"];
$fileSize = $_POST["fileSize"];
$lastModifiedAt = $_POST["lastModifiedAt"];
//-------------------------------------------------------
$row = $db->one("SELECT `profileId` FROM `users` WHERE `id` = ?;", [$curUserId], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    error_log(basename(__FILE__)." ".__LINE__." ".$curUserId." The user is not found.");
    exit(json_encode(["msg" => "The user is not found."]));
}
$uuid = $row["profileId"];
//-------------------------------------------------------
if($uuid){
    $db->exec(
        "UPDATE `fileInfo` SET 
        `name` = ?,
        `type` = ?,  
        `size` = ?, 
        `lastModifiedAt` = ?, 
        `updaterId`= ?, 
        `public` = TRUE
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
            ?, 
            ?, 
            ?, 
            ?, 
            ?, 
            ?, 
            ?
        );", [
            $uuid, 
            $fileName, 
            $fileType, 
            $fileSize, 
            $lastModifiedAt, 
            $profileFolderId, 
            $userId
        ], __FILE__, __LINE__
    );
    $db->exec("UPDATE `users` SET `profileId` = ? WHERE `id` = ?;", [$uuid, $curUserId], __FILE__, __LINE__);
}
exit(json_encode([
    "id" => $uuid, 
    "publicUrl" => "https://$publicBucket.s3.us-west-1.amazonaws.com/$uuid",
    "url" => putObjectUrl([
        "bucket" => $publicBucket, 
        "key" => $uuid, 
        "mime" => $fileType,
    ])
]));