<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/s3.php"; // putObjectUrl
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $trainingDataFolderId
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$fileSize = $_POST["fileSize"];
$lastModifiedAt = $_POST["lastModifiedAt"];
//-------------------------------------------------------
// get user data from sql database
$row = $db->one(
    "SELECT 
    `trainingId`
    FROM `users` 
    WHERE `id` = ?;", [$userId], __FILE__, __LINE__
);
if(!$row){
    http_response_code(404);
    exit(json_encode(["msg" => "You are not found in database."]));
}
$uuid = $row["trainingId"];
//-------------------------------------------------------
if($uuid){
    $db->exec(
        "UPDATE `fileInfo` SET 
        `size` = ?, 
        `lastModifiedAt` = ?, 
        `updaterId` = ?
        WHERE  id = ?;",
        [
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
            \"training\", 
            \"text\/plain\", 
            ?, 
            ?, 
            ?, 
            ?
        );", [
            $uuid, 
            $fileSize, 
            $lastModifiedAt, 
            $trainingDataFolderId, 
            $userId
        ], __FILE__, __LINE__
    );
    $db->exec("UPDATE `users` SET `trainingId` = ? WHERE `id` = ?;", [$uuid, $userId], __FILE__, __LINE__);
}
exit(json_encode(["id" => $uuid, "url" => putObjectUrl([
    "bucket" => $privateBucket, 
    "key" => $uuid, 
    "mime" => "text/plain"
])]));