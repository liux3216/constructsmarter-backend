<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = trim((string)($_POST["id"] ?? ""));
$validateReason = trim((string)($_POST["validateReason"] ?? ""));
if($id === "" || $validateReason === ""){
    http_response_code(400);
    exit(json_encode(["msg" => "Missing id or validate reason."]));
}
$db->exec("UPDATE `purchases` SET `void` = 'no', `validateReason` = ?, `updaterId` = ? WHERE `id` = ?;", [$validateReason, $userId, $id], __FILE__, __LINE__);
exit(json_encode(["id" => $id]));
