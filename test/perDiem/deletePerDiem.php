<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php";
require_once "/opt/bitnami/apache/htdocs/test/constants.php";
require_once __DIR__."/helpers.php";
$id = perDiemRequirePost("id");
$access = getPerDiemAccess($db, $userId);
$row = $db->one(
    "SELECT `requesterId`, `creatorId`, `approverId`, `updaterId`, `startDate`, `endDate`,
    `u1`.`email` AS `requesterEmail`,
    `u2`.`email` AS `creatorEmail`,
    `u3`.`email` AS `approverEmail`,
    `u4`.`email` AS `updaterEmail`,
    CONCAT_WS(\" \", `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`
    FROM `perDiems` `p`
    LEFT JOIN `users` `u1` ON `u1`.`id` = `p`.`requesterId`
    LEFT JOIN `users` `u2` ON `u2`.`id` = `p`.`creatorId`
    LEFT JOIN `users` `u3` ON `u3`.`id` = `p`.`approverId`
    LEFT JOIN `users` `u4` ON `u4`.`id` = `p`.`updaterId`
    WHERE `p`.`id` = ?;",
    [$id],
    __FILE__,
    __LINE__
);
if(!$row || !($access === "editAll" || in_array($userId, [$row["requesterId"], $row["creatorId"]], true))){
    http_response_code(403);
    exit(json_encode(["msg" => "You are not allowed to delete this per diem form."]));
}
$db->exec("DELETE FROM `perDiems` WHERE `id` = ?;", [$id], __FILE__, __LINE__);
$requestDates = $row["startDate"] === $row["endDate"] ? $row["startDate"] : "from {$row["startDate"]} to {$row["endDate"]}";
$emails = array_values(array_unique(array_filter([$row["approverEmail"], $row["requesterEmail"], $row["creatorEmail"], $row["updaterEmail"]])));
sendEmail([
    "path" => basename(__FILE__)." ".__LINE__,
    "selfEmail" => $email,
    "db" => $db,
    "to" => $emails,
    "summary" => "Per Diem Form Deletion",
    "body" => "&nbsp;&nbsp;&nbsp;&nbsp;$userName deleted a per diem form.<br>&nbsp;&nbsp;&nbsp;&nbsp;<a href = \"$mainUrl/PerDiem/$id\">{$row["requesterName"]} $requestDates</a>"
]);
exit(json_encode(["id" => (int)$id]));
