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

function requireTechnicians(array $src): array
{
    if (!array_key_exists("technicians", $src) || $src["technicians"] === "") {
        return [];
    }

    $rawTechnicians = $src["technicians"];
    if (is_string($rawTechnicians)) {
        $decoded = json_decode($rawTechnicians, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            jsonResponse(422, ["msg" => "technicians must be a valid JSON array."]);
        }
        $rawTechnicians = $decoded;
    }

    if (!is_array($rawTechnicians)) {
        jsonResponse(422, ["msg" => "technicians must be an array."]);
    }

    $technicians = [];
    foreach ($rawTechnicians as $index => $technician) {
        if (is_string($technician)) {
            $technician = json_decode($technician, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($technician)) {
                jsonResponse(422, ["msg" => "technicians[$index] must be a valid JSON object."]);
            }
        }

        if (!is_array($technician)) {
            jsonResponse(422, ["msg" => "technicians[$index] must be an object."]);
        }

        $assignmentId = requireInt($technician, "assignmentId", 1, null, false);
        if ($assignmentId === null && array_key_exists("id", $technician)) {
            $assignmentId = requireInt($technician, "id", 1, null, false);
        }

        $technicians[] = [
            "assignmentId"  => $assignmentId,
            "userId"        => requireField($technician, "userId", 1, 32, true),
            "laborCategory" => requireField($technician, "laborCategory", 1, 255, true),
            "fleetNumber"   => requireField($technician, "fleetNumber", 0, 255, false) ?? "",
            "perDiem"       => requireEnum($technician, "perDiem", ["yes", "no"], true, true),
        ];
    }

    return $technicians;
}

function syncAssignments(DB $db, $workId, array $technicians, string $userId): void
{
    $existingRows = $db->all(
        "SELECT `id` FROM `assignments` WHERE `workId` = ?;",
        [$workId],
        __FILE__,
        __LINE__
    );
    $existingIds = array_map(fn($row) => (int)$row["id"], $existingRows);
    $submittedIds = [];

    foreach ($technicians as $technician) {
        if ($technician["assignmentId"] !== null) {
            $assignmentId = (int)$technician["assignmentId"];
            if (in_array($assignmentId, $submittedIds, true)) {
                throw new InvalidArgumentException("Duplicate technician assignmentId: {$assignmentId}");
            }
            if (!in_array($assignmentId, $existingIds, true)) {
                throw new InvalidArgumentException("Invalid technician assignmentId: {$assignmentId}");
            }

            $submittedIds[] = $assignmentId;
            $db->exec(
                "UPDATE `assignments`
                SET `userId` = ?, `laborCategory` = ?, `fleetNumber` = ?, `perDiem` = ?, `updaterId` = ?, `updatedAt` = ?
                WHERE `id` = ? AND `workId` = ?;",
                [
                    $technician["userId"],
                    $technician["laborCategory"],
                    $technician["fleetNumber"],
                    $technician["perDiem"],
                    $userId,
                    date("Y-m-d H:i:s"),
                    $assignmentId,
                    $workId,
                ],
                __FILE__,
                __LINE__
            );
            continue;
        }

        $db->exec(
            "INSERT INTO `assignments`
            (`workId`, `userId`, `laborCategory`, `fleetNumber`, `perDiem`, `creatorId`)
            VALUES (?, ?, ?, ?, ?, ?);",
            [
                $workId,
                $technician["userId"],
                $technician["laborCategory"],
                $technician["fleetNumber"],
                $technician["perDiem"],
                $userId,
            ],
            __FILE__,
            __LINE__
        );
    }

    $deleteIds = array_values(array_diff($existingIds, $submittedIds));
    if ($deleteIds) {
        $placeholders = implode(", ", array_fill(0, count($deleteIds), "?"));
        $db->exec(
            "DELETE FROM `assignments` WHERE `workId` = ? AND `id` IN ($placeholders);",
            [$workId, ...$deleteIds],
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
    $hasTechnicians = array_key_exists("technicians", $_POST);
    $technicians = requireTechnicians($_POST);

    $data = [
        "projectId"       => requireInt($_POST, "projectId", null, null, true),
        "category"        => requireField($_POST, "category", 1, 255, true),
        "subCategory"     => requireField($_POST, "subCategory", 0, 255, false) ?? "",
        "location"        => requireField($_POST, "location", 1, 255, true),
        "jobTagLocation"  => requireEnum($_POST, "jobTagLocation", ["yes", "no"], false, true) ?? "no",
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
    if ($hasTechnicians) {
        syncAssignments($db, $id, $technicians, $userId);
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
