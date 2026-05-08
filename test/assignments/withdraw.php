<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }

    $id = requireInt($_POST, "id", 1, null, false);
    if ($id === null) {
        $id = requireInt($_POST, "assignmentId", 1, null, true);
    }

    $assignment = $db->one(
        "SELECT `id`, `status` FROM `assignments` WHERE `id` = ? AND `void` = 'no' LIMIT 1;",
        [$id],
        __FILE__,
        __LINE__
    );
    if (!$assignment) {
        jsonResponse(404, ["msg" => "The assignment is not found."]);
    }
    if ($assignment["status"] !== "Submitted") {
        jsonResponse(422, ["msg" => "Only submitted assignments can be withdrawn."]);
    }

    $now = date("Y-m-d H:i:s");
    $db->exec(
        "UPDATE `assignments`
         SET `status` = 'Created',
             `updaterId` = ?,
             `updatedAt` = ?
         WHERE `id` = ?;",
        [$userId, $now, $id],
        __FILE__,
        __LINE__
    );

    exit(json_encode(["id" => $id]));

} catch (InvalidArgumentException $e) {
    jsonResponse(422, ["msg" => $e->getMessage()]);

} catch (Throwable $e) {
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
