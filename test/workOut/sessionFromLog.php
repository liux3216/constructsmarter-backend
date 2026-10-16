<?php

function sessionFromLog($db, string $userId, array $input): int
{
    if (!isset($input['datePerformed'])) throw new InvalidArgumentException('A valid workout date is required.');
    return createSessionPlan($db, $userId, $input);
}

function createSessionPlan($db, string $userId, array $input): int
{
    $date = $input['datePerformed'] ?? null;
    $name = $input['name'] ?? null;
    $description = $input['description'] ?? '';
    if ($date !== null) {
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new InvalidArgumentException('A valid workout date is required.');
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        if (!checkdate($month, $day, $year)) throw new InvalidArgumentException('A valid workout date is required.');
    }
    if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 255) {
        throw new InvalidArgumentException('Enter a session name of up to 255 characters.');
    }
    if (!is_string($description)) throw new InvalidArgumentException('Invalid description.');

    $exercises = $input['exercises'] ?? '[]';
    $exercises = is_string($exercises) ? json_decode($exercises, true) : null;
    if (!is_array($exercises) || !array_is_list($exercises) || count($exercises) > 1000) throw new InvalidArgumentException('Invalid planned exercises.');
    foreach ($exercises as &$exercise) {
        if (!is_array($exercise) || (!is_string($exercise['workOutSettingId'] ?? null) && !is_int($exercise['workOutSettingId'] ?? null)) || !preg_match('/^[1-9]\d*$/', (string)$exercise['workOutSettingId']) || !is_array($exercise['sets'] ?? null) || !array_is_list($exercise['sets']) || !count($exercise['sets']) || count($exercise['sets']) > 1000) throw new InvalidArgumentException('Each exercise needs a valid type and planned sets.');
        foreach ($exercise['sets'] as &$set) {
            if (!is_array($set)) throw new InvalidArgumentException('Invalid planned set.');
            foreach (['weight','repetition','duration','calories'] as $field) {
                $value = $set[$field] ?? null;
                if ($value !== null && (!is_scalar($value) || is_bool($value) || !is_numeric($value) || !is_finite((float)$value) || (float)$value < 0 || (in_array($field, ['repetition','duration'], true) && floor((float)$value) != (float)$value))) throw new InvalidArgumentException('Planned set values must be nonnegative numbers; reps and duration must be whole numbers.');
                $set[$field] = $value;
            }
            if (isset($set['comments']) && (!is_string($set['comments']) || strlen($set['comments']) > 65535)) throw new InvalidArgumentException('Invalid set comments.');
            $set['comments'] = $set['comments'] ?? null;
        }
        unset($set);
    }
    unset($exercise);

    $db->begin();
    try {
        $groups = $date === null ? [] : $db->all(
            'SELECT g.id, g.workOutSettingId FROM workOutGroups g JOIN workOutSettings s ON s.id = g.workOutSettingId AND s.userId = g.userId WHERE g.userId = ? AND g.datePerformed = ? ORDER BY g.sortOrder IS NULL, g.sortOrder, g.createdAt, g.id FOR UPDATE',
            [$userId, $date], __FILE__, __LINE__
        );
        if ($date !== null && !$groups) throw new InvalidArgumentException('No exercises to copy for this date.');
        foreach ($groups as &$group) {
            $group['sets'] = $db->all('SELECT weight, repetition, duration, calories, comments FROM workOutSets WHERE userId = ? AND workOutGroupId = ? ORDER BY createdAt, id FOR UPDATE', [$userId, $group['id']], __FILE__, __LINE__);
            if (!$group['sets']) $group['sets'] = [array_fill_keys(['weight', 'repetition', 'duration', 'calories', 'comments'], null)];
        }
        unset($group);
        foreach ($exercises as $exercise) {
            $setting = $db->one('SELECT id, mode FROM workOutSettings WHERE id = ? AND userId = ? FOR UPDATE', [$exercise['workOutSettingId'], $userId], __FILE__, __LINE__);
            if (!$setting) throw new InvalidArgumentException('An exercise type is no longer available.');
            foreach ($exercise['sets'] as &$set) {
                if ($setting['mode'] === 'duration') { $set['weight'] = null; $set['repetition'] = null; }
                else $set['duration'] = null;
            }
            unset($set);
            $groups[] = $exercise;
        }
        $db->exec('INSERT INTO workOutLists (userId, name, description) VALUES (?, ?, ?)', [$userId, trim($name), trim($description)], __FILE__, __LINE__);
        $listId = (int)$db->lastInsertId();
        foreach ($groups as $position => $group) {
            $sets = $group['sets'];
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
