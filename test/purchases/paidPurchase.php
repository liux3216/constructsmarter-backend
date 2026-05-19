<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = trim((string)($_POST["id"] ?? ""));
$paid = trim((string)($_POST["paid"] ?? "no"));
if($id === ""){
    http_response_code(400);
    exit(json_encode(["msg" => "Missing id."]));
}
$paid = $paid === "yes" ? "yes" : "no";
$db->exec("UPDATE `purchases` SET `paid` = ?, `updaterId` = ? WHERE `id` = ?;", [$paid, $userId, $id], __FILE__, __LINE__);
exit(json_encode(["id" => $id, "paid" => $paid]));
