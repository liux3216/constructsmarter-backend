<?php
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/constants.php";

use Dompdf\Dompdf;
use Dompdf\Options;

const JSA_FOLDER_ID = "f2d7e6a71f5d463c8a4a0bfac17a2f13";

function generateJsaPdf(int $assignmentId, array $content, ?string $pdfId = null): string
{
    global $db, $privateBucket, $userId, $appName;

    $data = getJsaPdfData($assignmentId);
    $data["content"] = normalizeJsaPdfContent($content);
    $data["appName"] = $appName;

    $options = new Options();
    $options->set("defaultFont", "DejaVu Sans");
    $options->set("isHtml5ParserEnabled", true);
    $options->set("isRemoteEnabled", true);
    $options->set("chroot", "/opt/bitnami/apache/htdocs");

    $dompdf = new Dompdf($options);
    $html = renderJsaTemplate($data);
    $dompdf->loadHtml($html, "UTF-8");
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();

    $output = $dompdf->output();
    $size = strlen($output);
    if($pdfId === null || $pdfId === ""){
        $pdfId = bin2hex(random_bytes(16));
    }

    if(!uploadFileWithBody($privateBucket, $pdfId, $output, "application/pdf")){
        throw new RuntimeException("Failed to upload JSA PDF");
    }

    $parentId = resolveJsaPdfParentId($data["folderId"] ?? null, $assignmentId);
    if($parentId === JSA_FOLDER_ID){
        ensureJsaFolder();
    }

    $existing = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ?;", [$pdfId], __FILE__, __LINE__);
    if($existing){
        $db->exec(
            "UPDATE `fileInfo`
             SET `name` = ?, `type` = ?, `size` = ?, `parentId` = ?, `updaterId` = ?, `updatedAt` = NOW(), `status` = ?
             WHERE `id` = ?;",
            ["jsa_$assignmentId", "application/pdf", $size, $parentId, $userId, "uploaded", $pdfId],
            __FILE__,
            __LINE__
        );
    }else{
        $db->exec(
            "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?);",
            [$pdfId, "jsa_$assignmentId", "application/pdf", $size, $parentId, $userId, "uploaded"],
            __FILE__,
            __LINE__
        );
    }

    return $pdfId;
}

function resolveJsaPdfParentId(?string $folderId, int $assignmentId): string
{
    global $db, $userId;

    if(!$folderId){
        return JSA_FOLDER_ID;
    }

    $folder = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ?;", [$folderId], __FILE__, __LINE__);
    if($folder){
        return $folderId;
    }

    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`)
         VALUES (?, ?, ?, ?, ?, ?, ?);",
        [$folderId, "Work $assignmentId Files", "folder", 0, null, $userId, "uploaded"],
        __FILE__,
        __LINE__
    );

    return $folderId;
}

function ensureJsaFolder(): void
{
    global $db, $userId;

    $folder = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ?;", [JSA_FOLDER_ID], __FILE__, __LINE__);
    if($folder){
        return;
    }

    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`)
         VALUES (?, ?, ?, ?, ?, ?, ?);",
        [JSA_FOLDER_ID, "JSAs", "folder", 0, null, $userId, "uploaded"],
        __FILE__,
        __LINE__
    );
}

