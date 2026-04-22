<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $roots, $testerEmails
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//------------------------------------------------------------
if(!in_array($email, $testerEmails)) exit();
//----------------------------------------------------
$needle = $_POST["needle"];
$autocomplete = $_POST["autocomplete"];
$caseSensitive = $_POST["caseSensitive"];
$wholeWord = $_POST["wholeWord"];
$rootPath = $_POST["rootPath"];
//----------------------------------------------------
if($rootPath !== "" && !in_array($rootPath, $roots)){
    http_response_code(404);
    exit(json_encode(["msg" => "The root path is not valid."]));
}
$output = [];
function getOuput(string $dir): void {
    global $output, $needle, $autocomplete, $caseSensitive, $wholeWord;
    $str = "1234567890_abcdefghijklmnopqrstuvwxyz";
    foreach(scandir($dir) as $file){
        if(!in_array($file, [".", "..", ".git"]) && is_dir("$dir/$file")){
            getOuput("$dir/$file");
        }
        if(substr($file, -4) === ".php"){
            $haystack = file_get_contents("$dir/$file");
            if($autocomplete === "true"){
                unset($matches);
                if($caseSensitive === "true"){
                    $needleRegex = preg_quote($needle, "/");
                    $numsRes = preg_match_all("/\b(\w*$needleRegex\w*)\b/", $haystack, $matches, PREG_UNMATCHED_AS_NULL);
                }else if($caseSensitive === "false"){
                    $needleRegex = preg_quote($needle, "/");
                    $numsRes = preg_match_all("/\b(\w*$needleRegex\w*)\b/i", $haystack, $matches, PREG_UNMATCHED_AS_NULL);
                }
                if($numsRes !== false && $numsRes !== 0){
                    $arr = array_unique($matches[0]);
                    sort($arr);
                    $output[] = [
                        "path" => str_replace("/opt/bitnami/apache/htdocs", "", "$dir/$file"), 
                        "matches" => $arr
                    ];
                }
            }else if($autocomplete === "false"){
                $startIndex = 0;
                if($caseSensitive === "true"){
                    $pos = strpos($haystack, $needle, $startIndex);
                }else if($caseSensitive === "false"){
                    $pos = stripos($haystack, $needle, $startIndex);
                }
                while($pos > -1){
                    $startIndex = $pos + 1;
                    //--------------------------
                    if($wholeWord === "true"){
                        $left = false;
                        $right = false;
                        if($pos === 0 || stripos($str, $haystack[$pos - 1]) === false){
                            $left = true;
                        }
                        if($pos + strlen($needle) - 1 === strlen($haystack) - 1 || stripos($str, $haystack[$pos + strlen($needle)]) === false){
                            $right = true;
                        }
                        if($left && $right){
                            $output[] = [
                                "path" => str_replace("/opt/bitnami/apache/htdocs", "", "$dir/$file"), 
                                "lines" => substr_count($haystack, "\n", 0, $pos) + 1, 
                                "pos" => $pos - strrpos(substr($haystack, 0, $pos), "\n")
                                // , "segment" => substr($haystack, max(0, $pos - 10), strlen($needle) + 20)
                            ];
                        }
                    }else if($wholeWord === "false"){
                        $output[] = [
                            "path" => str_replace("/opt/bitnami/apache/htdocs", "", "$dir/$file"), 
                            "lines" => substr_count($haystack, "\n", 0, $pos) + 1, 
                            "pos" => $pos - strrpos(substr($haystack, 0, $pos), "\n")
                            // , "segment" => substr($haystack, max(0, $pos - 10), strlen($needle) + 20)
                        ];
                    }
                    //--------------------------
                    if($caseSensitive === "true"){
                        $pos = strpos($haystack, $needle, $startIndex);
                    }else if($caseSensitive === "false"){
                        $pos = stripos($haystack, $needle, $startIndex);
                    }
                }
            }
        }
    }
}
if($rootPath === ""){
    foreach($roots as $root){
        getOuput($root);
    }
}else{
    getOuput($rootPath);
}
exit(json_encode($output));