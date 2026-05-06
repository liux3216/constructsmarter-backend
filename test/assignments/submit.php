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
        "SELECT `id` FROM `assignments` WHERE `id` = ? AND `void` = 'no';",
        [$id],
        __FILE__,
        __LINE__
    );
    if (!$assignment) {
        jsonResponse(404, ["msg" => "The assignment is not found."]);
    }

    $now = date("Y-m-d H:i:s");
    $db->exec(
        "UPDATE `assignments`
        SET `status` = 'Submitted',
            `updaterId` = ?,
            `updatedAt` = ?
        WHERE `id` = ?;",
        [$userId, $now, $id],
        __FILE__,
        __LINE__
    );
} catch (InvalidArgumentException $e) {
    jsonResponse(422, ["msg" => $e->getMessage()]);

} catch (Throwable $e) {
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
