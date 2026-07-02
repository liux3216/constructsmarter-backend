<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$listId = $_POST["listId"];
$datePerformed = $_POST["datePerformed"];
$list = $db->one("SELECT * FROM `workOutLists` WHERE `id` = ? AND `userId` = ?;", [$listId, $userId], __FILE__, __LINE__);
if (!$list) exit(json_encode(["error" => "Session not found."]));
$db->exec("INSERT INTO `workOutListSessions` (`userId`, `listId`, `datePerformed`) VALUES (?, ?, ?);", [$userId, $listId, $datePerformed], __FILE__, __LINE__);
$id = (int)($db->one("SELECT LAST_INSERT_ID() AS `id`", [], __FILE__, __LINE__)["id"] ?? 0);
exit(json_encode(["id" => $id, "listId" => $listId, "datePerformed" => $datePerformed]));
