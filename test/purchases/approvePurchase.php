<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = trim((string)($_POST["id"] ?? ""));
$decision = trim((string)($_POST["approverDecision"] ?? ""));
$approverNotes = trim((string)($_POST["approverNotes"] ?? ""));
if($id === "" || !in_array($decision, ["Approved", "Rejected"], true)){
    http_response_code(400);
    exit(json_encode(["msg" => "Missing id or invalid decision."]));
}
$row = $db->one("SELECT `approverId` FROM `purchases` WHERE `id` = ?;", [$id], __FILE__, __LINE__);
if(!$row){
    http_response_code(404);
    exit(json_encode(["msg" => "Purchase not found."]));
}
if($row["approverId"] !== $userId){
    http_response_code(403);
    exit(json_encode(["msg" => "Only the approver can review this purchase."]));
}
$db->exec(
    "UPDATE `purchases` SET `status` = ?, `approvalTime` = NOW(), `approverNotes` = ?, `updaterId` = ? WHERE `id` = ?;",
    [$decision, $approverNotes, $userId, $id],
    __FILE__,
    __LINE__
);
exit(json_encode(["id" => $id]));
