<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $testerEmails, $mainRoot, $roots
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//------------------------------------------------------------
if(!in_array($email, $testerEmails)) exit();
//----------------------------------------------------
$action = $_POST["action"];
$path = array_key_exists("path", $_POST)?$_POST["path"]:NULL;
$rootPath = array_key_exists("rootPath", $_POST)?$_POST["rootPath"]:NULL;
$content = array_key_exists("content", $_POST)?$_POST["content"]:NULL;
//----------------------------------------------------
if($action === "delete"){
    if(file_exists($path)) unlink($path);
    exit();
}
if($action === "partDelete"){
    if(file_exists($path)) file_put_contents($path, $content);
    exit();
}
if($action === "read"){
    if($rootPath !== "" && !in_array($rootPath, $roots)){
        http_response_code(404);
        error_log(basename(__FILE__)." ".__LINE__." ".$userId." The root path is not valid.");
        exit(json_encode(["msg" => "The root path is not valid."]));
    }
    $output = [];
    function getOuput(string $dir): void {
        global $output;
        foreach(scandir($dir) as $file){
            if(!in_array($file, [".", "..", ".git"]) && is_dir("$dir/$file")){
                getOuput("$dir/$file");
            }
            if($file === "error_log"){
                $content = file_get_contents("$dir/$file");
                if($content === false) $content = "failed to load the file contents";
                $output[] = [
                    "path" => "$dir/$file",
                    "content" => $content
                ];
            }
        }
    }
    if($rootPath === ""){
        // foreach($roots as $root) getOuput($root);
        getOuput($mainRoot);
    }else{
        getOuput($rootPath);
    }
    exit(json_encode($output));
}

