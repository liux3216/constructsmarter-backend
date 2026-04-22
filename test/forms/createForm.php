<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
if(!isset($_POST["data"])){
    http_response_code(409);
    exit(json_encode(["msg" => "Missing payload"]));
}
$payload = json_decode($_POST["data"], true);
if(!$payload){
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid JSON payload"]));
}
$form = $payload["form"] ?? null;
$fields = $payload["fields"] ?? null;
if(!$form || !is_array($fields)){
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid form structure"]));
}
$formName = trim($form["name"] ?? "");
$formType = trim($form["type"] ?? "") ?: null;
$formRules = $form["rules"] === "[]" ? null : $form["rules"];
$formStatus = $form["status"] ?? "draft";
if($formName === ""){
    http_response_code(422);
    exit(json_encode(["msg" => "Form name is required"]));
}
$allowedStatus = ["draft", "published"];
if(!in_array($formStatus, $allowedStatus, true)){
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid status"]));
}
try {
    $db->begin();
    $db->exec(
        "INSERT INTO `forms` (`name`, `type`, `status`, `rules`) VALUES (?, ?, ?, ?);",
        [$formName, $formType, $formStatus, $formRules], __FILE__, __LINE__
    );
    $formId = (int)$db->lastInsertId();
    $values = [];
    $params = [];
    foreach ($fields as $field) {
        $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        array_push(
            $params,
            $formId,
            $field["field_key"], 
            $field["label"],
            $field["field_type"],
            $field["is_required"],
            $field["sort_order"], 
            $field["options"],
            $field["min"], 
            $field["max"], 
            $field["placeholder"], 
            $field["default_value"], 
            $field["sub_form_id"],
        );
    }
    if($values){
        $db->exec(
            "INSERT INTO `form_fields`
            (`form_id`, `field_key`, `label`, `field_type`, `is_required`, `sort_order`, `options`, `min`, `max`, `placeholder`, `default_value`, `sub_form_id`)
            VALUES ".implode(",", $values).";",
            $params, __FILE__, __LINE__
        );
    }
    $db->commit();
    exit(json_encode(["id" => $formId]));
} catch (Throwable $e) {
    $db->rollback();
    error_log("[createRecord] " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(["msg" => "Failed to create Form"]));
}