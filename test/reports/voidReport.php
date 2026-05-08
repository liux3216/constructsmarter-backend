<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$id = trim((string)($_POST["id"] ?? $_POST["reportHashKey"] ?? ""));
$voidReason = trim((string)($_POST["voidReason"] ?? ""));

if ($id === "" || $voidReason === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing id or void reason."]));
}

$db->exec(
    "UPDATE `reports`
     SET `void` = 'yes',
         `voidReason` = ?,
         `updaterId` = ?,
         `updatedAt` = NOW()
     WHERE `id` = ?;",
    [$voidReason, $userId, $id],
    __FILE__,
    __LINE__
);

exit(json_encode(["id" => $id]));
