<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
try{
    if($_SERVER["REQUEST_METHOD"] !== "POST"){
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }
    $id = requireInt($_POST, "id", 1, null, true);
    $preOrPost = requireEnum($_POST, "preOrPost", ["pre", "post"], true, true);
    $map = ["pre" => "Pre", "post" => "Post"];
    $key = "has".$map[$preOrPost]."Trailer";
    $hasTrailer = requireEnum($_POST, "hasTrailer", ["yes", "no"], true, true);
    $dataKey = $preOrPost."TrailerData";
    $data = null;
    $vehicleId = null;
    if($hasTrailer === "yes"){
        $data = requireField($_POST, "trailerData");
        $vehicleId = requireInt($_POST, "trailerId", 0, null, false);
    }
    $assignment = $db->one(
        "SELECT `id` FROM `assignments` WHERE `id` = ?;",
        [$id],
        __FILE__,
        __LINE__
    );
    if(!$assignment){
        jsonResponse(404, ["msg" => "The assignment is not found."]);
    }
    $sqlData = [
        $key => $hasTrailer, 
        $dataKey => $data, 
        "{$preOrPost}TrailerId" => $vehicleId
    ];
    $setClause = implode(", ", array_map(fn($column) => "`$column` = :$column", array_keys($sqlData)));
    $sqlData["id"] = $id;
    $db->begin();
    $db->exec("UPDATE `assignments` SET $setClause WHERE `id` = :id;", $sqlData, __FILE__, __LINE__);
    $db->commit();
}catch(InvalidArgumentException $e){
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);
}catch(Throwable $e){
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
