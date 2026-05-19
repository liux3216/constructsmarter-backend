<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//------------------------------------------------------
$reports = $db->all(
    "SELECT `r`.`id` AS `value`, 
    CONCAT_WS(' - ', NULLIF(TRIM(`p`.`projectNumber`), ''), NULLIF(TRIM(`org`.`name`), ''), NULLIF(TRIM(`p`.`clientProjectNumber`), '')) AS `label`,
    `r`.`startDate`, 
    `r`.`endDate`
    FROM `reports` `r`
    LEFT JOIN `projects` `p` ON `p`.`id` = `r`.`projectId`
    LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
    WHERE `r`.`void` = '' AND `r`.`reportTechId` = ? ORDER BY `label`;",
    [$userId],
    __FILE__,
    __LINE__
);
exit(json_encode($reports));
