<?php
require_once "/opt/bitnami/apache/htdocs/test/functions.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

function parseCompetencyServiceIds(array $input): array {
    $values = array_key_exists("competencyServiceIds", $input) ? $input["competencyServiceIds"] : [];
    if(!is_array($values)) $values = [$values];
    $serviceIds = [];
    foreach($values as $value){
        $serviceId = (int)$value;
        if($serviceId > 0) $serviceIds[] = $serviceId;
    }
    return array_values(array_unique($serviceIds));
}

try {
    if($_SERVER["REQUEST_METHOD"] !== "POST"){
        jsonResponse(405, ["error" => "Method Not Allowed"]);
    }
    if(!array_key_exists("curUserId", $_POST)){
        jsonResponse(409, ["msg" => "Missing user id"]);
    }
    $targetUserId = (string)$_POST["curUserId"];
    $competencyServiceIds = parseCompetencyServiceIds($_POST);
    $data = [
        "id"               => $targetUserId,
        // "email"            => strtolower(requireEmail($_POST, "userEmail", true)),
        // "verificationCode" => secureId(), 
        /* password	*/
	    /* isTmpPassword */
        "firstName"        => requireField($_POST, "firstName", 1, 50, true),
        "middleName"       => requireField($_POST, "middleName", 1, 50),
        "lastName"         => requireField($_POST, "lastName", 1, 50, true),
        "region"           => requireField($_POST, "region", 1, 50),
        "department"       => requireField($_POST, "department", 1, 100),
        "role"             => requireField($_POST, "role", 1, 50),
        "phoneNumber"      => requirePhone334($_POST, "phoneNumber"),
        "workPhone"        => requirePhone334($_POST, "workPhone"),
        "extension"        => requireExtension($_POST, "extension", false),
        "driverLicense"    => requireDriverLicense($_POST, "driverLicense", false),
        "ssn"              => requireSSN($_POST, "ssn", false),
        "birthDay"         => requireDate($_POST, "birthDay", false, "1900-01-01", date("Y-m-d")),
        "hireDate"         => requireDate($_POST, "hireDate", true, "1900-01-01"),
        "quitDate"         => requireDate($_POST, "quitDate"),
        "phaseLevel"       => requireField($_POST, "phaseLevel", 1, 50),
        "unionName"        => requireField($_POST, "unionName", 1, 100),
        "invoiceNumber"    => array_key_exists("invoiceNumber", $_POST) ? $_POST["invoiceNumber"] : null,
        "lanId"            => array_key_exists("lanId", $_POST) ? strtoupper($_POST["lanId"]) : null,
        "residence"        => requireField($_POST, "residence", 1, 255),
        "residenceState"   => requireField($_POST, "residenceState", 1, 50),
        "street"           => requireField($_POST, "street", 1, 255),
        "zipCode"          => requireField($_POST, "zipCode", 1, 20),
        "address"          => requireField($_POST, "address", 1, 255),
        "background"       => requireField($_POST, "background"), 
        "projects"         => requireEnum($_POST, "projects",      ["edit", "view", "no"], true) ?? "no",
        "assignments"      => requireEnum($_POST, "assignments",   ["yes", "no"], true) ?? "no",
        "purchases"        => requireEnum($_POST, "purchases",     ["approver", "paid", "edit", "view", "no"], true) ?? "no",
        "perDiem"          => requireEnum($_POST, "perDiem",       ["editAll", "edit", "view", "no"], true) ?? "no",
        "reports"          => requireEnum($_POST, "reports",       ["editAll", "edit", "view", "no"], true) ?? "no",
        "forms"            => requireEnum($_POST, "forms",         ["editAll", "edit", "view", "no"], true) ?? "no",
        "personel"         => requireEnum($_POST, "personel",      ["edit", "view", "no"], true) ?? "no",
        "fleets"           => requireEnum($_POST, "fleets",        ["edit", "view", "no"], true) ?? "no",
        "calendar"         => requireEnum($_POST, "calendar",      ["view", "no"], true) ?? "no",
        "timeOffs"         => requireEnum($_POST, "timeOffs",      ["approver", "editAll", "viewAll", "edit", "view"], true) ?? "view",
        "office"           => requireEnum($_POST, "office",        ["yes", "no"], true) ?? "no",
        "allOffice"        => requireEnum($_POST, "allOffice",     ["edit", "view", "no"], true) ?? "no",
        "outside"          => requireEnum($_POST, "outside",       ["locator", "standby", "qew", "runner", "no"], true) ?? "no",
        "outsideStatus"    => requireEnum($_POST, "outsideStatus", ["edit", "view", "no"], true) ?? "no",
                "metrics"          => requireEnum($_POST, "metrics",       ["all", "yes", "no"], true) ?? "no",
        "newspaper"        => requireEnum($_POST, "newspaper",     ["edit", "view", "no"], true) ?? "no",
        // community => requireEnum($_POST, "outsideStatus", ["editAll", "edit", "view", "no"], true) ?? "no",
        // training => requireEnum($_POST, "outsideStatus", ["edit", "view", "no"], true) ?? "no",
        // workOut => requireEnum($_POST, "outsideStatus", ["yes", "no"], true) ?? "no",
        // assignmentNotification => requireEnum($_POST, "outsideStatus", ["yes", "no"], true) ?? "no",
        "dispatch"         => requireEnum($_POST, "dispatch",      ["yes", "no"], false) ?? "no",
        /* userTheme */

        "updaterId"        => $userId,
    ];
    if(count($competencyServiceIds)){
        $placeholders = implode(", ", array_fill(0, count($competencyServiceIds), "?"));
        $serviceRows = $db->all(
            "SELECT `id` FROM `services` WHERE `void` = 'no' AND `id` IN ($placeholders);",
            $competencyServiceIds,
            __FILE__,
            __LINE__
        );
        if(count($serviceRows) !== count($competencyServiceIds)){
            throw new InvalidArgumentException("Invalid competency services.");
        }
    }
    $updateData = $data;
    unset($updateData["id"]);
    $setParts = [];
    foreach($updateData as $col => $_){
        $setParts[] = "`$col` = :$col";
    }
    $setSql = implode(", ", $setParts);
    $sql = "UPDATE `users` SET $setSql WHERE `id` = :id";
    $db->begin();
    if($data["office"] === "no"){
        $db->exec(
            "DELETE FROM `timeCard` WHERE `userId` = ?;",
            [$targetUserId], __FILE__, __LINE__
        );
    } else {
        $db->exec(
            "INSERT IGNORE INTO `timeCard` (`userId`) VALUES (?);",
            [$targetUserId], __FILE__, __LINE__
        );
    }
    if($data["outside"] === "no" || $data["outside"] === "runner"){
        foreach(["outsideML", "outsideSB", "outsideEOT", "outsidePOT", "outsideDaily"] as $table){
            $db->exec(
                "DELETE FROM `$table` WHERE `userId` = ?;",
                [$targetUserId], __FILE__, __LINE__
            );
        }
    } else {
        foreach(["outsideEOT", "outsidePOT", "outsideDaily"] as $table){
            $db->exec(
                "INSERT IGNORE INTO `$table` (`userId`) VALUES (?);",
                [$targetUserId], __FILE__, __LINE__
            );
        }
    }
    $db->exec($sql, $data, __FILE__, __LINE__);
    $db->exec(
        "DELETE FROM `users_competency` WHERE `userId` = ?;",
        [$targetUserId],
        __FILE__,
        __LINE__
    );
    foreach($competencyServiceIds as $serviceId){
        $db->exec(
            "INSERT INTO `users_competency` (`userId`, `serviceId`) VALUES (?, ?);",
            [$targetUserId, $serviceId],
            __FILE__,
            __LINE__
        );
    }
    $db->commit();
    jsonResponse(200, ["id" => $targetUserId]);
} catch (InvalidArgumentException $e) {
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);
} catch (Throwable $e) {
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
/*
// for outside or runner
$preCats = explode(", ", $prevOutsideTimeCards);
$cats = explode(", ", $outsideTimeCards);
$toAdd = array_diff($cats, $preCats);
$toDelete = array_diff($preCats, $cats);
foreach($toDelete as &$cat){
    if($cat === "locator" || $cat === "qew"){
        $row = $db->one("SELECT * FROM `outsideML` WHERE `userId` = ?;", [$curUserId], __FILE__, __LINE__);
        if($row){
            $empty = true;
            foreach($row as $key => $value){
                if($key !== "userId" && $value){
                    $empty = false;
                }
            }
            if($empty) $db->exec("DELETE FROM `outsideML` WHERE `userId` = ?;", [$curUserId], __FILE__, __LINE__);
        }
    }else if($cat === "standby"){
        $row = $db->one("SELECT * FROM `outsideSB` WHERE `userId` = ?;", [$curUserId], __FILE__, __LINE__);
        if($row){
            $empty = true;
            foreach ($row as $key => $value) {
                if($key !== "userId" && $value){
                    $empty = false;
                }
            }
            if($empty) $db->exe("DELETE FROM `outsideSB` WHERE `userId` = ?;", [$curUserId], __FILE__, __LINE__);
        }
    }
}
foreach($toAdd as &$cat){
    if($cat === "locator" || $cat === "qew"){
        $db->exec("INSERT IGNORE INTO `outsideML` (`userId`) VALUES (?);", [$curUserId], __FILE__, __LINE__);
    }else if($cat === "standby"){
        $db->exec("INSERT IGNORE INTO `standby` (`userId`) VALUES (?);", [$curUserId], __FILE__, __LINE__);
    }
}
*/