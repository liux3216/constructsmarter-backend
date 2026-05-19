<?php
require_once __DIR__ . '/helpers.php';
try {
    $equipmentName = rentalRequireString('equipmentName');
    $projectId = rentalRequireProjectId();
    $renterId = rentalRequireUserId('renterId');
    $department = rentalRequireString('department', 100);
    $rentalStartDate = rentalRequireDate('rentalStartDate');
    $rentalExpireDate = rentalRequireDate('rentalExpireDate', true);
    if ($rentalExpireDate !== null && $rentalExpireDate < $rentalStartDate) {
        throw new InvalidArgumentException('Expire date cannot be before start date.');
    }
    $id = rentalId();
    $db->exec(
        'INSERT INTO `rental_statuses` (`id`, `equipmentName`, `projectId`, `renterId`, `department`, `rentalStartDate`, `rentalExpireDate`, `status`, `notes`, `creatorId`) VALUES (?, ?, ?, ?, ?, ?, ?, "rented", ?, ?);',
        [$id, $equipmentName, $projectId, $renterId, $department, $rentalStartDate, $rentalExpireDate, rentalOptionalString('notes'), $userId],
        __FILE__,
        __LINE__
    );
    exit(json_encode(['id' => $id]));
} catch (InvalidArgumentException $e) {
    rentalResponse(422, ['msg' => $e->getMessage()]);
}
