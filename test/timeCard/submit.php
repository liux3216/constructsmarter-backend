<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php"; // sendEmail
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $mainUrl
require_once "functions.php"; // isValidWeekNum, defaultEmptyData, getDayName, getDateFromWeekNum
if(
    !array_key_exists("week", $_POST)
) jsonResponse(409, ["msg" => "Missing parameters"]);
$week = $_POST["week"];
if (!isValidWeekNum($week)) jsonResponse(422, ["msg" => "Invalid week."]);
$approverEmail = "d.agama@bessmti.com";
if(array_key_exists("employeeId", $_POST)){
    // todo: check permission
    $employeeId = $_POST["employeeId"];
    $employee = $db->one("SELECT CONCAT_WS(\" \", `firstName`, `middleName`, `lastName`) AS `userName` FROM `users` WHERE `id` = ?", [$employeeId], __FILE__, __LINE__);
    if(!$employee) jsonResponse(404, ["msg" => "Employee not found."]);
    $employeeName = $employee["userName"];
}else{
    $employeeId = $userId;
    $employeeName = $userName;
}
try{
    $db->begin();
    $existing = $db->one(
        "SELECT `data` FROM `timeCard` WHERE `userId` = ? AND `week` = ? FOR UPDATE",
        [$employeeId, $week],
        __FILE__, __LINE__
    );
    if(!$existing) $data = defaultEmptyData();
    else $data = json_decode($existing["data"], true);
    if($data["status"] !== "Created" && $data["status"] !== "Rejected"){
        throw new InvalidArgumentException("Timecard is already " . strtolower($data["status"]) . ".");
    }
    // Validate all punches have both in and out
    foreach($data["form"] as $dayIndex => $day){
        foreach($day["inOut"] as $io){
            if(!array_key_exists("out", $io) && array_key_exists("modifiedOut", $io)){
                throw new InvalidArgumentException("Missing punch out on " . getDayName($dayIndex) . ".");
            }
        }
    }
    foreach($data["form"] as $dayIndex => $day){
        if(!array_key_exists("inOut", $day)) continue;
        $totalHours = array_sum(array_column($day["inOut"], "duration"));
        $requiredBreaks = floor($totalHours / 4);
        $actualBreaks = count(array_filter($day["inOut"], fn($io) => array_key_exists("break", $io),));
        if($actualBreaks < $requiredBreaks){
            throw new InvalidArgumentException("Missing 10-min break(s) on " . getDayName($dayIndex) . ".");
        }
    }
    foreach($data["form"] as $dayIndex => &$day){
        $totalHours = array_sum(array_column($day["inOut"], "duration"));
        $day["date"] = getDateFromWeekNum($week, $dayIndex);
        if($dayIndex >= 5){ // Sat, Sun
            $day["regular"] = 0;
            $day["ot"]      = $totalHours;
        }else{
            $day["regular"] = min($totalHours, 8);
            $day["ot"]      = max($totalHours - 8, 0);
        }
    }
    unset($day);
    $data["status"] = "Submitted";
    if(!$existing){
        $db->exec(
            "INSERT INTO `timeCard` (`userId`, `week`, `data`) VALUES(?, ?, ?);",
            [$employeeId, $week, json_encode($data)], __FILE__, __LINE__
        );
    }else{
        $db->exec(
            "UPDATE `timeCard` SET `data` = ? WHERE `userId` = ? AND `week` = ?",
            [json_encode($data), $employeeId, $week], __FILE__, __LINE__
        );
    }
    $db->commit();
    echo json_encode(["success" => true]);
    $extraText = " for $employeeName";
    sendEmail([
        "path" => basename(__FILE__)." ".__LINE__, 
        "selfEmail" => $email, 
        "db" => $db, 
        "to" => $approverEmail, 
        "cc" => $email, 
        "summary" => "Time Card Submission Review", 
        "body" => "&nbsp;&nbsp;&nbsp;&nbsp;$userName submitted a time card$extraText, Please review and provide your decision below:<br>
        <br>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <a href = \"$mainUrl/AllTimeCards/$week/$employeeId\">
            Week $week
        </a>"
    ]);
}catch(InvalidArgumentException $e){
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);
}catch(Throwable $e){
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}