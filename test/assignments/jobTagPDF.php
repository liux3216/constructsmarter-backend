<?php
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/constants.php";

use Dompdf\Dompdf;
use Dompdf\Options;

const JOB_TAG_FOLDER_ID = "5171ef7b62ffe9b115929581d047f988";

function generateJobTagPdf(int $assignmentId, ?string $pdfId = null, array $signatures = []): string
{
    global $db, $privateBucket, $userId, $appName;

    $data = getJobTagPdfData($assignmentId);
    $data["techSign"] = $signatures["techSign"] ?? "";
    $data["clientSign"] = $signatures["clientSign"] ?? "";
    $data["appName"] = $appName;
    $options = new Options();
    $options->set("defaultFont", "DejaVu Sans");
    $options->set("isHtml5ParserEnabled", true);
    $options->set("isRemoteEnabled", true);
    $options->set("chroot", "/opt/bitnami/apache/htdocs");

    $dompdf = new Dompdf($options);
    $html = renderJobTagTemplate($data);
    $dompdf->loadHtml($html, "UTF-8");
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();

    $output = $dompdf->output();
    $size = strlen($output);
    if ($pdfId === null || $pdfId === "") {
        $pdfId = md5(rand());
    }

    if (!uploadFileWithBody($privateBucket, $pdfId, $output, "application/pdf")) {
        throw new RuntimeException("Failed to upload job tag PDF");
    }

    ensureJobTagFolder();
    $existing = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ?;", [$pdfId], __FILE__, __LINE__);
    if ($existing) {
        $db->exec(
            "UPDATE `fileInfo`
             SET `name` = ?, `type` = ?, `size` = ?, `parentId` = ?, `updaterId` = ?, `updatedAt` = NOW(), `status` = ?
             WHERE `id` = ?;",
            ["jobTag_$assignmentId", "application/pdf", $size, JOB_TAG_FOLDER_ID, $userId, "uploaded", $pdfId],
            __FILE__,
            __LINE__
        );
    } else {
        $db->exec(
            "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?);",
            [$pdfId, "jobTag_$assignmentId", "application/pdf", $size, JOB_TAG_FOLDER_ID, $userId, "uploaded"],
            __FILE__,
            __LINE__
        );
    }

    return $pdfId;
}

function ensureJobTagFolder(): void
{
    global $db, $userId;

    $folder = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ?;", [JOB_TAG_FOLDER_ID], __FILE__, __LINE__);
    if ($folder) {
        return;
    }

    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`)
         VALUES (?, ?, ?, ?, ?, ?, ?);",
        [JOB_TAG_FOLDER_ID, "Job Tags", "folder", 0, null, $userId, "uploaded"],
        __FILE__,
        __LINE__
    );
}

function getJobTagPdfData(int $assignmentId): array
{
    global $db;

    $row = $db->one(
        "SELECT
            `a`.`id`,
            `a`.`workId`,
            `p`.`projectNumber`,
            `p`.`clientProjectNumber`,
            `p`.`clientPONumber`,
            `org`.`name` AS `clientName`,
            `w`.`location` AS `workLocation`,
            `p`.`location` AS `projectLocation`,
            CONCAT_WS(' ', `pm`.`firstName`, `pm`.`middleName`, `pm`.`lastName`) AS `projectManager`,
            CONCAT_WS(' ', `siteContact`.`firstName`, `siteContact`.`middleName`, `siteContact`.`lastName`) AS `requestor`,
            CONCAT_WS(' ', `assignedUser`.`firstName`, `assignedUser`.`middleName`, `assignedUser`.`lastName`) AS `memberName`,
            CONCAT_WS(' ', `supervisorUser`.`firstName`, `supervisorUser`.`middleName`, `supervisorUser`.`lastName`) AS `supervisor`,
            `a`.`preDriver`,
            `a`.`postDriver`,
            `a`.`travelStartTime`,
            `a`.`workStartTime`,
            `a`.`hadLunch`,
            `a`.`workEndTime`,
            `a`.`travelEndTime`,
            `a`.`workFinished`,
            `a`.`workRequired`,
            `a`.`workPerformed`,
            `a`.`additionalInfo`,
            `a`.`coords`,
            `a`.`updatedAt`,
            `a`.`createdAt`
        FROM `assignments` `a`
        LEFT JOIN `works` `w` ON `w`.`id` = `a`.`workId`
        LEFT JOIN `projects` `p` ON `p`.`id` = `w`.`projectId`
        LEFT JOIN `organizations` `org` ON `org`.`id` = `p`.`organizationId`
        LEFT JOIN `users` `assignedUser` ON `assignedUser`.`id` = `a`.`userId`
        LEFT JOIN `users` `supervisorUser` ON `supervisorUser`.`id` = `w`.`supervisorId`
        LEFT JOIN `users` `pm` ON `pm`.`id` = `p`.`projectManagerId`
        LEFT JOIN `contacts` `siteContact` ON `siteContact`.`id` = `w`.`siteContactId`
        WHERE `a`.`id` = ?;",
        [$assignmentId],
        __FILE__,
        __LINE__
    );

    if (!$row) {
        throw new RuntimeException("Assignment {$assignmentId} not found");
    }

    $row["jobSite"] = $row["workLocation"] ?: $row["projectLocation"];
    $row["submitDateTime"] = $row["updatedAt"] ?: $row["createdAt"];

    return $row;
}

function renderJobTagTemplate(array $data): string
{
    ob_start();
    require __DIR__ . "/jobTagPDF.tpl.php";
    return (string)ob_get_clean();
}

function jobTagH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function jobTagYesNo(?string $value): string
{
    if ($value === null || $value === "") {
        return "";
    }
    return strtolower($value) === "yes" ? "Yes" : "No";
}

function jobTagDate(?string $value): string
{
    if (!$value) {
        return "";
    }
    $time = strtotime($value);
    return $time ? date("Y-m-d", $time) : $value;
}

function jobTagTime(?string $value): string
{
    if (!$value) {
        return "";
    }
    $time = strtotime($value);
    return $time ? date("g:i A", $time) : $value;
}

function jobTagCoords(?string $value): array
{
    if (!$value) {
        return ["latLong" => "", "accuracy" => ""];
    }

    $parts = array_map("trim", explode(",", $value));
    return [
        "latLong" => count($parts) >= 2 ? $parts[0] . "," . $parts[1] : "",
        "accuracy" => $parts[2] ?? "",
    ];
}
