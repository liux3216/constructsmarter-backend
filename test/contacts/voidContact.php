<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = $_POST["id"];
$voidReason = $_POST["voidReason"];
//-----------------------------------------------------------------
$db->exec(
    "UPDATE `contacts` SET `void` = \"yes\", `voidReason` = ? WHERE `id` = ?;", 
    [$voidReason, $id], __FILE__, __LINE__
);