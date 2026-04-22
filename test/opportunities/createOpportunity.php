<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

try{
    if($_SERVER["REQUEST_METHOD"] !== "POST"){
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }
    $bidType = ["Fixed Bid", "Per Day"];
    $category = ["Locating","Locating & CCTV","Locating & Potholing","Locating & Surveying","Potholing","CCTV","CCTV & Potholing","Surveying","Scanning","SoCal Opportunities","Lunch & Learn Session","New Business","Proposal"];
    $state = ["OPEN","WON","LOST","SUSPENDED","ABANDONED"];
    
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
        "creatorId"        => $userId,
    ];
    $columns = array_keys($data);
    $fields  = implode(", ", array_map(fn($c) => "`$c`", $columns));
    $values  = implode(", ", array_map(fn($c) => ":$c", $columns));
    $sql = "INSERT INTO `opportunities` ($fields) VALUES ($values);";
    $db->begin();
    $db->exec($sql, $data, __FILE__, __LINE__);
    $id = $db->lastInsertId();
    $db->syncJunction('opportunities_contact', 'opportunityId', $id, 'contactId', json_decode($_POST["contactIds"], true));
    $db->syncJunction('opportunities_userResponsible', 'opportunityId', $id, 'userId', json_decode($_POST["userIds"], true));
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
