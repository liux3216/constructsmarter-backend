<?php
require_once '/opt/bitnami/apache/htdocs/test/auth/internalAuth.php';
require_once __DIR__.'/service.php';
try {exit(json_encode(vocabularyApi($db,(string)$userId,$_POST)));}
catch(InvalidArgumentException $error){http_response_code(422);exit(json_encode(['msg'=>$error->getMessage()]));}
catch(OutOfBoundsException $error){http_response_code(404);exit(json_encode(['msg'=>$error->getMessage()]));}
catch(RuntimeException $error){http_response_code(409);exit(json_encode(['msg'=>$error->getMessage()]));}
