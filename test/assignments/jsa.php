<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/test/sendEmail.php";
require_once "/opt/bitnami/apache/htdocs/test/constants.php";
require_once __DIR__ . "/jsaPDF.php";

header("Content-Type: application/json");

$assignmentId = trim((string)($_POST["assignmentId"] ?? $_POST["AssignmentId"] ?? $_POST["id"] ?? ""));
$action = trim((string)($_POST["action"] ?? ""));
$forSave = filter_var($_POST["forSave"] ?? false, FILTER_VALIDATE_BOOLEAN);

$jsaScalarKeys = [
    "assignmentId", "formId", "openDateTime", "loc", "loc2", "loc3", "loc4",
    "check1", "sel1", "sel2", "sel3", "additionalPPE", "notes", "memberName", "submitDate",
    "confinedSpaceHazard", "airMonitoring", "hotWork", "permit", "fireWatch", "respirators", "attendant"
];
$jsaRepeatedArrayKeys = [
    "eyeFace", "handGloves", "foot", "head", "gasDetector",
    "respiratoryProtection", "protectiveClothing", "hearing",
    "fallProtectionList", "signs", "iSigns"
];
$jsaJsonStringKeys = [
    "others", "sif", "hazardControls", "helpers",
    "jobSafetyAnalysisSign", "jobSafetyAnalysisInternalSigns", "jobSafetyAnalysisSigns", "sign"
];

function jsaPostValue(string $key): mixed
{
    if(array_key_exists($key, $_POST)) return $_POST[$key];
    if(array_key_exists($key."[]", $_POST)) return $_POST[$key."[]"];
    return null;
}

function jsaNormalizeArray(mixed $value): array
{
    if($value === null || $value === "") return [];
    return is_array($value) ? array_values($value) : [$value];
}

