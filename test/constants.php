<?php
require_once "/opt/bitnami/apache/conf/constants.php";
// $mainIP
// $mainRoot 
// $sqlInfo
// $emailHost
// $appEmail
// $appEmailPassword
$sqlInfo["database"] = "test";
$companyName = "Construct Smarter";
$rootName = "test";
$mainUrl = "https://test.constructsmarter.com";
$appName = "Construct Smarter";
$allowedOrigins = [
    "https://test.constructsmarter.com", 
    "https://constructsmarter.netlify.app", 
    "http://localhost:3000", 
    "http://localhost:3001"
];
//-----------------------------------------------
$testerEmails = [
    "jun909l@yahoo.com"
];
$roots = [
    "test" => "/opt/bitnami/apache/htdocs/test", 
    "mti" => "/opt/bitnami/apache/htdocs/mti"
];
$profileFolderId = "a4c1d0dcd82a9c9b6938b30acd1787c4";
$trainingProblemFolderId = "8fbfe7d21b41f3e2d5f2cae5ffbbe2b5";
$trainingDataFolderId = "454ccd28e21b3d9d57133a71034c9c65";