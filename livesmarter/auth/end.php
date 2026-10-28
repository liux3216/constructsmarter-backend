<?php
require_once __DIR__.'/internalAuth.php';
$db->exec('UPDATE livesmarter_accounts SET auth_version=auth_version+1 WHERE user_id=?',[$userId]);
foreach(['/livesmarter/','/test/livesmarter/'] as $path)setcookie('livesmarter_jwt','',['expires'=>time()-3600,'path'=>$path,'secure'=>true,'httponly'=>true,'samesite'=>'None']);liveRespond(['success'=>true]);
