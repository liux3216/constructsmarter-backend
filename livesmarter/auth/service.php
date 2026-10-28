<?php
function liveBase64(string $value):string{return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
function liveDecode(string $value):string|false{return base64_decode(strtr($value,'-_','+/'),true);}
function liveIssueToken(string $id,int $version,string $secret,int $now=0):string {
 $now=$now?:time();$header=liveBase64(json_encode(['alg'=>'HS256','typ'=>'JWT']));
 $body=liveBase64(json_encode(['iss'=>'livesmarter','aud'=>'livesmarter','sub'=>$id,'v'=>$version,'iat'=>$now,'exp'=>$now+43200]));
 return $header.'.'.$body.'.'.liveBase64(hash_hmac('sha256',$header.'.'.$body,$secret,true));
}
function liveVerifyToken(string $token,string $secret,int $now=0):?array {
 $parts=explode('.',$token);if(count($parts)!==3)return null;
 [$header,$body,$signature]=$parts;
 if(!hash_equals(liveBase64(hash_hmac('sha256',$header.'.'.$body,$secret,true)),$signature))return null;
 $head=json_decode(liveDecode($header)?:'',true);$claims=json_decode(liveDecode($body)?:'',true);$now=$now?:time();
 if(($head['alg']??'')!=='HS256'||!is_array($claims)||($claims['iss']??'')!=='livesmarter'||($claims['aud']??'')!=='livesmarter'||!is_string($claims['sub']??null)||!is_int($claims['v']??null)||!is_int($claims['exp']??null)||$claims['exp']<=$now||($claims['iat']??PHP_INT_MAX)>$now+60)return null;
 return $claims;
}
function liveAccount($db,string $id):?array{return $db->one('SELECT u.id,u.email,u.password,u.firstName,u.middleName,u.lastName,u.phoneNumber,u.birthDay,u.gender,u.isTmpPassword,u.profileId,u.userTheme,u.verificationCode,u.quitDate,a.is_admin,a.active,a.auth_version FROM users u JOIN livesmarter_accounts a ON a.user_id=u.id WHERE u.id=? AND a.deleted_at IS NULL',[$id]);}
function livePublicUser(array $user):array {
 return ['id'=>$user['id'],'email'=>$user['email'],'firstName'=>$user['firstName']??'','middleName'=>$user['middleName']??'','lastName'=>$user['lastName']??'','phoneNumber'=>$user['phoneNumber']??'','birthDay'=>$user['birthDay']??'','gender'=>$user['gender']??'','emailVerified'=>$user['verificationCode']===null,'isTmpPassword'=>(bool)$user['isTmpPassword'],'userName'=>trim(implode(' ',array_filter([$user['firstName']??'',$user['middleName']??'',$user['lastName']??'']))),'userTheme'=>$user['userTheme']?:'#115e59','profileId'=>$user['profileId'],'profileUrl'=>empty($user['profileId'])?'':'https://constructsmarterpublic.s3.us-west-1.amazonaws.com/'.rawurlencode($user['profileId']),'isAdmin'=>(bool)$user['is_admin'],'active'=>(bool)$user['active'],'role'=>$user['is_admin']?'Administrator':'Member','workOut'=>'yes','purchases'=>'no'];
}
function liveSendToken(array $user,array $config):string {
 $token='Bearer '.liveIssueToken((string)$user['id'],(int)$user['auth_version'],$config['jwt_secret']);
 header('Authorization: '.$token);header('X-Auth-Token: '.$token);
 setcookie('livesmarter_jwt',$token,['expires'=>time()+43200,'path'=>'/livesmarter/','secure'=>true,'httponly'=>true,'samesite'=>'None']);return $token;
}
function liveReadToken():string {
 $value=$_SERVER['HTTP_X_APP_TOKEN']??$_SERVER['HTTP_AUTHORIZATION']??$_POST['token']??$_COOKIE['livesmarter_jwt']??'';
 return is_string($value)?preg_replace('/^Bearer\s+/i','',trim($value)):'';
}
function liveAuthenticate($db,array $config):array {
 $claims=liveVerifyToken(liveReadToken(),$config['jwt_secret']);
 if(!$claims)liveFail('Your LiveSmarter session has expired. Please sign in again.',401);
 $user=liveAccount($db,$claims['sub']);
 if(!$user||!$user['active']||$user['verificationCode']!==null||$user['isTmpPassword']||(int)$user['auth_version']!==$claims['v'])liveFail('Your LiveSmarter session has expired. Please sign in again.',401);
 return $user;
}
