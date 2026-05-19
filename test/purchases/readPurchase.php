<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
$id = trim((string)($_POST["id"] ?? ""));
if($id === "") purchaseJsonResponse(400, ["msg" => "Missing id."]);
$user = $db->one("SELECT `purchases` FROM `users` WHERE `id` = ?;", [$userId], __FILE__, __LINE__);
$access = $user["purchases"] ?? "no";
[$scopeSql, $scopeParams] = purchaseScope("p", $userId, $access);
$projectLabel = purchaseProjectLabel();
$row = $db->one(
    "SELECT
        `p`.*, $projectLabel AS `projectName`,
        CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`,
        CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `approverName`,
        CONCAT_WS(' ', `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`) AS `creatorName`,
        CONCAT_WS(' ', `u4`.`firstName`, `u4`.`middleName`, `u4`.`lastName`) AS `updaterName`,
        CONCAT_WS(' ', `u5`.`firstName`, `u5`.`middleName`, `u5`.`lastName`) AS `submitterName`,
        CONCAT_WS(' ', `u6`.`firstName`, `u6`.`middleName`, `u6`.`lastName`) AS `notifierName`
    " . purchaseFromSql() . " WHERE `p`.`id` = ? AND $scopeSql LIMIT 1;",
    array_merge([$id], $scopeParams),
    __FILE__,
    __LINE__
);
if(!$row){
    purchaseJsonResponse(404, ["msg" => "Purchase not found."]);
}
purchaseHydrateRow($row);
exit(json_encode($row));
