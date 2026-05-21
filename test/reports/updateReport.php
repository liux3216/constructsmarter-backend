<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

function optionalReportInt(array $src, string $key): ?int
{
    if (!array_key_exists($key, $src) || trim((string)$src[$key]) === "") {
        return null;
    }
    return requireInt($src, $key, 0, null, false);
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }

    $action = requireEnum($_POST, "action", ["Add", "Update"], true, true);
    $projectId = requireInt(
        ["projectId" => $_POST["projectId"] ?? ""],
        "projectId",
        1,
        null,
        true
    );
    $startDate = requireDate($_POST, "startDate", true);
    $endDate = requireDate($_POST, "endDate", true);

    if ($endDate < $startDate) {
        jsonResponse(422, ["msg" => "End date must be on or after start date."]);
    }

    $data = [
        "projectId"     => $projectId,
        "startDate"     => $startDate,
        "endDate"       => $endDate,
        "pothole"       => optionalReportInt($_POST, "pothole"),
        "ep"            => optionalReportInt($_POST, "ep"),
        "manhole"       => optionalReportInt($_POST, "manhole"),
        "code"          => requireField($_POST, "code", 0, 255, false) ?? "",
        "priority"      => requireField($_POST, "priority", 0, 32, false) ?? "",
        "pending"       => requireEnum($_POST, "pending", ["Yes", "No"], false, false) ?? "No",
        "sup"           => requireEnum($_POST, "sup", ["Yes", "No"], false, false) ?? "No",
        "reportTechId"  => requireField($_POST, "reportTechId", 1, 32, true),
        "notes"         => requireField($_POST, "notes", 0, 99999, false) ?? "",
        "cadLocation"   => requireField($_POST, "cadLocation", 0, 99999, false) ?? "",
        "videoLocation" => requireField($_POST, "videoLocation", 0, 99999, false) ?? "",
    ];

    $reportTech = $db->one(
        "SELECT `id` FROM `users` WHERE `id` = ? AND `reports` = 'edit' AND `void` = 'no' LIMIT 1;",
        [$data["reportTechId"]],
        __FILE__,
        __LINE__
    );
    if (!$reportTech) {
        jsonResponse(422, ["msg" => "Report tech must be an active user with Reports edit permission."]);
    }

    if ($action === "add") {
        $insertData = $data + [
            "pdfId"     => "",
            "status"    => "Created",
            "creatorId" => $userId,
        ];
        $columns = array_keys($insertData);
        $columnSql = implode(", ", array_map(fn($c) => "`$c`", $columns));
        $valueSql = implode(", ", array_map(fn($c) => ":$c", $columns));

        $db->exec(
            "INSERT INTO `reports` ($columnSql) VALUES ($valueSql);",
            $insertData,
            __FILE__,
            __LINE__
        );
        $id = $db->lastInsertId();
        jsonResponse(200, ["id" => $id]);
    }

    $id = trim((string)($_POST["id"] ?? ""));
    if ($id === "") {
        jsonResponse(422, ["msg" => "Report id is required."]);
    }

    $exists = $db->one(
        "SELECT `id` FROM `reports` WHERE `id` = ? LIMIT 1;",
        [$id],
        __FILE__,
        __LINE__
    );
    if (!$exists) {
        jsonResponse(404, ["msg" => "Report not found."]);
    }

    $updateData = $data + [
        "updaterId" => $userId,
        "updatedAt" => date("Y-m-d H:i:s"),
        "id"        => $id,
    ];
    $setColumns = array_filter(array_keys($updateData), fn($c) => $c !== "id");
    $setSql = implode(", ", array_map(fn($c) => "`$c` = :$c", $setColumns));

    $db->exec(
        "UPDATE `reports` SET $setSql WHERE `id` = :id;",
        $updateData,
        __FILE__,
        __LINE__
    );

    jsonResponse(200, ["id" => $id]);

} catch (Throwable $e) {
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
