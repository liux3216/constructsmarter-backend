<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "functions.php"; // isValidWeekNum
if(!array_key_exists("week", $_POST) || !array_key_exists("data", $_POST)){
    jsonResponse(409, ["msg" => "Missing parameters"]);
}
$week = $_POST["week"];
if (!isValidWeekNum($week)) jsonResponse(422, ["msg" => "Invalid week."]);
$incoming = json_decode($_POST["data"], true);
try{
    $db->begin();
    $existing = $db->one(
        "SELECT `data` FROM `timeCard` WHERE `userId` = ? AND `week` = ? FOR UPDATE",
        [$userId, $week], __FILE__, __LINE__
    );
    if(!$existing) throw new InvalidArgumentException("Timecard not found.");
    $data = json_decode($existing["data"], true);
    if($data["status"] !== "Created" && $data["status"] !== "Rejected"){
        throw new InvalidArgumentException("Timecard is already " . strtolower($data["status"]) . ".");
    }
    foreach($data["form"] as $dayIndex => &$day){
        foreach($day["inOut"] as $ioIndex => &$io){
            $incomingIo = $incoming["form"][$dayIndex]["inOut"][$ioIndex] ?? [];
            $io["modifiedIn"]       = $incomingIo["modifiedIn"]       ?? $io["modifiedIn"]       ?? null;
            $io["modifiedOut"]      = $incomingIo["modifiedOut"]      ?? $io["modifiedOut"]      ?? null;
            $io["modifiedInNotes"]  = $incomingIo["modifiedInNotes"]  ?? $io["modifiedInNotes"]  ?? null;
            $io["modifiedOutNotes"] = $incomingIo["modifiedOutNotes"] ?? $io["modifiedOutNotes"] ?? null;
            // Recalculate duration from effective in/out
            $effectiveIn  = $io["modifiedIn"]  ?: $io["in"];
            $effectiveOut = $io["modifiedOut"] ?: $io["out"];
            if($effectiveIn && $effectiveOut){
                $io["duration"] = round(
                    (strtotime($effectiveOut) - strtotime($effectiveIn)) / 3600,
                    2
                );
            }
        }
        unset($io);
    }
    unset($day);
    $db->exec(
        "UPDATE `timeCard` SET `data` = ? WHERE `userId` = ? AND `week` = ?",
        [json_encode($data), $userId, $week], __FILE__, __LINE__
    );
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