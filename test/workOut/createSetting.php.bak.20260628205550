<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$name = $_POST["name"];
$description = $_POST["description"];
$mode = $_POST["mode"];
$db->exec("INSERT INTO `workOutSettings` (`userId`, `name`, `description`, `mode`) VALUES (?, ?, ?, ?);", [$userId, $name, $description, $mode], __FILE__, __LINE__);
$id = (int)($db->one("SELECT LAST_INSERT_ID() AS `id`", [], __FILE__, __LINE__)["id"] ?? 0);
exit((string)$id);
