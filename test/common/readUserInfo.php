<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//----------------------------------------------------
$id = $_POST["id"];
//----------------------------------------------------
$today = date("Y-m-d");
$user = $db->one(
    "SELECT *, CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `userName` 
    FROM `users` 
    WHERE `id` = \"$id\" AND (`quitDate` IS NULL OR `quitDate` > \"$today\");"
);
if(!$user){
    http_response_code(230);
    exit(["msg" => "The user is not found."]);
}
echo json_encode($user);
