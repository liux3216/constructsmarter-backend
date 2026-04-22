<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$name = $_POST["name"];
$description = $_POST["description"];
$mode = $_POST["mode"];
$uuid = md5(rand());
$settings = $db->exec("INSERT INTO `workOutSettings` (`id`, `userId`, `name`, `description`, `mode`) VALUES (\"$uuid\", \"$userId\", ?, ?, ?);", [$name, $description, $mode], __FILE__, __LINE__);
exit($uuid);