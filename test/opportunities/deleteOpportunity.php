<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = (int)($_POST["id"] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit(json_encode(["msg" => "Invalid opportunity id."]));
}
//-------------------------------------------------
$db->exec(
    "UPDATE `projects` SET `opportunityId` = NULL WHERE `opportunityId` = ?;",
    [$id],
    __FILE__,
    __LINE__
);
$db->exec(
    "DELETE FROM `opportunities_contact` WHERE `opportunityId` = ?;",
    [$id],
    __FILE__,
    __LINE__
);
$db->exec(
    "DELETE FROM `opportunities_userResponsible` WHERE `opportunityId` = ?;",
    [$id],
    __FILE__,
    __LINE__
);
$db->exec(
    "DELETE FROM `opportunities` WHERE `id` = ?;",
    [$id],
    __FILE__,
    __LINE__
);
exit(json_encode(["id" => $id]));
