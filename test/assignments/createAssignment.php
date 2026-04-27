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

    $data = [
        "workId"        => requireInt($_POST, "workId", null, null, true),
        "userId"        => requireField($_POST, "userId", 1, 32, true),
        "laborCategory" => requireField($_POST, "laborCategory", 1, 255, true),
        "fleetNumber"   => requireField($_POST, "fleetNumber", 0, 255, false) ?? "",
        "perDiem"       => requireEnum($_POST, "perDiem", ["yes", "no"], true, true),
        "coords"        => requireAssignmentCoords($_POST),
        "creatorId"     => $userId,
    ];

    $columns = array_keys($data);
    $fields = implode(", ", array_map(fn($c) => "`$c`", $columns));
    $values = implode(", ", array_map(fn($c) => ":$c", $columns));
    $sql = "INSERT INTO `assignments` ($fields) VALUES ($values);";

    $db->begin();
    $db->exec($sql, $data, __FILE__, __LINE__);
    $id = $db->lastInsertId();
    $db->commit();

    jsonResponse(201, ["id" => $id]);

} catch (InvalidArgumentException $e) {
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);

} catch (Throwable $e) {
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
