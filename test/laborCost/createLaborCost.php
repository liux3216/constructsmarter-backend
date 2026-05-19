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
    exit(json_encode(["msg" => "Invalid labor cost data."]));
}

$db->begin();
try {
    $db->exec(
        "DELETE FROM `labor_costs` WHERE `week` = ?;",
        [$week],
        __FILE__,
        __LINE__
    );

    foreach ($data as $projectId => $amount) {
        if (!preg_match('/^\d+$/', (string)$projectId)) {
            continue;
        }
        $numericAmount = (float)$amount;
        if ($numericAmount <= 0) {
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
        $db->exec(
            "INSERT INTO `labor_costs` (`week`, `projectId`, `amount`, `creatorId`, `updaterId`)
             VALUES (?, ?, ?, ?, ?);",
            [$week, $projectId, round($numericAmount, 2), $userId, $userId],
            __FILE__,
            __LINE__
        );
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}

exit(json_encode(["msg" => "ok"]));
