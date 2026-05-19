<?php
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
try {
    if($_SERVER["REQUEST_METHOD"] !== "POST"){
        jsonResponse(405, ["error" => "Method Not Allowed"]);
    }
    $newUserId      = secureId();
    $verificationCode = secureId();
    $data = [
        "id"               => $newUserId,
        "email"            => strtolower(requireEmail($_POST, "userEmail", true)),
        "verificationCode" => $verificationCode,
        /* password        */
        /* isTmpPassword   */
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
        "projects"         => requireEnum($_POST, "projects",      ["edit", "view", "no"], true)         ?? "no",
        "workLogs"         => requireEnum($_POST, "workLogs",      ["yes", "no"], true)                  ?? "no",
        "purchases"        => requireEnum($_POST, "purchases",     ["approver", "paid", "edit", "view", "no"], true) ?? "no",
        "perDiem"          => requireEnum($_POST, "perDiem",       ["editAll", "edit", "view", "no"], true) ?? "no",
        "reports"          => requireEnum($_POST, "reports",       ["editAll", "edit", "view", "no"], true) ?? "no",
        "forms"            => requireEnum($_POST, "forms",         ["editAll", "edit", "view", "no"], true) ?? "no",
        "personel"         => requireEnum($_POST, "personel",      ["edit", "view", "no"], true)         ?? "no",
        "fleets"           => requireEnum($_POST, "fleets",        ["edit", "view", "no"], true)         ?? "no",
        "calendar"         => requireEnum($_POST, "calendar",      ["view", "no"], true)                 ?? "no",
        "timeOffs"         => requireEnum($_POST, "timeOffs",      ["approver", "editAll", "viewAll", "edit", "view"], true) ?? "view",
        "office"           => requireEnum($_POST, "office",        ["yes", "no"], true)                  ?? "no",
        "allOffice"        => requireEnum($_POST, "allOffice",     ["edit", "view", "no"], true)         ?? "no",
        "outside"          => requireEnum($_POST, "outside",       ["locator", "standby", "qew", "runner", "no"], true) ?? "no",
        "outsideStatus"    => requireEnum($_POST, "outsideStatus", ["edit", "view", "no"], true)         ?? "no",
                "metrics"          => requireEnum($_POST, "metrics",       ["all", "yes", "no"], true) ?? "no",
        "newspaper"        => requireEnum($_POST, "newspaper",     ["edit", "view", "no"], true)         ?? "no",
        // "community"     => requireEnum($_POST, "community",      ["editAll", "edit", "view", "no"], true) ?? "no",
        // "training"      => requireEnum($_POST, "training",       ["edit", "view", "no"], true)         ?? "no",
        // "workOut"       => requireEnum($_POST, "workOut",        ["yes", "no"], true)                  ?? "no",
        // "workLogNotification" => requireEnum($_POST, "workLogNotification", ["yes", "no"], true)       ?? "no",
        "dispatch"         => requireEnum($_POST, "dispatch",      ["yes", "no"], false)                 ?? "no",
        /* userTheme       */
        "creatorId"        => $userId,
        "userTheme"        => "#333333",
    ];
    $cols    = implode(", ", array_map(fn($c) => "`$c`", array_keys($data)));
    $params  = implode(", ", array_map(fn($c) => ":$c",  array_keys($data)));
    $sql     = "INSERT INTO `users` ($cols) VALUES ($params)";
    $db->begin();
    $db->exec($sql, $data, __FILE__, __LINE__);
    if($data["office"] === "yes" || $data["outside"] === "runner"){
        $db->exec(
            "INSERT INTO `timeCard` (`userId`) VALUES (?);",
            [$newUserId], __FILE__, __LINE__
        );
    }
    if($data["outside"] !== "no" && $data["outside"] !== "runner"){
        foreach(["outsidePOT", "outsideEOT", "outsideDaily"] as $table){
            $db->exec(
                "INSERT INTO `$table` (`userId`) VALUES (?);",
                [$newUserId], __FILE__, __LINE__
            );
        }
    }
    $db->commit();
    sendEmail([
        "path"      => basename(__FILE__)." ".__LINE__,
        "selfEmail" => $email,
        "db"        => $db,
        "to"        => $data["email"],
        "summary"   => "Construct Smarter App Verification",
        "body"      => "&nbsp;&nbsp;&nbsp;&nbsp;Click link below to finish verification process.<br/><br/>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <a href=\"http://$mainIP/$rootName/users/verification.php?email={$data['email']}&code=$verificationCode\">
            link
        </a>"
    ]);
    jsonResponse(200, ["id" => $newUserId]);
} catch (InvalidArgumentException $e) {
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);
} catch (Throwable $e) {
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
/*
$cats = explode(", ", $outsideTimeCards);
foreach($cats as &$cat){
    if($cat === "locator" || $cat === "qew"){
        $db->exec("INSERT INTO `outsideML` (`userId`) VALUES (?);", [$id], __FILE__, __LINE__);     
    }else if($cat === "standby"){
        $db->exec("INSERT INTO `outsideSB` (`userId`) VALUES (?);", [$id], __FILE__, __LINE__);
    }
}
*/