<?php
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/SearchHelper.php";

function leadEmailBody(string $leadName): string {
    $safeName = htmlspecialchars($leadName !== "" ? $leadName : "there", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    return "<body>
        Hi {$safeName},<br><br>
        Quick note from Construct Smarter: we build fully customizable construction ERP software with native, configurable payroll processing built exclusively for contractors.<br>
        <br>
        Most generic construction tools lock you into rigid workflows and force you to use separate third-party payroll apps that don't sync with your job costing. Our platform solves both issues:<br>
        Every workflow, report, and dashboard fully customizable to your team's processes<br>
        Embedded payroll engine handling certified prevailing wage, union labor, and field time tracking, fully tailored to your compliance rules<br>
        <br>
        Are you currently struggling with disconnected payroll and project software, or limited customization in your current system? I'd be happy to share a focused 10-minute walkthrough of our payroll &amp; custom configuration tools at your convenience.<br>
        <br>
        Jun Liu<br>
        Construct Smarter<br>
        Customized Construction ERP System<br>
        Website: <a href=\"https://constructsmarter.lovable.app\">https://constructsmarter.lovable.app</a><br>
        Phone: 952-818-4630<br>
        Email: <a href=\"mailto:jun909l@yahoo.com\">jun909l@yahoo.com</a><br>
        <img src=\"cid:logo\" alt=\"Construct Smarter Logo\">
    </body>";
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }

    $search = new SearchHelper("leads");
    $likeFields = ["businessPhone", "extension", "fax", "mobile", "background", "overseaAddress", "email", "role", "website", "industry", "voidReason", "validateReason"];
    $equalFields = ["creatorId", "updaterId", "source", "status", "referredBy", "userResponsible1", "userResponsible2"];
    $betweenDateTimeFields = ["createdAt", "updatedAt"];

    $search->equals("organizationId", requireInt($_POST, "organizationId", null, null, false));
    $search->when(
        array_key_exists("address", $_POST),
        fn($q) => $q->raw(
            "CONCAT_WS(' ', `leads`.`street`, `leads`.`city`, `leads`.`state`, `leads`.`zipCode`) LIKE ?",
            ["%" . $_POST["address"] . "%"]
        )
    );
    $search->when(
        array_key_exists("name", $_POST),
        fn($q) => $q->raw(
            "CONCAT_WS(' ', `leads`.`firstName`, `leads`.`middleName`, `leads`.`lastName`) LIKE ?",
            ["%" . $_POST["name"] . "%"]
        )
    );
    $search->when(
        array_key_exists("noOrganizationAssociated", $_POST) && $_POST["noOrganizationAssociated"] === "1",
        fn($q) => $q->raw("`leads`.`organizationId` IS NULL", [])
    );

    if(!array_key_exists("void", $_POST)) $search->equals("void", "no");
    else if($_POST["void"] !== "all") $search->equals("void", $_POST["void"]);

    foreach($likeFields as $field){
        $search->like($field, $_POST[$field] ?? null);
    }
    foreach($equalFields as $field){
        $search->equals($field, $_POST[$field] ?? null);
    }
    foreach($betweenDateTimeFields as $field){
        $search->between($field, "datetime");
    }

    $search->equals("sent", 0);
    $search->raw("`leads`.`email` IS NOT NULL AND `leads`.`email` <> ''", []);

    $batchLimit = array_key_exists("limit", $_POST) ? (int)$_POST["limit"] : 100;
    if ($batchLimit < 1 || $batchLimit > 100) {
        $batchLimit = 100;
    }
    $sleepSeconds = array_key_exists("sleepSeconds", $_POST) ? (int)$_POST["sleepSeconds"] : 10;
    if ($sleepSeconds < 0 || $sleepSeconds > 10) {
        $sleepSeconds = 10;
    }

    $whereSql = $search->getWhereSql();
    $params = $search->getParams();
    $leads = $db->all(
        "SELECT `id`, `email`, CONCAT_WS(' ', `firstName`, `middleName`, `lastName`) AS `name`
         FROM `leads`
         $whereSql
         ORDER BY `createdAt` DESC LIMIT $batchLimit;",
        $params,
        __FILE__, __LINE__
    );

    $sentCount = 0;
    $skippedInvalidEmail = 0;
    $failed = [];
    $halted = false;
    $haltReason = "";

    foreach ($leads as $lead) {
        $leadEmail = trim((string)($lead["email"] ?? ""));
        if (!filter_var($leadEmail, FILTER_VALIDATE_EMAIL)) {
            $skippedInvalidEmail++;
            continue;
        }

        try {
            sendEmail([
                "path" => basename(__FILE__)." ".__LINE__,
                "selfEmail" => $email,
                "db" => $db,
                "to" => $leadEmail,
                "summary" => "Quick note from Construct Smarter",
                "body" => leadEmailBody(trim((string)$lead["name"])),
                "noBodyTemplate" => true,
            ]);
            // sendEmail throws on PHPMailer failure; only mark sent after a successful SMTP send.
            $db->exec("UPDATE `leads` SET `sent` = 1, `updaterId` = ? WHERE `id` = ?;", [$userId, $lead["id"]], __FILE__, __LINE__);
            $sentCount++;
            if ($sleepSeconds > 0 && $sentCount < count($leads)) {
                sleep($sleepSeconds);
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            error_log($e);
            $failed[] = $lead["id"];

            if (preg_match('/Could not authenticate|SMTP Error|data not accepted|DATA END command failed|Error sending message for delivery/i', $message)) {
                $halted = true;
                $haltReason = $message;
                break;
            }
        }
    }

    jsonResponse(200, [
        "sent" => $sentCount,
        "skippedInvalidEmail" => $skippedInvalidEmail,
        "failed" => count($failed),
        "failedIds" => $failed,
        "halted" => $halted,
        "haltReason" => $haltReason,
    ]);
} catch (InvalidArgumentException $e) {
    jsonResponse(422, ["msg" => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
