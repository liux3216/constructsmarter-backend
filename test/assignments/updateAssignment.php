<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

function requireAssignmentCoords(array $src, string $key = "coords"): ?string
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

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }

    $id = requireInt($_POST, "id", null, null, true);
    $data = [
        "workId"        => requireInt($_POST, "workId", null, null, true),
        "userId"        => requireField($_POST, "userId", 1, 32, true),
        "laborCategory" => requireField($_POST, "laborCategory", 1, 255, true),
        "fleetNumber"   => requireField($_POST, "fleetNumber", 0, 255, false) ?? "",
        "perDiem"       => requireEnum($_POST, "perDiem", ["yes", "no"], true, true),
        "coords"        => requireAssignmentCoords($_POST),
        "updaterId"     => $userId,
        "updatedAt"     => date("Y-m-d H:i:s"),
    ];

    $setClause = implode(
        ", ",
        array_map(fn($c) => "`$c` = :$c", array_keys($data))
    );
    $data["id"] = $id;

    $db->begin();
    $db->exec("UPDATE `assignments` SET $setClause WHERE `id` = :id;", $data, __FILE__, __LINE__);
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
