<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

try{
    if($_SERVER["REQUEST_METHOD"] !== "POST"){
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }
    $data = [
        "name"           => requireField($_POST, "name", 1, 150),
        "website"        => requireWebsite($_POST, "website", 0, 255), 
        "phoneNumber"    => requirePhone334($_POST, "phoneNumber"),
        "extension"      => requireExtension($_POST, "extension"),
        "fax"            => requirePhone334($_POST, "fax"),
        "street"         => requireField($_POST, "street", 1, 50),
        "city"           => requireField($_POST, "city", 1, 50),
        "state"          => requireField($_POST, "state", 1, 50),
        "zipCode"        => requireField($_POST, "zipCode", 1, 50),
        "overseaAddress" => requireField($_POST, "overseaAddress", 1, 255),
        "background"     => requireField($_POST, "background"),
        "creatorId"      => $userId,
    ];
    $columns = array_keys($data);
    $fields  = implode(", ", array_map(fn($c) => "`$c`", $columns));
    $values  = implode(", ", array_map(fn($c) => ":$c", $columns));
    $sql = "INSERT INTO `organizations` ($fields) VALUES ($values);";
    $db->begin();
    $db->exec($sql, $data, __FILE__, __LINE__);
    $id = $db->lastInsertId();
    $db->commit();
    jsonResponse(201, ["id" => $id]);
}catch(InvalidArgumentException $e){
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);

} catch (Throwable $e) {
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
