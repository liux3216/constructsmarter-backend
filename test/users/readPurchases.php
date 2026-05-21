<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$curUserId = $_POST["curUserId"];
$rows = $db->all("SELECT `id`, `poNumber`, `total` FROM `purchases` WHERE `requestorId` = ?;", [$curUserId], __FILE__, __LINE__);
exit(json_encode($rows));
