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
$search = new SearchHelper("contacts");
$likeFields = ["phoneNumber", "extension", "fax", "directNumber", "background", "overseaAddress", "role", "voidReason", "validateReason"];
$equalFields = ["creatorId", "updaterId"];
$betweenDateTimeFields = ["createdAt", "updatedAt"];
$search->equals("organizationId", requireInt($_POST, "organizationId", null, null, false));
$search->when(
    array_key_exists("address", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `contacts`.`street`, `contacts`.`city`, `contacts`.`state`, `contacts`.`zipCode`) LIKE ?",
        ["%" . $_POST["address"] . "%"]
    )
);
$search->when(
    array_key_exists("name", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `contacts`.`firstName`, `contacts`.`middleName`, `contacts`.`lastName`) LIKE ?",
        ["%".$_POST["name"]."%"]
    )
);
$search->when(
    array_key_exists("email", $_POST),
    fn($q) => $q->raw(
        "(`contacts`.`email1` LIKE ? OR `contacts`.`email2` LIKE ?)",
        ["%".$_POST["email"]."%", "%".$_POST["email"]."%"]
    )
);
$search->when(
    array_key_exists("noOrganizationAssociated", $_POST) && $_POST["noOrganizationAssociated"] === "1", 
    fn($q) => $q->raw(
        "`contacts`.`organizationId` IS NULL",
        []
    )
);
if(!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if($_POST["void"] !== "all") $search->equals("void", $_POST["void"]);
foreach($likeFields as $field){
    $search->like($field, $_POST[$field] ?? null);
}
foreach($equalFields as $field){
    $search->equals($field, $_POST[$field] ?? null);
}
foreach($betweenDateTimeFields as $field){
    $search->between($field, "datetime");
}
$whereSql = $search->getWhereSql();
$params   = $search->getParams();
/* ---------- count ---------- */
$countSql = "SELECT COUNT(*) AS `total` FROM `contacts` $whereSql;";
$totalRow = $db->one($countSql, $params, __FILE__, __LINE__);
$total = (int)($totalRow["total"] ?? 0);
/* ---------- page overflow guard ---------- */
$maxPage = max(1, (int)ceil($total / $limit));
if ($page > $maxPage) {
  $page = $maxPage;
  $offset = ($page - 1) * $limit;
}
/* ---------- data ---------- */
$contacts = $db->all(
    "SELECT 
    `contacts`.`id`, 
    CONCAT_WS(\" \", `contacts`.`firstName`, `contacts`.`middleName`, `contacts`.`lastName`) AS `name`, 
    `contacts`.`email1`, 
    `contacts`.`email2`, 
    `contacts`.`directNumber`, 
    `contacts`.`phoneNumber`, 
    `contacts`.`extension`, 
    `contacts`.`fax`,
    `contacts`.`role`,
    `contacts`.`organizationId`,
    `organizations`.`name` AS `organizationName`
    FROM `contacts`
    LEFT JOIN `organizations` ON `organizations`.`id` = `contacts`.`organizationId`
    $whereSql ORDER BY `contacts`.`createdAt` DESC LIMIT $limit OFFSET $offset;", 
    $params, __FILE__, __LINE__
);
/* ---------- response ---------- */
exit(json_encode([
    "contacts" => $contacts,
    "page"  => $page,
    "limit" => $limit,
    "total" => $total
]));