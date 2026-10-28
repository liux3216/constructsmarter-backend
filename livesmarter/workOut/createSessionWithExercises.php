<?php
require_once '/opt/bitnami/apache/htdocs/livesmarter/auth/internalAuth.php';
require_once __DIR__ . '/sessionFromLog.php';
try {
    exit(json_encode(createSessionPlan($db, (string)$userId, $_POST)));
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    exit(json_encode(['msg' => $error->getMessage()]));
}
