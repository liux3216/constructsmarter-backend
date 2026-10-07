<?php
require_once '/opt/bitnami/apache/htdocs/test/auth/internalAuth.php';
require_once __DIR__ . '/service.php';
try { exit(json_encode(dietApi($db,(string)$userId,$_POST))); }
catch (InvalidArgumentException $e) { http_response_code(422); exit(json_encode(['msg'=>$e->getMessage()])); }
catch (OutOfBoundsException $e) { http_response_code(404); exit(json_encode(['msg'=>$e->getMessage()])); }
