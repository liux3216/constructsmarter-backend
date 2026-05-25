<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";

define("PURCHASE_FILEINFO_FOLDER_ID", "9f48a8a36463c0f9f6b3d0f97b5e0c11");
define("PURCHASE_MAX_FILE_BYTES", 8 * 1024 * 1024);

function purchaseJsonResponse(int $status, array $payload){
    http_response_code($status);
    exit(json_encode($payload));
}

function purchaseRequireString(string $key, bool $required = true): string {
    $value = array_key_exists($key, $_POST) ? trim((string)$_POST[$key]) : "";
    if($required && $value === ""){
        throw new InvalidArgumentException("Missing $key.");
    }
    return $value;
}

function purchaseOptionalId(string $key): ?string {
    $value = array_key_exists($key, $_POST) ? trim((string)$_POST[$key]) : "";
    return $value === "" ? null : $value;
}

function purchaseMoney(string $key): string {
    $value = array_key_exists($key, $_POST) ? trim((string)$_POST[$key]) : "0";
    if($value === "") $value = "0";
    if(!is_numeric($value)){
        throw new InvalidArgumentException("Invalid $key.");
    }
    return number_format((float)$value, 2, ".", "");
}

function purchaseEnum(string $key, array $allowed, string $default = ""): string {
    $value = array_key_exists($key, $_POST) ? trim((string)$_POST[$key]) : $default;
    if($value === "") return $default;
    if(!in_array($value, $allowed, true)){
        throw new InvalidArgumentException("Invalid $key.");
    }
    return $value;
}

function purchaseGenerateId(): string {
    return md5(uniqid((string)mt_rand(), true));
}

function purchaseParseLineItems(string $raw): array {
    $decoded = json_decode($raw, true);
    if(!is_array($decoded)){
        throw new InvalidArgumentException("Invalid line items.");
    }
    $lines = [];
    foreach($decoded as $line){
        if(!is_array($line)) continue;
        $vendorId = trim((string)($line["vendorId"] ?? ""));
        $vendorName = trim((string)($line["vendorName"] ?? ""));
        $description = trim((string)($line["description"] ?? ""));
        $unitPrice = (string)($line["unitPrice"] ?? "0");
        $quantity = (string)($line["quantity"] ?? "0");
        if($vendorId === "" && $vendorName === "" && $description === "" && trim($unitPrice) === "" && trim($quantity) === ""){
            continue;
        }
        $unitPriceFloat = is_numeric($unitPrice) ? (float)$unitPrice : 0.0;
        $quantityFloat = is_numeric($quantity) ? (float)$quantity : 0.0;
        $lineTotal = number_format($unitPriceFloat * $quantityFloat, 2, ".", "");
        $lines[] = [
            "vendorId" => $vendorId,
            "vendorName" => $vendorName,
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

function purchaseEnsureFileFolder(): void {
    global $db, $userId;
    $folder = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ?;", [PURCHASE_FILEINFO_FOLDER_ID], __FILE__, __LINE__);
    if($folder) return;
    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?);",
        [PURCHASE_FILEINFO_FOLDER_ID, "Purchases", "folder", 0, null, $userId, "uploaded"],
        __FILE__,
        __LINE__
    );
}

function purchaseSyncFileInfo(string $fileId, string $name, string $mimeType, int $size): void {
    global $db, $userId;
    purchaseEnsureFileFolder();
    $existing = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ?;", [$fileId], __FILE__, __LINE__);
    if($existing){
        $db->exec(
            "UPDATE `fileInfo` SET `name` = ?, `type` = ?, `size` = ?, `parentId` = ?, `updaterId` = ?, `status` = 'uploaded' WHERE `id` = ?;",
            [$name, $mimeType, $size, PURCHASE_FILEINFO_FOLDER_ID, $userId, $fileId],
            __FILE__,
            __LINE__
        );
        return;
    }
    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?);",
        [$fileId, $name, $mimeType, $size, PURCHASE_FILEINFO_FOLDER_ID, $userId, "uploaded"],
        __FILE__,
        __LINE__
    );
}

function purchaseResolveExistingFileId(string $fieldName, string $fallback = ""): string {
    $key = $fieldName . "IdCurrent";
    if(array_key_exists($key, $_POST)){
        return trim((string)$_POST[$key]);
    }
    return $fallback;
}

