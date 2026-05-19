<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__."/helpers.php";
require_once __DIR__."/purchasePDF.php";
try{
    $id = purchaseGenerateId();
    $poNumber = purchaseRequireString("poNumber");
    $poDate = purchaseRequireString("poDate");
    $poType = purchaseEnum("poType", ["pr", "re"], "pr");
    $category = purchaseRequireString("category");
    $projectId = purchaseOptionalId("projectId");
    $requesterId = purchaseRequireString("requesterId");
    $approverId = purchaseRequireString("approverId");
    $department = purchaseRequireString("department");
    $paymentMethod = purchaseRequireString("paymentMethod");
    $last4 = purchaseRequireString("last4", false);
    $billable = purchaseEnum("billable", ["yes", "no"], "no");
    $includedInProposal = purchaseEnum("includedInProposal", ["yes", "no"], "no");
    $clientInvoiceNumber = purchaseRequireString("clientInvoiceNumber", false);
    $notes = purchaseRequireString("notes", false);
    $subtotal = purchaseMoney("subtotal");
    $tax = purchaseMoney("tax");
    $discount = purchaseMoney("discount");
    $total = purchaseMoney("total");
    $lines = purchaseParseLineItems((string)($_POST["data"] ?? "[]"));
    $data = json_encode($lines);
    $requester = $db->one("SELECT `id` FROM `users` WHERE `id` = ? AND `void` = 'no';", [$requesterId], __FILE__, __LINE__);
    $approver = $db->one("SELECT `id` FROM `users` WHERE `id` = ? AND `void` = 'no';", [$approverId], __FILE__, __LINE__);
    $project = $projectId ? $db->one("SELECT `id` FROM `projects` WHERE `id` = ? AND `void` = 'no';", [$projectId], __FILE__, __LINE__) : ["id" => null];
    if(!$requester || !$approver || ($projectId && !$project)){
        purchaseJsonResponse(400, ["msg" => "Invalid requester, approver, or project."]);
    }
    $quoteFileId = purchaseUploadPdf("quoteFile", "", $poNumber . " quote.pdf");
    $receiptFileId = purchaseUploadPdf("receiptFile", "", $poNumber . " receipt.pdf");
    $db->exec(
        "INSERT INTO `purchases` (
            `id`, `pdfId`, `poNumber`, `poDate`, `poType`, `category`, `projectId`, `requesterId`, `approverId`,
            `department`, `paymentMethod`, `last4`, `billable`, `includedInProposal`, `clientInvoiceNumber`,
            `notes`, `subtotal`, `tax`, `discount`, `total`, `data`, `status`, `paid`, `billed`,
            `submitterId`, `submitTime`, `quoteFileId`, `receiptFileId`, `creatorId`
        ) VALUES (?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', 'no', 'no', ?, NOW(), ?, ?, ?);",
        [$id, $poNumber, $poDate, $poType, $category, $projectId, $requesterId, $approverId, $department, $paymentMethod, $last4, $billable, $includedInProposal, $clientInvoiceNumber, $notes, $subtotal, $tax, $discount, $total, $data, $userId, $quoteFileId, $receiptFileId, $userId],
        __FILE__,
        __LINE__
    );
    $purchase = $db->one(
        "SELECT `p`.*, " . purchaseProjectLabel() . " AS `projectName`,
            CONCAT_WS(' ', `u1`.`firstName`, `u1`.`middleName`, `u1`.`lastName`) AS `requesterName`,
            CONCAT_WS(' ', `u2`.`firstName`, `u2`.`middleName`, `u2`.`lastName`) AS `approverName`
        " . purchaseFromSql() . " WHERE `p`.`id` = ?;",
        [$id],
        __FILE__,
        __LINE__
    );
    $purchase["data"] = $lines;
    $pdfId = generatePurchasePdf($id, $poNumber, null, $purchase);
    $db->exec("UPDATE `purchases` SET `pdfId` = ? WHERE `id` = ?;", [$pdfId, $id], __FILE__, __LINE__);
    exit(json_encode(["id" => $id]));
}catch(InvalidArgumentException $e){
    purchaseJsonResponse(422, ["msg" => $e->getMessage()]);
}catch(Throwable $e){
    error_log($e);
    purchaseJsonResponse(500, ["msg" => "Internal Server Error"]);
}
