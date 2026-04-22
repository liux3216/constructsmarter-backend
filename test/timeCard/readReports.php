<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//------------------------------------------------------
$reports = $db->all(
    "SELECT `r`.`id` AS `value`, 
    CONCAT(
    `p`.`projectNumber`, \" - \",`org`.`name`,\" - \", `p`.`clientProjectNumber`
    ) AS `label`,
    `r`.`startDate`, 
    `r`.`endDate`
    FROM `reports` `r`
    LEFT JOIN `projects` `p` ON `p`.`id` = `r`.`projectId`
    LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
    WHERE `r`.`void` = \"\" AND `reportTechId` = \"$userId\" ORDER BY `label`;"
);
exit(json_encode($reports));