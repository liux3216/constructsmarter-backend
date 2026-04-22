<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$settings = $db->all("SELECT * FROM `workOutSettings` WHERE `userId` = ? ORDER BY `createdAt`;", [$userId], __FILE__, __LINE__);
//-------------------------------------------------------
$groups = $db->all("SELECT * FROM `workOutGroups` WHERE `userId` = ? ORDER BY `createdAt`;", [$userId], __FILE__, __LINE__);
//-------------------------------------------------------
$sets = $db->all("SELECT * FROM `workOutSets` WHERE `userId` = ? ORDER BY `createdAt`;", [$userId], __FILE__, __LINE__);
//-------------------------------------------------
exit(json_encode(["settings" => $settings, "groups" => $groups, "sets" => $sets]));