<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$formId = isset($_POST["formId"]) ? (int)$_POST["formId"] : 0;
$page = isset($_POST["page"]) ? max(1, (int)$_POST["page"]) : 1;
$limit = isset($_POST["limit"]) ? max(1, (int)$_POST["limit"])  : 10;
if ($formId <= 0) {
    http_response_code(407);
    exit(json_encode(["msg" => "Invalid formId"]));
}
$offset = ($page - 1) * $limit;
$form = $db->one(
    "SELECT `id`, `name`, `type` FROM `forms` WHERE `id` = ?;",
    [$formId]
);
if (!$form) {
    http_response_code(404);
    exit(json_encode(["msg" => "Form not found"]));
}
$totalRow = $db->one("SELECT COUNT(*) AS `total` FROM `form_records` WHERE `form_id` = ?;", [$formId]);
$total = (int)$totalRow["total"];
$items = $db->all(
    "SELECT
        `id`,
        `name`,
        `createdAt`,
        `updatedAt`
     FROM `form_records`
     WHERE `form_id` = ?
     ORDER BY `createdAt` DESC
     LIMIT ? OFFSET ?;",
    [$formId, $limit, $offset], __FILE__, __LINE__
);
exit(json_encode([
    "items" => $items,
    "total" => $total, 
    "formName" => $form["name"], 
    "formType" => $form["type"]
]));
