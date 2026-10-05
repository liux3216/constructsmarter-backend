<?php
function setHistoryPage($db, string $userId, array $input): array {
    $exerciseId = filter_var($input['exerciseId'] ?? null, FILTER_VALIDATE_INT);
    $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT);
    $pageSize = filter_var($input['pageSize'] ?? 10, FILTER_VALIDATE_INT);
    if (!$exerciseId || $exerciseId < 1 || !$page || $page < 1 || !$pageSize || $pageSize < 1 || $pageSize > 100) {
        throw new InvalidArgumentException('Invalid exercise or page.');
    }
    $from = 'FROM `workOutSets` s INNER JOIN `workOutGroups` g ON g.`id` = s.`workOutGroupId` INNER JOIN `workOutSettings` e ON e.`id` = g.`workOutSettingId` WHERE e.`id` = ? AND e.`userId` = ? AND g.`userId` = ? AND s.`userId` = ?';
    $params = [$exerciseId, $userId, $userId, $userId];
    $total = (int)$db->one("SELECT COUNT(*) AS total $from", $params)['total'];
    $totalPages = max(1, (int)ceil($total / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;
    $items = $db->all("SELECT s.`id`, g.`datePerformed`, s.`weight`, s.`repetition`, s.`duration`, s.`calories`, s.`comments` $from ORDER BY g.`datePerformed` DESC, s.`createdAt` DESC, s.`id` DESC LIMIT $pageSize OFFSET $offset", $params);
    foreach ($items as &$item) {
        $duration = max(0, (int)($item['duration'] ?? 0));
        $item['hours'] = (string)intdiv($duration, 3600);
        $item['minutes'] = (string)intdiv($duration % 3600, 60);
        $item['seconds'] = (string)($duration % 60);
    }
    unset($item);
    return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => $totalPages];
}
