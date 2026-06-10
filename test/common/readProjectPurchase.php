<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/purchases/helpers.php";

header("Content-Type: application/json");

$projectId = trim((string)($_POST["id"] ?? ""));
if ($projectId === "") {
    purchaseJsonResponse(400, ["msg" => "Missing project id."]);
}

$user = $db->one("SELECT `purchases` FROM `users` WHERE `id` = ?;", [$userId], __FILE__, __LINE__);
$access = $user["purchases"] ?? "no";
[$scopeSql, $scopeParams] = purchaseScope("p", $userId, $access);

$rows = $db->all(
    "SELECT
        `p`.`id`,
        `p`.`pdfId`,
        `p`.`poNumber`,
        `p`.`projectId`,
        `p`.`billable`,
        `p`.`includedInProposal`,
        `p`.`subtotal`,
        `p`.`tax`,
        `p`.`discount`,
        `p`.`total`,
        `p`.`data`,
        `p`.`status`,
        `p`.`submitTime`,
        `p`.`receiptFileId`
    FROM `purchases` `p`
    WHERE `p`.`projectId` = ?
      AND `p`.`void` = 'no'
      AND $scopeSql
    ORDER BY COALESCE(`p`.`submitTime`, `p`.`createdAt`) DESC, `p`.`id` DESC;",
    array_merge([$projectId], $scopeParams),
    __FILE__,
    __LINE__
);

foreach ($rows as &$row) {
    $lineItems = json_decode((string)($row["data"] ?? "[]"), true);
    if (!is_array($lineItems)) {
        $lineItems = [];
    }
    $normalizedLineItems = [];
    foreach ($lineItems as $line) {
        if (!is_array($line)) continue;
        $description = trim((string)($line["description"] ?? $line["projectDescription"] ?? ""));
        $normalizedLineItems[] = [
            "vendorName" => (string)($line["vendorName"] ?? ""),
            "projectDescription" => $description,
            "description" => $description,
            "unitPrice" => (string)($line["unitPrice"] ?? "0.00"),
            "quantity" => (string)($line["quantity"] ?? "0.00"),
            "lineTotal" => (string)($line["lineTotal"] ?? "0.00"),
        ];
    }

    $decision = "";
    if (($row["status"] ?? "") === "Approved") {
        $decision = "Approved";
    } elseif (($row["status"] ?? "") === "Rejected") {
        $decision = "Rejected";
    }

    $row["approval"] = json_encode([["approverDecision" => $decision]]);
    $row["data"] = json_encode($normalizedLineItems);
    $row["formId"] = (string)($row["pdfId"] ?? "");
    $row["receiptLink"] = !empty($row["receiptFileId"])
        ? "https://drive.google.com/file/d/" . $row["receiptFileId"] . "/view"
        : "";
    $row["includedInProposal"] = strtolower((string)($row["includedInProposal"] ?? "no")) === "yes" ? "Yes" : "No";
    $row["billable"] = strtolower((string)($row["billable"] ?? "no")) === "yes" ? "yes" : "no";
    unset($row["pdfId"], $row["receiptFileId"], $row["status"]);
}
unset($row);

exit(json_encode($rows));
