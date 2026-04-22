<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$fileId = $_POST["fileId"];
$db->exec("UPDATE `fileInfo` SET `status` = \"uploaded\" WHERE `id` = ?;", [$fileId], __FILE__, __LINE__);
exit();