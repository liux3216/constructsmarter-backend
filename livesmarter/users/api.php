<?php
require_once dirname(__DIR__).'/auth/internalAuth.php';
require_once dirname(__DIR__).'/auth/mailer.php';
$admin=(bool)$liveUser['is_admin'];$action=$_POST['action']??'';
if($action==='list'){
 $rows=$admin?$db->all('SELECT u.id FROM users u JOIN livesmarter_accounts a ON a.user_id=u.id WHERE a.deleted_at IS NULL ORDER BY u.firstName,u.lastName'): [['id'=>$userId]];
 liveRespond(['users'=>array_map(fn($row)=>livePublicUser(liveAccount($db,$row['id'])),$rows),'isAdmin'=>$admin,'currentUserId'=>$userId]);
}
if($action==='delete'){
 if(!$admin)liveFail('Administrator access required.',403);
 $id=(string)($_POST['id']??'');
 if($id==='')liveFail('Choose an account to delete.');
 try{
  $db->begin();
  $admins=$db->all('SELECT user_id FROM livesmarter_accounts WHERE is_admin=1 AND active=1 AND deleted_at IS NULL FOR UPDATE');
  $existing=liveAccount($db,$id);
  if(!$existing)throw new InvalidArgumentException('User no longer exists.');
  if($existing['is_admin']&&$existing['active']&&count($admins)<=1)throw new InvalidArgumentException('Keep at least one active administrator.');
  if($id===$userId)throw new InvalidArgumentException('You cannot delete the account you are signed in to.');
  // Retain log ownership and audit references; remove access and hide the account.
  $db->exec('UPDATE livesmarter_accounts SET deleted_at=UTC_TIMESTAMP(),active=0,auth_version=auth_version+1 WHERE user_id=? AND deleted_at IS NULL',[$id]);
  $db->commit();liveRespond(['success'=>true]);
 }catch(InvalidArgumentException $error){$db->rollback();liveFail($error->getMessage());}
 catch(Throwable $error){$db->rollback();error_log('LiveSmarter account deletion: '.get_class($error));liveFail('Could not delete this account.',500);}
}
if($action==='resend'){
 if(!$admin)liveFail('Administrator access required.',403);
 $pending=liveAccount($db,(string)($_POST['id']??''));
 if(!$pending||$pending['verificationCode']===null)liveFail('This account does not need email verification.');
 try{liveVerificationMail($pending,'liveMail');liveRespond(['success'=>true,'msg'=>'Verification email sent.']);}
 catch(Throwable $error){liveFail('Could not send verification email. Please try again.',503);}
}
if($action!=='save')liveFail('Unknown user action.');
$id=(string)($_POST['id']??'');if(!$admin&&$id!==$userId)liveFail('Administrator access required.',403);
$first=trim((string)($_POST['firstName']??''));$last=trim((string)($_POST['lastName']??''));$email=strtolower(trim((string)($_POST['email']??'')));$phone=trim((string)($_POST['phoneNumber']??''));$password=(string)($_POST['password']??'');$middle=trim((string)($_POST['middleName']??''));$birthday=trim((string)($_POST['birthDay']??''));$gender=trim((string)($_POST['gender']??''));
if($first===''||$last===''||mb_strlen($first)>50||mb_strlen($last)>50||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>254||mb_strlen($phone)>40)liveFail('Enter a valid name, email and phone.');
if($password!=='')liveFail('Use Change Password or Forgot Password to change a password.');
if(mb_strlen($middle)>50||mb_strlen($gender)>50)liveFail('Middle name or gender is too long.');
$date=$birthday!==''?DateTimeImmutable::createFromFormat('!Y-m-d',$birthday):null;
if($birthday!==''&&(!$date||$date->format('Y-m-d')!==$birthday||$birthday<'1900-01-01'||$birthday>date('Y-m-d')))liveFail('Enter a valid birthday between 1900 and today.');
$isAdmin=$admin?(int)(($_POST['isAdmin']??'0')==='1'):(int)$liveUser['is_admin'];$active=$admin?(int)(($_POST['active']??'1')==='1'):(int)$liveUser['active'];
try{
 $db->begin();
 $admins=$db->all('SELECT user_id FROM livesmarter_accounts WHERE is_admin=1 AND active=1 AND deleted_at IS NULL FOR UPDATE');
 $existing=$id!==''?liveAccount($db,$id):null;
 if($id!==''&&!$existing)throw new InvalidArgumentException('User no longer exists.');
 if($existing&&$existing['is_admin']&&$existing['active']&&(!$isAdmin||!$active)&&count($admins)<=1)throw new InvalidArgumentException('Keep at least one active administrator.');
 if($db->one('SELECT id FROM users WHERE LOWER(email)=? AND id<>?',[$email,$id]))throw new InvalidArgumentException('This email is already in use.');
 if($id===''){
  $id=bin2hex(random_bytes(16));
  $code=bin2hex(random_bytes(24));
  $db->exec('INSERT INTO users (id,email,password,firstName,middleName,lastName,phoneNumber,birthDay,gender,verificationCode,isTmpPassword,userTheme,creatorId) VALUES (?,?,?,?,?,?,?,?,?,?,1,?,?)',[$id,$email,password_hash(bin2hex(random_bytes(32)),PASSWORD_ARGON2ID),$first,$middle,$last,$phone,$birthday?:null,$gender,$code,'#115e59',$userId]);
  $db->exec('INSERT INTO livesmarter_accounts (user_id,is_admin,active,auth_version) VALUES (?,?,?,1)',[$id,$isAdmin,$active]);
 }else{
  $db->exec('UPDATE users SET email=?,firstName=?,middleName=?,lastName=?,phoneNumber=?,birthDay=?,gender=?,updaterId=? WHERE id=?',[$email,$first,$middle,$last,$phone,$birthday?:null,$gender,$userId,$id]);
  $changed=$email!==$existing['email']||(bool)$existing['is_admin']!==(bool)$isAdmin||(bool)$existing['active']!==(bool)$active;
  $db->exec('UPDATE livesmarter_accounts SET is_admin=?,active=?,auth_version=auth_version+? WHERE user_id=?',[$isAdmin,$active,$changed?1:0,$id]);

 }
 if(!isset($existing)||!$existing)liveVerificationMail(liveAccount($db,$id),'liveMail');
 $db->commit();$saved=liveAccount($db,$id);$result=['success'=>true,'user'=>livePublicUser($saved)];if($id===$userId&&$saved['active'])$result['token']=liveSendToken($saved,$config);liveRespond($result);
}catch(InvalidArgumentException $error){$db->rollback();liveFail($error->getMessage());}catch(Throwable $error){$db->rollback();error_log('LiveSmarter user save: '.get_class($error));liveFail('Could not save this user or send verification email. Please try again.',500);}
