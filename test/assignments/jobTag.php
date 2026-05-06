<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__ . "/jobTagPDF.php";

function requireJobTagDateTime(array $src, string $key): ?string
{
    $value = requireField($src, $key, 0, 32, false);
    if ($value === null) {
        return null;
    }

    $value = str_replace("T", " ", $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        $value .= ":00";
    }

    $date = DateTime::createFromFormat("Y-m-d H:i:s", $value);
    if (!$date || $date->format("Y-m-d H:i:s") !== $value) {
        jsonResponse(409, ["msg" => "{$key} must be in format YYYY-MM-DDTHH:MM"]);
    }

    return $value;
}

function optionalJobTagSignature(array $src, array $keys): ?string
{
    foreach ($keys as $key) {
        $value = requireField($src, $key, 0, 2000000, false);
        if ($value !== null) {
            return $value;
        }
    }
    return null;
}

function requireJobTagCoords(array $src, string $key = "coords"): ?string
{
    $value = requireField($src, $key, 0, 255, false);
    if ($value === null) {
        return null;
    }
    if (!preg_match('/^-?\d+(?:\.\d+)?,\s*-?\d+(?:\.\d+)?,\s*\d+(?:\.\d+)?$/', $value)) {
        jsonResponse(422, ["msg" => "{$key} must be in format lat,long, accuracy"]);
    }
    return $value;
}

function requireJobTagStatus(array $src): string
{
    return requireEnum($src, "action", ["Saved", "Submitted"], true, false);
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }

    $id = requireInt($_POST, "assignmentId", 1, null, true);
    $assignment = $db->one(
        "SELECT `id`, `jobTagFileId` FROM `assignments` WHERE `id` = ?;",
        [$id],
        __FILE__,
        __LINE__
    );
    if (!$assignment) {
        jsonResponse(404, ["msg" => "The assignment is not found."]);
    }

    $data = [
        "travelStartTime" => requireJobTagDateTime($_POST, "travelStartTime"),
        "workStartTime"   => requireJobTagDateTime($_POST, "workStartTime"),
        "hadLunch"        => requireEnum($_POST, "hadLunch", ["yes", "no"], false, true),
        "workEndTime"     => requireJobTagDateTime($_POST, "workEndTime"),
        "travelEndTime"   => requireJobTagDateTime($_POST, "travelEndTime"),
        "workFinished"    => requireEnum($_POST, "workFinished", ["yes", "no", "unknown"], false, true),
        "workRequired"    => requireField($_POST, "workRequired", 0, 99999, false),
        "workPerformed"   => requireField($_POST, "workPerformed", 0, 99999, false),
        "additionalInfo"  => requireField($_POST, "additionalInfo", 0, 99999, false),
        "coords"          => requireJobTagCoords($_POST),
        "jobTagStatus"    => requireJobTagStatus($_POST),
        "updaterId"       => $userId,
        "updatedAt"       => date("Y-m-d H:i:s"),
    ];
    foreach (["isPreDriver", "isPostDriver", "hasPreTrailer", "hasPostTrailer"] as $field) {
        if (array_key_exists($field, $_POST)) {
            $data[$field] = requireEnum($_POST, $field, ["yes", "no"], false, true);
        }
    }
    $signatures = [
        "techSign" => optionalJobTagSignature($_POST, ["techSign", "technicianSignature"]),
        "clientSign" => optionalJobTagSignature($_POST, ["clientSign", "clientSupervisorSignature", "clientSignature"]),
    ];

    $setClause = implode(
        ", ",
        array_map(fn($c) => "`$c` = :$c", array_keys($data))
    );
    $data["id"] = $id;

    $db->begin();
    $db->exec("UPDATE `assignments` SET $setClause WHERE `id` = :id;", $data, __FILE__, __LINE__);
    $pdfId = generateJobTagPdf($id, $assignment["jobTagFileId"], $signatures);
    $db->exec(
        "UPDATE `assignments` SET `jobTagFileId` = ? WHERE `id` = ?;",
        [$pdfId, $id],
        __FILE__,
        __LINE__
    );
    $db->commit();

    jsonResponse(200, [
        "id" => $id,
        "pdfId" => $pdfId,
        "pdfUrl" => getObjectUrl($privateBucket, $pdfId, "jobTag_$id.pdf"),
    ]);

} catch (InvalidArgumentException $e) {
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);

} catch (Throwable $e) {
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
