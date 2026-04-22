<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$q = requireField($_POST, "q", 0, "max", true);
if (!$q) exit(json_encode([]));
$rows = $db->all(
"SELECT 
`projects`.`id` AS `value`, 
CONCAT_WS(\" - \", `projects`.`projectNumber`, `organizations`.`name`, `projects`.`clientProjectNumber`) AS `label`, 
`projects`.`organizationId`, 
`projects`.`location`, 
`projects`.`coords`
FROM `projects`
LEFT JOIN `organizations` ON `organizations`.`id` = `projects`.`organizationId`
WHERE CONCAT_WS(\" - \", `projects`.`projectNumber`, `organizations`.`name`, `projects`.`clientProjectNumber`) LIKE ?
ORDER BY `projects`.`createdAt`  LIMIT 20;",  ["%{$q}%"], __FILE__, __LINE__);
exit(json_encode($rows));