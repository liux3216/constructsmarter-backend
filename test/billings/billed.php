<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = (int)($_POST["id"] ?? 0);
$billed = trim((string)($_POST["billed"] ?? ""));
$billed = $billed === "yes" ? "yes" : "no";
//-------------------------------------------------
if(!$id){
    http_response_code(400);
    exit(json_encode(["msg" => "Missing billing id."]));
}
$billing = $db->one(
    "SELECT `id` FROM `billings` WHERE `id` = ? LIMIT 1;",
    [$id],
    __FILE__, __LINE__
);
if(!$billing){
    http_response_code(404);
    exit(json_encode(["msg" => "The billing is not found."]));
}
//-------------------------------------------------
$db->exec(
    "UPDATE `billings` SET `billed` = ?, `updaterId` = ? WHERE `id` = ?;",
    [$billed, $userId, $id],
    __FILE__, __LINE__
);
//-------------------------------------------------
exit(json_encode([
    "id" => $id,
    "billed" => $billed
]));
