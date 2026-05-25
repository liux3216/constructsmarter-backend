<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
try{
    if($_SERVER["REQUEST_METHOD"] !== "POST"){
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }
    $id = requireField($_POST, "id", 1, 32, true);
    $voidReason = requireField($_POST, "voidReason", 1);
    $db->begin();
    $db->exec(
        "UPDATE `services` SET `void` = 'yes', `voidReason` = ?, `updaterId` = ? WHERE `id` = ?;",
        [$voidReason, $userId, $id],
        __FILE__, __LINE__
    );
    $db->commit();
    jsonResponse(200, ["id" => $id]);
}catch(InvalidArgumentException $e){
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);
}catch(Throwable $e){
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
