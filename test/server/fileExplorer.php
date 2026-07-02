<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $testerEmails
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//------------------------------------------------------------
if(!in_array($email, $testerEmails)) exit();
//----------------------------------------------------
$action = $_POST["action"];
$content = array_key_exists("content", $_POST)?$_POST["content"]:NULL;
$fileName = array_key_exists("fileName", $_POST)?$_POST["fileName"]:NULL;
$newFileName = array_key_exists("newFileName", $_POST)?$_POST["newFileName"]:NULL;
$newPath = array_key_exists("newPath", $_POST)?$_POST["newPath"]:NULL;
$loalFilePath = array_key_exists("filePath", $_POST)?$_POST["filePath"]:"";
$fallBackPath = "/opt/bitnami/apache/htdocs/test";
$isFallBack = false;
//----------------------------------------------------
$filePath = "/opt/bitnami/apache/htdocs/$loalFilePath";
if(!$loalFilePath || !file_exists($filePath)){
    $filePath = $fallBackPath;
    $isFallBack = true;
}
function getOuput(string $dir): void {
    global $output;
    foreach(
        array_filter(scandir($dir), function(string $file): bool {
            return !in_array($file, [".", "..", ".git"]);
        }) as $file
    ){
        if(is_dir("$dir/$file")){
            $output[] = [
                "isFolder" => true,
                "path" => "$file"
            ];
        }else if(is_file("$dir/$file")){
            $output[] = [
                "isFolder" => false,
                "path" => "$file"
            ];
        }
    }
}
function getSize(string $dir): int {
    $count = 0;
    foreach(
        array_filter(scandir($dir), function(string $file): bool {
            return !in_array($file, [".", "..", ".git"]);
        }) as $file
    ){
        if(is_dir("$dir/$file")){
            $count += getSize("$dir/$file");
        }else if(is_file("$dir/$file")){
            $count += filesize("$dir/$file");
        }
    }
    return $count;
}
function zipFolder(string $source, string $destination): bool {
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }
    $zip = new ZipArchive();
    if(!$zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE)){
        return false;
    }
    $source = realpath($source);
    if(is_dir($source)){
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach($files as $name => $file){
            if(!$file->isDir()){
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($source) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }else if(is_file($source)){
        $zip->addFile($source, basename($source));
    }

    return $zip->close();
}
function buildDownloadPayload(string $fullPath, string $downloadName): array {
    if(is_dir($fullPath)){
        $tempFile = tempnam(sys_get_temp_dir(), 'zip');
        if($tempFile === false || !zipFolder($fullPath, $tempFile)){
            http_response_code(500);
            exit(json_encode(["msg" => "Unable to prepare folder download."]));
        }
        $bytes = file_get_contents($tempFile);
        unlink($tempFile);
        if($bytes === false){
            http_response_code(500);
            exit(json_encode(["msg" => "Unable to read folder archive."]));
        }
        return [
            "name" => preg_match('/\.zip$/i', $downloadName) ? basename($downloadName) : (basename($downloadName) . '.zip'),
            "mimeType" => 'application/zip',
            "base64" => base64_encode($bytes),
            "isFolder" => true,
        ];
    }
    if(!is_file($fullPath)){
        http_response_code(404);
        exit(json_encode(["msg" => "The file path is not valid."]));
    }
    $bytes = file_get_contents($fullPath);
    if($bytes === false){
        http_response_code(500);
        exit(json_encode(["msg" => "Unable to read file content."]));
    }
    $mimeType = function_exists('mime_content_type') ? mime_content_type($fullPath) : null;
    if(!$mimeType) $mimeType = 'application/octet-stream';
    return [
        "name" => basename($downloadName),
        "mimeType" => $mimeType,
        "base64" => base64_encode($bytes),
        "isFolder" => false,
    ];
}
if($action === "delete"){
    if(is_dir($filePath)){
        $files = scandir($filePath);
        if($files === false){
            http_response_code(404);
            exit(json_encode(["msg" => "The file path is not valid."]));
        }
        $numFiles = count(array_filter($files, function(string $file): bool {
            return !in_array($file, [".", "..", ".git"]);
        }));
        if($numFiles > 0){
            http_response_code(409);
            exit(json_encode(["msg" => "The folder still have $numFiles file(s) in it."]));
        }
        rmdir($filePath);
    }else if(is_file($filePath)){
        unlink($filePath);
    }
}else if($action === "update"){
    file_put_contents($filePath, $content);
}else if($action === "createFile"){
    file_put_contents("$filePath/$fileName", "");
}else if($action === "createFolder"){
    mkdir("$filePath/$fileName", 0755);
}else if($action === "rename"){
    rename("$filePath/$fileName", "$filePath/$newFileName");
}else if($action === "copy"){
    copy("$filePath/$fileName", "$filePath/$newFileName");
}else if($action === "property"){
    if(is_dir($filePath)){
        $fileSize = getSize($filePath);
    }else if(is_file($filePath)){
        $fileSize = filesize($filePath);
    }
    exit("size: $fileSize bytes.\nlast modified: ".date("F d Y H:i:s.", filemtime($filePath)));
}else if($action === "move"){
    if(!is_dir($newPath)){
        http_response_code(404);
        exit(json_encode(["msg" => "The new path is not valid."]));
    }
    rename("$filePath/$fileName", "$newPath/$fileName");
}else if($action === "upload"){
    $files = $_FILES["files"];
    if($files["name"][0] != null){
        for($i = 0; $i < count($files["name"]); $i++){
            move_uploaded_file($files['tmp_name'][$i], "$filePath/".basename($files["name"][$i]));
        }
    }
}else if($action === "download"){
    if(file_exists("$filePath/$fileName")){
        if(is_dir("$filePath/$fileName")){
            $file = tempnam("tmp", "zip");
            zipFolder("$filePath/$fileName", $file);
            readfile($file);
            unlink($file);
        }else if(is_file("$filePath/$fileName")){
            readfile("$filePath/$fileName");
        }
    }
}else if($action === "downloadMobile"){
    $downloadPath = "$filePath/$fileName";
    if(!file_exists($downloadPath)){
        http_response_code(404);
        exit(json_encode(["msg" => "The file path is not valid."]));
    }
    exit(json_encode(buildDownloadPayload($downloadPath, $fileName)));
}else if($action == "read"){
    $output = [];
    if(is_dir($filePath)){
        getOuput($filePath);
        exit(json_encode([
            "isFallBack" => $isFallBack,
            "type" => "dir",
            "content" => $output
        ]));
    }else if(is_file($filePath)){
        exit(json_encode([
            "isFallBack" => $isFallBack,
            "type" => "file",
            "content" => file_get_contents($filePath)
        ]));
    }
}
exit();
