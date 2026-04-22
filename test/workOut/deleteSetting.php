<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$id = $_POST["id"];
$db->exec("DELETE FROM `workOutSettings`WHERE `userId` = \"$userId\" AND `id` = ?;", [$id], __FILE__, __LINE__);
exit();