<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
try{
    if($_SERVER["REQUEST_METHOD"] !== "POST"){
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }
    $priceRaw = trim((string)($_POST["price"] ?? ""));
    if($priceRaw === "" || !is_numeric($priceRaw)){
        throw new InvalidArgumentException("price must be a valid number");
    }
    $data = [
        "code" => requireField($_POST, "code", 0, 100),
        "name" => requireField($_POST, "name", 1, 150),
        "category" => requireField($_POST, "category", 0, 100),
        "price" => number_format((float)$priceRaw, 2, ".", ""),
        "costType" => requireField($_POST, "costType", 1, 100),
        "notes" => requireField($_POST, "notes"),
        "void" => "no",
        "voidReason" => null,
        "validateReason" => null,
        "creatorId" => $userId,
    ];
    $columns = array_keys($data);
    $fields = implode(", ", array_map(fn($c) => "`$c`", $columns));
    $values = implode(", ", array_map(fn($c) => ":$c", $columns));
    $sql = "INSERT INTO `services` ($fields) VALUES ($values);";
    $db->begin();
    $db->exec($sql, $data, __FILE__, __LINE__);
    $id = (int)($db->one("SELECT LAST_INSERT_ID() AS `id`", [], __FILE__, __LINE__)["id"] ?? 0);
    $db->commit();
    jsonResponse(201, ["id" => $id]);
}catch(InvalidArgumentException $e){
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);
}catch(Throwable $e){
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