function purchaseUploadPdf(string $fieldName, string $existingKey, string $displayName): string {
    global $privateBucket;
    $existingKey = purchaseResolveExistingFileId($fieldName, $existingKey);
    if(!array_key_exists($fieldName, $_FILES) || !$_FILES[$fieldName]["tmp_name"]){
        return $existingKey;
    }
    $file = $_FILES[$fieldName];
    if(($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
        throw new InvalidArgumentException("Failed to upload $fieldName.");
    }
    if(!is_uploaded_file($file["tmp_name"])){
        throw new InvalidArgumentException("Invalid upload for $fieldName.");
    }
    $size = (int)($file["size"] ?? 0);
    if($size <= 0){
        throw new InvalidArgumentException("$fieldName is empty.");
    }
    if($size > PURCHASE_MAX_FILE_BYTES){
        throw new InvalidArgumentException("$fieldName exceeds max upload size.");
    }
    $mime = strtolower((string)mime_content_type($file["tmp_name"]));
    $name = strtolower((string)($file["name"] ?? ""));
    if($mime !== "application/pdf" && $mime !== "application/x-pdf"){
        throw new InvalidArgumentException("$fieldName must be a PDF.");
    }
    if($name !== "" && !str_ends_with($name, ".pdf")){
        throw new InvalidArgumentException("$fieldName must be a PDF.");
    }
    $key = $existingKey !== "" ? $existingKey : purchaseGenerateId();
    if(!uploadFile($privateBucket, $key, $file["tmp_name"])){
        throw new RuntimeException("Failed to upload $fieldName.");
    }
    purchaseSyncFileInfo($key, $displayName, "application/pdf", $size);
    return $key;
}

function purchaseAccess(string $access): string {
    return in_array($access, ["edit", "paid"], true) ? "all" : "limited";
}

function purchaseScope(string $alias, string $userId, string $access): array {
    if(purchaseAccess($access) === "all"){
        return ["1=1", []];
    }
    return [
        "(`$alias`.`requesterId` = ? OR `$alias`.`creatorId` = ? OR `$alias`.`approverId` = ? OR `$alias`.`submitterId` = ?)",
        [$userId, $userId, $userId, $userId]
    ];
}

function purchaseProjectLabel(string $projectAlias = "pr", string $orgAlias = "o"): string {
    return "CONCAT_WS(' - ', `$projectAlias`.`projectNumber`, `$orgAlias`.`name`, `$projectAlias`.`clientProjectNumber`)";
}

function purchaseFromSql(): string {
    return "FROM `purchases` `p`
LEFT JOIN `users` `u1` ON `u1`.`id` = `p`.`requesterId`
LEFT JOIN `users` `u2` ON `u2`.`id` = `p`.`approverId`
LEFT JOIN `users` `u3` ON `u3`.`id` = `p`.`creatorId`
LEFT JOIN `users` `u4` ON `u4`.`id` = `p`.`updaterId`
LEFT JOIN `users` `u5` ON `u5`.`id` = `p`.`submitterId`
LEFT JOIN `users` `u6` ON `u6`.`id` = `p`.`notifiedBy`
LEFT JOIN `projects` `pr` ON `pr`.`id` = `p`.`projectId`
LEFT JOIN `organizations` `o` ON `o`.`id` = `pr`.`organizationId`";
}

function purchaseHydrateRow(array &$row): void {
    global $privateBucket;
    $row["requester"] = $row["requesterId"] ? ["label" => $row["requesterName"], "value" => $row["requesterId"]] : null;
    $row["approver"] = $row["approverId"] ? ["label" => $row["approverName"], "value" => $row["approverId"]] : null;
    $row["data"] = $row["data"] ? (json_decode($row["data"], true) ?: []) : [];
    $row["pdfId"] = $row["pdfId"] ? getObjectUrl($privateBucket, $row["pdfId"], ($row["poNumber"] ?: "purchase") . ".pdf") : "";
    $row["quoteUrl"] = $row["quoteFileId"] ? getObjectUrl($privateBucket, $row["quoteFileId"], ($row["poNumber"] ?: "purchase") . " quote.pdf") : "";
    $row["receiptUrl"] = $row["receiptFileId"] ? getObjectUrl($privateBucket, $row["receiptFileId"], ($row["poNumber"] ?: "purchase") . " receipt.pdf") : "";
}
