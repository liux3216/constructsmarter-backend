<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$name = $_POST["name"];
$description = $_POST["description"];
$mode = $_POST["mode"];
$id = $_POST["id"];
$db->exec(
    "UPDATE `workOutSettings` SET 
    `name` = ?, `description` = ?, `mode` = ? 
    WHERE `id` = ? AND `userId` = \"$userId\";", 
    [$name, $description, $mode, $id], __FILE__, __LINE__
);
exit();