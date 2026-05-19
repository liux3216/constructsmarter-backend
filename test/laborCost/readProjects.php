<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$rows = $db->all(
    "SELECT
        CAST(`p`.`id` AS CHAR) AS `id`,
        COALESCE(`p`.`projectNumber`, '') AS `projectNumber`,
        CONCAT_WS(' - ',
            NULLIF(TRIM(`p`.`projectNumber`), ''),
            NULLIF(TRIM(`org`.`name`), ''),
            NULLIF(TRIM(`p`.`clientProjectNumber`), '')
        ) AS `projectName`,
        COALESCE(`p`.`projectManagerId`, '') AS `projectManagerId`,
        CONCAT_WS(' ', `pm`.`firstName`, `pm`.`middleName`, `pm`.`lastName`) AS `projectManagerName`,
        COALESCE(`p`.`pipeline`, '') AS `pipeline`,
        COALESCE(`p`.`subPipeline`, '') AS `subPipeline`
     FROM `projects` `p`
     LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
     LEFT JOIN `users` `pm` ON `pm`.`id` = `p`.`projectManagerId`
     WHERE `p`.`void` = 'no'
     ORDER BY `p`.`projectNumber`, `p`.`id`;
    ",
    [],
    __FILE__,
    __LINE__
);

exit(json_encode($rows));
