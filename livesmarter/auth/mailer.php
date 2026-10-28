<?php
function liveMail(string $to,string $subject,string $body):void {
 global $emailHost,$appEmail,$appEmailPassword;
 require_once '/opt/bitnami/apache/htdocs/components/vendor/autoload.php';
 $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
 $mail->isSMTP();$mail->Host=$emailHost;$mail->SMTPAuth=true;$mail->Username=$appEmail;$mail->Password=$appEmailPassword;
 $mail->SMTPSecure=\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;$mail->Port=587;$mail->Timeout=20;
 $mail->setFrom($appEmail,'LiveSmarter');$mail->addAddress($to);$mail->isHTML(true);$mail->Subject=$subject;$mail->Body=$body;$mail->AltBody=strip_tags($body);$mail->send();
}
function liveVerificationMail(array $user,callable $mailer):void {
 $url='https://life.constructsmarter.com/Verify?'.http_build_query(['email'=>$user['email'],'code'=>$user['verificationCode']]);
 $mailer($user['email'],'Verify your LiveSmarter email','<p>Welcome to LiveSmarter.</p><p><a href="'.htmlspecialchars($url,ENT_QUOTES).'">Verify your email</a> to receive a temporary password.</p>');
}
