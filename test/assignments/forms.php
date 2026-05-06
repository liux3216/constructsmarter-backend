<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

header("Content-Type: application/json");

function assignmentFormString(array $src, string $key, int $max = 255, bool $required = false): ?string
{
    $value = requireField($src, $key, 0, $max, $required);
    if ($value === "undefined") {
        if ($required) {
            jsonResponse(409, ["msg" => "{$key} cannot be empty"]);
        }
        return null;
    }
    return $value;
}

function assignmentFormJsonString(array $src, string $key, string $default): string
{
    $value = assignmentFormString($src, $key, 10000000, false);
    if ($value === null || $value === "") {
        return $default;
    }
    json_decode($value, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(407, ["msg" => "{$key} must be valid JSON"]);
    }
    return $value;
}

function assignmentFormDecoded(?string $value, mixed $default): mixed
{
    if ($value === null || $value === "") {
        return $default;
    }
    $decoded = json_decode($value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
}

function assignmentFormResponse(array $row): array
{
    return [
        "id" => (int)$row["id"],
        "assignmentId" => (int)$row["assignmentId"],
        "formName" => $row["formName"],
        "title" => $row["title"],
        "formRequired" => $row["formRequired"],
        "status" => $row["status"],
        "content" => assignmentFormDecoded($row["content"] ?? null, []),
        "data" => $row["content"] ?? "{}",
        "sign" => $row["signData"] ?? "[]",
        "signData" => assignmentFormDecoded($row["signData"] ?? null, []),
        "sketches" => assignmentFormDecoded($row["sketches"] ?? null, []),
        "sketches2" => assignmentFormDecoded($row["sketches2"] ?? null, []),
        "docNum" => $row["docNum"],
        "formId" => $row["formId"],
        "docId" => $row["docId"],
        "cancel" => $row["cancel"],
        "noPDF" => $row["noPDF"],
        "createdAt" => $row["createdAt"],
        "updatedAt" => $row["updatedAt"],
    ];
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }

    $assignmentId = requireInt($_POST, "assignmentId", 1, null, true);
    $formName = assignmentFormString($_POST, "formName", 100, true);
    $action = requireEnum($_POST, "action", ["Read", "Save", "Submit"], true, false);

    $assignment = $db->one(
        "SELECT `id` FROM `assignments` WHERE `id` = ? AND `void` = 'no';",
        [$assignmentId],
        __FILE__,
        __LINE__
    );
    if (!$assignment) {
        jsonResponse(404, ["msg" => "The assignment is not found."]);
    }

    $existing = $db->one(
        "SELECT * FROM `assignment_forms` WHERE `assignmentId` = ? AND `formName` = ?;",
        [$assignmentId, $formName],
        __FILE__,
        __LINE__
    );

    if ($action === "Read") {
        if (!$existing) {
            jsonResponse(200, [
                "assignmentId" => $assignmentId,
                "formName" => $formName,
                "content" => [],
                "data" => "{}",
                "sign" => "[]",
                "signData" => [],
                "sketches" => [],
                "sketches2" => [],
            ]);
        }
        jsonResponse(200, assignmentFormResponse($existing));
    }

    $now = date("Y-m-d H:i:s");
    $status = $action === "Save" ? "Saved" : "Submitted";
    $isSignChanged = assignmentFormString($_POST, "isSignChanged", 8, false);
    $isSketchesChanged = assignmentFormString($_POST, "isSketchesChanged", 8, false);
    $isSketchesChanged2 = assignmentFormString($_POST, "isSketchesChanged2", 8, false);

    $content = assignmentFormJsonString($_POST, "data", "{}");
    $signData = ($isSignChanged === "No" && $existing) ? ($existing["signData"] ?? "[]") : assignmentFormJsonString($_POST, "signData", "[]");
    $sketches = ($isSketchesChanged === "No" && $existing) ? ($existing["sketches"] ?? "[]") : assignmentFormJsonString($_POST, "sketches", "[]");
    $sketches2 = ($isSketchesChanged2 === "No" && $existing) ? ($existing["sketches2"] ?? "[]") : assignmentFormJsonString($_POST, "sketches2", "[]");

    $values = [
        "assignmentId" => $assignmentId,
        "formName" => $formName,
        "title" => assignmentFormString($_POST, "title", 255, false),
        "formRequired" => requireEnum($_POST, "formRequired", ["yes", "no"], false, true),
        "status" => $status,
        "content" => $content,
        "signData" => $signData,
        "sketches" => $sketches,
        "sketches2" => $sketches2,
        "docNum" => assignmentFormString($_POST, "docNum", 255, false),
        "formId" => assignmentFormString($_POST, "formId", 255, false),
        "docId" => assignmentFormString($_POST, "docId", 255, false),
        "cancel" => assignmentFormString($_POST, "cancel", 32, false),
        "noPDF" => requireEnum($_POST, "noPDF", ["yes", "no"], false, true),
        "updaterId" => $userId,
        "updatedAt" => $now,
    ];

    $db->begin();
    if ($existing) {
        $db->exec(
            "UPDATE `assignment_forms`
            SET `title` = :title,
                `formRequired` = :formRequired,
                `status` = :status,
                `content` = :content,
                `signData` = :signData,
                `sketches` = :sketches,
                `sketches2` = :sketches2,
                `docNum` = :docNum,
                `formId` = :formId,
                `docId` = :docId,
                `cancel` = :cancel,
                `noPDF` = :noPDF,
                `updaterId` = :updaterId,
                `updatedAt` = :updatedAt
            WHERE `assignmentId` = :assignmentId AND `formName` = :formName;",
            $values,
            __FILE__,
            __LINE__
        );
        $recordId = (int)$existing["id"];
    } else {
        $values["creatorId"] = $userId;
        $values["createdAt"] = $now;
        $db->exec(
            "INSERT INTO `assignment_forms`
            (`assignmentId`, `formName`, `title`, `formRequired`, `status`, `content`, `signData`, `sketches`, `sketches2`, `docNum`, `formId`, `docId`, `cancel`, `noPDF`, `creatorId`, `createdAt`, `updaterId`, `updatedAt`)
            VALUES
            (:assignmentId, :formName, :title, :formRequired, :status, :content, :signData, :sketches, :sketches2, :docNum, :formId, :docId, :cancel, :noPDF, :creatorId, :createdAt, :updaterId, :updatedAt);",
            $values,
            __FILE__,
            __LINE__
        );
        $recordId = (int)$db->lastInsertId();
    }
    $db->commit();

    $row = $db->one(
        "SELECT * FROM `assignment_forms` WHERE `id` = ?;",
        [$recordId],
        __FILE__,
        __LINE__
    );
    jsonResponse(200, assignmentFormResponse($row));

} catch (InvalidArgumentException $e) {
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);

} catch (Throwable $e) {
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
