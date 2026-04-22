<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$formId = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
if ($formId <= 0) {
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid form id"]));
}
$form = $db->one("SELECT `id`, `status` FROM `forms` WHERE `id` = ?;", [$formId], __FILE__, __LINE__);
if(!$form){
    http_response_code(404);
    exit(json_encode(["msg" => "Form not found"]));
}
/*
 * 如果你已经有 submissions / records：
 *
 * $used = $db->one(
 *   "SELECT id FROM form_records WHERE form_id = ?",
 *   [$formId], __FILE__, __LINE__
 * );
 *
 * if ($used) {
 *   http_response_code(409);
 *   exit(json_encode(["msg" => "Form is already in use"]));
 * }
 */
try {
    $db->begin();
    $db->exec("DELETE FROM `form_fields` WHERE `form_id` = ?;", [$formId], __FILE__, __LINE__);
    $db->exec("DELETE FROM `forms` WHERE `id` = ?;", [$formId], __FILE__, __LINE__);
    $db->commit();
    exit();
} catch (Throwable $e) {
    $db->rollback();
    error_log("[deleteForm] ".$e->getMessage());
    http_response_code(500);
    exit(json_encode(["msg" => "Failed to delete form"]));
}