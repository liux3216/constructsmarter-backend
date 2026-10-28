<?php
function listSettingsPage($db, string $userId, array $input, string $publicBucket): array {
    $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT);
    $pageSize = filter_var($input['pageSize'] ?? 20, FILTER_VALIDATE_INT);
    $name = $input['name'] ?? '';
    $mode = $input['mode'] ?? 'all';
    $targetIds = $input['targetAreaIds'] ?? '';
    if (!$page || $page < 1 || !$pageSize || $pageSize < 1 || $pageSize > 100 || !is_string($name) || strlen($name) > 200 || !in_array($mode, ['all', 'repetition', 'duration'], true) || !is_string($targetIds)) {
        throw new InvalidArgumentException('Invalid exercise list filters or page.');
    }
    $ids = $targetIds === '' ? [] : array_values(array_unique(explode(',', $targetIds)));
    if (count($ids) > 50) throw new InvalidArgumentException('Too many target areas.');
    $where = ['s.`userId` = ?'];
    $params = [$userId];
    if (trim($name) !== '') {
        $where[] = "s.`name` LIKE ? ESCAPE '='";
        $params[] = '%' . str_replace(['=', '%', '_'], ['==', '=%', '=_'], trim($name)) . '%';
    }
    if ($mode !== 'all') { $where[] = 's.`mode` = ?'; $params[] = $mode; }
    foreach ($ids as $id) {
        if (!preg_match('/^[1-9]\d*$/', $id)) throw new InvalidArgumentException('Invalid target area.');
        $where[] = 'EXISTS (SELECT 1 FROM `workOutSettingTargetAreas` j WHERE j.`workOutSettingId` = s.`id` AND j.`targetAreaId` = ?)';
        $params[] = $id;
    }
    $predicate = implode(' AND ', $where);
    $total = (int)$db->one("SELECT COUNT(*) AS total FROM `workOutSettings` s WHERE $predicate", $params)['total'];
    $totalPages = max(1, (int)ceil($total / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;
    // Integer bounds validated above; SQL pagination happens before loading target areas.
    $items = $db->all("SELECT s.* FROM `workOutSettings` s WHERE $predicate ORDER BY s.`createdAt` DESC, s.`id` DESC LIMIT $pageSize OFFSET $offset", $params);
    $targets = [];
    if ($items) {
        $placeholders = implode(',', array_fill(0, count($items), '?'));
        $rows = $db->all("SELECT j.`workOutSettingId`, a.`id`, a.`name`, j.`percentage` FROM `workOutSettingTargetAreas` j INNER JOIN `workOutTargetAreas` a ON a.`id` = j.`targetAreaId` WHERE j.`workOutSettingId` IN ($placeholders) ORDER BY a.`sortOrder`, a.`name`", array_column($items, 'id'));
        foreach ($rows as $row) $targets[(string)$row['workOutSettingId']][] = ['id' => $row['id'], 'name' => $row['name'], 'percentage' => (string)$row['percentage']];
    }
    foreach ($items as &$item) {
        $item['targetAreas'] = $targets[(string)$item['id']] ?? [];
        $item['targetAreaIds'] = array_map(fn($area) => (string)$area['id'], $item['targetAreas']);
        $item['profileUrl'] = empty($item['profileId']) ? '' : "https://$publicBucket.s3.us-west-1.amazonaws.com/" . rawurlencode($item['profileId']);
    }
    unset($item);
    return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => $totalPages];
}
