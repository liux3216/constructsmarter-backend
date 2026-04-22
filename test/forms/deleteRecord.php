<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/s3.php"; // deleteFile
$record_id = isset($_POST["record_id"]) ? (int)$_POST["record_id"] : 0;
if($record_id <= 0){
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid record_id"]));
}
$record = $db->one(
    "SELECT `pdfId`, `form_id` FROM `form_records` WHERE `id` = ?;",
    [$record_id], __FILE__, __LINE__
);
if(!$record){
    http_response_code(404);
    exit(json_encode(["msg" => "Record not found"]));
}
$fields = $db->all(
    "SELECT `field_key` FROM `form_fields` WHERE `form_id` = ? AND `field_type` = ?;",
    [$record["form_id"], "picture"], __FILE__, __LINE__
);
if(count($fields)){
    $field_keys = array_map(function(array $item): string | null {return $item["field_key"];}, $fields);
    $parentIdRows = $db->all(
        "SELECT `value_text` FROM `form_values` WHERE `record_id` = ? AND `value_text` IS NOT NULL AND `field_key` IN (".implode(",", array_fill(0, count($field_keys), "?")).");",
        array_merge([$record_id], $field_keys), __FILE__, __LINE__
    );
    $parentIds = array_map(function(array $item): string | null{return $item["value_text"];}, $parentIdRows);
    $idRows = $db->all(
        "SELECT `id` FROM `fileInfo` WHERE `parentId` IN (".implode(",", array_fill(0, count($parentIds), "?")).");",
        $parentIds, __FILE__, __LINE__
    );
    $ids = array_map(function(array $item): string | null {return $item["id"];}, $idRows);
}else{
    $parentIds = [];
    $ids = [];
}
try {
    $db->begin();
    if($record["pdfId"]) $db->exec(
        "DELETE FROM `fileInfo` WHERE `id` = ?;",
        [$record["pdfId"]], __FILE__, __LINE__
    );
    if(count($parentIds)) $db->exec(
        "DELETE FROM `fileInfo` WHERE `id` IN (".implode(",", array_fill(0, count($parentIds), "?")).");",
        $parentIds, __FILE__, __LINE__
    );
    $db->exec(
        "DELETE FROM `form_values` WHERE `record_id` = ?;",
        [$record_id], __FILE__, __LINE__
    );
    $db->exec(
        "DELETE FROM `form_records` WHERE `id` = ?;",
        [$record_id], __FILE__, __LINE__
    );
    $db->commit();
    foreach($ids as $id){
        try{
            deleteFile($privateBucket, $id);
        }catch(InvalidArgumentException $e){
            error_log("File Not Found. " . $e->getMessage());
        }
    }
    exit(json_encode(["ok" => true]));
} catch (Throwable $e) {
    $db->rollback();
    error_log("[deleteRecord] ".$e->getMessage());
    http_response_code(500);
    exit(json_encode(["msg" => "Failed to delete record"]));
}