<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = trim((string)($_POST["id"] ?? ""));
if($id === ""){
    http_response_code(400);
    exit(json_encode(["msg" => "Missing id."]));
}
$row = $db->one("SELECT `void` FROM `purchases` WHERE `id` = ?;", [$id], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    exit(json_encode(["msg" => "Purchase not found."]));
}
if($row["void"] !== "yes"){
    http_response_code(400);
    exit(json_encode(["msg" => "Purchase must be void first."]));
}
$db->exec("DELETE FROM `purchases` WHERE `id` = ?;", [$id], __FILE__, __LINE__);
exit(json_encode(["id" => $id]));
