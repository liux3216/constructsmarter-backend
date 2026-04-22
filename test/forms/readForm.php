<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$formId = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
if ($formId <= 0) {
    http_response_code(407);
    echo json_encode(["error" => "Invalid form id"]);
    exit;
}
$form = $db->one(
    "SELECT
        `id`,
        `name`,
        `type`,
        `status`,
        `rules`,
        `createdAt`,
        `updatedAt`
     FROM `forms`
     WHERE `id` = ?;",
    [$formId], __FILE__, __LINE__
);
if(!$form){
    http_response_code(404);
    echo json_encode(["error" => "Form not found"]);
    exit;
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
    [$formId], __FILE__, __LINE__
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
    if($field["field_type"] === "user" && $field["default_value"] !== null){
        $userKey[] = $field["default_value"];
    }
    if($field["field_type"] === "users" && $field["default_value"] !== null){
        array_push($userKey, ...json_decode($field["default_value"], true));
    }
}
unset($field);
$userKey = array_values(array_unique($userKey));
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
        if($field["field_type"] === "user" && $field["default_value"] !== null){
            $value = $field["default_value"];
            if(!array_key_exists($value, $userMap)) $field["default_value"] = null;
            else $field["default_value"] = ["value" => $value, "label" => $userMap[$value]];
        }
        if($field["field_type"] === "users" && $field["default_value"] !== null){
            $values = json_decode($field["default_value"], true);
            $filtered = array_filter($values, fn($v) => array_key_exists($v, $userMap));
            $values = array_map(function (string $value): array {
                global $userMap;
                return ["value" => $value, "label" => $userMap[$value]];
            }, $filtered);
            $field["default_value"] = array_values($values);
        }
        if($field["field_type"] == "picture" && $field["default_value"] !== null) {
            $id = $field["default_value"];
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
    unset($field);
}
echo json_encode([
    "form" => $form,
    "fields" => $fields
]);
