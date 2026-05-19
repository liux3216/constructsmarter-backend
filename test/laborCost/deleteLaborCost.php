<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$week = trim((string)($_POST["week"] ?? ""));
if (!preg_match('/^w\d{4}-\d{2}$/', $week)) {
    http_response_code(409);
    exit(json_encode(["msg" => "Invalid week."]));
}

$db->exec(
    "DELETE FROM `labor_costs` WHERE `week` = ?;",
    [$week],
    __FILE__,
    __LINE__
);

exit(json_encode(["msg" => "ok"]));
