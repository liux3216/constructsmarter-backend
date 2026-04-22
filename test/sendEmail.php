<?php
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $appEmail, $emailHost, $appEmailPassword, $appName
function sendEmail(array $params): void {
    $db = $params["db"];
    $to = $params["to"];
    $cc = array_key_exists("cc", $params)?$params["cc"]:NULL;
    $summary = $params["summary"];
    $body = $params["body"];
    $attachments = array_key_exists("attachments", $params)?$params["attachments"]:NULL;
    $selfEmail = $params["selfEmail"];
    $path = $params["path"];
    $noBodyTemplate = array_key_exists("noBodyTemplate", $params)?$params["noBodyTemplate"]:NULL;
    $clientBodyTemplate = array_key_exists("clientBodyTemplate", $params)?$params["clientBodyTemplate"]:NULL;
    global $emailHost, $appEmail, $appEmailPassword, $appName;
    if(!$selfEmail){
        $selfEmail = $appEmail;
    }
    if($noBodyTemplate !== true && $clientBodyTemplate !== true){
        if(is_array($to)){
            if(count($to) === 1){
                $row = $db->one("SELECT CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `userName` FROM `users` WHERE `email` = ?;", [$to[0]], __FILE__, __LINE__);
                if(!$row){
                    http_response_code(404);
                    error_log(basename(__FILE__)." ".__LINE__." ".$selfEmail." The email(".$to[0].") is not found.");
                    exit(json_encode(["msg" => "The email(".$to[0].") is not found."]));
                }
                $userName = $row["userName"];
            }else if(count($to) === 2){
                $userName = "Both";
            }else{
                $userName = "All";
            }
        }else{
            $row = $db->one("SELECT CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `userName` FROM `users` WHERE `email` = ?;", [$to], __FILE__, __LINE__);
            $userName = $row["userName"];
        }
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $emailHost;
        $mail->SMTPAuth = true;
        $mail->Username = $appEmail;
        $mail->Password = $appEmailPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom($appEmail, $appName);
        if(is_array($to)){
            foreach($to as $email){
                $mail->addAddress($email);
            }
        }else{
            $mail->addAddress($to);
        }
        if($cc){
            if(is_array($cc)){
                foreach($cc as $ccEmail){
                    $mail->AddCC($ccEmail);
                }
            }else{
                $mail->AddCC($cc);
            }
        }
        $mail->addReplyTo($appEmail, $appName);
        $mail->AddEmbeddedImage("/opt/bitnami/apache/htdocs/test/logo.png", "logo");
        if($attachments){
            if(is_array($attachments)){
              foreach($attachments as $attachment){
                    if($attachment->id && $attachment->name){
                        $validName = str_replace(["\\", "/"], "_", $attachment->name);
                        function getTextData($value){return $value;}
                        $blob = getTextData($attachment->id);
                        if($blob !== false){
                            $fp = fopen($validName, "w");
                            if($fp !== false){
                                fwrite($fp, $blob);
                                fclose($fp);
                                $mail->addAttachment($validName);
                            }else{
                                error_log("$path $selfEmail attachment failed to load. (path: $validName)");
                            }
                        }else{
                            error_log("$path $selfEmail attachment failed to load. (id: $attachment->id)");
                        }
                    }else if(file_exists($attachment->fileName)){
						$mail->addAttachment($attachment->fileName);
					}else{
                        error_log("$path $selfEmail ".json_encode($attachment));
                    }
                }
            }
        }
        $mail->isHTML(true);
        $mail->Subject = $summary;
        $clientBodyTemplateText = "$body<br>
        <br>
        Best Regards,<br>
        $appName<br>
        <br>
        <img src=\"cid:logo\">";
        if($noBodyTemplate !== true && $clientBodyTemplate !== true){
            $body = "<body>
                Dear $userName,<br>
                <br>
                $clientBodyTemplateText
            </body>";
        }
        if($clientBodyTemplate === true){
            $body = "<body>
                $clientBodyTemplateText
            </body>";
        }
        $mail->Body = wordwrap($body, 70);
        $mail->send();
        if($attachments){
            if(is_array($attachments)){
                foreach($attachments as $attachment){
                    if(file_exists($attachment->name)){
                        unlink(str_replace("\\", "_", $attachment->name));
                    }else if(file_exists($attachment->fileName)){
						unlink($attachment->fileName);
					}
                }
            }else{
                error_log(__FILE__.": no path of $attachment->name");
            }
        }
    }catch(phpmailerException $e){
        error_log($path." ".$selfEmail." ".json_encode($e));
    }catch(Exception $e){
        error_log($path." ".$selfEmail." ".$mail->ErrorInfo);
    }
}