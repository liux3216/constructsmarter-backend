<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

function requireDateTimeField(array $src, string $key, bool $required = false): ?string
{
    if (!array_key_exists($key, $src)) {
        if ($required) {
            jsonResponse(422, ["msg" => "$key is required."]);
        }
        return null;
    }

    if (is_array($src[$key]) || is_object($src[$key])) {
        jsonResponse(422, ["msg" => "$key must be a valid date time."]);
    }

    $value = trim((string)$src[$key]);
    if ($value === "") {
        if ($required) {
            jsonResponse(422, ["msg" => "$key cannot be empty."]);
        }
        return null;
    }

    $dt = DateTime::createFromFormat("Y-m-d H:i", $value);
    $errors = DateTime::getLastErrors();
    if (
        !$dt ||
        ($errors !== false && ($errors["warning_count"] > 0 || $errors["error_count"] > 0)) ||
        $dt->format("Y-m-d H:i") !== $value
    ) {
        jsonResponse(422, ["msg" => "$key must be in format YYYY-MM-DD HH:MM."]);
    }

    return $dt->format("Y-m-d H:i:s");
}

function requireLabors(array $src): array
{
    if (!array_key_exists("labors", $src) || $src["labors"] === "") {
        return [];
    }

    $rawLabors = $src["labors"];
    if (is_string($rawLabors)) {
        $decoded = json_decode($rawLabors, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            jsonResponse(422, ["msg" => "labors must be a valid JSON array."]);
        }
        $rawLabors = $decoded;
    }

    if (!is_array($rawLabors)) {
        jsonResponse(422, ["msg" => "labors must be an array."]);
    }

    $labors = [];
    foreach ($rawLabors as $index => $labor) {
        if (is_string($labor)) {
            $labor = json_decode($labor, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($labor)) {
                jsonResponse(422, ["msg" => "labors[$index] must be a valid JSON object."]);
            }
        }

        if (!is_array($labor)) {
            jsonResponse(422, ["msg" => "labors[$index] must be an object."]);
        }

        $labors[] = [
            "userId"        => requireField($labor, "userId", 1, 32, true),
            "laborCategory" => requireField($labor, "laborCategory", 1, 255, true),
            "fleetNumber"   => requireField($labor, "fleetNumber", 0, 255, false) ?? "",
            "perDiem"       => requireEnum($labor, "perDiem", ["yes", "no"], true, true),
        ];
    }

    return $labors;
}

function insertAssignments(DB $db, $workId, array $labors, string $creatorId): void
{
    foreach ($labors as $labor) {
        $db->exec(
            "INSERT INTO `assignments`
            (`workId`, `userId`, `laborCategory`, `fleetNumber`, `perDiem`, `creatorId`)
            VALUES (?, ?, ?, ?, ?, ?);",
            [
                $workId,
                $labor["userId"],
                $labor["laborCategory"],
                $labor["fleetNumber"],
                $labor["perDiem"],
                $creatorId,
            ],
            __FILE__,
            __LINE__
        );
    }
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }

    $id = requireInt($_POST, "id", null, null, true);
    $hasLabors = array_key_exists("labors", $_POST);
    $labors = requireLabors($_POST);

    $data = [
        "projectId"       => requireInt($_POST, "projectId", null, null, true),
        "category"        => requireField($_POST, "category", 1, 255, true),
        "location"        => requireField($_POST, "location", 1, 255, true),
        "coords"          => requireField($_POST, "coords", 0, 255, false) ?? "",
        "startTime"       => requireDateTimeField($_POST, "startTime", true),
        "endTime"         => requireDateTimeField($_POST, "endTime", true),
        "allDay"          => requireEnum($_POST, "allDay", ["yes", "no"], true, true),
        "supervisorId"    => requireField($_POST, "supervisorId", 0, 32, false),
        "siteContactId"   => requireInt($_POST, "siteContactId", null, null, false),
        "cadRequired"     => requireEnum($_POST, "cadRequired", ["yes", "no"], false, true),
        "reportRequired"  => requireEnum($_POST, "reportRequired", ["yes", "no"], false, true),
        "waiveJSA"        => requireEnum($_POST, "waiveJSA", ["yes", "no"], true, true),
        "leadId"          => requireField($_POST, "leadId", 0, 32, false),
        "description"     => requireField($_POST, "description", 0, 99999, false) ?? "",
        "updaterId"       => $userId,
        "updatedAt"       => date("Y-m-d H:i:s"),
    ];

    if (strtotime($data["endTime"]) < strtotime($data["startTime"])) {
        jsonResponse(422, ["msg" => "endTime must be after startTime."]);
    }

    $setClause = implode(
        ", ",
        array_map(fn($c) => "`$c` = :$c", array_keys($data))
    );
    $data["id"] = $id;

    $db->begin();
    $db->exec("UPDATE `works` SET $setClause WHERE `id` = :id;", $data, __FILE__, __LINE__);
    if ($hasLabors) {
        $db->exec("DELETE FROM `assignments` WHERE `workId` = ?;", [$id], __FILE__, __LINE__);
        insertAssignments($db, $id, $labors, $userId);
    }
    $db->commit();

    jsonResponse(200, ["id" => $id]);

} catch (InvalidArgumentException $e) {
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);

} catch (Throwable $e) {
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
