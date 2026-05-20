<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/standby/helpers.php";

$currentWeek = trim((string)($_POST['currentWeek'] ?? ''));
$targetUserId = trim((string)($_POST['userId'] ?? $_POST['userEmail'] ?? $userId));
$action = trim((string)($_POST['action'] ?? 'save'));
$inputDataStr = (string)($_POST['data'] ?? '');
if ($currentWeek === '' || $inputDataStr === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'currentWeek and data are required.']));
}
$data = json_decode($inputDataStr, true);
if (!is_array($data)) {
    http_response_code(400);
    exit(json_encode(['msg' => 'data is invalid.']));
}
[$weekMap, $weekData] = standbyGetWeekData($db, $targetUserId, $currentWeek, $userId);
$existingAllId = $weekData['id'] ?? null;
$existingBillableId = $weekData['billableId'] ?? null;

if ($action === 'save') {
    if (!empty($existingAllId)) standbyDeleteFileIfExists($existingAllId);
    if (!empty($existingBillableId)) standbyDeleteFileIfExists($existingBillableId);
    $data['status'] = 'Saved';
    $data['id'] = null;
    $data['billableId'] = null;
    standbySaveWeekData($db, $targetUserId, $currentWeek, $weekMap, $data, $userId);
    exit(json_encode(['ok' => true]));
}

if ($action === 'submit') {
    $files = ['all' => null, 'billable' => null];
    if (($data['status'] ?? '') !== 'Submitted') {
        $files = standbyGenerateWorkbookFiles($currentWeek, $data, $existingAllId, $existingBillableId);
    }
    $data['status'] = 'Submitted';
    $data['submitTime'] = date('Y-m-d H:i:s');
    $data['id'] = $files['all'];
    $data['billableId'] = $files['billable'];
    standbySaveWeekData($db, $targetUserId, $currentWeek, $weekMap, $data, $userId);
    exit(json_encode($files));
}

http_response_code(400);
exit(json_encode(['msg' => 'Unsupported action.']));
