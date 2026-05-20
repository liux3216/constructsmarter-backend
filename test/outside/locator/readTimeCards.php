<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/locator/helpers.php";

$week = trim((string)($_POST['week'] ?? ''));
$weekStart = trim((string)($_POST['weekStart'] ?? ''));
$weekEnd = trim((string)($_POST['weekEnd'] ?? ''));

$rows = $db->all(
    "SELECT `u`.`id`, `u`.`email`, CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `userName`, `u`.`lanId`, `u`.`hireDate`, `u`.`quitDate`, `u`.`outside`, COALESCE(`m`.`data`, '') AS `data` FROM `users` `u` LEFT JOIN `outsideML` `m` ON `m`.`userId` = `u`.`id` WHERE `u`.`void` = 'no' AND `u`.`outside` IN ('locator', 'qew') ORDER BY `userName` ASC",
    [],
    __FILE__,
    __LINE__
);

function locatorNormalizeWeekData(array $weekMap, string $week): array {
    $weekDataRaw = $weekMap[$week] ?? null;
    $weekData = is_string($weekDataRaw) && $weekDataRaw !== '' ? json_decode($weekDataRaw, true) : null;
    if (!is_array($weekData)) $weekData = locatorDefaultWeekData();
    if (!isset($weekData['form']) || !is_array($weekData['form'])) $weekData['form'] = locatorDefaultWeekData()['form'];
    while (count($weekData['form']) < 7) $weekData['form'][] = locatorDefaultDayData();
    if (!isset($weekData['emergency']) || !is_array($weekData['emergency'])) $weekData['emergency'] = [];
    if (!isset($weekData['status']) || !is_string($weekData['status'])) $weekData['status'] = 'Created';
    return $weekData;
}

$output = [];
foreach ($rows as $row) {
    $weekMap = locatorGetWeekMap($row['data'] ?? '');
    $item = [
        'id' => $row['id'],
        'email' => $row['email'],
        'userName' => preg_replace('/\\s+/', ' ', trim((string)$row['userName'])),
        'lanId' => $row['lanId'],
        'hireDate' => $row['hireDate'],
        'quitDate' => $row['quitDate'],
        'outside' => $row['outside'],
        'outsideTimeCards' => '',
    ];
    if ($week !== '') {
        $item[$week] = json_encode(locatorNormalizeWeekData($weekMap, $week));
    } elseif ($weekStart !== '' && $weekEnd !== '') {
        foreach ($weekMap as $wk => $value) {
            if ($wk >= $weekStart && $wk <= $weekEnd) {
                $item[$wk] = json_encode(locatorNormalizeWeekData($weekMap, $wk));
            }
        }
    }
    $output[] = $item;
}

exit(json_encode($output));
