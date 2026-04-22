<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
//authorization: todo
/* ---------- params ---------- */
$page = array_key_exists("page", $_POST) ? (int)$_POST["page"] : 1;
$limit = array_key_exists("limit", $_POST) ? (int)$_POST["limit"] : 10;
if ($page < 1)  $page = 1;
if ($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;
/* ---------- search builder ---------- */
$search = new SearchHelper("projects");
$search->equals("organizationId", requireInt($_POST, "organizationId", null, null, false));
$contactId = requireInt($_POST, "contactId", null, null, false);
if ($contactId !== null) {
    $search->raw(
        "EXISTS (SELECT 1 FROM `projects_contact` WHERE `projects_contact`.`projectId` = `projects`.`id` AND `projects_contact`.`contactId` = ?)",
        [$contactId]
    );
}
if (!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if ($_POST["void"] !== "all") $search->equals("void", $_POST["void"]);
$likeFields = [
    "projectNumber",
    "clientProjectNumber",
    "location",
    "nearestMedicalFacility",
    "usaTicketNumber",
    "clientPONumber",
    "description",
    "notes",
];
foreach ($likeFields as $field) {
    $search->like($field, $_POST[$field] ?? null);
}
// joined table LIKE fields (handled via raw)
$joinedLikeFields = [
    "organizationName" => "`organizations`.`name`",
    "projectManager"   => "CONCAT_WS(' ', `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`)",
    "opportunityName"  => "`opportunities`.`opportunityName`",
    "requestor"        => "CONCAT_WS(' ', `u_req`.`firstName`, `u_req`.`middleName`, `u_req`.`lastName`)",
    "creator"          => "CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`)",
    "updater"          => "CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`)",
];
foreach ($joinedLikeFields as $key => $expr) {
    $val = $_POST[$key] ?? null;
    if ($val !== null && $val !== "") {
        $search->raw("$expr LIKE ?", ["%$val%"]);
    }
}
// exact equal fields
$equalFields = [
    "pipeline",
    "subPipeline",
    "stage",
    "reportNeeded",
    "prevailing",
    "cpr",
    "region",
    "billingType",
    "accurateTime",
    "clientSignatureRequired",
    "sendToClient",
];
foreach ($equalFields as $field) {
    $search->equals($field, $_POST[$field] ?? null);
}
// date/number range fields
$betweenDateFields = [
    "usaTicketDate",
];
$betweenDateTimeFields = [
    "projectCreationDate" => "createdAt",
    "projectDateUpdated"  => "updatedAt",
];
$betweenNumberFields = [
    "days",
    "laborHours",
    "materialCost",
];
foreach ($betweenDateFields as $field) {
    $search->between($field, "date");
}
foreach ($betweenDateTimeFields as $postKey => $dbCol) {
    $search->between($dbCol, "datetime", $postKey);
}
foreach ($betweenNumberFields as $field) {
    $search->between($field, "number");
}
/* ---------- fav filter ---------- */
if (array_key_exists("fav", $_POST) && $_POST["fav"] === "1"){
    $userRow = $db->one("SELECT `fav` FROM `users` WHERE `id` = ?;", [$userId], __FILE__,  __LINE__);
    $favJson = $userRow["fav"] ?? "[]";
    $favData = json_decode($favJson, true);
    $favProjectIds = [];
    if (is_array($favData) && isset($favData["projects"]) && is_array($favData["projects"])) {
        $favProjectIds = array_values(array_filter(
            array_map("intval", $favData["projects"]),
            fn($id) => $id > 0
        ));
    }
    if (count($favProjectIds) === 0) {
        $search->raw("1 = 0");
    } else {
        $placeholders = implode(", ", array_fill(0, count($favProjectIds), "?"));
        $search->raw("`projects`.`id` IN ($placeholders)", $favProjectIds);
    }
}

$whereSql = $search->getWhereSql();
$params = $search->getParams();
/* ---------- count ---------- */
$countSql = "SELECT COUNT(*) AS `total` FROM `projects` $whereSql;";
$totalRow = $db->one($countSql, $params, __FILE__, __LINE__);
$total = (int)($totalRow["total"] ?? 0);
/* ---------- page overflow guard ---------- */
$maxPage = max(1, (int)ceil($total / $limit));
if ($page > $maxPage) {
  $page = $maxPage;
  $offset = ($page - 1) * $limit;
}
/* ---------- data ---------- */
$sql = "SELECT 
`projects`.`id`, 
CONCAT_WS(\" - \", `projects`.`projectNumber`, `organizations`.`name`, `projects`.`clientProjectNumber`) AS `projectName`, 
`projects`.`projectManagerId`, 
CONCAT_WS(\" \", `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`) AS `projectManagerName`, 
`projects`.`pipeline`, 
`projects`.`stage`, 
`projects`.`prevailing`, 
`projects`.`cpr`, 
`projects`.`dirNumber`, 
`projects`.`usaTicketExpirationDate`
FROM `projects`
LEFT JOIN `users` `u1` ON `u1`.`id` = `projects`.`creatorId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `projects`.`updaterId`
LEFT JOIN `users` `u3` ON `u3`.`id` = `projects`.`projectManagerId`
LEFT JOIN `organizations` ON `organizations`.`id` = `projects`.`organizationId`
$whereSql ORDER BY `projects`.`createdAt` DESC LIMIT $limit OFFSET $offset;";
$projects = $db->all($sql, $params, __FILE__, __LINE__);
/* ---------- response ---------- */
exit(json_encode([
    "projects" => $projects,
    "page"  => $page,
    "limit" => $limit,
    "total" => $total
]));