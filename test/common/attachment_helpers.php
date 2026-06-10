<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

function attachmentReadFiles(DB $db, string $folderId): array {
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

function attachmentEnsureFolderRecord(DB $db, string $folderId, string $folderName, string $userId): void {
    if ($folderId === "") return;
    $existing = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ? LIMIT 1;", [$folderId], __FILE__, __LINE__);
    if ($existing) return;
    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`) VALUES (?, ?, 'folder', 0, NULL, ?, 'uploaded');",
        [$folderId, $folderName !== '' ? $folderName : 'Files', $userId],
        __FILE__,
        __LINE__
    );
}

function attachmentDeleteFolder(DB $db, string $folderId): void {
    if ($folderId === '') return;
    global $privateBucket;
    $files = $db->all(
        "SELECT `id` FROM `fileInfo` WHERE `parentId` = ? AND `type` <> 'folder';",
        [$folderId],
        __FILE__,
        __LINE__
    );
    foreach ($files as $file) {
        $fileId = trim((string)($file['id'] ?? ''));
        if ($fileId === '') continue;
        try {
            deleteFile($privateBucket, $fileId);
        } catch (Throwable $e) {
            error_log('Failed to delete attachment file ' . $fileId . ': ' . $e->getMessage());
        }
    }
    $db->exec("DELETE FROM `fileInfo` WHERE `parentId` = ?;", [$folderId], __FILE__, __LINE__);
    $db->exec("DELETE FROM `fileInfo` WHERE `id` = ?;", [$folderId], __FILE__, __LINE__);
}

function attachmentResolveTarget(DB $db, string $sqlTable, int $recordId, string $userId): array {
    switch ($sqlTable) {
        case 'projects': {
            $row = $db->one(
                "SELECT `folderId`, `projectNumber`, `clientProjectNumber` FROM `projects` WHERE `id` = ? LIMIT 1;",
                [$recordId],
                __FILE__,
                __LINE__
            );
            if (!$row) {
                jsonResponse(404, ['msg' => 'Project not found.']);
            }
            $folderId = trim((string)($row['folderId'] ?? ''));
            if ($folderId === '') {
                $folderId = secureId();
                $db->exec(
                    "UPDATE `projects` SET `folderId` = ?, `updaterId` = ? WHERE `id` = ?;",
                    [$folderId, $userId, $recordId],
                    __FILE__,
                    __LINE__
                );
            }
            $folderName = trim((string)($row['projectNumber'] ?? '')) ?: trim((string)($row['clientProjectNumber'] ?? '')) ?: 'Project Files';
            attachmentEnsureFolderRecord($db, $folderId, $folderName, $userId);
            return ['folderId' => $folderId, 'files' => attachmentReadFiles($db, $folderId)];
        }
        case 'works': {
            $row = $db->one(
                "SELECT `folderId`, `category`, `subCategory` FROM `works` WHERE `id` = ? LIMIT 1;",
                [$recordId],
                __FILE__,
                __LINE__
            );
            if (!$row) {
                jsonResponse(404, ['msg' => 'Work not found.']);
            }
            $folderId = trim((string)($row['folderId'] ?? ''));
            if ($folderId === '') {
                $folderId = secureId();
                $db->exec(
                    "UPDATE `works` SET `folderId` = ?, `updaterId` = ? WHERE `id` = ?;",
                    [$folderId, $userId, $recordId],
                    __FILE__,
                    __LINE__
                );
            }
            $folderName = trim((string)($row['category'] ?? '')) ?: trim((string)($row['subCategory'] ?? '')) ?: ('Work ' . $recordId . ' Files');
            attachmentEnsureFolderRecord($db, $folderId, $folderName, $userId);
            return ['folderId' => $folderId, 'files' => attachmentReadFiles($db, $folderId)];
        }
        case 'assignments': {
            $row = $db->one(
                "SELECT `folderId`, `laborCategory`, `workId`, `creatorId` FROM `assignments` WHERE `id` = ? LIMIT 1;",
                [$recordId],
                __FILE__,
                __LINE__
            );
            if (!$row) {
                jsonResponse(404, ['msg' => 'Assignment not found.']);
            }
            $folderId = trim((string)($row['folderId'] ?? ''));
            if ($folderId === '') {
                $folderId = secureId();
                $db->exec(
                    "UPDATE `assignments` SET `folderId` = ?, `updaterId` = ? WHERE `id` = ?;",
                    [$folderId, $userId, $recordId],
                    __FILE__,
                    __LINE__
                );
            }
            $folderName = trim((string)($row['laborCategory'] ?? '')) ?: ('Assignment ' . $recordId . ' Files');
            attachmentEnsureFolderRecord($db, $folderId, $folderName, $userId ?: (string)($row['creatorId'] ?? ''));
            return ['folderId' => $folderId, 'files' => attachmentReadFiles($db, $folderId)];
        }
        case 'posts': {
            $row = $db->one(
                "SELECT `picFolderId`, `subject`, `creatorId` FROM `posts` WHERE `id` = ? LIMIT 1;",
                [$recordId],
                __FILE__,
                __LINE__
            );
            if (!$row) {
                jsonResponse(404, ['msg' => 'Post not found.']);
            }
            $folderId = trim((string)($row['picFolderId'] ?? ''));
            if ($folderId === '') {
                $folderId = secureId();
                $db->exec(
                    "UPDATE `posts` SET `picFolderId` = ? WHERE `id` = ?;",
                    [$folderId, $recordId],
                    __FILE__,
                    __LINE__
                );
            }
            $folderName = trim((string)($row['subject'] ?? '')) ?: ('Community Post ' . $recordId . ' Photos');
            attachmentEnsureFolderRecord($db, $folderId, $folderName, $userId ?: trim((string)($row['creatorId'] ?? '')));
            return ['folderId' => $folderId, 'files' => attachmentReadFiles($db, $folderId)];
        }
        default:
            jsonResponse(422, ['msg' => 'Unsupported attachment target.']);
    }
}
