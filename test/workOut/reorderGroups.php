<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__ . "/saveGroupOrder.php";

try {
    saveGroupOrder($db, (string)$userId, $_POST["datePerformed"] ?? null, $_POST["groupIds"] ?? null);
    exit(json_encode(["success" => true]));
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    exit(json_encode(["msg" => $error->getMessage()]));
} catch (RuntimeException $error) {
    http_response_code(409);
    exit(json_encode(["msg" => $error->getMessage()]));
}
