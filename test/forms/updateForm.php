<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$raw = $_POST["data"] ?? "";
if(!$raw){
    http_response_code(400);
    exit(json_encode(["msg" => "Missing payload"]));
}
$data = json_decode($raw, true);
if(!is_array($data)){
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid JSON"]));
}
$form = $data["form"] ?? null;
$fields = $data["fields"] ?? null;
if(
    !$form ||
    !isset($form["id"], $form["name"], $form["type"], $form["status"])
){
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid form payload"]));
}
$formId = $form["id"];
if($formId <= 0){
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid form id"]));
}
if(!is_array($fields)){
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid fields"]));
}
if(!in_array($form["status"], ["draft", "published"], true)){
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid status"]));
}
$exists = $db->one("SELECT `id` FROM `forms` WHERE `id` = ?;",[$formId], __FILE__, __LINE__);
if(!$exists){
    http_response_code(404);
    exit(json_encode(["msg" => "Form not found"]));
}
$formName = trim($form["name"] ?? "");
$formType = trim($form["type"] ?? "");
$formRules = $form["rules"] === "[]" ? null : $form["rules"];
$formStatus = $form["status"] ?? "draft";
try{
    $db->begin();
     /* ---------- update form ---------- */
    $db->exec(
        "UPDATE `forms`
         SET
            `name` = ?,
            `type` = ?,
            `status` = ?,
            `rules` = ?, 
            `updatedAt` = NOW()
         WHERE `id` = ?;",
        [
            $formName,
            $formType,
            $formStatus,
            $formRules,
            $formId
        ], __FILE__, __LINE__
    );
    /* ---------- delete all existing fields ---------- */
    // $db->exec("DELETE FROM form_fields WHERE form_id = ?", [$formId], __FILE__, __LINE__);
    /* ---------- load existing fields ---------- */
    $existingFields = $db->all(
        "SELECT 
        `field_key`,
        `label`,
        `field_type`,
        `is_required`,
        `min`,
        `max`,
        `placeholder`, 
        `default_value`,
        `options`, 
        `sort_order`
        FROM `form_fields` 
        WHERE `form_id` = ?;", 
        [$formId], __FILE__, __LINE__
    );
    $existingByFieldKey = [];
    foreach($existingFields as $existingField){
        $existingByFieldKey[$existingField["field_key"]] = $existingField;
    }
    /* ---------- payload indexed by id ---------- */
    $payloadById = [];
    foreach($fields as $field){
        if(!isset($field["field_key"])){
            http_response_code(400);
            exit(json_encode(["msg" => "Field Key missing"]));
        }
        $payloadById[$field["field_key"]] = $field;
    }
    $existingIds = array_keys($existingByFieldKey);
    $payloadIds  = array_keys($payloadById);
    /* ---------- delete removed ---------- */
    $toDelete = array_diff($existingIds, $payloadIds);
    if($toDelete && count($toDelete)){
        $db->exec(
            "DELETE FROM `form_fields`
             WHERE `form_id` = ?
             AND `field_key` IN (".implode(",", array_fill(0, count($toDelete), "?")).");",
            array_merge([$formId], array_values($toDelete))
        );
    }
    /* ---------- insert or diff-update ---------- */
    $diffKeys = ["label", "field_type", "is_required", "sort_order", "options", "min", "max", "placeholder", "default_value", "sub_form_id"];
    foreach($payloadById as $field_key => $field){
        if(
            !array_key_exists("label", $field) ||
            !array_key_exists("field_type", $field)
        ){
            http_response_code(407);
            exit(json_encode(["msg" => "Invalid field payload"]));
        }
        /* ---------- NEW ---------- */
        if(!isset($existingByFieldKey[$field_key])){
            $db->exec(
                "INSERT INTO `form_fields` 
                (`form_id`, `field_key`, `label`, `field_type`, `is_required`, `sort_order`, `options`, `min`, `max`,`placeholder`, `default_value`, `sub_form_id`)
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);",
                [
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
                    $field["sub_form_id"]
                ], __FILE__, __LINE__
            );
            continue;
        }
        /* ---------- DIFF UPDATE ---------- */
        $existing = $existingByFieldKey[$field_key];
        $set = [];
        $params = [];
        foreach($diffKeys as $key){
            $oldVal = array_key_exists($key, $existing) ? $existing[$key] : null;
            $newVal = array_key_exists($key, $field) ? $field[$key] : null;
            if($newVal !== $oldVal){
                $set[] = "$key = ?";
                $params[] = $newVal;
            }
        }
        if($set){
            $sql = "UPDATE form_fields
            SET ".implode(", ", $set)."
            WHERE `field_key` = ? AND `form_id` = ?;";
            $params[] = $field_key;
            $params[] = $formId;
            $db->exec($sql, $params);
        }
        // else: no change → skip update
    }
    $db->commit();
    exit();
}catch(Throwable $e){
    $db->rollback();
    error_log("[updateForm] " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(["msg" => "Failed to update form"]));
}
