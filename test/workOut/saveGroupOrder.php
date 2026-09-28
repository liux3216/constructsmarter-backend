<?php

function saveGroupOrder($db, string $userId, $date, $encodedIds): void
{
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException("A valid workout date is required.");
    }
    [$year, $month, $day] = array_map('intval', explode('-', $date));
    if (!checkdate($month, $day, $year)) {
        throw new InvalidArgumentException("A valid workout date is required.");
    }
    $ids = is_string($encodedIds) ? json_decode($encodedIds, true) : null;
    if (!is_array($ids) || !array_is_list($ids) || !$ids || count($ids) > 10000) {
        throw new InvalidArgumentException("An ordered list of exercise IDs is required.");
    }
    foreach ($ids as $id) {
        if ((!is_string($id) && !is_int($id)) || !preg_match('/^[1-9]\d*$/', (string)$id)) {
            throw new InvalidArgumentException("Invalid exercise ID.");
        }
    }
    $ids = array_map('strval', $ids);
    if (count(array_unique($ids)) !== count($ids)) {
        throw new InvalidArgumentException("Exercise IDs must be unique.");
    }

    $db->begin();
    try {
        $rows = $db->all(
            "SELECT `id` FROM `workOutGroups` WHERE `userId` = ? AND `datePerformed` = ? ORDER BY `id` FOR UPDATE",
            [$userId, $date], __FILE__, __LINE__
        );
        $currentIds = array_map(fn($row) => (string)$row['id'], $rows);
        if (count($ids) !== count($currentIds) || array_diff($ids, $currentIds)) {
            throw new RuntimeException("The workout log has changed. Refresh it before reordering.");
        }
        foreach ($ids as $position => $id) {
            $db->exec(
                "UPDATE `workOutGroups` SET `sortOrder` = ? WHERE `id` = ? AND `userId` = ? AND `datePerformed` = ?",
                [$position, $id, $userId, $date], __FILE__, __LINE__
            );
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}
