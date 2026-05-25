<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = trim((string)($_POST["id"] ?? ""));
if($id === ""){
    http_response_code(400);
    exit(json_encode(["msg" => "Missing id."]));
}
$db->exec("UPDATE `proposals` SET `notifiedAt` = NOW(), `notifiedBy` = ? WHERE `id` = ?;", [$userId, $id], __FILE__, __LINE__);
exit(json_encode(["id" => $id]));
