<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/locator/helpers.php";

$week = trim((string)($_POST['week'] ?? ''));
$userIdParam = trim((string)($_POST['userId'] ?? ''));
if ($week === '' || $userIdParam === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'week and userId are required.']));
}

$row = $db->one(
    "SELECT `u`.`id`, `u`.`email`, CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `userName`, `u`.`lanId`, `u`.`hireDate`, `u`.`quitDate`, `u`.`outside`, COALESCE(`m`.`data`, '') AS `data` FROM `users` `u` LEFT JOIN `outsideML` `m` ON `m`.`userId` = `u`.`id` WHERE `u`.`id` = ? LIMIT 1",
    [$userIdParam],
    __FILE__,
    __LINE__
);
if (!$row) {
    http_response_code(404);
    exit(json_encode(['msg' => 'User not found.']));
}

$weekMap = locatorGetWeekMap($row['data'] ?? '');
$weekDataRaw = $weekMap[$week] ?? null;
$weekData = is_string($weekDataRaw) && $weekDataRaw !== '' ? json_decode($weekDataRaw, true) : null;
if (!is_array($weekData)) $weekData = locatorDefaultWeekData();
if (!isset($weekData['form']) || !is_array($weekData['form'])) $weekData['form'] = locatorDefaultWeekData()['form'];
while (count($weekData['form']) < 7) $weekData['form'][] = locatorDefaultDayData();
if (!isset($weekData['emergency']) || !is_array($weekData['emergency'])) $weekData['emergency'] = [];
if (!isset($weekData['status']) || !is_string($weekData['status'])) $weekData['status'] = 'Created';

exit(json_encode([
    'id' => $row['id'],
    'email' => $row['email'],
    'userName' => preg_replace('/\\s+/', ' ', trim((string)$row['userName'])),
    'lanId' => $row['lanId'],
    'hireDate' => $row['hireDate'],
    'quitDate' => $row['quitDate'],
    'outside' => $row['outside'],
    'outsideTimeCards' => '',
    $week => json_encode($weekData),
]));
