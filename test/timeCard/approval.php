<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php"; // sendEmail
require_once "/opt/bitnami/apache/htdocs/s3.php"; // uploadFileWithBody
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $mainUrl
require_once "functions.php"; // isValidWeekNum
require_once "timeCardPDF.php"; // generateTimeCardPDF;
if (
    !array_key_exists("week", $_POST) ||
    !array_key_exists("employeeId", $_POST) ||
    !array_key_exists("comments", $_POST)
) jsonResponse(409, ["msg" => "Missing parameters"]);
$week = $_POST["week"];
$employeeId = $_POST["employeeId"];
$decision = $_POST["decision"];
$comments = $_POST["comments"];
$approverUserId = $userId; // from internalAuth
if (!isValidWeekNum($week)) jsonResponse(422, ["msg" => "Invalid week."]);
try {
    $db->begin();
    $existing = $db->one(
        "SELECT `timeCard`.`id`, `timeCard`.`data`, CONCAT_WS(\" \", `users`.`firstName`, `users`.`middleName`, `users`.`lastName`) AS `employeeName` FROM `timeCard` LEFT JOIN `users` ON `users`.`id` = `timeCard`.`userId` WHERE `userId` = ? AND `week` = ? FOR UPDATE",
        [$employeeId, $week], __FILE__, __LINE__
    );
    if(!$existing){
        throw new InvalidArgumentException("Timecard not found.");
    }
    $data = json_decode($existing["data"], true);
    $id = $existing["id"];
    if ($data["status"] !== "Submitted" && $data["status"] !== "Approved") {
        throw new InvalidArgumentException("Timecard is not submitted. Current status: " . strtolower($data["status"]) . ".");
    }
    $data["week"]        = $week;
    $data["comments"]    = $comments;
    $data["approvedBy"]  = $approverUserId;
    $data["approvedAt"]  = date("Y-m-d H:i:s");
    $data["status"] = $decision;
    $db->exec(
        "UPDATE `timeCard` SET `data` = ? WHERE `userId` = ? AND `week` = ?",
        [json_encode($data), $employeeId, $week], __FILE__, __LINE__
    );
    if($decision === "Approved"){
        $pdfId = array_key_exists("pdfId", $data) ? $data["pdfId"]: null;
        $data["userName"] = $existing["employeeName"];
        $data["userId"] = $employeeId;
        $id = generateTimeCardPDF($id, $pdfId, $data);
        unset($data["userName"]);
        unset($data["userId"]);
        $data["pdfId"] = $id;
         $db->exec(
            "UPDATE `timeCard` SET `data` = ? WHERE `userId` = ? AND `week` = ?",
            [json_encode($data), $employeeId, $week], __FILE__, __LINE__
        );
    }
    $db->commit();
    $decisionLower = strtolower($decision);
    sendEmail([
        "path" => basename(__FILE__)." ".__LINE__, 
        "selfEmail" => $email, 
        "db" => $db, 
        "to" => $email, 
        "summary" => "Time Card $week Is $decision", 
        "body" => "&nbsp;&nbsp;&nbsp;&nbsp;Your time card $week is $decisionLower.<br><br>Approval Comment: $comments",
    ]);
    echo json_encode(["success" => true]);
} catch (InvalidArgumentException $e) {
    $db->rollBack();
    jsonResponse(422, ["msg" => $e->getMessage()]);
} catch (Throwable $e) {
    $db->rollBack();
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}