<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$id = trim((string)($_POST["id"] ?? $_POST["reportHashKey"] ?? ""));

if ($id === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing id."]));
}

$db->exec(
    "DELETE FROM `reports` WHERE `id` = ?;",
    [$id],
    __FILE__,
    __LINE__
);

exit(json_encode(["id" => $id]));
