<?php
require_once __DIR__ . '/helpers.php';
$id = trim((string)($_POST['id'] ?? ''));
if ($id === '') rentalResponse(400, ['msg' => 'Missing id.']);
$row = $db->one(
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
     WHERE `r`.`id` = ? LIMIT 1",
    [$id], __FILE__, __LINE__
);
if (!$row) rentalResponse(404, ['msg' => 'Rental not found.']);
$row['projectId'] = $row['projectId'] ? (int)$row['projectId'] : null;
$row['renter'] = $row['renterId'] ? ['label' => $row['renterName'], 'value' => $row['renterId'], 'department' => $row['department']] : null;
$row['returnedBy'] = $row['returnedById'] ? ['label' => $row['returnedByName'], 'value' => $row['returnedById']] : null;
exit(json_encode($row));
