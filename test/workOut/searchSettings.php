<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$query = $_POST['q'] ?? '';
if (!is_string($query) || strlen($query) > 200) {
    http_response_code(422);
    exit(json_encode(['msg' => 'Enter an exercise name up to 200 characters.']));
}
$query = trim($query);
if ($query === '') exit(json_encode([]));
// Search literal text, including exercise names containing SQL wildcard characters.
$pattern = '%' . str_replace(['=', '%', '_'], ['==', '=%', '=_'], $query) . '%';
$rows = $db->all(
    "SELECT `id` AS `value`, `name` AS `label` FROM `workOutSettings` WHERE `userId` = ? AND `name` LIKE ? ESCAPE '=' ORDER BY `name`, `id` LIMIT 50;",
    [$userId, $pattern], __FILE__, __LINE__
);
foreach ($rows as &$row) $row['value'] = (string)$row['value'];
unset($row);
exit(json_encode($rows));
