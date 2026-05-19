<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = (int)($_POST["id"] ?? 0);
$billingNumber = trim((string)($_POST["billingNumber"] ?? ""));
$projectId = (int)($_POST["projectId"] ?? 0);
$contactId = array_key_exists("contactId", $_POST) && $_POST["contactId"] !== "" ? (int)$_POST["contactId"] : null;
$approverId = trim((string)($_POST["approverId"] ?? ""));
$fromDate = trim((string)($_POST["fromDate"] ?? ""));
$toDate = trim((string)($_POST["toDate"] ?? ""));
$amountRaw = trim((string)($_POST["amount"] ?? ""));
$billable = trim((string)($_POST["billable"] ?? ""));
$notes = trim((string)($_POST["notes"] ?? ""));
//-------------------------------------------------
if(
    !$id ||
    $billingNumber === "" ||
    !$projectId ||
    $approverId === "" ||
    $fromDate === "" ||
    $toDate === "" ||
    $amountRaw === "" ||
    !in_array($billable, ["yes", "no"], true)
){
    http_response_code(400);
    exit(json_encode(["msg" => "Missing or invalid required billing fields."]));
}
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)){
    http_response_code(400);
    exit(json_encode(["msg" => "Invalid billing dates."]));
}
if($fromDate > $toDate){
    http_response_code(400);
    exit(json_encode(["msg" => "The start date must be before or equal to the end date."]));
}
if(!is_numeric($amountRaw) || (float)$amountRaw < 0){
    http_response_code(400);
    exit(json_encode(["msg" => "Invalid billing amount."]));
}
$amount = number_format((float)$amountRaw, 2, '.', '');
$notes = $notes === "" ? null : $notes;
//-------------------------------------------------
$billing = $db->one(
    "SELECT `id`, `billed` FROM `billings` WHERE `id` = ? LIMIT 1;",
    [$id],
    __FILE__, __LINE__
);
if(!$billing){
    http_response_code(404);
    exit(json_encode(["msg" => "The billing is not found."]));
}
$duplicate = $db->one(
    "SELECT `id` FROM `billings` WHERE `billingNumber` = ? AND `id` <> ? LIMIT 1;",
    [$billingNumber, $id],
    __FILE__, __LINE__
);
if($duplicate){
    http_response_code(409);
    exit(json_encode(["msg" => "The billing number already exists."]));
}
$project = $db->one(
    "SELECT `id` FROM `projects` WHERE `id` = ? LIMIT 1;",
    [$projectId],
    __FILE__, __LINE__
);
if(!$project){
    http_response_code(400);
    exit(json_encode(["msg" => "The project is not found."]));
}
$approver = $db->one(
    "SELECT `id` FROM `users` WHERE `id` = ? LIMIT 1;",
    [$approverId],
    __FILE__, __LINE__
);
if(!$approver){
    http_response_code(400);
    exit(json_encode(["msg" => "The approver is not found."]));
}
if($contactId !== null){
    $contact = $db->one(
        "SELECT `id` FROM `contacts` WHERE `id` = ? LIMIT 1;",
        [$contactId],
        __FILE__, __LINE__
    );
    if(!$contact){
        http_response_code(400);
        exit(json_encode(["msg" => "The contact is not found."]));
    }
}
//-------------------------------------------------
try {
    $db->begin();
    $db->exec(
        "UPDATE `billings` SET
            `billingNumber` = ?,
            `projectId` = ?,
            `contactId` = ?,
            `approverId` = ?,
            `fromDate` = ?,
            `toDate` = ?,
            `amount` = ?,
            `billable` = ?,
            `notes` = ?,
            `submitterId` = ?,
            `submitTime` = NOW(),
            `status` = 'submitted',
            `notifiedBy` = NULL,
            `notifiedAt` = NULL,
            `approvalTime` = NULL,
            `approverNotes` = NULL,
            `updaterId` = ?,
            `void` = 'no',
            `voidReason` = NULL,
            `validateReason` = NULL
        WHERE `id` = ?;",
        [$billingNumber, $projectId, $contactId, $approverId, $fromDate, $toDate, $amount, $billable, $notes, $userId, $userId, $id],
        __FILE__, __LINE__
    );
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    error_log(__FILE__.":".$e->getMessage());
    http_response_code(500);
    exit(json_encode(["msg" => "Failed to update billing."]));
}
//-------------------------------------------------
exit(json_encode([
    "id" => $id,
    "billed" => $billing["billed"]
]));
