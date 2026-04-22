<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = requireField($_POST, "q", 0, "max", true);
if (!$q) exit(json_encode([]));
$form_id = requireInt($_POST, "form_id", null, null, true);
$rows = $db->all(
    "SELECT `id` AS `value`, `name` AS `label`
     FROM `form_records`
     WHERE 
     `creatorId` = ?
     AND `form_id` = ?
     AND `status` = \"active\"
     AND `name` LIKE ?
     ORDER BY `name`
     LIMIT 20;",
    [$userId, $form_id, "%{$q}%"]
);
exit(json_encode($rows));