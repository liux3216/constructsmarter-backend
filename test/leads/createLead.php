<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

try{
    if($_SERVER["REQUEST_METHOD"] !== "POST"){
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }
    $source = [
        "Outreach",
        "Patrner Referral",
        "Phone Enquiry",
        "Web",
        "Others"
    ];
    $status = [
        "Not Contacted",
        "Contacted",
        "Converted",
        "Disqualified"
    ];
    $data = [
        "organizationId"   => requireInt($_POST, "organizationId", null, null, false),
        "firstName"        => requireField($_POST, "firstName", 1, 150),
        "middleName"       => requireField($_POST, "middleName", 1, 150),
        "lastName"         => requireField($_POST, "lastName", 1, 150),
        "role"             => requireField($_POST, "role", 1, 255),
        "email"            => requireEmail($_POST, "email", false, 255),

        "website"          => requireWebsite($_POST, "website", 1, 255),
        "industry"         => requireField($_POST, "industry", 1, 255),
        "source"           => requireField($_POST, "source", 1, 255),
        "status"           => requireField($_POST, "status", 1, 255),
        "referredBy"       => requireField($_POST, "referredBy", 1, 50),
        "userResponsible1" => requireField($_POST, "userResponsible1", 1, 50),
        "userResponsible2" => requireField($_POST, "userResponsible2", 1, 50),

        "businessPhone"    => requirePhone334($_POST, "businessPhone"),
        "extension"        => requireExtension($_POST, "extension"),
        "fax"              => requirePhone334($_POST, "fax"),
        "mobile"           => requirePhone334($_POST, "mobile"),
        "street"           => requireField($_POST, "street", 1, 50),
        "city"             => requireField($_POST, "city", 1, 50),
        "state"            => requireField($_POST, "state", 1, 50),
        "zipCode"          => requireZipCode($_POST, "zipCode"),
        "overseaAddress"   => requireField($_POST, "overseaAddress", 1, 255),
        "background"       => requireField($_POST, "background"),
        "sent"             => 0,
        "creatorId"        => $userId,
    ];
    $columns = array_keys($data);
    $fields  = implode(", ", array_map(fn($c) => "`$c`", $columns));
    $values  = implode(", ", array_map(fn($c) => ":$c", $columns));
    $sql = "INSERT INTO `leads` ($fields) VALUES ($values);";
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
