<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------------
$userTheme = $_POST["userTheme"];
//-------------------------------------------------------
$db->exec("UPDATE `users` SET `userTheme` = ? WHERE `id` = ?;", [$userTheme, $userId], __FILE__, __LINE__);