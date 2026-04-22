<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "recordPDF.php";
$raw = $_POST["data"] ?? "";
if (!$raw) {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing payload"]));
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid JSON"]));
}
$formId = isset($data["formId"]) ? (int)$data["formId"] : 0;
$record_name = isset($data["record_name"]) ? $data["record_name"] : "";
$values = $data["values"] ?? null;
if ($formId <= 0 || !is_array($values)) {
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid payload"]));
}
$form = $db->one(
    "SELECT `name` FROM `forms` WHERE `id` = ? AND `status` = \"published\";",
    [$formId], __FILE__, __LINE__
);
if(!$form){
    http_response_code(404);
    exit(json_encode(["msg" => "Form not found or not published"]));
}
$fields = $db->all(
    "SELECT `field_key`, `field_type`, `label` FROM `form_fields` WHERE `form_id` = ? ORDER BY `sort_order` ASC;",
    [$formId], __FILE__, __LINE__
);
$fieldTypeMap = [];
foreach ($fields as $f) {
    $fieldTypeMap[$f["field_key"]] = $f["field_type"];
}
try {
    $db->begin();
    $db->exec(
        "INSERT INTO `form_records` (`form_id`, `name`, `creatorId`, `createdAt`, `updatedAt`) VALUES (?, ?, ?, NOW(), NOW());",
        [$formId, $record_name, $userId], __FILE__, __LINE__
    );
    $record_id = (int)$db->lastInsertId();
    $sqlText = "INSERT INTO `form_values` (`record_id`, `field_key`, `value_text`) VALUES (?, ?, ?);";
    $sqlNumber = "INSERT INTO `form_values` (`record_id`, `field_key`, `value_number`) VALUES (?, ?, ?);";
    $sqlDate = "INSERT INTO `form_values` (`record_id`, `field_key`, `value_date`) VALUES (?, ?, ?);";
    $sqlJson = "INSERT INTO `form_values` (`record_id`, `field_key`, `value_json`) VALUES (?, ?, ?);";
    foreach($values as $key => $value){
        if(!isset($fieldTypeMap[$key])){
            continue;
        }
        $type = $fieldTypeMap[$key];
        // "display": "Display",
        switch($type){
            case "range":
            case "number":
                $db->exec($sqlNumber, [
                    $record_id,
                    $key,
                    $value === "" ? null : (float)$value
                ], __FILE__, __LINE__);
                break;
            case "date":
                $db->exec($sqlDate, [
                    $record_id,
                    $key,
                    $value ?: null
                ], __FILE__, __LINE__);
                break;
            case "users":
            case "sub_form":
            case "checkbox":
            case "multiSelect":
                if(is_array($value)) $options = json_encode($value);
                else if($value) $options = json_encode([$value]);
                else $options = null;
                $db->exec($sqlJson, [
                    $record_id,
                    $key,
                    $options
                ], __FILE__, __LINE__);
                break;
            case "signature":
                break;
            case "user":
            case "select": 
            case "radio": 
            case "text":
            case "time":
            case "month":
            case "week":
            case "datetime-local":
            case "picture"; 
            case "textarea":

            case "file":
            case "color":
            case "email":
            case "password": 
            case "search":
            case "tel":
            case "url": 
            default:
                $db->exec($sqlText, [
                    $record_id,
                    $key,
                    $value ?: null
                ], __FILE__, __LINE__);
                break;
        }
    }
    $pdfId = generateRecordPdf(
        $formId, 
        $record_name, 
        $record_id
    );
    if($pdfId !== ""){
        $db->exec("UPDATE `form_records` SET `pdfId` = ? WHERE `id` = ?", [$pdfId, $record_id], __FILE__, __LINE__);
    }
    $db->commit();
}catch(Throwable $e){
    $db->rollback();
    error_log(__FILE__.":".$e->getMessage());
    http_response_code(500);
    exit(json_encode(["msg" => "Failed to create record"]));
}
exit(json_encode([
    "id" => $record_id
]));