<?php
require_once dirname(__DIR__).'/bootstrap.php';require_once dirname(__DIR__).'/auth/passwords.php';
try{liveRespond(['success'=>true,'msg'=>livePasswordAction($db,'forgot',$_POST,'liveMail')]);}
catch(InvalidArgumentException $error){liveFail($error->getMessage());}
catch(Throwable $error){error_log('LiveSmarter password mail failed: '.get_class($error));liveFail('Could not complete the password request. Please try again.',503);}
