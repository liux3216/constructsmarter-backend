<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $trainingProblemFolderId
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$rows = $db->all("SELECT `name`, `id`, `description` FROM `fileInfo` WHERE `parentId` = ?;", [$trainingProblemFolderId], __FILE__, __LINE__);
exit(json_encode($rows));