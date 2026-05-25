<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
function proposalJsonResponse(int $status, array $payload){
    http_response_code($status);
    exit(json_encode($payload));
}

function proposalRequireString(string $key, bool $required = true): string {
    $value = array_key_exists($key, $_POST) ? trim((string)$_POST[$key]) : "";
    if($required && $value === ""){
        throw new InvalidArgumentException("Missing $key.");
    }
    return $value;
}

function proposalOptionalId(string $key): ?string {
    $value = array_key_exists($key, $_POST) ? trim((string)$_POST[$key]) : "";
    return $value === "" ? null : $value;
}

function proposalMoney(string $key): string {
    $value = array_key_exists($key, $_POST) ? trim((string)$_POST[$key]) : "0";
    if($value === "") $value = "0";
    if(!is_numeric($value)){
        throw new InvalidArgumentException("Invalid $key.");
    }
    return number_format((float)$value, 2, ".", "");
}


function proposalGenerateId(): string {
    return md5(uniqid((string)mt_rand(), true));
}

function proposalParseLineItems(string $raw): array {
    $decoded = json_decode($raw, true);
    if(!is_array($decoded)){
        throw new InvalidArgumentException("Invalid line items.");
    }
    $lines = [];
    foreach($decoded as $line){
        if(!is_array($line)) continue;
        $serviceId = trim((string)($line["serviceId"] ?? ""));
        $serviceName = trim((string)($line["serviceName"] ?? ""));
        $description = trim((string)($line["description"] ?? ""));
        $unitPrice = (string)($line["unitPrice"] ?? "0");
        $quantity = (string)($line["quantity"] ?? "0");
        if($serviceId === "" && $serviceName === "" && $description === "" && trim($unitPrice) === "" && trim($quantity) === ""){
            continue;
        }
        $unitPriceFloat = is_numeric($unitPrice) ? (float)$unitPrice : 0.0;
        $quantityFloat = is_numeric($quantity) ? (float)$quantity : 0.0;
        $lineTotal = number_format($unitPriceFloat * $quantityFloat, 2, ".", "");
        $lines[] = [
            "serviceId" => $serviceId,
            "serviceName" => $serviceName,
            "description" => $description,
            "unitPrice" => number_format($unitPriceFloat, 2, ".", ""),
            "quantity" => number_format($quantityFloat, 2, ".", ""),
            "lineTotal" => $lineTotal,
        ];
    }
    if(!count($lines)){
        throw new InvalidArgumentException("At least one line item is required.");
    }
    return $lines;
}

function proposalFromSql(): string {
    return "FROM `proposals` `p`
LEFT JOIN `users` `u1` ON `u1`.`id` = `p`.`requesterId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `p`.`approverId`
LEFT JOIN `users` `u3` ON `u3`.`id` = `p`.`creatorId`
LEFT JOIN `users` `u4` ON `u4`.`id` = `p`.`updaterId`
LEFT JOIN `users` `u5` ON `u5`.`id` = `p`.`submitterId`
LEFT JOIN `users` `u6` ON `u6`.`id` = `p`.`notifiedBy`
LEFT JOIN `projects` `pr` ON `pr`.`id` = `p`.`projectId`
LEFT JOIN `organizations` `o` ON `o`.`id` = `pr`.`organizationId`";
}

function proposalProjectLabel(string $projectAlias = "pr", string $orgAlias = "o"): string {
    return "CONCAT_WS(' - ', `$projectAlias`.`projectNumber`, `$orgAlias`.`name`, `$projectAlias`.`clientProjectNumber`)";
}

function proposalHydrateRow(array &$row): void {
    $row["requester"] = $row["requesterId"] ? ["label" => $row["requesterName"], "value" => $row["requesterId"]] : null;
    $row["approver"] = $row["approverId"] ? ["label" => $row["approverName"], "value" => $row["approverId"]] : null;
    $row["data"] = $row["data"] ? (json_decode($row["data"], true) ?: []) : [];
    global $privateBucket;
    $row["pdfId"] = $row["pdfId"] ? getObjectUrl($privateBucket, $row["pdfId"], ($row["proposalNumber"] ?: "proposal") . ".pdf") : "";
}
