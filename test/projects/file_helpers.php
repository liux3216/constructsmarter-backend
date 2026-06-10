<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

function projectEnsureFolderRecord(DB $db, string $folderId, int $projectId, string $userId): void {
    if ($folderId === "") {
        return;
    }
    $existing = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ? LIMIT 1;", [$folderId], __FILE__, __LINE__);
    if ($existing) {
        return;
    }
    $project = $db->one(
        "SELECT `projectNumber`, `clientProjectNumber` FROM `projects` WHERE `id` = ? LIMIT 1;",
        [$projectId],
        __FILE__,
        __LINE__
    );
    $folderName = trim((string)($project["projectNumber"] ?? ""));
    if ($folderName === "") {
        $folderName = trim((string)($project["clientProjectNumber"] ?? ""));
    }
    if ($folderName === "") {
        $folderName = "Project Files";
    }
    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`) VALUES (?, ?, 'folder', 0, NULL, ?, 'uploaded');",
        [$folderId, $folderName, $userId],
        __FILE__,
        __LINE__
    );
}

function projectEnsureFolderId(DB $db, int $projectId, string $userId): string {
    $row = $db->one("SELECT `folderId` FROM `projects` WHERE `id` = ? LIMIT 1;", [$projectId], __FILE__, __LINE__);
    if (!$row) {
        jsonResponse(404, ["msg" => "Project not found."]);
    }
    $folderId = trim((string)($row["folderId"] ?? ""));
    if ($folderId === "") {
        $folderId = secureId();
        $db->exec("UPDATE `projects` SET `folderId` = ?, `updaterId` = ? WHERE `id` = ?;", [$folderId, $userId, $projectId], __FILE__, __LINE__);
    }
    projectEnsureFolderRecord($db, $folderId, $projectId, $userId);
    return $folderId;
}

function projectReadFiles(DB $db, string $folderId): array {
    if ($folderId === "") return [];
    global $privateBucket;
    $files = $db->all(
        "SELECT `id`, `name`, `type`, `type` AS `mimeType`, `size`, `lastModifiedAt`, `description`, `status`
         FROM `fileInfo`
         WHERE `parentId` = ? AND `type` <> 'folder'
         ORDER BY `createdAt` DESC, `name` ASC;",
        [$folderId],
        __FILE__,
        __LINE__
    );
    foreach ($files as &$file) {
        $file["webViewLink"] = getObjectUrl($privateBucket, $file["id"], $file["name"], "+30 minutes");
    }
    unset($file);
    return $files;
}
