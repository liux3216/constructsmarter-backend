<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }

    $id = trim((string)($_POST["id"] ?? ""));
    if ($id === "") {
        jsonResponse(422, ["msg" => "Report id is required."]);
    }

    $report = $db->one(
        "SELECT `id`, `pdfId` FROM `reports` WHERE `id` = ? AND `void` = 'no' LIMIT 1;",
        [$id],
        __FILE__,
        __LINE__
    );
    if (!$report) {
        jsonResponse(404, ["msg" => "Report not found."]);
    }
    if (!$report["pdfId"]) {
        jsonResponse(422, ["msg" => "A report file is required before submit."]);
    }

    $db->exec(
        "UPDATE `reports`
         SET `status` = 'Submitted',
             `updaterId` = ?,
             `updatedAt` = NOW()
         WHERE `id` = ?;",
        [$userId, $id],
        __FILE__,
        __LINE__
    );

    exit(json_encode(["id" => $id]));

} catch (Throwable $e) {
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
