<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/standby/helpers.php";

$week = trim((string)($_POST['week'] ?? ''));
$prevWeek = trim((string)($_POST['prevWeek'] ?? ''));
$targetUserId = trim((string)($_POST['userId'] ?? $userId));
if ($targetUserId === '') $targetUserId = $userId;
if ($week === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'week is required.']));
}

$userRow = $db->one(
    "SELECT `id`, `email`, CONCAT_WS(' ', `firstName`, `middleName`, `lastName`) AS `userName`, `lanId`, `phoneNumber`, `street`, `residence`, `residenceState`, `zipCode` FROM `users` WHERE `id` = ? LIMIT 1",
    [$targetUserId],
    __FILE__,
    __LINE__
);
if (!$userRow) {
    http_response_code(404);
    exit(json_encode(['msg' => 'User not found.']));
}

[, $current] = standbyGetWeekData($db, $targetUserId, $week, $userId);
$output = [
    'id' => $userRow['id'],
    'email' => $userRow['email'],
    'userName' => preg_replace('/\\s+/', ' ', trim((string)$userRow['userName'])),
    'lanId' => $userRow['lanId'],
    'phoneNumber' => $userRow['phoneNumber'],
    'street' => $userRow['street'],
    'residence' => $userRow['residence'],
    'residenceState' => $userRow['residenceState'],
    'zipCode' => $userRow['zipCode'],
    $week => json_encode($current),
];
if ($prevWeek !== '') {
    [, $previous] = standbyGetWeekData($db, $targetUserId, $prevWeek, $userId);
    $output[$prevWeek] = json_encode($previous);
}
exit(json_encode($output));
