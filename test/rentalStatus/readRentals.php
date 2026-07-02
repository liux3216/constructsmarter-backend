<?php
require_once __DIR__ . '/helpers.php';
$page = max(1, (int)($_POST['page'] ?? 1));
$limit = (int)($_POST['limit'] ?? 10);
$limit = ($limit < 1 || $limit > 100) ? 10 : $limit;
$offset = ($page - 1) * $limit;
$status = trim((string)($_POST['status'] ?? 'all'));
$startFrom = trim((string)($_POST['rentalStartDateFrom'] ?? ''));
$startTo = trim((string)($_POST['rentalStartDateTo'] ?? ''));
if ($startFrom === '' && $startTo === '') {
    $startFrom = date('Y-m-01');
    $startTo = date('Y-m-d');
}
$where = ['`r`.`void` = "no"'];
$params = [];
if (in_array($status, ['rented', 'returned'], true)) {
    $where[] = '`r`.`status` = ?';
    $params[] = $status;
}
if ($startFrom !== '') {
    $where[] = '`r`.`rentalStartDate` >= ?';
    $params[] = $startFrom;
}
if ($startTo !== '') {
    $where[] = '`r`.`rentalStartDate` <= ?';
    $params[] = $startTo;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);
$total = (int)($db->one("SELECT COUNT(*) AS `total` FROM `rental_statuses` `r` $whereSql", $params, __FILE__, __LINE__)['total'] ?? 0);
$rows = $db->all(
    "SELECT
        `r`.`id`, `r`.`equipmentName`, `r`.`projectId`, `r`.`renterId`, `r`.`department`,
        `r`.`rentalStartDate`, `r`.`rentalExpireDate`, `r`.`status`, `r`.`notes`,
        `r`.`returnedById`, `r`.`rentalReturnDate`, `r`.`returnNotes`,
        `r`.`creatorId`, `r`.`createdAt`, `r`.`updaterId`, `r`.`updatedAt`, `r`.`void`, `r`.`voidReason`,
        CONCAT_WS(' - ', NULLIF(TRIM(`p`.`projectNumber`), ''), NULLIF(TRIM(`o`.`name`), ''), NULLIF(TRIM(`p`.`clientProjectNumber`), '')) AS `projectName`,
        CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `renterName`,
        CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `returnedByName`,
        CONCAT_WS(' ', `u3`.`firstName`, `u3`.`middleName`, `u3`.`lastName`) AS `creatorName`,
        CONCAT_WS(' ', `u4`.`firstName`, `u4`.`middleName`, `u4`.`lastName`) AS `updaterName`
     FROM `rental_statuses` `r`
     LEFT JOIN `projects` `p` ON `p`.`id` = `r`.`projectId`
     LEFT JOIN `organizations` `o` ON `o`.`id` = `p`.`organizationId`
     LEFT JOIN `users` `u1` ON `u1`.`id` = `r`.`renterId`
     LEFT JOIN `users` `u2` ON `u2`.`id` = `r`.`returnedById`
     LEFT JOIN `users` `u3` ON `u3`.`id` = `r`.`creatorId`
     LEFT JOIN `users` `u4` ON `u4`.`id` = `r`.`updaterId`
     $whereSql
     ORDER BY `r`.`rentalStartDate` DESC, `r`.`createdAt` DESC
     LIMIT $limit OFFSET $offset",
    $params,
    __FILE__,
    __LINE__
);
foreach ($rows as &$row) {
    $row['projectId'] = $row['projectId'] ? (int)$row['projectId'] : null;
    $row['renter'] = $row['renterId'] ? ['label' => $row['renterName'], 'value' => $row['renterId'], 'department' => $row['department']] : null;
    $row['returnedBy'] = $row['returnedById'] ? ['label' => $row['returnedByName'], 'value' => $row['returnedById']] : null;
}
unset($row);
exit(json_encode(['rentals' => $rows, 'page' => $page, 'limit' => $limit, 'total' => $total]));
