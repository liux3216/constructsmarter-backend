<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$proposalNumber = trim((string)($_POST["proposalNumber"] ?? ""));
$id = trim((string)($_POST["id"] ?? ""));
if($proposalNumber === "") exit(json_encode([]));
if($id !== ""){
    $row = $db->one("SELECT `id`, `proposalNumber` FROM `proposals` WHERE `proposalNumber` = ? AND `id` <> ? LIMIT 1;", [$proposalNumber, $id], __FILE__, __LINE__);
} else {
    $row = $db->one("SELECT `id`, `proposalNumber` FROM `proposals` WHERE `proposalNumber` = ? LIMIT 1;", [$proposalNumber], __FILE__, __LINE__);
}
exit(json_encode($row ?: []));
