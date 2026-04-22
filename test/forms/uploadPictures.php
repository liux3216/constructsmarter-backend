<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/s3.php"; // putObjectUrl, deleteFile
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$parentId = array_key_exists("parentId", $_POST)?$_POST["parentId"]:null;
$fileId = array_key_exists("fileId", $_POST)?$_POST["fileId"]:NULL;
$fileName = array_key_exists("fileName", $_POST)?$_POST["fileName"]:NULL;
$fileSize = array_key_exists("fileSize", $_POST)?$_POST["fileSize"]:NULL;
$fileType = array_key_exists("fileType", $_POST)?$_POST["fileType"]:NULL;
$lastModifiedAt = array_key_exists("lastModifiedAt", $_POST)?$_POST["lastModifiedAt"]:NULL;
$caption = array_key_exists("caption", $_POST)?$_POST["caption"]:NULL;
$urls = [];
$ids = [];
$map = [];
$newMap = [];
$existingMap = [];
if(!is_array($parentId) || !is_array($fileId) || !is_array($fileName) || !is_array($fileSize) || !is_array($fileType) || !is_array($lastModifiedAt) || !is_array($caption)) exit();
for($i = 0; $i < count($fileName); $i++){
    if(str_starts_with($parentId[$i], "new")){
        $field_key = substr($parentId[$i], 3);
        if(array_key_exists($field_key, $map)){
            $parentId[$i] = $map[$field_key];
        }else{
            $map[$field_key] = md5(rand());
            $parentId[$i] = $map[$field_key];
            $db->exec("INSERT INTO `fileInfo` (
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
            );", [$parentId[$i], $parentId[$i], null, $userId]);
        }
        if(!array_key_exists($parentId[$i], $existingMap)) $existingMap[$parentId[$i]] = [];
    }else{
        if(!array_key_exists($parentId[$i], $existingMap)){
            $existingIds = $db->all("SELECT `id`, `description` from `fileInfo` WHERE `parentId` = ?;", [$parentId[$i]]);
            $mapIdtoCaption = [];
            foreach($existingIds as $existingId){
                $mapIdtoCaption[$existingId["id"]] = $existingId["description"];
            }
            $existingMap[$parentId[$i]] = $mapIdtoCaption;
        }
    }
    if(!array_key_exists($parentId[$i], $newMap)) $newMap[$parentId[$i]] = [];
    array_push($newMap[$parentId[$i]], $fileId[$i]);
    if(array_key_exists($fileId[$i], $existingMap[$parentId[$i]])){
        if($existingMap[$parentId[$i]][$fileId[$i]] !== $caption[$i]) $db->exec("UPDATE `fileInfo` SET `description` = ? WHERE `id` = ? AND `creatorId` = ?;", [$caption[$i], $fileId[$i], $userId], __FILE__, __LINE__);
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
                `creatorId`,
                `parentId`
            ) VALUES (
                ?, 
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
                $caption[$i], 
                $userId,
                $parentId[$i]
            ], __FILE__, __LINE__
        );
        $ids[] = $uuid;
        $urls[] = putObjectUrl([
            "bucket" => $privateBucket, 
            "key" => $uuid, 
            "mime" => $fileType[$i]
        ]);
    }
}
$idsToBeDeleted = [];
foreach($existingMap as $pId => $items){
    foreach($items as $id => $caption){
        if(in_array($id, $newMap[$pId])) continue;
        $idsToBeDeleted[] = $id;
        // $db->exec("DELETE FROM `fileInfo` WHERE `id` = ?;", [$id], __FILE__, __LINE__);
        try{
            deleteFile($privateBucket, $id);
        }catch(InvalidArgumentException $e){
            error_log("File Not Found. " . $e->getMessage());
        }
    }
}
if($idsToBeDeleted && count($idsToBeDeleted)){
    $db->exec("DELETE FROM `fileInfo` WHERE `id` IN (".implode(",", array_fill(0, count($idsToBeDeleted), "?")).");", $idsToBeDeleted, __FILE__, __LINE__);
}
exit(json_encode(["urls" => $urls, "ids" => $ids, "map" => $map]));