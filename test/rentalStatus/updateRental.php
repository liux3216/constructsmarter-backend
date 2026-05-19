<?php
require_once __DIR__ . '/helpers.php';
try {
    $id = rentalRequireString('id', 32);
    $existing = rentalFind($id);
    $equipmentName = rentalRequireString('equipmentName');
    $projectId = rentalRequireProjectId();
    $renterId = rentalRequireUserId('renterId');
    $department = rentalRequireString('department', 100);
    $rentalStartDate = rentalRequireDate('rentalStartDate');
    $rentalExpireDate = rentalRequireDate('rentalExpireDate', true);
    if ($rentalExpireDate !== null && $rentalExpireDate < $rentalStartDate) {
        throw new InvalidArgumentException('Expire date cannot be before start date.');
    }
    if (!empty($existing['rentalReturnDate']) && $existing['rentalReturnDate'] < $rentalStartDate) {
        throw new InvalidArgumentException('Return date cannot be before start date.');
    }
    $db->exec(
        'UPDATE `rental_statuses` SET `equipmentName` = ?, `projectId` = ?, `renterId` = ?, `department` = ?, `rentalStartDate` = ?, `rentalExpireDate` = ?, `notes` = ?, `updaterId` = ? WHERE `id` = ?;',
        [$equipmentName, $projectId, $renterId, $department, $rentalStartDate, $rentalExpireDate, rentalOptionalString('notes'), $userId, $id],
        __FILE__,
        __LINE__
    );
    exit(json_encode(['id' => $id]));
} catch (InvalidArgumentException $e) {
    rentalResponse(422, ['msg' => $e->getMessage()]);
}
