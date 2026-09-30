<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__ . '/listSettingsPage.php';
try {
    exit(json_encode(listSettingsPage($db, (string)$userId, $_POST, $publicBucket)));
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    exit(json_encode(['msg' => $error->getMessage()]));
}
