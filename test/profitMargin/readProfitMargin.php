<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$projectId = trim((string)($_POST['projectId'] ?? ''));
$selectedProjectId = $projectId !== '' ? (int)$projectId : 0;
$selectedProjectManagerId = trim((string)($_POST['projectManagerId'] ?? ''));
$summaryStart = trim((string)($_POST['summaryStart'] ?? ''));
$summaryEnd = trim((string)($_POST['summaryEnd'] ?? ''));
$oh = trim((string)($_POST['oh'] ?? '1.54'));
$projectLabel = "CONCAT_WS(' - ', NULLIF(TRIM(`p`.`projectNumber`), ''), NULLIF(TRIM(`o`.`name`), ''), NULLIF(TRIM(`p`.`clientProjectNumber`), ''))";

$projects = $db->all(
    "SELECT
        `p`.`id` AS `value`,
        $projectLabel AS `label`,
        `p`.`budgets`,
        `p`.`projectManagerId`,
        CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `projectManagerName`,
        DATE(`p`.`createdAt`) AS `projectCreationDate`
     FROM `projects` `p`
     LEFT JOIN `organizations` `o` ON `o`.`id` = `p`.`organizationId`
     LEFT JOIN `users` `u` ON `u`.`id` = `p`.`projectManagerId`
     WHERE `p`.`void` = 'no'
     ORDER BY `label` ASC",
    [],
    __FILE__,
    __LINE__
) ?: [];

$managers = $db->all(
    "SELECT DISTINCT
        `u`.`id` AS `value`,
        CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `label`
     FROM `projects` `p`
     JOIN `users` `u` ON `u`.`id` = `p`.`projectManagerId`
     WHERE `p`.`void` = 'no' AND `p`.`projectManagerId` IS NOT NULL AND `p`.`projectManagerId` <> ''
     ORDER BY `label` ASC",
    [],
    __FILE__,
    __LINE__
) ?: [];

$laborCosts = $db->all(
    "SELECT `week`, CAST(`projectId` AS CHAR) AS `projectId`, CAST(`amount` AS DECIMAL(12,2)) AS `amount`
     FROM `labor_costs`
     ORDER BY `week` ASC, `projectId` ASC",
    [],
    __FILE__,
    __LINE__
) ?: [];

$importedBillings = $db->all(
    "SELECT `week`, CAST(`projectId` AS CHAR) AS `projectId`, CAST(SUM(`amount`) AS DECIMAL(12,2)) AS `amount`
     FROM `imported_billings`
     GROUP BY `week`, `projectId`
     ORDER BY `week` ASC, `projectId` ASC",
    [],
    __FILE__,
    __LINE__
) ?: [];

$purchases = [];
if ($selectedProjectId > 0) {
    $purchases = $db->all(
        "SELECT `poDate`, CAST(`total` AS DECIMAL(12,2)) AS `total`, LOWER(`billable`) AS `billable`, LOWER(`includedInProposal`) AS `includedInProposal`
         FROM `purchases`
         WHERE `void` = 'no' AND `projectId` = ?
         ORDER BY `poDate` ASC, `createdAt` ASC",
        [$selectedProjectId],
        __FILE__,
        __LINE__
    ) ?: [];
}

exit(json_encode([
    'projects' => $projects,
    'managers' => $managers,
    'laborCosts' => $laborCosts,
    'importedBillings' => $importedBillings,
    'purchases' => $purchases,
    'selectedProjectId' => $selectedProjectId ? (string)$selectedProjectId : '',
    'selectedProjectManagerId' => $selectedProjectManagerId,
    'summaryStart' => $summaryStart,
    'summaryEnd' => $summaryEnd,
    'oh' => $oh,
]));
