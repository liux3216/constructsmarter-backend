<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = $_POST["id"];
$db->exec("DELETE FROM `workOutListItemSets` WHERE `listItemId` = ? AND `userId` = ?;", [$id, $userId], __FILE__, __LINE__);
$db->exec("DELETE FROM `workOutListItems` WHERE `id` = ? AND `userId` = ?;", [$id, $userId], __FILE__, __LINE__);
exit();
