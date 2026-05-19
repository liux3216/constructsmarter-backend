<?php
require_once __DIR__ . "/_billings.php";

$rows = $db->all(
    "SELECT
        CAST(`p`.`id` AS CHAR) AS `projectId`,
        CONCAT_WS(' - ',
            NULLIF(TRIM(`p`.`projectNumber`), ''),
            NULLIF(TRIM(`org`.`name`), ''),
            NULLIF(TRIM(`p`.`clientProjectNumber`), '')
        ) AS `projectName`
     FROM `projects` `p`
     LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
     WHERE `p`.`void` = 'no'
     ORDER BY `p`.`projectNumber`, `org`.`name`, `p`.`clientProjectNumber`;",
    [],
    __FILE__,
    __LINE__
);

exit(json_encode($rows));
