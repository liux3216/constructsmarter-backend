<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

$week = trim((string)($_POST["week"] ?? ""));
$data = json_decode((string)($_POST["data"] ?? "{}"), true);
if (!preg_match('/^w\d{4}-\d{2}$/', $week)) {
    http_response_code(409);
    exit(json_encode(["msg" => "Invalid week."]));
}
if (!is_array($data)) {
    http_response_code(409);
    exit(json_encode(["msg" => "Invalid imported billing data."]));
}

$db->begin();
try {
    $db->exec(
        "DELETE FROM `imported_billings` WHERE `week` = ?;",
        [$week],
        __FILE__,
        __LINE__
    );

    foreach ($data as $projectId => $items) {
        if (!preg_match('/^\d+$/', (string)$projectId) || !is_array($items)) {
            continue;
        }
        $project = $db->one(
            "SELECT `id` FROM `projects` WHERE `id` = ? AND `void` = 'no' LIMIT 1;",
            [$projectId],
            __FILE__,
            __LINE__
        );
        if (!$project) {
            continue;
        }

        foreach ($items as $item) {
            $billingDate = trim((string)($item["date"] ?? ""));
            $billingNumber = trim((string)($item["billingNumber"] ?? ""));
            $amount = (float)($item["amount"] ?? 0);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $billingDate) || $billingNumber === '' || $amount === 0.0) {
                continue;
            }
            $db->exec(
                "INSERT INTO `imported_billings` (`week`, `projectId`, `billingDate`, `billingNumber`, `amount`, `creatorId`, `updaterId`)
                 VALUES (?, ?, ?, ?, ?, ?, ?);",
                [$week, $projectId, $billingDate, $billingNumber, round($amount, 2), $userId, $userId],
                __FILE__,
                __LINE__
            );
        }
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}

exit(json_encode(["msg" => "ok"]));
