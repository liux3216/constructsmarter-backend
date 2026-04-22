<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = requireInt($_POST, "id", null, null, true);
$organization = $db->one(
    "SELECT `name` FROM `organizations` WHERE `id` = ?;",
    [$id], __FILE__, __LINE__
);
if(!$organization) {
    http_response_code(404);
    exit(json_encode(["msg" => "Organization not found."]));
}
exit(json_encode($organization));