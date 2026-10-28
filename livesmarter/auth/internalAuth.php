<?php
require_once dirname(__DIR__).'/bootstrap.php';require_once __DIR__.'/service.php';
$liveUser=liveAuthenticate($db,$config);
$userId=$liveUser['id'];$userName=livePublicUser($liveUser)['userName'];$email=$liveUser['email'];$version='livesmarter-1';
