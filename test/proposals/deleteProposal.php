<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = trim((string)($_POST["id"] ?? ""));
$db->exec("DELETE FROM `proposals` WHERE `id` = ?;", [$id], __FILE__, __LINE__);
