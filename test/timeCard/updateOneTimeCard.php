<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "functions.php"; // isValidWeekNum, defaultEmptyData
if (
    !array_key_exists("week", $_POST) ||
    !array_key_exists("employeeId", $_POST) ||
    !array_key_exists("data", $_POST)
) jsonResponse(409, ["msg" => "Missing parameters"]);
$week = $_POST["week"];
$employeeId = $_POST["employeeId"];
$incoming = json_decode($_POST["data"], true);
if (!isValidWeekNum($week)) jsonResponse(422, ["msg" => "Invalid week."]);
try {
    $db->begin();
    $existing = $db->one(
        "SELECT `data` FROM `timeCard` WHERE `userId` = ? AND `week` = ? FOR UPDATE",
        [$employeeId, $week], __FILE__, __LINE__
    );
    if(!$existing) $data = defaultEmptyData();
    else $data = json_decode($existing["data"], true);
    if ($data["status"] !== "Created" && $data["status"] !== "Rejected") {
        throw new InvalidArgumentException("Timecard is already " . strtolower($data["status"]) . ".");
    }
    foreach ($data["form"] as $dayIndex => &$day) {
        foreach ($day["inOut"] as $ioIndex => &$io) {
            $incomingIo = $incoming["form"][$dayIndex]["inOut"][$ioIndex] ?? [];
            if ($io["m"] ?? false) {
                $io["in"]  = $incomingIo["in"]  ?? $io["in"];
                $io["out"] = $incomingIo["out"]  ?? $io["out"];
            }
            $io["break"]            = $incomingIo["break"]            ?? $io["break"];
            $io["modifiedIn"]       = $incomingIo["modifiedIn"]       ?? $io["modifiedIn"]       ?? null;
            $io["modifiedOut"]      = $incomingIo["modifiedOut"]      ?? $io["modifiedOut"]      ?? null;
            $io["modifiedInNotes"]  = $incomingIo["modifiedInNotes"]  ?? $io["modifiedInNotes"]  ?? null;
            $io["modifiedOutNotes"] = $incomingIo["modifiedOutNotes"] ?? $io["modifiedOutNotes"] ?? null;
            $effectiveIn  = $io["modifiedIn"]  ?: $io["in"];
            $effectiveOut = $io["modifiedOut"] ?: $io["out"];
            if ($effectiveIn && $effectiveOut) {
                $io["duration"] = round(
                    (strtotime($effectiveOut) - strtotime($effectiveIn)) / 3600,
                    2
                );
            }
        }
        unset($io);
        $existingCount = count($day["inOut"]);
        $incomingInOut = $incoming["form"][$dayIndex]["inOut"] ?? [];
        if (count($incomingInOut) > $existingCount) {
            for ($i = $existingCount; $i < count($incomingInOut); $i++) {
                $newIo = $incomingInOut[$i];
                if ($newIo["m"] ?? false) {
                    $effectiveIn  = $newIo["modifiedIn"]  ?: ($newIo["in"]  ?? "");
                    $effectiveOut = $newIo["modifiedOut"] ?: ($newIo["out"] ?? "");
                    $day["inOut"][] = [
                        "m"        => true,
                        "in"       => $newIo["in"]  ?? "",
                        "out"      => $newIo["out"] ?? "",
                        "break"    => $newIo["break"] ?? false,
                        "duration" => ($effectiveIn && $effectiveOut)
                            ? round((strtotime($effectiveOut) - strtotime($effectiveIn)) / 3600, 2)
                            : null,
                        "modifiedIn"       => $newIo["modifiedIn"]       ?? null,
                        "modifiedOut"      => $newIo["modifiedOut"]      ?? null,
                        "modifiedInNotes"  => $newIo["modifiedInNotes"]  ?? null,
                        "modifiedOutNotes" => $newIo["modifiedOutNotes"] ?? null,
                        "notes"            => $newIo["notes"]            ?? "",
                    ];
                }
            }
        }
        $filtered = [];
        foreach($incoming["form"][$dayIndex]["inOut"] ?? [] as $incomingIo){
            if(!($incomingIo["m"] ?? false)){
                $filtered[] = null;
            }
        }
        $nonManual  = array_values(array_filter($day["inOut"], fn($io) => !($io["m"] ?? false)));
        $manualKept = array_values(array_filter(
            $incoming["form"][$dayIndex]["inOut"] ?? [],
            fn($io) => ($io["m"] ?? false)
        ));
        $rebuilt = [];
        $nonManualIdx = 0;
        foreach($incoming["form"][$dayIndex]["inOut"] ?? [] as $incomingIo){
            if($incomingIo["m"] ?? false){
                foreach($day["inOut"] as $builtIo){
                    if(($builtIo["m"] ?? false) && $builtIo["in"] === $incomingIo["in"]){
                        $rebuilt[] = $builtIo;
                        break;
                    }
                }
            }else{
                if(isset($nonManual[$nonManualIdx])){
                    $rebuilt[] = $nonManual[$nonManualIdx++];
                }
            }
        }
        $day["inOut"] = $rebuilt;
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