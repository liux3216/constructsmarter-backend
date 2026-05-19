<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//------------------------------------------------------
$projectId = $_POST["projectId"];
//------------------------------------------------------
$rows = $db->all(
    "SELECT `billingNumber`, `fromDate`, `toDate` 
    FROM `billings`
    WHERE `projectId` = ? 
    AND `void` <> \"yes\";", [$projectId], __FILE__, __LINE__
);
echo json_encode($rows);