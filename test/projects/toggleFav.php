<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$projectId = requireInt($_POST, "id", null, null, true);
$user = $db->one(
    "SELECT `fav`
    FROM `users` 
    WHERE `id` = ?;", [$userId], __FILE__,__LINE__
);
if(!$user){
    jsonResponse(404,["msg" => "The user is not found."]);
}
$fav = $user["fav"];
//-------------------------------------------------
if($fav !== null){
    $fav = json_decode($fav, true);
    if(is_array($fav)){
        if(array_key_exists("projects", $fav)){
            if(!in_array($projectId, $fav["projects"])){
                array_push($fav["projects"], $projectId);
                $fav = json_encode($fav);
            }else{
                $fav["projects"] = array_values(array_filter($fav["projects"], fn($item) => $item !== $projectId));
                $fav = json_encode($fav);
            }
        }else{
            $fav["projects"] = [$projectId];
            $fav = json_encode($fav);
        }
    }
}else{
    $fav = [];
    $fav["projects"] = [$projectId];
    $fav = json_encode($fav);
}
$db->exec(
    "UPDATE `users` 
    SET `fav` = ?
    WHERE `id` = ?;", [$fav, $userId], __FILE__,__LINE__
);
exit($fav);   