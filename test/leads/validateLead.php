<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = $_POST["id"];
$validateReason = $_POST["validateReason"];
//-------------------------------------------------
$db->exec(
    "UPDATE `leads` SET `void` = \"no\", `validateReason` = ? WHERE `id` = ?;", 
    [$validateReason, $id], __FILE__, __LINE__
);