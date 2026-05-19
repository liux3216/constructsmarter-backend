<?php
require_once __DIR__ . '/helpers.php';
try {
    $id = rentalRequireString('id', 32);
    $existing = rentalFind($id);
    $returnedById = rentalRequireUserId('returnedById');
    $rentalReturnDate = rentalRequireDate('rentalReturnDate');
    if ($rentalReturnDate < $existing['rentalStartDate']) {
        throw new InvalidArgumentException('Return date cannot be before rental start date.');
    }
    $db->exec(
        'UPDATE `rental_statuses` SET `status` = "returned", `returnedById` = ?, `rentalReturnDate` = ?, `returnNotes` = ?, `updaterId` = ? WHERE `id` = ?;',
        [$returnedById, $rentalReturnDate, rentalOptionalString('returnNotes'), $userId, $id],
        __FILE__,
        __LINE__
    );
    exit(json_encode(['id' => $id]));
} catch (InvalidArgumentException $e) {
    rentalResponse(422, ['msg' => $e->getMessage()]);
}