function jsaDecodeContent(?string $value): array
{
    if(!$value) return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function jsaBuildContent(array $existingContent): array
{
    global $jsaScalarKeys, $jsaRepeatedArrayKeys, $jsaJsonStringKeys;

    $content = $existingContent;
    foreach($jsaScalarKeys as $key){
        $value = jsaPostValue($key);
        if($value !== null && !is_array($value)){
            $content[$key] = $value;
        }
    }
    foreach($jsaRepeatedArrayKeys as $key){
        $value = jsaPostValue($key);
        if($value !== null){
            $content[$key] = jsaNormalizeArray($value);
        }
    }
    foreach($jsaJsonStringKeys as $key){
        $value = jsaPostValue($key);
        if($value !== null && !is_array($value)){
            $content[$key] = $value;
        }
    }
    if(($content["jobSafetyAnalysisSign"] ?? "") === "" && !empty($content["sign"])){
        $content["jobSafetyAnalysisSign"] = $content["sign"];
    }
    return $content;
}

function jsaUpdateContent(int|string $assignmentId, array $content, bool $forSave, string $now): void
{
    global $db, $userId;
    $db->exec(
        "UPDATE `assignments` SET
        `jsaContent` = ?,
        `jsaStatus` = ?,
        `jsaSaveTime` = ?,
        `jsaSubmitTime` = ?,
        `updaterId` = ?,
        `updatedAt` = ?
        WHERE `id` = ?;",
        [
            json_encode($content),
            $forSave ? "saved" : "submitted",
            $forSave ? $now : null,
            $forSave ? null : $now,
            $userId,
            $now,
            $assignmentId,
        ],
        __FILE__,
        __LINE__
    );
}

try{
    if($_SERVER["REQUEST_METHOD"] !== "POST"){
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }
    if($assignmentId === ""){
        jsonResponse(400, ["msg" => "Missing assignment id."]);
    }

    $assignment = $db->one(
        "SELECT `a`.*,
        CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `assignedMemberName`
        FROM `assignments` `a`
        LEFT JOIN `users` `u` ON `u`.`id` = `a`.`userId`
        WHERE `a`.`id` = ? AND `a`.`void` = 'no';",
        [$assignmentId],
        __FILE__,
        __LINE__
    );
    if(!$assignment){
        jsonResponse(404, ["msg" => "The assignment is not found."]);
    }

    $workId = (int)$assignment["workId"];
    $work = $db->one(
        "SELECT `w`.`id`, `w`.`projectId`, `w`.`startTime`, `w`.`endTime`, `w`.`folderId`,
        CONCAT_WS(' - ', NULLIF(TRIM(`p`.`projectNumber`), ''), NULLIF(TRIM(`org`.`name`), ''), NULLIF(TRIM(`p`.`clientProjectNumber`), '')) AS `projectName`
        FROM `works` `w`
        LEFT JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
        LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
        WHERE `w`.`id` = ? AND `w`.`void` = 'no';",
        [$workId],
        __FILE__,
        __LINE__
    );
    if(!$work){
        jsonResponse(404, ["msg" => "The work is not found."]);
    }

    $content = jsaDecodeContent($assignment["jsaContent"] ?? null);

    if(strcasecmp($action, "Read") === 0){
        $otherAssignments = $db->all(
            "SELECT `a`.`id` AS `assignmentId`, `a`.`laborCategory`, `a`.`jsaContent`,
            CONCAT_WS(' ', `u`.`firstName`, `u`.`middleName`, `u`.`lastName`) AS `laborName`
            FROM `assignments` `a`
            LEFT JOIN `users` `u` ON `u`.`id` = `a`.`userId`
            WHERE `a`.`workId` = ? AND `a`.`id` <> ? AND `a`.`void` = 'no'
            ORDER BY `a`.`createdAt` ASC;",
            [$workId, $assignmentId],
            __FILE__,
            __LINE__
        );

        $internalSigns = [];
        foreach($otherAssignments as $row){
            $otherContent = jsaDecodeContent($row["jsaContent"] ?? null);
            $sign = $otherContent["jobSafetyAnalysisSign"] ?? "[]";
            $row["data"] = json_decode((string)$sign, true) ?: [];
            $row["ppe"] = empty($row["data"]) ? "no" : "yes";
            unset($row["jsaContent"]);
            $internalSigns[] = $row;
        }

        jsonResponse(200, [
            "sign" => $content["jobSafetyAnalysisSign"] ?? "[]",
            "signs" => $content["jobSafetyAnalysisSigns"] ?? "[]",
            "content" => $content,
            "internalSigns" => $internalSigns,
        ]);
    }

    $now = date("Y-m-d H:i:s");
    $content = jsaBuildContent($content);
    $content["assignmentId"] = $assignmentId;
    $content["workId"] = $workId;
    $content["savedAt"] = $now;
    $content["savedBy"] = $userId;
    $content["status"] = $forSave ? "saved" : "submitted";
    jsaUpdateContent($assignmentId, $content, $forSave, $now);

    if(!empty($content["jobSafetyAnalysisInternalSigns"])){
        $internalSigns = json_decode((string)$content["jobSafetyAnalysisInternalSigns"], true);
        if(is_array($internalSigns)){
            foreach($internalSigns as $internalSign){
                $internalAssignmentId = $internalSign["assignmentId"] ?? $internalSign["AssignmentId"] ?? null;
                if(!$internalAssignmentId) continue;
                $internalAssignment = $db->one(
                    "SELECT `jsaContent` FROM `assignments` WHERE `id` = ?;",
                    [$internalAssignmentId],
                    __FILE__,
                    __LINE__
                );
                $internalContent = jsaDecodeContent($internalAssignment["jsaContent"] ?? null);
                $internalContent["jobSafetyAnalysisSign"] = json_encode($internalSign["data"] ?? []);
                $internalContent["status"] = $forSave ? "saved" : "submitted";
                $internalContent["savedAt"] = $now;
                $internalContent["savedBy"] = $userId;
                jsaUpdateContent($internalAssignmentId, $internalContent, $forSave, $now);
            }
        }
    }

    $jsaFileId = $assignment["jsaFileId"] ?? null;
    if(!$forSave){
        $jsaFileId = generateJsaPdf((int)$assignmentId, $content, $jsaFileId);
        $db->exec(
            "UPDATE `assignments` SET `jsaFileId` = ? WHERE `id` = ?;",
            [$jsaFileId, $assignmentId],
            __FILE__,
            __LINE__
        );

        $emails = array_column(
            $db->all(
                "SELECT `u`.`email`
                FROM `assignments` `a`
                LEFT JOIN `users` `u` ON `u`.`id` = `a`.`userId`
                WHERE `a`.`workId` = ? AND `a`.`void` = 'no' AND `u`.`email` IS NOT NULL;",
                [$workId],
                __FILE__,
                __LINE__
            ),
            "email"
        );
        if(count($emails) > 0){
            $projectName = $work["projectName"] ?: "Work #".$workId;
            sendEmail([
                "path" => basename(__FILE__)." ".__LINE__,
                "selfEmail" => $email,
                "db" => $db,
                "to" => $emails,
                "summary" => "$projectName JSA Initiated",
                "body" => "$projectName JSA Initiated, please continue in $appName.",
            ]);
        }
    }

    jsonResponse(200, [
        "assignmentId" => $assignmentId,
        "workId" => $workId,
        "jobSafetyAnalysisContent" => $content,
        "jsaSaveTime" => $forSave ? $now : null,
        "jsaSubmitTime" => $forSave ? null : $now,
        "status" => $forSave ? "saved" : "submitted",
        "jsaFileId" => $jsaFileId,
        "jsaPdfUrl" => $jsaFileId ? getObjectUrl($privateBucket, $jsaFileId, "jsa_$assignmentId.pdf") : "",
    ]);

}catch(Throwable $e){
    error_log($e);
    jsonResponse(500, ["msg" => "Internal Server Error"]);
}
