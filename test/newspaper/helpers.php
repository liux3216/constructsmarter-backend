<?php
require_once "/opt/bitnami/apache/htdocs/s3.php";

if(realpath($_SERVER["SCRIPT_FILENAME"] ?? "") === __FILE__){
    http_response_code(403);
    exit(json_encode(["msg" => "Forbidden"]));
}

define("NEWSPAPER_ROOT_ID", "9c0f2b7a1d4e6f8890ab12cd34ef5678");
define("CURRENT_NEWSPAPER_KEY", "currentNewspaperId");

ensureNewspaperRoot();
ensureCurrentNewspaperEntity();

function ensureNewspaperRoot(): void {
    global $db, $userId;
    if(!is_object($db) || !method_exists($db, "one") || !method_exists($db, "exec")){
        error_log(__FILE__.": DB bootstrap missing in ensureNewspaperRoot");
        return;
    }
    $exists = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ?;", [NEWSPAPER_ROOT_ID], __FILE__, __LINE__);
    if($exists) return;
    $db->exec(
        "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `parentId`, `creatorId`, `status`) VALUES (?, Newspaper, folder, 0, NULL, ?, uploaded);",
        [NEWSPAPER_ROOT_ID, $userId],
        __FILE__,
        __LINE__
    );
}

function ensureCurrentNewspaperEntity(): void {
    global $db, $userId;
    if(!is_object($db) || !method_exists($db, "one") || !method_exists($db, "exec")){
        error_log(__FILE__.": DB bootstrap missing in ensureCurrentNewspaperEntity");
        return;
    }
    $row = $db->one("SELECT `entityKey` FROM `entities` WHERE `entityKey` = ?;", [CURRENT_NEWSPAPER_KEY], __FILE__, __LINE__);
    if($row) return;
    $db->exec(
        "INSERT INTO `entities` (`entityKey`, `entityType`, `textValue`, `jsonValue`, `updaterId`) VALUES (?, text, , NULL, ?);",
        [CURRENT_NEWSPAPER_KEY, $userId],
        __FILE__,
        __LINE__
    );
}

function newspaperJsonResponse(int $status, array $payload): void {
    http_response_code($status);
    exit(json_encode($payload));
}

function assertCanEditNewspaper(): void {
    global $db, $userId;
    $row = $db->one("SELECT `newspaper` FROM `users` WHERE `id` = ?;", [$userId], __FILE__, __LINE__);
    if(!$row || ($row["newspaper"] ?? "no") !== "edit"){
        newspaperJsonResponse(403, ["msg" => "Forbidden"]);
    }
}

function newspaperRequireString(string $key): string {
    $value = trim((string)($_POST[$key] ?? ""));
    if($value === "") throw new InvalidArgumentException("Missing $key.");
    return $value;
}

function newspaperOptionalString(string $key): string {
    return trim((string)($_POST[$key] ?? ""));
}

function assertFolderWithinRoot(string $folderId): void {
    global $db;
    if($folderId === NEWSPAPER_ROOT_ID) return;
    $row = $db->one(
        "WITH RECURSIVE `chain` AS (
            SELECT `id`, `parentId` FROM `fileInfo` WHERE `id` = ?
            UNION ALL
            SELECT `f`.`id`, `f`.`parentId` FROM `fileInfo` `f`
            JOIN `chain` `c` ON `f`.`id` = `c`.`parentId`
        )
        SELECT 1 AS `ok` FROM `chain` WHERE `id` = ? LIMIT 1;",
        [$folderId, NEWSPAPER_ROOT_ID],
        __FILE__,
        __LINE__
    );
    if(!$row){
        newspaperJsonResponse(403, ["msg" => "Folder is outside root."]);
    }
}

function assertFileWithinRoot(string $fileId): array {
    global $db;
    $row = $db->one("SELECT * FROM `fileInfo` WHERE `id` = ?;", [$fileId], __FILE__, __LINE__);
    if(!$row){
        newspaperJsonResponse(404, ["msg" => "File not found."]);
    }
    if(($row["parentId"] ?? null) === null){
        newspaperJsonResponse(403, ["msg" => "Invalid root access."]);
    }
    assertFolderWithinRoot((string)$row["parentId"]);
    return $row;
}

function newspaperNormalizeRow(array $row): array {
    return [
        "id" => (string)$row["id"],
        "name" => (string)$row["name"],
        "type" => (string)$row["type"],
        "size" => (int)($row["size"] ?? 0),
        "parentId" => $row["parentId"],
        "description" => (string)($row["description"] ?? ""),
    ];
}

function readNewspaperBody(string $fileId): string {
    global $s3Client, $privateBucket;
    try {
        $result = $s3Client->getObject([
            Bucket => $privateBucket,
            Key => $fileId,
        ]);
        return (string)$result[Body];
    } catch (Throwable $e) {
        error_log($e->getMessage());
        return "";
    }
}
