<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$workOutSettingId = $_POST["workOutSettingId"];
$notes = $_POST["notes"];
$id = $_POST["id"];
$db->exec(
    "UPDATE `workOutGroups` SET 
    `workOutSettingId` = ?, 
    `notes` = ? 
    WHERE `id` = ? AND `userId` = \"$userId\";", 
    [$workOutSettingId, $notes, $id], __FILE__, __LINE__
);
exit();