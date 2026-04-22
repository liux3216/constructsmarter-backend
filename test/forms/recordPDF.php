<?php
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";
use Dompdf\Dompdf;
use Dompdf\Options;
function generateRecordPdf(int $formId, string $recordName, int $recordId, string $uuid = null): string {
    global $db, $userId;
    $form = getForm($formId);
    $fields = getFormFields($formId);
    $values = getFormValuesIndexed($recordId);
    $hydrated = hydrateFieldsForPdf($fields, $values);
    $options = new Options();
    $options->set("defaultFont", "DejaVu Sans");
    $options->set("isRemoteEnabled", true);
    $dompdf = new Dompdf($options);
    ob_start();
    $data = $hydrated;
    include "recordPDF.tpl.php";
    $html = ob_get_clean();
    $dompdf->loadHtml($html);
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();
    $output = $dompdf->output();
    $size = strlen($output);
    if ($uuid === null) {
        $uuid = md5(rand());
        uploadFileWithBody($privateBucket, $uuid, $output, "application/pdf");
        $db->exec(
            "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?);",
            [$uuid, "record_$recordId", "application/pdf", $size,
             "c316df1c2bbdbcc578d0a5ef5a83a5e7", $userId, "uploaded"],
            __FILE__, __LINE__
        );
    } else {
        uploadFileWithBody($privateBucket, $uuid, $output, "application/pdf");
        $db->exec("UPDATE `fileInfo` SET `size` = ? WHERE `id` = ?;", [$size, $uuid]);
    }
    return $uuid;
}
function hydrateFieldsForPdf(array $fields, array $values): array {
    $result = [];
    foreach ($fields as $f) {
        $key = $f["field_key"];
        $type = $f["field_type"];
        if ($type === "sub_form") {
            $ids = json_decode($values[$key] ?? "[]", true);
            $result[] = [
                "type" => "sub_form",
                "label" => $f["label"],
                "records" => hydrateSubForm($f["sub_form_id"], (array)$ids)
            ];
            continue;
        }
        $result[] = [
            "type" => $type,
            "label" => $f["label"],
            "value" => normalizePdfValue($type, $values[$key] ?? null)
        ];
    }
    return $result;
}
function hydrateSubForm(int $formId, array $recordIds): array {
    $records = [];
    $fields = getFormFields($formId);
    foreach ($recordIds as $rid) {
        $values = getFormValuesIndexed($rid);
        $records[] = [
            "record_id" => $rid,
            "fields" => hydrateFieldsForPdf($fields, $values)
        ];
    }
    return $records;
}
function normalizePdfValue(string $type, $value): string {
    global $db;
    if ($value === null) return "";
    if (in_array($type, ["checkbox", "multiSelect"], true)) {
        return implode(", ", json_decode($value, true) ?: []);
    }
    if ($type === "users" && $value) {
        $userIds = json_decode($value, true);
        if(count($userIds)){
            $users = $db->all("SELECT CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `name` FROM `users` WHERE `id` IN (".implode(",", array_fill(0, count($userIds), "?")).");", $userIds,__FILE__, __LINE__);
            $userNames = array_map(function(array $item): string | null {return $item["name"];}, $users);
        }else{
            $userNames = [];
        }
        return implode(", ", $userNames);
    }
    if ($type === "user" && $value) {
        $user = $db->one("SELECT CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `name` FROM `users` WHERE `id` = ?;", [$value],__FILE__, __LINE__);
        if(!$user) return "";
        return $user["name"];
    }
    return $value;
}
function getForm(int $formId): array {
    global $db;
    $form = $db->one(
        "SELECT `id`, name
         FROM `forms`
         WHERE id = ? AND status = \"published\";",
        [$formId],
        __FILE__,
        __LINE__
    );

    if (!$form) {
        throw new RuntimeException("Form {$formId} not found or not published");
    }
    return $form;
}
function getFormFields(int $formId): array {
    global $db;
    return $db->all(
        "SELECT `field_key`, `field_type`, `label`, `sub_form_id`
         FROM `form_fields`
         WHERE `form_id` = ?
         ORDER BY `sort_order` ASC;",
        [$formId],
        __FILE__,
        __LINE__
    );
}
function getFormValuesIndexed(int $recordId): array {
    global $db;
    $rows = $db->all(
        "SELECT `field_key`,
                `value_text`,
                `value_number`,
                `value_date`,
                `value_json`
         FROM `form_values`
         WHERE `record_id` = ?;",
        [$recordId],
        __FILE__,
        __LINE__
    );
    $out = [];
    foreach ($rows as $r) {
        if ($r["value_json"] !== null) {
            $out[$r["field_key"]] = $r["value_json"];
        } elseif ($r["value_text"] !== null) {
            $out[$r["field_key"]] = $r["value_text"];
        } elseif ($r["value_number"] !== null) {
            $out[$r["field_key"]] = (string)$r["value_number"];
        } elseif ($r["value_date"] !== null) {
            $out[$r["field_key"]] = $r["value_date"];
        } else {
            $out[$r["field_key"]] = "";
        }
    }
    return $out;
}
function sanitizeHtml(string $html): string {
    static $purifier = null;
    if ($purifier === null) {
        $config = HTMLPurifier_Config::createDefault();
        $config->set("HTML.Allowed", 
            "div[class],b,i,strong,em,u,br,p,ul,ol,li"
        );
        $config->set("Attr.AllowedClasses", [
            "sub-table" => true,
            "figure" => true,
            "figcaption" => true,
            "picture" => true,
            "label" => true,
        ]);
        $config->set("Cache.DefinitionImpl", null);
        $config->set("Cache.SerializerPath",
            "/opt/bitnami/apache/htdocs/cache/htmlpurifier"
        );
        $config->set("CSS.AllowedProperties", []);
        $config->set("AutoFormat.AutoParagraph", false);
        $config->set("AutoFormat.RemoveEmpty", true);
        $purifier = new HTMLPurifier($config);
    }
    return $purifier->purify($html);
}