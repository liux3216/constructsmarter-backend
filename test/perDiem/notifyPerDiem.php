<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php";
require_once "/opt/bitnami/apache/htdocs/test/constants.php";
require_once __DIR__."/helpers.php";
$id = perDiemRequirePost("id");
$access = getPerDiemAccess($db, $userId);
[$scopeSql, $scopeParams] = perDiemScope("p", $userId, $access);
$row = $db->one(
    "SELECT `p`.`status`, `p`.`startDate`, `p`.`endDate`,
    CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`,
    `u1`.`email` AS `requesterEmail`, `u2`.`email` AS `approverEmail`
    FROM `perDiems` `p`
    LEFT JOIN `users` `u1` ON `u1`.`id` = `p`.`requesterId`
    LEFT JOIN `users` `u2` ON `u2`.`id` = `p`.`approverId`
    WHERE `p`.`id` = ? AND $scopeSql;",
    array_merge([$id], $scopeParams),
    __FILE__,
    __LINE__
);
if(!$row){
    http_response_code(400);
    exit(json_encode(["msg" => "The per diem form is not found."]));
}
if(!in_array($row["status"], ["Approved", "Rejected"], true)){
    $requestDates = $row["startDate"] === $row["endDate"] ? $row["startDate"] : "from {$row["startDate"]} to {$row["endDate"]}";
    sendEmail([
        "path" => basename(__FILE__)." ".__LINE__,
        "selfEmail" => $email,
        "db" => $db,
        "to" => $row["approverEmail"],
        "cc" => [$row["requesterEmail"], $email],
        "summary" => "Per Diem Form Review",
        "body" => "&nbsp;&nbsp;&nbsp;&nbsp;$userName notified you about a per diem form, Please review and provide your decision below:<br><br>&nbsp;&nbsp;&nbsp;&nbsp;<a href = \"$mainUrl/PerDiem/$id\">{$row["requesterName"]} $requestDates</a>"
    ]);
}
$db->exec("UPDATE `perDiems` SET `notifiedBy` = ?, `notifiedAt` = ? WHERE `id` = ?;", [$userId, date("Y-m-d H:i:s"), $id], __FILE__, __LINE__);
exit(json_encode(["id" => (int)$id]));
