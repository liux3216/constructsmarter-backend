<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $testerEmails
require_once "/opt/bitnami/apache/htdocs/s3.php"; // getObjectUrl, deleteFile, putObjectUrl
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//------------------------------------------------------------
if(!in_array($email, $testerEmails)) exit();
//----------------------------------------------------
$action = $_POST["action"];
$parentId = array_key_exists("parentId", $_POST)?$_POST["parentId"]:NULL;
$newFileName = array_key_exists("newFileName", $_POST)?$_POST["newFileName"]:NULL;
$fileId = array_key_exists("fileId", $_POST)?$_POST["fileId"]:NULL;
$newParentId = array_key_exists("newParentId", $_POST)?$_POST["newParentId"]:NULL;
$folderName = array_key_exists("folderName", $_POST)?$_POST["folderName"]:NULL;
$fileName = array_key_exists("fileName", $_POST)?$_POST["fileName"]:NULL;
$fileSize = array_key_exists("fileSize", $_POST)?$_POST["fileSize"]:NULL;
$fileType = array_key_exists("fileType", $_POST)?$_POST["fileType"]:NULL;
$lastModifiedAt = array_key_exists("lastModifiedAt", $_POST)?$_POST["lastModifiedAt"]:NULL;
//----------------------------------------------------
switch($action){
    case "readFolder":
        $files = $db->all("SELECT * FROM `fileInfo` WHERE `parentId` <=> ?;", [$parentId], __FILE__, __LINE__);
        $parents = $db->all(
            "WITH RECURSIVE `chain` AS (
                SELECT `id`, `name`, `parentId`
                FROM `fileInfo`
                WHERE `id` <=> ?
                UNION ALL
                SELECT `f`.`id`, `f`.`name`, `f`.`parentId`
                FROM `fileInfo` `f`
                JOIN `chain` `c` ON `f`.id = `c`.`parentId`
            )
            SELECT * FROM `chain`;", [$parentId], __FILE__, __LINE__
        );
        exit(json_encode(["files" => $files, "parents" => $parents]));
    case "uploadFiles":
        if(is_array($fileName)){
            $urls = [];
            $ids = [];
            for($i = 0; $i < count($fileName); $i++){
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
                        $fileName[$i], 
                        $fileType[$i], 
                        $fileSize[$i], 
                        $lastModifiedAt[$i], 
                        $parentId,
                        $userId
                    ], __FILE__, __LINE__
                );
                $ids[] = $uuid;
                $urls[] = putObjectUrl([
                    "bucket" => $privateBucket, 
                    "key" => $uuid, 
                    "mime" => $fileType[$i]
                ]);
            }
            exit(json_encode(["urls" => $urls, "ids" => $ids]));
        }
    case "createFolder":
        $uuid = md5(rand());
        $db->exec(
            "INSERT INTO `fileInfo` (
                `id`, 
                `name`, 
                `type`, 
                `parentId`, 
                `creatorId`,
                `status`
            ) VALUES (
                ?, 
                ?, 
                \"folder\",  
                ?, 
                ?,
                \"uploaded\"
            );", [
                $uuid, 
                $folderName,
                $parentId,
                $userId
            ], __FILE__, __LINE__
        );
        exit();
    case "rename":
        $db->exec(
            "UPDATE `fileInfo` SET 
            `name` = ?, 
            `updaterId`= ? 
            WHERE `id` = ?;", 
            [
                $newFileName, 
                $userId, 
                $fileId
            ], __FILE__, __LINE__
        );
        exit();
    case "move":
        $db->exec(
            "UPDATE `fileInfo` SET 
            `parentId` = ?, 
            `updaterId`= ?
            WHERE `id` = ?;", [
                $newParentId, 
                $userId,
                $fileId
            ], __FILE__, __LINE__
        );
        exit();
    case "downloadFile":
        // can be a json output
        exit(getObjectUrl($privateBucket, $fileId, $fileName));
    case "deleteFile":
        $db->exec("DELETE FROM `fileInfo` WHERE `id` = ?;", [$fileId], __FILE__, __LINE__);
        try{
            deleteFile($privateBucket, $fileId);
        }catch(InvalidArgumentException $e){
            error_log("File Not Found. " . $e->getMessage());
        }
        exit();
    case "deleteFolder":
        // recursively delete all files and folders within the folder
        $db->exec("DELETE FROM `fileInfo` WHERE `id` = ?;", [$fileId], __FILE__, __LINE__);
        exit();
    default:
        http_response_code(409);
        exit(json_encode(["msg" => "Action is not recognized."]));
}