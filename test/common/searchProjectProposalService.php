<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = trim((string)($_POST["q"] ?? ""));
$projectId = requireInt($_POST, "projectId", 1, null, true);
if(!$projectId){
    exit(json_encode([]));
}
$project = $db->one("SELECT `proposalId` FROM `projects` WHERE `id` = ?;", [$projectId], __FILE__, __LINE__);
$proposalId = (int)($project["proposalId"] ?? 0);
if($proposalId <= 0){
    exit(json_encode([]));
}
$proposal = $db->one("SELECT `data` FROM `proposals` WHERE `id` = ? AND `void` = 'no';", [$proposalId], __FILE__, __LINE__);
if(!$proposal){
    exit(json_encode([]));
}
$lines = json_decode((string)($proposal["data"] ?? "[]"), true);
if(!is_array($lines)) $lines = [];
$serviceIds = [];
foreach($lines as $line){
    if(!is_array($line)) continue;
    $serviceId = (int)($line["serviceId"] ?? 0);
    if($serviceId > 0) $serviceIds[$serviceId] = true;
}
$serviceIds = array_keys($serviceIds);
if(!count($serviceIds)){
    exit(json_encode([]));
}
$placeholders = implode(", ", array_fill(0, count($serviceIds), "?"));
$params = $serviceIds;
$sql = "SELECT `id` AS `value`, CONCAT_WS(' - ', `code`, `name`) AS `label`
    FROM `services`
    WHERE `void` = 'no' AND `id` IN ($placeholders)";
if($q !== ""){
    $sql .= " AND CONCAT_WS(' - ', `code`, `name`, `category`) LIKE ?";
    $params[] = "%{$q}%";
}
$sql .= " ORDER BY `name` ASC LIMIT 20;";
$rows = $db->all($sql, $params, __FILE__, __LINE__);
exit(json_encode($rows));
