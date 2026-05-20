<?php
require_once "/opt/bitnami/apache/htdocs/test/outside/locator/helpers.php";

$action = trim((string)($_POST['action'] ?? 'Read'));
$week = trim((string)($_POST['week'] ?? $_POST['currentWeek'] ?? $_POST['wk'] ?? ''));
$targetUserId = trim((string)($_POST['userId'] ?? $userId));
if ($targetUserId === '') $targetUserId = $userId;

if ($week === '') {
    http_response_code(400);
    exit(json_encode(['msg' => 'week is required.']));
}

[$weekMap, $weekData] = locatorGetWeekData($db, $targetUserId, $week, $userId);
$now = date('Y-m-d H:i:s');

switch ($action) {
    case 'Read':
        exit(json_encode([$week => json_encode($weekData)]));

    case 'SaveDay': {
        $index = intval($_POST['index'] ?? -1);
        $dayData = json_decode((string)($_POST['dayData'] ?? ''), true);
        if ($index < 0 || $index > 6 || !is_array($dayData)) {
            http_response_code(400);
            exit(json_encode(['msg' => 'Invalid day payload.']));
        }
        $dayData['status'] = 'Saved';
        $dayData['saveTime'] = $now;
        $weekData['form'][$index] = $dayData;
        locatorSaveWeekData($db, $targetUserId, $week, $weekMap, $weekData, $userId);
        exit(json_encode(['ok' => true]));
    }

    case 'SubmitDay': {
        $index = intval($_POST['index'] ?? -1);
        $dayData = json_decode((string)($_POST['dayData'] ?? ''), true);
        if ($index < 0 || $index > 6 || !is_array($dayData)) {
            http_response_code(400);
            exit(json_encode(['msg' => 'Invalid day payload.']));
        }
        $existingId = $weekData['form'][$index]['id'] ?? null;
        $dayData['status'] = 'Submitted';
        $dayData['submitTime'] = $now;
        if (($dayData['workStatus'] ?? 'Work') !== 'Work') {
            if (is_string($existingId) && strlen($existingId) === 32) locatorDeletePdf($existingId);
            $dayData['id'] = (string)($dayData['workStatus'] ?? 'Submitted');
            $output = $dayData['id'];
        } else {
            $existingPdfId = is_string($existingId) && strlen($existingId) === 32 ? $existingId : null;
            $pdfId = locatorGeneratePdf($_POST, $existingPdfId, false);
            $dayData['id'] = $pdfId;
            $output = $pdfId;
        }
        $weekData['form'][$index] = $dayData;
        locatorSaveWeekData($db, $targetUserId, $week, $weekMap, $weekData, $userId);
        exit((string)$output);
    }

    case 'SubmitWeek':
        $weekData['status'] = 'Submitted';
        $weekData['submitTime'] = $now;
        locatorSaveWeekData($db, $targetUserId, $week, $weekMap, $weekData, $userId);
        exit(json_encode(['ok' => true]));

    case 'AddEmergency':
        $weekData['emergency'][] = locatorDefaultDayData();
        locatorSaveWeekData($db, $targetUserId, $week, $weekMap, $weekData, $userId);
        exit(json_encode(['ok' => true, 'index' => count($weekData['emergency']) - 1]));

    case 'DeleteEmergency': {
        $index = intval($_POST['index'] ?? -1);
        if (!isset($weekData['emergency'][$index])) {
            http_response_code(404);
            exit(json_encode(['msg' => 'Emergency entry is not found.']));
        }
        $existingId = $weekData['emergency'][$index]['id'] ?? null;
        if (is_string($existingId) && strlen($existingId) === 32) locatorDeletePdf($existingId);
        array_splice($weekData['emergency'], $index, 1);
        locatorSaveWeekData($db, $targetUserId, $week, $weekMap, $weekData, $userId);
        exit(json_encode(['ok' => true]));
    }

    case 'SaveEmergency': {
        $index = intval($_POST['index'] ?? -1);
        $dayData = json_decode((string)($_POST['dayData'] ?? ''), true);
        if ($index < 0 || !is_array($dayData)) {
            http_response_code(400);
            exit(json_encode(['msg' => 'Invalid emergency payload.']));
        }
        $dayData['status'] = 'Saved';
        $dayData['saveTime'] = $now;
        $weekData['emergency'][$index] = $dayData;
        locatorSaveWeekData($db, $targetUserId, $week, $weekMap, $weekData, $userId);
        exit(json_encode(['ok' => true]));
    }

    case 'SubmitEmergency': {
        $index = intval($_POST['index'] ?? -1);
        $dayData = json_decode((string)($_POST['dayData'] ?? ''), true);
        if ($index < 0 || !is_array($dayData)) {
            http_response_code(400);
            exit(json_encode(['msg' => 'Invalid emergency payload.']));
        }
        $existingId = $weekData['emergency'][$index]['id'] ?? null;
        $existingPdfId = is_string($existingId) && strlen($existingId) === 32 ? $existingId : null;
        $pdfId = locatorGeneratePdf($_POST, $existingPdfId, true);
        $dayData['status'] = 'Submitted';
        $dayData['submitTime'] = $now;
        $dayData['id'] = $pdfId;
        $weekData['emergency'][$index] = $dayData;
        locatorSaveWeekData($db, $targetUserId, $week, $weekMap, $weekData, $userId);
        exit($pdfId);
    }

    default:
        http_response_code(400);
        exit(json_encode(['msg' => 'Unsupported action.']));
}
