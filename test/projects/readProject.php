<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

$id = trim((string)($_POST["id"] ?? $_POST["projectHashKey"] ?? ""));
if ($id === "") {
    http_response_code(400);
    exit(json_encode(["msg" => "Missing project id."]));
}

/*
 * This version targets the newer id-based tables used by the current
 * organizations / contacts / opportunities modules and aliases the result
 * back into the legacy field names that the projects frontend still expects.
 *
 * If your related tables still use hash-key columns, swap these joins:
 * - organizations.id        -> organizations.organizationHashKey
 * - contacts.id             -> contacts.contactHashKey
 * - opportunities.id        -> opportunities.opportunityHashKey
 */
$sql = "SELECT
`p`.`id`,
`p`.`id` AS `projectHashKey`,
CONCAT_WS(' - ',
    NULLIF(TRIM(`p`.`projectNumber`), ''),
    NULLIF(TRIM(`org`.`name`), ''),
    NULLIF(TRIM(`p`.`clientProjectNumber`), '')
) AS `projectName`,
`p`.`projectNumber`,
`p`.`organizationId`,
`org`.`name` AS `organizationName`,
`p`.`clientProjectNumber`,
CONCAT_WS(' ', `pm`.`firstName`, `pm`.`middleName`, `pm`.`lastName`) AS `projectManagerName`,
`pm`.`id` AS `projectManagerId`,
`p`.`projectManagerId`,
`p`.`pipeline`,
`p`.`subPipeline`,
`p`.`stage`,
`p`.`reportNeeded`,
`p`.`prevailing`,
`p`.`cpr`,
`p`.`dirNumber`,
`p`.`location`,
`p`.`coords`,
`p`.`nearestMedicalFacility`,
`p`.`opportunityId`,
`opp`.`opportunityName`,
`p`.`clientPONumber`,
`p`.`region`,
`p`.`billingType`,
`p`.`rate`,
`p`.`days`,
`p`.`laborHours`,
`p`.`materialCost`,
`p`.`budget`,
`p`.`budgets`,
`p`.`profit`, 
`p`.`description`,
`p`.`notes`,
`p`.`accurateTime`,
`p`.`clientSignatureRequired`,
`p`.`sendToClient`,
`p`.`usaTicketNumber`,
`p`.`usaTicketDate`,
`p`.`createdAt`,
`p`.`updatedAt`,
CONCAT_WS(' ', `creatorUser`.`firstName`, `creatorUser`.`middleName`, `creatorUser`.`lastName`) AS `creatorName`,
`p`.`creatorId`,
CONCAT_WS(' ', `updaterUser`.`firstName`, `updaterUser`.`middleName`, `updaterUser`.`lastName`) AS `updaterName`,
`p`.`updaterId`,
CONCAT_WS(' ', `statusUser`.`firstName`, `statusUser`.`middleName`, `statusUser`.`lastName`) AS `statusChangerName`,
`p`.`statusChangerId`,
`p`.`statusChangeDateTime`, 
`p`.`reportFileId`,
`p`.`reportCreatedAt`,
`p`.`reportTechId`,
`p`.`cctv`,
`p`.`KMLId`
FROM `projects` `p`
LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
LEFT JOIN `opportunities` `opp` ON `opp`.`id` = `p`.`opportunityId`
LEFT JOIN `users` `pm` ON `pm`.`id` = `p`.`projectManagerId`
LEFT JOIN `users` `creatorUser` ON `creatorUser`.`id` = `p`.`creatorId`
LEFT JOIN `users` `updaterUser` ON `updaterUser`.`id` = `p`.`updaterId`
LEFT JOIN `users` `statusUser` ON `statusUser`.`id` = `p`.`statusChangerId`
WHERE `p`.`id` = ?;";

$row = $db->one($sql, [$id], __FILE__, __LINE__);

if($row["budgets"] !== null) $row["budgets"] = json_decode($row["budgets"], true);

$contacts = $db->all(
    "SELECT `contactId` AS `value`, CONCAT_WS(\" \", `contacts`.`firstName`, `contacts`.`middleName`, `contacts`.`lastName`) AS `label`
    FROM `projects_contact` 
    LEFT JOIN `contacts` ON `contacts`.`id` = `projects_contact`.`contactId`
    WHERE `projectId` = ?",
    [$id],
    __FILE__, __LINE__
);
$row['contacts'] = $contacts;
if(!$row){
    http_response_code(400);
    exit(json_encode(["msg" => "The project is not found."]));
}
exit(json_encode($row));