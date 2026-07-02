<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = $_POST["id"] ?? null;
$name = trim($_POST["name"] ?? "");
$description = trim($_POST["description"] ?? "");
if (!$id) exit(json_encode(["error" => "Session id is required."]));
if ($name === "") exit(json_encode(["error" => "Session name is required."]));
$db->exec("UPDATE `workOutLists` SET `name` = ?, `description` = ? WHERE `id` = ? AND `userId` = ?;", [$name, $description, $id, $userId], __FILE__, __LINE__);
exit(json_encode(["success" => true]));
