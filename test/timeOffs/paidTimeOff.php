<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-----------------------------------------------------------------
$paid = $_POST["paid"];
$id = $_POST["id"];
//-----------------------------------------------------------------
$db->exec("UPDATE `timeOffs` SET `paid` = ? WHERE `id` = ?;", [$paid, $id], __FILE__, __LINE__);