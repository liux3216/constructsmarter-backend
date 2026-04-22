<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php"; // sendEmail
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $mainUrl
//-------------------------------------------------
$projectId = requireInt($_POST, "id", null, null, true);
$stage = $_POST["stage"];
if($stage === "Report Deliverable"){
    //-------------------------------------------------
    $reports = $db->all(
        "SELECT  
        `reportTechId`
        FROM `reports`
        WHERE `projectId` = ? AND `void` = ?;", [$projectId, "no"]
    );
    $reportTechEmails = [];
    while($row = $reports){
        $reportTechEmails[] = $row["reportTechId"];
    }
    //-------------------------------------------------
    $project = $db->one(
        "SELECT 
        CONCAT_WS(' - ',
            NULLIF(TRIM(`p`.`projectNumber`), ''),
            NULLIF(TRIM(`org`.`name`), ''),
            NULLIF(TRIM(`p`.`clientProjectNumber`), '')
        ) AS `projectName`, 
        `u`.`email` AS `projectManagerEmail`
        FROM `projects` `p` 
        LEFT JOIN `users` `u` ON `u`.`id` = `p`.`projectManagerId` 
		LEFT JOIN `organizations` `org` ON `p`.`organizationId` = `org`.`id` 
        WHERE `p`.`id` = ?;", [$projectId], __FILE__, __LINE__
    );
    if(!$project){
        http_response_code(230);
        echo("The project is not found.");
        error_log(basename(__FILE__)." ".__LINE__." ".$email." The user is not found.");
        $conn->close();
        xweturn;
    }
    $projectName = $project["projectName"];
    $projectManagerEmail = $project["projectManagerEmail"];
    sendEmail([
        "path" => basename(__FILE__)." ".__LINE__, 
        "selfEmail" => $email, 
        "db" => $db, 
        "to" => $reportTechEmails, 
        "cc" => [$email, $projectManagerEmail], 
        "summary" => "Report Deliverable for $projectName", 
        "body" => "&nbsp;&nbsp;&nbsp;&nbsp;$userName set the project stage to \"Report Deliverable\":<br>
        &nbsp;&nbsp;&nbsp;&nbsp;<span style = \"background-color:coral\"><strong>&nbsp;$projectName&nbsp;</strong></span><br>
        <br>
        <a href = \"$mainUrl/Projects/$projectId\">Log in to the App to see detail</a><br>"
    ]);
}
//-------------------------------------------------
$db->exec(
    "UPDATE `projects` SET 
    `stage` = ?,
    `statusChangeDateTime` = ?,
    `statusChangerId` = ?
    WHERE `id` = ?;", [$stage, date("Y-m-d H:i:s"), $userId, $projectId], __FILE__, __LINE__
);
exit();