function getJsaPdfData(int $assignmentId): array
{
    global $db;

    $row = $db->one(
        "SELECT
            `a`.`id`,
            `a`.`workId`,
            `a`.`laborCategory`,
            `a`.`jsaSubmitTime`,
            `a`.`updatedAt`,
            `a`.`createdAt`,
            CONCAT_WS(' ', `assignedUser`.`firstName`, `assignedUser`.`middleName`, `assignedUser`.`lastName`) AS `memberName`,
            CONCAT_WS(' - ', NULLIF(TRIM(`p`.`projectNumber`), ''), NULLIF(TRIM(`org`.`name`), ''), NULLIF(TRIM(`p`.`clientProjectNumber`), '')) AS `projectName`,
            `p`.`projectNumber`,
            `p`.`clientProjectNumber`,
            `p`.`clientPONumber`,
            `org`.`name` AS `clientName`,
            `w`.`location` AS `workLocation`,
            `p`.`location` AS `projectLocation`,
            `w`.`description` AS `workDescription`,
            `w`.`folderId`,
            CONCAT_WS(' ', `supervisorUser`.`firstName`, `supervisorUser`.`middleName`, `supervisorUser`.`lastName`) AS `supervisor`,
            CONCAT_WS(' ', `leadUser`.`firstName`, `leadUser`.`middleName`, `leadUser`.`lastName`) AS `leadName`,
            CONCAT_WS(' ', `pm`.`firstName`, `pm`.`middleName`, `pm`.`lastName`) AS `projectManager`,
            CONCAT_WS(' ', `siteContact`.`firstName`, `siteContact`.`middleName`, `siteContact`.`lastName`) AS `siteContactName`,
            COALESCE(`siteContact`.`directNumber`, `siteContact`.`phoneNumber`) AS `siteContactPhone`
        FROM `assignments` `a`
        LEFT JOIN `works` `w` ON `w`.`id` = `a`.`workId`
        LEFT JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
        LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
        LEFT JOIN `users` `assignedUser` ON `assignedUser`.`id` = `a`.`userId`
        LEFT JOIN `users` `supervisorUser` ON `supervisorUser`.`id` = `w`.`supervisorId`
        LEFT JOIN `users` `leadUser` ON `leadUser`.`id` = `w`.`leadId`
        LEFT JOIN `users` `pm` ON `pm`.`id` = `p`.`projectManagerId`
        LEFT JOIN `contacts` `siteContact` ON `siteContact`.`id` = `w`.`siteContactId`
        WHERE `a`.`id` = ?;",
        [$assignmentId],
        __FILE__,
        __LINE__
    );

    if(!$row){
        throw new RuntimeException("Assignment {$assignmentId} not found");
    }

    $row["jobSite"] = $row["workLocation"] ?: $row["projectLocation"];
    $row["submitDateTime"] = $row["jsaSubmitTime"] ?: ($row["updatedAt"] ?: $row["createdAt"]);

    return $row;
}

function renderJsaTemplate(array $data): string
{
    ob_start();
    include __DIR__ . "/jsaPDF.tpl.php";
    return ob_get_clean();
}

function normalizeJsaPdfContent(array $content): array
{
    $content["hazardControls"] = jsaPdfDecode($content["hazardControls"] ?? [], []);
    $content["sif"] = jsaPdfDecode($content["sif"] ?? [], []);
    $content["others"] = jsaPdfDecode($content["others"] ?? [], []);
    $content["jobSafetyAnalysisSign"] = jsaPdfDecode($content["jobSafetyAnalysisSign"] ?? [], []);
    $content["jobSafetyAnalysisInternalSigns"] = jsaPdfDecode($content["jobSafetyAnalysisInternalSigns"] ?? [], []);
    $content["jobSafetyAnalysisSigns"] = jsaPdfDecode($content["jobSafetyAnalysisSigns"] ?? [], []);
    $content["iSigns"] = is_array($content["iSigns"] ?? null) ? $content["iSigns"] : [];
    $content["signs"] = is_array($content["signs"] ?? null) ? $content["signs"] : [];
    return $content;
}

function jsaPdfDecode(mixed $value, mixed $default): mixed
{
    if(is_array($value)){
        return $value;
    }
    if(!is_string($value) || $value === ""){
        return $default;
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : $default;
}

function jsaPdfH(mixed $value): string
{
    if(is_array($value)){
        $value = implode(", ", array_filter(array_map("strval", $value)));
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function jsaPdfDateTime(?string $value): string
{
    if(!$value){
        return "";
    }
    $time = strtotime($value);
    return $time ? date("m/d/Y g:i A", $time) : $value;
}

function jsaPdfYesNo(?string $value): string
{
    if($value === null || $value === ""){
        return "";
    }
    return strtoupper($value) === "NA" ? "NA" : ucfirst(strtolower($value));
}

function jsaPdfList(mixed $value): string
{
    if(!is_array($value)){
        return jsaPdfH($value);
    }
    $items = array_values(array_filter(array_map("strval", $value), fn($item) => $item !== ""));
    return jsaPdfH(implode(", ", $items));
}

function jsaPdfOther(array $others, string $key): string
{
    $value = $others[$key."Content"] ?? $others[$key] ?? "";
    return is_string($value) ? $value : "";
}

function jsaPdfSignatureSrc(mixed $value): string
{
    if(is_string($value) && str_starts_with($value, "data:image/")){
        return $value;
    }
    if(is_array($value)){
        foreach($value as $item){
            $src = jsaPdfSignatureSrc($item);
            if($src !== ""){
                return $src;
            }
        }
    }
    return "";
}
