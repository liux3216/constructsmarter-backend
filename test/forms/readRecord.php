<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/s3.php"; // uploagetObjectUrlFileWithBody, getObjectUrl
$record_id = isset($_POST["record_id"]) ? (int)$_POST["record_id"] : 0;
if ($record_id <= 0) {
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid record_id"]));
}
$record = $db->one(
    "SELECT `form_id`, `pdfId`, `name` 
     FROM `form_records`
     WHERE `id` = ?;",
    [$record_id], __FILE__, __LINE__
);
if (!$record) {
    http_response_code(404);
    exit(json_encode(["msg" => "Record not found"]));
}
$form_id = (int)$record["form_id"];
$form = $db->one(
    "SELECT `id`, `name`, `type`, `status`, `rules` 
     FROM `forms`
     WHERE `id` = ?;",
    [$form_id], __FILE__, __LINE__
);
if (!$form) {
    http_response_code(404);
    exit(json_encode(["msg" => "Form not found"]));
}
$fields = $db->all(
    "SELECT
        `field_key`,
        `label`,
        `field_type`,
        `is_required`,
        `sort_order`,
        `options`, 
        `min`,
        `max`,
        `placeholder`, 
        `default_value`, 
        `sub_form_id`, 
        `forms`.`name` AS `sub_form_name`
    FROM `form_fields`
    LEFT JOIN `forms` ON `forms`.`id` = `form_fields`.`sub_form_id`
    WHERE `form_id` = ?
    ORDER BY `sort_order` ASC;",
    [$form_id], __FILE__, __LINE__
);
$userKey = [];
foreach($fields as &$field){
    if ($field["options"] !== null) {
        $field["options"] = json_decode($field["options"], true);
    }
    if($field["default_value"] !== null){
        if (in_array($field["field_type"], ["checkbox", "multiSelect", "sub_form"], true)) {
            $field["default_value"] = json_decode($field["default_value"], true);
        }
    }
    if ($field["field_type"] === "user" && $field["default_value"] !== null) {
        $userKey[] = $field["default_value"];
    }
     if ($field["field_type"] === "users" && $field["default_value"] !== null) {
        array_push($userKey, ...array_values(json_decode($field["default_value"], true)));
    }
}
unset($field);
$userKey = array_unique($userKey);
if(count($userKey) > 0){
    $users = $db->all(
        "SELECT
            `id` AS `value`,
            CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `label`
        FROM `users`
        WHERE `id` IN (".implode(",", array_fill(0, count($userKey), "?")).");",
        $userKey, __FILE__, __LINE__
    );
    $userMap = [];
    foreach($users as $user){
        $userMap[$user["value"]] = $user["label"];
    }
    foreach($fields as &$field){
        if ($field["field_type"] === "user" && $field["default_value"] !== null) {
            $value = $field["default_value"];
            if(!array_key_exists($value, $userMap)) $field["default_value"] = null;
            else $field["default_value"] = ["value" => $value, "label" => $userMap[$value]];
        }
        if ($field["field_type"] === "users" && $field["default_value"] !== null) {
            $values = json_decode($field["default_value"], true);
            $filtered = array_filter($values, fn($v) => array_key_exists($v, $userMap));
            $values = array_map(function (string $value): array {
                global $userMap;
                return ["value" => $value, "label" => $userMap[$value]];
            }, $filtered);
            $field["default_value"] = $values;
        }
    }
    unset($field);
}
$rows = $db->all("SELECT `field_key`, `value_text`, `value_number`, `value_date`, `value_json` FROM `form_values` WHERE `record_id` = ?", [$record_id], __FILE__, __LINE__);
$values = [];
foreach ($rows as $row) {
    if ($row["value_text"] !== null) {
        $values[$row["field_key"]] = $row["value_text"];
    } elseif ($row["value_number"] !== null) {
        $values[$row["field_key"]] = $row["value_number"];
    } elseif ($row["value_date"] !== null) {
        $values[$row["field_key"]] = $row["value_date"];
    } elseif ($row["value_json"] !== null) {
        $values[$row["field_key"]] = json_decode($row["value_json"], true);
    } else {
        $values[$row["field_key"]] = null;
    }
    foreach ($fields as $field) {
        if($row["field_key"] == $field["field_key"]){
            if($field["field_type"] == "sub_form") {
                $ids = $values[$row["field_key"]] ?? [];
                if (!is_array($ids) || empty($ids)) {
                    $records = [];
                } else {
                    $ids = array_map("intval", $ids);
                    $placeholders = implode(",", array_fill(0, count($ids), "?"));
                    $records = $db->all(
                        "SELECT `id` AS `value`, `name` AS `label`
                        FROM `form_records`
                        WHERE `creatorId` = ?
                        AND `form_id` = ?
                        AND `status` = \"active\"
                        AND `id` IN ($placeholders)
                        ORDER BY `id`;", array_merge([$userId, $field["sub_form_id"]], $ids), __FILE__, __LINE__
                    );
                }
                $values[$row["field_key"]] = $records;
            }else if($field["field_type"] == "users") {
                $ids = $values[$row["field_key"]] ?? [];
                if (!is_array($ids) || empty($ids)) {
                    $users = [];
                } else {
                    $placeholders = implode(",", array_fill(0, count($ids), "?"));
                    $users = $db->all(
                        "SELECT `id` AS `value`, CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `label`
                        FROM `users`
                        WHERE `id` IN ($placeholders) ORDER BY `id`;", $ids, __FILE__, __LINE__
                    );
                }
                $values[$row["field_key"]] = $users;
            }else if($field["field_type"] == "user") {
                $id = $values[$row["field_key"]];
                if ($id) {
                    $user = $db->one(
                        "SELECT `id` AS `value`, CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `label`
                        FROM `users`
                        WHERE `id` = ?;", [$id], __FILE__, __LINE__
                    );
                }
                $values[$row["field_key"]] = $user ?? null;
            }else if($field["field_type"] == "picture") {
                $id = $values[$row["field_key"]];
                if ($id){
                    $files = $db->all("SELECT `id`, `name`, `description` AS `caption` FROM `fileInfo` WHERE `parentId` = ?;", [$id], __FILE__, __LINE__);
                    foreach($files as &$file){
                        $file["url"] = getObjectUrl($privateBucket, $file["id"], $file["name"]);
                        unset($file["name"]);
                    }
                    unset($file);
                }
                $values[$row["field_key"]] = ["id" => $id, "files" => $files ?? null];
            }
        }

    }
}
$pdfId = "";
if($record["pdfId"]) $pdfId = getObjectUrl($privateBucket, $record["pdfId"], "record_$record_id.pdf");
exit(json_encode([
    "schema" => [
        "form" => $form,
        "fields" => $fields
    ],
    "values" => $values,
    "record_id" => $record_id,
    "record_name" => $record["name"],
    "pdfId" => $pdfId
]));
