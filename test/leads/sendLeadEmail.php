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

    $whereSql = $search->getWhereSql();
    $params = $search->getParams();
    $leads = $db->all(
        "SELECT `id`, `email`, CONCAT_WS(' ', `firstName`, `middleName`, `lastName`) AS `name`
         FROM `leads`
         $whereSql
         ORDER BY `createdAt` DESC;",
        $params,
        __FILE__, __LINE__
    );

    $sentCount = 0;
    $skippedInvalidEmail = 0;
    $failed = [];

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
            $db->exec("UPDATE `leads` SET `sent` = 1, `updaterId` = ? WHERE `id` = ?;", [$userId, $lead["id"]], __FILE__, __LINE__);
            $sentCount++;
        } catch (Throwable $e) {
            error_log($e);
            $failed[] = $lead["id"];
        }
    }

    jsonResponse(200, [
        "sent" => $sentCount,
        "skippedInvalidEmail" => $skippedInvalidEmail,
        "failed" => count($failed),
        "failedIds" => $failed,
    ]);
} catch (InvalidArgumentException $e) {
    jsonResponse(422, ["msg" => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
