<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }
    // ID is required for update
    $id = requireInt($_POST, "id", null, null, true);
    // Build update data
    $data = [
        "organizationId"  => requireInt($_POST, "organizationId", null, null, false),
        "opportunityName" => requireField($_POST, "opportunityName", 1, 150),
        "probability"     => requireInt($_POST, "probability"),
        "bidType"         => requireField($_POST, "bidType", 1, 150),
        "bidAmount"       => requireInt($_POST, "bidAmount", null, null, false),
        "category"        => requireField($_POST, "category", 1, 150),
        "state"           => requireField($_POST, "state", 1, 150),
        "location"        => requireField($_POST, "location", 1, 150),
        "actualCloseDate" => requireDate($_POST, "actualCloseDate", false),
        "background"      => requireField($_POST, "background"),
        "updaterId"       => $userId
    ];
    if (empty($data)) {
        jsonResponse(400, ["msg" => "No fields to update."]);
    }
    // Build dynamic SET clause
    $setClause = implode(
        ", ",
        array_map(fn($c) => "`$c` = :$c", array_keys($data))
    );
    $data["id"] = $id;
    $db->begin();
    $db->exec("UPDATE `opportunities` SET $setClause WHERE `id` = :id;", $data, __FILE__, __LINE__);
    $db->syncJunction('opportunities_contact', 'opportunityId', $id, 'contactId', json_decode($_POST["contactIds"], true));
    $db->syncJunction('opportunities_userResponsible', 'opportunityId', $id, 'userId', json_decode($_POST["userIds"], true));
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
