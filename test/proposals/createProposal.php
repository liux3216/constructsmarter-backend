<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
require_once __DIR__."/proposalPDF.php";
try{
    $proposalNumber = proposalRequireString("proposalNumber");
    $proposalDate = proposalRequireString("proposalDate");
    $projectId = proposalOptionalId("projectId");
    $requesterId = proposalRequireString("requesterId");
    $approverId = proposalRequireString("approverId");
    $department = proposalRequireString("department");
    $notes = proposalRequireString("notes", false);
    $subtotal = proposalMoney("subtotal");
    $tax = proposalMoney("tax");
    $discount = proposalMoney("discount");
    $total = proposalMoney("total");
    $lines = proposalParseLineItems((string)($_POST["data"] ?? "[]"));
    $data = json_encode($lines);
    $requester = $db->one("SELECT `id` FROM `users` WHERE `id` = ? AND `void` = 'no';", [$requesterId], __FILE__, __LINE__);
    $approver = $db->one("SELECT `id` FROM `users` WHERE `id` = ? AND `void` = 'no';", [$approverId], __FILE__, __LINE__);
    $project = $projectId ? $db->one("SELECT `id` FROM `projects` WHERE `id` = ? AND `void` = 'no';", [$projectId], __FILE__, __LINE__) : ["id" => null];
    if(!$requester || !$approver || ($projectId && !$project)){
        proposalJsonResponse(400, ["msg" => "Invalid requester, approver, or project."]);
    }
    $db->exec(
        "INSERT INTO `proposals` (
            `pdfId`, `proposalNumber`, `proposalDate`, `projectId`, `requesterId`, `approverId`, `department`,
            `notes`, `subtotal`, `tax`, `discount`, `total`, `data`, `status`,
            `submitterId`, `submitTime`, `creatorId`
        ) VALUES ('', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', ?, NOW(), ?);",
        [$proposalNumber, $proposalDate, $projectId, $requesterId, $approverId, $department, $notes, $subtotal, $tax, $discount, $total, $data, $userId, $userId],
        __FILE__,
        __LINE__
    );
    $created = $db->one("SELECT `id` FROM `proposals` WHERE `proposalNumber` = ? ORDER BY `id` DESC LIMIT 1;", [$proposalNumber], __FILE__, __LINE__);
    $id = (int)($created["id"] ?? 0);
    if(!$id){
        proposalJsonResponse(500, ["msg" => "Failed to create proposal."]);
    }
    $proposal = $db->one(
        "SELECT `p`.*, " . proposalProjectLabel() . " AS `projectName`,
            CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`,
            CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `approverName`
        " . proposalFromSql() . " WHERE `p`.`id` = ?;",
        [$id],
        __FILE__,
        __LINE__
    );
    $proposal["data"] = $lines;
    $pdfId = generateProposalPdf((string)$id, $proposalNumber, null, $proposal);
    $db->exec("UPDATE `proposals` SET `pdfId` = ? WHERE `id` = ?;", [$pdfId, $id], __FILE__, __LINE__);
    exit(json_encode(["id" => $id]));
}catch(InvalidArgumentException $e){
    proposalJsonResponse(422, ["msg" => $e->getMessage()]);
}catch(Throwable $e){
    error_log($e);
    proposalJsonResponse(500, ["msg" => "Internal Server Error"]);
}
