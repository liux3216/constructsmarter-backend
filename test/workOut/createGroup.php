<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$workOutSettingId = $_POST["workOutSettingId"];
$datePerformed = $_POST["datePerformed"];
$notes = $_POST["notes"];
$db->exec("INSERT INTO `workOutGroups` (`userId`, `notes`, `workOutSettingId`, `datePerformed`) VALUES (?, ?, ?, ?);", [$userId, $notes, $workOutSettingId, $datePerformed], __FILE__, __LINE__);
$id = (int)($db->one("SELECT LAST_INSERT_ID() AS `id`", [], __FILE__, __LINE__)["id"] ?? 0);
exit((string)$id);
