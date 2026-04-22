<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";
/* ---------- pagination ---------- */
$page  = max(1, (int)($_POST["page"]  ?? 1));
$limit = (int)($_POST["limit"] ?? 10);
$limit = ($limit < 1 || $limit > 100) ? 10 : $limit;
$offset = ($page - 1) * $limit;
/* ---------- where builder ---------- */
$search = new SearchHelper("users");
$likeFields = [
    "email", "role", "phoneNumber", "workphone", "extension",
    "driverLicense", "ssn", "phaseLevel", "unionName",
    "invoiceNumber", "lanId", "residence", "residenceState",
    "street", "zipCode", "address", "background", "voidReason", "validateReason"
];
$equalFields = [
    "region", "department",
    "projects", "workLogs", "purchases", "PerDiem", "reports", "forms",
    "personel", "fleets", "calendar", "timeOffs", "office", "allOffice",
    "outside", "outsideStatus", "metrics", "newspaper", "community",
    "training", "workOut", "workLogNotification", "dispatch",
];
$betweenDateFields = ["birthDay", "hireDate", "quitDate"];
if (!array_key_exists("void", $_POST)) $search->equals("void", "no");
else if ($_POST["void"] !== "all")     $search->equals("void", $_POST["void"]);
// "name" searches across all three name columns
$search->when(
    array_key_exists("name", $_POST),
    fn($q) => $q->raw(
        "CONCAT_WS(' ', `users`.`firstName`, `users`.`middleName`, `users`.`lastName`) LIKE ?",
        ["%" . $_POST["name"] . "%"]
    )
);
foreach ($likeFields as $field) {
    $search->like($field, $_POST[$field] ?? null);
}
foreach ($equalFields as $field) {
    $search->equals($field, $_POST[$field] ?? null);
}
foreach ($betweenDateFields as $field) {
    $search->between($field, "datetime");
}
$whereSql = $search->getWhereSql();
$params   = $search->getParams();
/* ---------- total ---------- */
$total = (int)($db->one(
    "SELECT COUNT(*) AS `total` FROM `users` $whereSql;",
    $params
)["total"] ?? 0);
$maxPage = max(1, ceil($total / $limit));
$page = min($page, $maxPage);
$offset = ($page - 1) * $limit;
/* ---------- data ---------- */
$users = $db->all(
    "SELECT 
        CONCAT_WS(' ', `users`.`firstName`, `users`.`middleName`, `users`.`lastName`) AS `userName`,
        `users`.`email`, `users`.`workPhone`, `users`.`phoneNumber`, 
        `users`.`dispatch`, `users`.`region`, `users`.`department`,
        `users`.`role`, `users`.`version`,`users`.`quitDate`, `users`.`id`, 
        `fileInfo`.`name` AS `profileFileName`,
        IF(`profileId` IS NOT NULL,
           CONCAT('https://$publicBucket.s3.us-west-1.amazonaws.com/', `profileId`),
           ''
        ) AS `profileUrl`,
        CASE WHEN `users`.`mvrId` <> '' THEN 'yes' ELSE '' END AS `hasMVR`, 
        `verificationCode`
     FROM `users`
     LEFT JOIN `fileInfo` ON `users`.`profileId` = `fileInfo`.`id`
     $whereSql
     ORDER BY `users`.`firstName`
     LIMIT $limit OFFSET $offset;",
    $params
);

exit(json_encode([
    "users" => $users,
    "page"  => $page,
    "limit" => $limit,
    "total" => $total
]));
