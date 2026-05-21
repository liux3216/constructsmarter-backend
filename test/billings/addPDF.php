<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$id = (int)($_POST["id"] ?? 0);
if (!$id) {
    http_response_code(422);
    exit(json_encode(["msg" => "Billing id is required."]));
}
if (!isset($_FILES["pdf"]) || !is_array($_FILES["pdf"])) {
    http_response_code(422);
    exit(json_encode(["msg" => "PDF file is required."]));
}
$file = $_FILES["pdf"];
if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode(["msg" => "Failed to upload PDF file."]));
}
$name = trim((string)($file["name"] ?? "billing.pdf"));
$type = trim((string)($file["type"] ?? "application/pdf"));
$size = (int)($file["size"] ?? 0);
$tmpName = trim((string)($file["tmp_name"] ?? ""));
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if ($ext !== "pdf") {
    http_response_code(400);
    exit(json_encode(["msg" => "Please upload a PDF file."]));
}
if ($tmpName === "" || !is_uploaded_file($tmpName)) {
    http_response_code(400);
    exit(json_encode(["msg" => "Invalid uploaded file."]));
}

$billing = $db->one(
    "SELECT `id`, `billingNumber`, `pdfId` FROM `billings` WHERE `id` = ? LIMIT 1;",
    [$id],
    __FILE__,
    __LINE__
);
if (!$billing) {
    http_response_code(404);
    exit(json_encode(["msg" => "Billing not found."]));
}

$fileId = trim((string)($billing["pdfId"] ?? ""));
$fileInfo = $fileId !== "" ? $db->one(
    "SELECT `id` FROM `fileInfo` WHERE `id` = ? LIMIT 1;",
    [$fileId],
    __FILE__,
    __LINE__
) : null;
if ($fileId === "") {
    $fileId = md5(uniqid((string)mt_rand(), true));
}
if (!uploadFile($privateBucket, $fileId, $tmpName)) {
    http_response_code(500);
    exit(json_encode(["msg" => "Failed to upload billing PDF."]));
}

$storedName = ($billing["billingNumber"] ?: "billing_$id") . ".pdf";
if ($fileInfo) {
    $db->exec(
        "UPDATE `fileInfo`
         SET `name` = ?, `type` = ?, `size` = ?, `lastModifiedAt` = NOW(), `updaterId` = ?, `status` = 'uploaded'
         WHERE `id` = ?;",
        [$storedName, $type ?: "application/pdf", $size, $userId, $fileId],
        __FILE__,
        __LINE__
    );
} else {
    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `lastModifiedAt`, `creatorId`, `status`)
         VALUES (?, ?, ?, ?, NOW(), ?, 'uploaded');",
        [$fileId, $storedName, $type ?: "application/pdf", $size, $userId],
        __FILE__,
        __LINE__
    );
}

$db->exec(
    "UPDATE `billings`
     SET `pdfId` = ?, `updaterId` = ?, `updatedAt` = NOW()
     WHERE `id` = ?;",
    [$fileId, $userId, $id],
    __FILE__,
    __LINE__
);

exit(json_encode(["id" => $id, "pdfId" => $fileId]));
