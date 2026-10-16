<?php

function sessionFromLog($db, string $userId, array $input): int
{
    $date = $input['datePerformed'] ?? null;
    $name = $input['name'] ?? null;
    $description = $input['description'] ?? '';
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('A valid workout date is required.');
    }
    [$year, $month, $day] = array_map('intval', explode('-', $date));
    if (!checkdate($month, $day, $year)) throw new InvalidArgumentException('A valid workout date is required.');
    if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 255) {
        throw new InvalidArgumentException('Enter a session name of up to 255 characters.');
    }
    if (!is_string($description)) throw new InvalidArgumentException('Invalid description.');

    $db->begin();
    try {
        $groups = $db->all(
            'SELECT g.id, g.workOutSettingId FROM workOutGroups g JOIN workOutSettings s ON s.id = g.workOutSettingId AND s.userId = g.userId WHERE g.userId = ? AND g.datePerformed = ? ORDER BY g.sortOrder IS NULL, g.sortOrder, g.createdAt, g.id FOR UPDATE',
            [$userId, $date], __FILE__, __LINE__
        );
        if (!$groups) throw new InvalidArgumentException('No exercises to copy for this date.');
        $db->exec('INSERT INTO workOutLists (userId, name, description) VALUES (?, ?, ?)', [$userId, trim($name), trim($description)], __FILE__, __LINE__);
        $listId = (int)$db->lastInsertId();
        foreach ($groups as $position => $group) {
            $sets = $db->all('SELECT weight, repetition, duration, calories, comments FROM workOutSets WHERE userId = ? AND workOutGroupId = ? ORDER BY createdAt, id FOR UPDATE', [$userId, $group['id']], __FILE__, __LINE__);
            // A logged exercise without sets becomes one blank planned set, as in the session editor.
            if (!$sets) $sets = [array_fill_keys(['weight', 'repetition', 'duration', 'calories', 'comments'], null)];
            $values = fn($set) => [$set['weight'], $set['repetition'], $set['duration'], $set['calories'], $set['comments']];
            $db->exec('INSERT INTO workOutListItems (userId, listId, workOutSettingId, sortOrder, setCount, weight, repetition, duration, calories, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$userId, $listId, $group['workOutSettingId'], $position, count($sets), ...$values($sets[0])], __FILE__, __LINE__);
            $itemId = (int)$db->lastInsertId();
            foreach ($sets as $index => $set) {
                $db->exec('INSERT INTO workOutListItemSets (userId, listItemId, setIndex, weight, repetition, duration, calories, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$userId, $itemId, $index + 1, ...$values($set)], __FILE__, __LINE__);
            }
        }
        $db->commit();
        return $listId;
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}
