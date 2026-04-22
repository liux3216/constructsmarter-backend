<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = $_POST["id"];
//-------------------------------------------------
$db->exec(
    "DELETE FROM `leads` WHERE `id` = ?;", 
    [$id], __FILE__, __LINE__
);