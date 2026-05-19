<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
try{
    if($_SERVER["REQUEST_METHOD"] !== "POST"){
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }
    $id = requireField($_POST, "id", 1, 32, true);
    $data = [
        "vendorName" => requireField($_POST, "vendorName", 1, 150),
        "website" => requireWebsite($_POST, "website", 0, 255),
        "phoneNumber" => requirePhone334($_POST, "phoneNumber"),
        "extension" => requireExtension($_POST, "extension"),
        "fax" => requirePhone334($_POST, "fax"),
        "country" => requireField($_POST, "country", 0, 100),
        "street" => requireField($_POST, "street", 0, 100),
        "city" => requireField($_POST, "city", 0, 50),
        "state" => requireField($_POST, "state", 0, 50),
        "zipCode" => requireField($_POST, "zipCode", 0, 20),
        "background" => requireField($_POST, "background"),
        "updaterId" => $userId,
    ];
    $setClause = implode(", ", array_map(fn($c) => "`$c` = :$c", array_keys($data)));
    $data["id"] = $id;
    $db->begin();
    $db->exec("UPDATE `vendors` SET $setClause WHERE `id` = :id;", $data, __FILE__, __LINE__);
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
