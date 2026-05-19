<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
$requesterId = perDiemRequirePost("requesterId");
$rows = $db->all(
    "SELECT `p`.`id`, `p`.`startDate`, `p`.`endDate`, CONCAT_WS(\" - \", `pr`.`projectNumber`, `o`.`name`, `pr`.`clientProjectNumber`) AS `projectName`
    FROM `perDiems` `p`
    LEFT JOIN `projects` `pr` ON `pr`.`id` = `p`.`projectId`
    LEFT JOIN `organizations` `o` ON `o`.`id` = `pr`.`organizationId`
    WHERE `p`.`requesterId` = ? AND `p`.`void` = 'no'
    ORDER BY `p`.`startDate`;",
    [$requesterId],
    __FILE__,
    __LINE__
);
exit(json_encode($rows));
