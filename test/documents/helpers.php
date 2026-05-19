<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";

define("DOCUMENTS_ROOT_ID", "c4d9f1f0b13f4d7f8e9a0b1c2d3e4f50");
define("SAFETY_ROOT_ID", "b3a27e3cc4f5416794e0d46c1af7d2c1");

ensureDocumentRoots();

function assertCanEditDocuments(): void {
    global $db, $userId;
    $row = $db->one("SELECT `projects` FROM `users` WHERE `id` = ?;", [$userId], __FILE__, __LINE__);
    if(!$row || ($row["projects"] ?? "no") === "no"){
        http_response_code(403);
        exit(json_encode(["msg" => "Forbidden"]));
    }
}

function documentsJsonResponse(int $status, array $payload): void {
    http_response_code($status);
    exit(json_encode($payload));
}

function ensureDocumentRoots(): void {
    global $db, $userId;
    $roots = [
        DOCUMENTS_ROOT_ID => "Documents",
        SAFETY_ROOT_ID => "Safety",
    ];
    foreach($roots as $id => $name){
        $exists = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ?;", [$id], __FILE__, __LINE__);
        if($exists) continue;
        $db->exec(
            "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`) VALUES (?, ?, 'folder', 0, NULL, ?, 'uploaded');",
            [$id, $name, $userId],
            __FILE__,
            __LINE__
        );
    }
}

function documentRequireString(string $key): string {
    $value = trim((string)($_POST[$key] ?? ""));
    if($value === "") throw new InvalidArgumentException("Missing $key.");
    return $value;
}

function documentOptionalString(string $key): string {
    return trim((string)($_POST[$key] ?? ""));
}

function isRootAllowed(string $rootFolderId): bool {
    return in_array($rootFolderId, [DOCUMENTS_ROOT_ID, SAFETY_ROOT_ID], true);
}

function assertFolderWithinRoot(string $folderId, string $rootFolderId): void {
    global $db;
    if($folderId === $rootFolderId) return;
    $row = $db->one(
        "WITH RECURSIVE `chain` AS (
            SELECT `id`, `parentId` FROM `fileInfo` WHERE `id` = ?
            UNION ALL
            SELECT `f`.`id`, `f`.`parentId` FROM `fileInfo` `f`
            JOIN `chain` `c` ON `f`.`id` = `c`.`parentId`
        )
        SELECT 1 AS `ok` FROM `chain` WHERE `id` = ? LIMIT 1;",
        [$folderId, $rootFolderId],
        __FILE__,
        __LINE__
    );
    if(!$row){
        documentsJsonResponse(403, ["msg" => "Folder is outside root."]);
    }
}

function assertFileWithinRoot(string $fileId, string $rootFolderId): void {
    global $db;
    $row = $db->one("SELECT `parentId` FROM `fileInfo` WHERE `id` = ?;", [$fileId], __FILE__, __LINE__);
    if(!$row){
        documentsJsonResponse(404, ["msg" => "File not found."]);
    }
    if($row["parentId"] === null){
        documentsJsonResponse(403, ["msg" => "Invalid root access."]);
    }
    assertFolderWithinRoot((string)$row["parentId"], $rootFolderId);
}

function normalizeDocumentRow(array $row): array {
    return [
        "id" => (string)$row["id"],
        "name" => (string)$row["name"],
        "type" => (string)$row["type"],
        "size" => (int)($row["size"] ?? 0),
        "parentId" => $row["parentId"],
        "description" => (string)($row["description"] ?? ""),
        "webViewLink" => "",
    ];
}
