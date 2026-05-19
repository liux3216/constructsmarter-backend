<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$importWeek = trim((string)($_POST["importWeek"] ?? ""));
if (!preg_match('/^w\d{4}-\d{2}$/', $importWeek)) {
    http_response_code(409);
    exit(json_encode(["msg" => "Invalid import week."]));
}

$row = $db->one(
    "SELECT COUNT(*) AS `total`
     FROM `imported_billings`
     WHERE `week` = ?;",
    [$importWeek],
    __FILE__,
    __LINE__
);

exit(json_encode(((int)($row["total"] ?? 0)) > 0 ? "yes" : "no"));
