<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
$id = trim((string)($_POST["id"] ?? ""));
if($id === "") proposalJsonResponse(400, ["msg" => "Missing id."]);
$projectLabel = proposalProjectLabel();
$row = $db->one(
    "SELECT
        `p`.*, $projectLabel AS `projectName`,
        CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`,
        CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `approverName`,
        CONCAT_WS(' ', `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`) AS `creatorName`,
        CONCAT_WS(' ', `u4`.`firstName`, `u4`.`middleName`, `u4`.`lastName`) AS `updaterName`,
        CONCAT_WS(' ', `u5`.`firstName`, `u5`.`middleName`, `u5`.`lastName`) AS `submitterName`,
        CONCAT_WS(' ', `u6`.`firstName`, `u6`.`middleName`, `u6`.`lastName`) AS `notifierName`
    " . proposalFromSql() . " WHERE `p`.`id` = ? LIMIT 1;",
    [$id],
    __FILE__,
    __LINE__
);
if(!$row){
    proposalJsonResponse(404, ["msg" => "Proposal not found."]);
}
proposalHydrateRow($row);
exit(json_encode($row));
