<?php
require_once __DIR__.'/service.php';require_once __DIR__.'/mailer.php';
function livePasswordUser($db,string $email):array {
 $row=$db->one('SELECT id FROM users WHERE LOWER(email)=? LIMIT 1',[strtolower(trim($email))]);
 $user=$row?liveAccount($db,$row['id']):null;
 if(!$user||!$user['active'])throw new InvalidArgumentException('No active account found for this email.');
 return $user;
}
// All password mutations and mail dispatch share a transaction. Mailers can be injected in CLI checks.
function livePasswordAction($db,string $action,array $input,callable $mailer):string {
 try{
  $db->begin();
  $user=livePasswordUser($db,(string)($input['email']??''));
  $db->one('SELECT user_id FROM livesmarter_accounts WHERE user_id=? FOR UPDATE',[$user['id']]);
  $user=liveAccount($db,$user['id']);
  if(!$user||!$user['active'])throw new InvalidArgumentException('No active account found for this email.');
  if($action==='verify'){
   if($user['verificationCode']===null)throw new InvalidArgumentException('This email is already verified. You can sign in or use Forgot Password.');
   if(!hash_equals((string)$user['verificationCode'],(string)($input['code']??'')))throw new InvalidArgumentException('Invalid verification link.');
  }elseif($user['verificationCode']!==null)throw new InvalidArgumentException('Verify your email first. Ask an administrator to resend the verification email if needed.');
  if($action==='change'){
   $current=(string)($input['currentPassword']??'');$next=(string)($input['newPassword']??'');
   if(!password_verify($current,(string)$user['password']))throw new InvalidArgumentException('Incorrect current password.');
   if(strlen($next)<8||strlen($next)>256)throw new InvalidArgumentException('Passwords must have between 8 and 256 characters.');
   $db->exec('UPDATE users SET password=?,isTmpPassword=0 WHERE id=?',[password_hash($next,PASSWORD_ARGON2ID),$user['id']]);
   $mailer($user['email'],'LiveSmarter password changed','<p>Your LiveSmarter password has been changed.</p>');
   $message='Password changed. Sign in with your new password.';
  }elseif(in_array($action,['verify','forgot'],true)){
   $temporary=bin2hex(random_bytes(16));
   $db->exec('UPDATE users SET password=?,verificationCode=NULL,isTmpPassword=1 WHERE id=?',[password_hash($temporary,PASSWORD_ARGON2ID),$user['id']]);
   $mailer($user['email'],'Your LiveSmarter temporary password','<p>Your temporary password is:</p><p><strong>'.$temporary.'</strong></p><p>Use <a href="https://life.constructsmarter.com/ChangePassword">Change Password</a> with your email and this temporary password before signing in.</p>');
   $message='A temporary password has been sent to your email. Use Change Password before signing in.';
  }else throw new InvalidArgumentException('Unknown password action.');
  $db->exec('UPDATE livesmarter_accounts SET auth_version=auth_version+1 WHERE user_id=?',[$user['id']]);
  $db->commit();return $message;
 }catch(Throwable $error){$db->rollback();throw $error;}
}
