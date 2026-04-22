<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "functions.php"; // isValidWeekNum, defaultEmptyData
if(
    !array_key_exists("week", $_POST) || 
    !array_key_exists("data", $_POST)
) jsonResponse(409, ["msg" => "Missing parameters"]);
$week = $_POST["week"];
if (!isValidWeekNum($week)) jsonResponse(422, ["msg" => "Invalid week."]);
if(array_key_exists("employeeId", $_POST)){
    // todo: check permission
    $employeeId = $_POST["employeeId"];
}else{
    $employeeId = $userId;
}
$incoming = json_decode($_POST["data"], true);
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
    foreach($data["form"] as $dayIndex => &$day){
        $day["notes"] = $incoming["form"][$dayIndex]["notes"] ?? $day["notes"];
        foreach ($day["inOut"] as $ioIndex => &$io) {
            $incomingIo = $incoming["form"][$dayIndex]["inOut"][$ioIndex] ?? [];
            $io["break"]   = $incomingIo["break"] ?? $io["break"];
            $io["notes"]   = $incomingIo["notes"] ?? $io["notes"];
            $io["reports"] = $incomingIo["reports"] ?? $io["reports"];
        }
        unset($io);
    }
    unset($day);
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
}catch(InvalidArgumentException $e){
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);
}catch(Throwable $e){
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}