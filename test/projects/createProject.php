<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

try{
    if($_SERVER["REQUEST_METHOD"] !== "POST"){
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }
    $data = [
        "region"                 => requireField($_POST, "region", 1, 150),
        "pipeline"               => requireField($_POST, "pipeline", 1, 150),
        "subPipeline"            => requireField($_POST, "subPipeline", 0, 150, false) ?? "",
        "reportNeeded"           => requireField($_POST, "reportNeeded", 1, 10),
        "stage"                  => requireField($_POST, "stage", 1, 150),
        "projectNumber"          => requireField($_POST, "projectNumber", 1, 150),
        "organizationId"         => requireInt($_POST, "organizationId", null, null, false),
        "clientProjectNumber"    => requireField($_POST, "clientProjectNumber", 1, 150),
        "projectManagerId"       => requireField($_POST, "projectManagerId", 1, 150),
        "clientPONumber"         => requireField($_POST, "clientPONumber", 0, 150, false) ?? "",
        "usaTicketNumber"        => requireField($_POST, "usaTicketNumber", 0, 150, false) ?? "",
        "usaTicketDate"          => requireDate($_POST, "usaTicketDate", false),
        "location"               => requireField($_POST, "location", 0, 255, false) ?? "",
        "coords"                 => requireField($_POST, "coords", 0, 150, false) ?? "",
        "nearestMedicalFacility" => requireField($_POST, "nearestMedicalFacility", 0, 255, false) ?? "",
        "billingType"            => requireField($_POST, "billingType", 0, 50, false) ?? "",
        "days"                   => requireInt($_POST, "days", null, null, false),
        "laborHours"             => requireInt($_POST, "laborHours", null, null, false),
        "materialCost"           => requireInt($_POST, "materialCost", null, null, false),
        "budgets"                => requireField($_POST, "budgets", 0, 9999, false) ?? "[]",
        "description"            => requireField($_POST, "description", 0, 99999, false) ?? "",
        "folderId"               => secureId(),
        "opportunityId"          => requireInt($_POST, "opportunityId", null, null, false),
        "proposalId"             => requireInt($_POST, "proposalId", null, null, false),
        "prevailing"             => (isset($_POST["prevailing"]) && strtolower($_POST["prevailing"]) === "yes") ? "yes" : "no",
        "cpr"                    => (isset($_POST["cpr"]) && strtolower($_POST["cpr"]) === "yes") ? "yes" : "no",
        "dirNumber"              => requireField($_POST, "dirNumber", 0, 150, false) ?? "",
        "accurateTime"           => (isset($_POST["accurateTime"]) && $_POST["accurateTime"] === "yes") ? "yes" : "no",
        "clientSignatureRequired"=> (isset($_POST["clientSignatureRequired"]) && $_POST["clientSignatureRequired"] === "yes") ? "yes" : "no",
        "sendToClient"           => (isset($_POST["sendToClient"]) && $_POST["sendToClient"] === "yes") ? "yes" : "no",
        "notes"                  => requireField($_POST, "notes", 0, 99999, false) ?? "",
        "creatorId"              => $userId,
    ];
    $columns = array_keys($data);
    $fields  = implode(", ", array_map(fn($c) => "`$c`", $columns));
    $values  = implode(", ", array_map(fn($c) => ":$c", $columns));
    $sql = "INSERT INTO `projects` ($fields) VALUES ($values);";
    $db->begin();
    $db->exec($sql, $data, __FILE__, __LINE__);
    $id = $db->lastInsertId();
    $contactIds = json_decode($_POST["contactIds"] ?? "[]", true);
    $db->syncJunction('projects_contact', 'projectId', $id, 'contactId', is_array($contactIds) ? $contactIds : []);
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
