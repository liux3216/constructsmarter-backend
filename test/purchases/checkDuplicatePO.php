<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$poNumber = trim((string)($_POST["poNumber"] ?? ""));
$id = trim((string)($_POST["id"] ?? ""));
if($poNumber === "") exit(json_encode([]));
$sql = "SELECT `id`, `poNumber` FROM `purchases` WHERE `poNumber` = ?" . ($id ? " AND `id` <> ?" : "") . " LIMIT 1;";
$params = $id ? [$poNumber, $id] : [$poNumber];
$row = $db->one($sql, $params, __FILE__, __LINE__);
exit(json_encode($row ?: []));
