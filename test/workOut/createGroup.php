<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$workOutSettingId = $_POST["workOutSettingId"];
$datePerformed = $_POST["datePerformed"];
$notes = $_POST["notes"];
$uuid = md5(rand());
$db->exec("INSERT INTO `workOutGroups` (`id`, `userId`, `notes`, `workOutSettingId`, `datePerformed`) VALUES (\"$uuid\", \"$userId\",?, ?, ?);", [$notes, $workOutSettingId, $datePerformed], __FILE__, __LINE__);
exit($uuid);