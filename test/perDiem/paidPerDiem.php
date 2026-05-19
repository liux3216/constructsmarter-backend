<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
$id = perDiemRequirePost("id");
$paid = perDiemRequirePost("paid");
$access = getPerDiemAccess($db, $userId);
if($access !== "editAll"){
    http_response_code(403);
    exit(json_encode(["msg" => "You are not allowed to update paid state."]));
}
if(!in_array($paid, ["yes", "no"], true)){
    http_response_code(400);
    exit(json_encode(["msg" => "Invalid paid state."]));
}
$db->exec("UPDATE `perDiems` SET `paid` = ?, `updaterId` = ? WHERE `id` = ?;", [$paid, $userId, $id], __FILE__, __LINE__);
exit(json_encode(["id" => (int)$id, "paid" => $paid]));
