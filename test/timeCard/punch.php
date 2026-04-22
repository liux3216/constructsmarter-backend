<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "functions.php"; // defaultEmptyData
if(
    !array_key_exists("cordArray", $_POST) || 
    !array_key_exists("userAgent", $_POST)
) jsonResponse(409, ["msg" => "Missing parameters"]);
$cordArray = json_decode($_POST["cordArray"], true);
$userAgent = $_POST["userAgent"];
$currentWeek = date("oW"); // e.g. "202509"
$todayTime   = date("H:i:s");
$dayIndex = ((int)date("w") + 6) % 7;
try{
    $db->begin();
    $existing = $db->one("SELECT `data` FROM `timeCard` WHERE userId = ? AND `week` = ? FOR UPDATE", [$userId, $currentWeek], __FILE__, __LINE__);
    if(!$existing) $data = defaultEmptyData();
    else $data = json_decode($existing["data"], true);
    if($data["status"] !== "Created" && $data["status"] !== "Rejected") throw new InvalidArgumentException("current week timecard is " . strtolower($data["status"]));
    $inOut = $data["form"][$dayIndex]["inOut"];
    $last  = end($inOut) ?: null;
    if($last){
        $refTime = $last["modifiedOut"] ?? $last["out"] ?? null;
        if(!$last["out"] && !($last["modifiedOut"] ?? null)) $refTime = $last["modifiedIn"] ?? $last["in"];
        if($refTime){
            $diff = (strtotime($todayTime) - strtotime($refTime)) / 60;
            if ($diff < 1) throw new InvalidArgumentException("Punch too frequently.");
        }
    }
    if(!$inOut || ($last && ($last["out"] || ($last["modifiedOut"] ?? null)))){
        // Punch IN
        $inOut[] = [
            "in"         => $todayTime,
            "inLocation" => $cordArray,
            "inUserAgent"=> $userAgent,
            "out"        => "",
        ];
    }else{
        // Punch OUT — compute duration
        $inTime  = $last["modifiedIn"] ?? $last["in"];
        $duration = round((strtotime($todayTime) - strtotime($inTime)) / 3600, 2);
        $inOut[count($inOut) - 1] = array_merge($last, [
            "out"          => $todayTime,
            "outLocation"  => $cordArray,
            "outUserAgent" => $userAgent,
            "duration"     => $duration,
        ]);
    }
    $data["form"][$dayIndex]["inOut"] = $inOut;
    if($existing){
        $db->exec("UPDATE `timeCard` SET `data` = ? WHERE `userId` = ? AND `week` = ?;", [json_encode($data), $userId, $currentWeek], __FILE__, __LINE__);
    }else{
        $db->exec("INSERT INTO `timeCard` (`data`, `userId`, `week`) VALUES (?, ?, ?);", [json_encode($data), $userId, $currentWeek], __FILE__, __LINE__);
    }
    $db->commit();
}catch (InvalidArgumentException $e) {
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);
} catch (Throwable $e) {
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
exit();