<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php";
require_once "/opt/bitnami/apache/htdocs/test/constants.php";
require_once __DIR__."/helpers.php";
require_once __DIR__."/perDiemPDF.php";

$id = perDiemRequirePost("id");
$requesterId = perDiemRequirePost("requesterId");
$projectId = perDiemRequirePost("projectId");
$approverId = perDiemRequirePost("approverId");
$startDate = perDiemRequirePost("startDate");
$endDate = perDiemRequirePost("endDate");
$hotelName = perDiemRequirePost("hotelName");
$hotelAddress = perDiemRequirePost("hotelAddress");
$notes = array_key_exists("notes", $_POST) ? trim((string)$_POST["notes"]) : "";

$access = getPerDiemAccess($db, $userId);
$current = $db->one(
    "SELECT `id`, `pdfId`, `createdAt`, `creatorId`, CONCAT_WS(\" \", `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `creatorName`, `requesterId`, `approverNotes`, `approvalTime`
     FROM `perDiems` `p`
     LEFT JOIN `users` `u` ON `u`.`id` = `p`.`creatorId`
     WHERE `p`.`id` = ?;",
    [$id],
    __FILE__,
    __LINE__
);
if (!$current || !perDiemCanEditRow($current, $userId, $access)) {
    http_response_code(403);
    exit(json_encode(["msg" => "You are not allowed to update this per diem form."]));
}

$requester = $db->one("SELECT `email`, `department`, CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `name` FROM `users` WHERE `id` = ?;", [$requesterId], __FILE__, __LINE__);
$project = $db->one("SELECT CONCAT_WS(\" - \", `projects`.`projectNumber`, `organizations`.`name`, `projects`.`clientProjectNumber`) AS `name` FROM `projects` LEFT JOIN `organizations` ON `organizations`.`id` = `projects`.`organizationId` WHERE `projects`.`id` = ?;", [$projectId], __FILE__, __LINE__);
$approver = $db->one("SELECT `email`, CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `name` FROM `users` WHERE `id` = ?;", [$approverId], __FILE__, __LINE__);
if (!$requester || !$project || !$approver) {
    http_response_code(400);
    exit(json_encode(["msg" => "Invalid requester, project, or approver."]));
}

try {
    $db->begin();
    $perDiemRow = [
        'projectId' => $projectId,
        'projectName' => $project['name'],
        'requesterId' => $requesterId,
        'requesterName' => $requester['name'],
        'department' => $requester['department'],
        'startDate' => $startDate,
        'endDate' => $endDate,
        'hotelName' => $hotelName,
        'hotelAddress' => $hotelAddress,
        'notes' => $notes,
        'status' => 'Re-Submitted',
        'approverId' => $approverId,
        'approverName' => $approver['name'],
        'creatorId' => $current['creatorId'],
        'creatorName' => $current['creatorName'],
        'createdAt' => $current['createdAt'],
        'updaterId' => $userId,
        'updaterName' => $userName,
        'updatedAt' => date('Y-m-d H:i:s'),
        'approverNotes' => $current['approverNotes'] ?? '',
        'approvalTime' => $current['approvalTime'] ?? null,
    ];
    $pdfId = generatePerDiemPdf((int)$id, $current['pdfId'] ?: null, $perDiemRow);
    $db->exec(
        "UPDATE `perDiems` SET
            `projectId` = ?,
            `requesterId` = ?,
            `approverId` = ?,
            `startDate` = ?,
            `endDate` = ?,
            `hotelName` = ?,
            `hotelAddress` = ?,
            `notes` = ?,
            `pdfId` = ?,
            `updaterId` = ?,
            `status` = 'Re-Submitted'
         WHERE `id` = ?;",
        [$projectId, $requesterId, $approverId, $startDate, $endDate, $hotelName, $hotelAddress, $notes, $pdfId, $userId, $id],
        __FILE__,
        __LINE__
    );
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    error_log(__FILE__ . ':' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['msg' => 'Failed to update per diem form']));
}

$requestDates = $startDate === $endDate ? $startDate : "from $startDate to $endDate";
$ccEmails = [$email];
if ($requester['email'] !== $email) $ccEmails[] = $requester['email'];
sendEmail([
    'path' => basename(__FILE__) . ' ' . __LINE__,
    'selfEmail' => $email,
    'db' => $db,
    'to' => $approver['email'],
    'cc' => $ccEmails,
    'summary' => 'Per Diem Form Re-Submission Review',
    'body' => "&nbsp;&nbsp;&nbsp;&nbsp;$userName re-submitted a per diem form, Please review and provide your decision below:<br><br>&nbsp;&nbsp;&nbsp;&nbsp;<a href = \"$mainUrl/PerDiem/$id\">{$requester['name']} {$project['name']} $requestDates</a>"
]);
exit(json_encode(['id' => (int)$id]));
