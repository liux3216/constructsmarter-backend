<?php
require_once dirname(__DIR__).'/bootstrap.php';require_once __DIR__.'/service.php';
$email=strtolower(trim((string)($_POST['email']??'')));$password=$_POST['password']??'';
$row=$db->one('SELECT id FROM users WHERE LOWER(email)=? LIMIT 1',[$email]);$user=$row?liveAccount($db,$row['id']):null;
if(!$user||!is_string($password)||!password_verify($password,(string)$user['password']))liveFail('Incorrect email or password.',401);
if(!$user['active'])liveFail('This LiveSmarter account is inactive.',403);
if($user['verificationCode']!==null)liveFail('Verify your email first. Ask an administrator to resend the verification email if needed.',403);
if($user['isTmpPassword'])liveFail('Temporary password. Please use Change Password before signing in.',403);
liveRespond(['user'=>livePublicUser($user),'token'=>liveSendToken($user,$config)]);
