<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$name = trim($_POST["name"] ?? "");
$description = trim($_POST["description"] ?? "");
if ($name === "") exit(json_encode(["error" => "Session name is required."]));
$db->exec("INSERT INTO `workOutLists` (`userId`, `name`, `description`) VALUES (?, ?, ?);", [$userId, $name, $description], __FILE__, __LINE__);
$id = (int)($db->one("SELECT LAST_INSERT_ID() AS `id`", [], __FILE__, __LINE__)["id"] ?? 0);
exit((string)$id);
