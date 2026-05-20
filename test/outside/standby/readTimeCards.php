<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/standby/helpers.php";

$week = trim((string)($_POST['week'] ?? ''));
if ($week === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'week is required.']));
}

$rows = $db->all(
    "SELECT `u`.`id`, `u`.`email`, CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `userName`, `u`.`lanId`, `u`.`phoneNumber`, `u`.`street`, `u`.`residence`, `u`.`residenceState`, `u`.`zipCode`, `u`.`hireDate`, `u`.`quitDate`, `u`.`outside`, COALESCE(`s`.`data`, '') AS `data` FROM `users` `u` LEFT JOIN `outsideStandby` `s` ON `s`.`userId` = `u`.`id` WHERE `u`.`void` = 'no' AND `u`.`outside` = 'standby' ORDER BY `userName` ASC",
    [],
    __FILE__,
    __LINE__
);

$output = [];
foreach ($rows as $row) {
    $weekMap = standbyGetWeekMap($row['data'] ?? '');
    $weekDataRaw = $weekMap[$week] ?? null;
    $weekData = is_string($weekDataRaw) && $weekDataRaw !== '' ? json_decode($weekDataRaw, true) : null;
    if (!is_array($weekData)) $weekData = standbyDefaultWeekData();
    if (!isset($weekData['data']) || !is_array($weekData['data'])) $weekData['data'] = standbyDefaultWeekData()['data'];
    while (count($weekData['data']) < 7) $weekData['data'][] = [[]];
    if (!isset($weekData['status'])) $weekData['status'] = 'Created';
    $output[] = [
        'id' => $row['id'],
        'email' => $row['email'],
        'userName' => preg_replace('/\\s+/', ' ', trim((string)$row['userName'])),
        'lanId' => $row['lanId'],
        'phoneNumber' => $row['phoneNumber'],
        'street' => $row['street'],
        'residence' => $row['residence'],
        'residenceState' => $row['residenceState'],
        'zipCode' => $row['zipCode'],
        'hireDate' => $row['hireDate'],
        'quitDate' => $row['quitDate'],
        'outside' => $row['outside'],
        $week => json_encode($weekData),
    ];
}

exit(json_encode($output));
