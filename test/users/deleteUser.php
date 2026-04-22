<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
// remove calendar access
$id = $_POST["id"];
$db->exec("DELETE FROM `users` WHERE `id` = ?;", [$id], __FILE__, __LINE__);
$db->exec("DELETE FROM `outsideML` WHERE `userId` = ?;", [$id], __FILE__, __LINE__);
$db->exec("DELETE FROM `outsideDaily` WHERE `userId` = ?;", [$id], __FILE__, __LINE__);
$db->exec("DELETE FROM `outsideEOT` WHERE `userId` = ?;", [$id], __FILE__, __LINE__);
$db->exec("DELETE FROM `outsidePOT` WHERE `userId` = ?;", [$id], __FILE__, __LINE__);