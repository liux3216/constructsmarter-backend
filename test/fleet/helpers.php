<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once "/opt/bitnami/apache/htdocs/s3.php";

const FLEET_INVENTORY_CATALOG_KEY = "fleetInventoryCatalog";
const FLEET_INVENTORY_YARDS_KEY = "fleetInventoryYards";

function fleetEntityRead(DB $db, string $entityKey): array {
    $row = $db->one(
        "SELECT `jsonValue` FROM `entities` WHERE `entityKey` = ? AND `entityType` = 'json' LIMIT 1;",
        [$entityKey],
        __FILE__,
        __LINE__
    );
    if (!$row) {
        return [];
    }
    $data = json_decode((string)($row["jsonValue"] ?? "{}"), true);
    return is_array($data) ? $data : [];
}

function fleetEntityWrite(DB $db, string $entityKey, array $data, string $actorId): void {
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    $existing = $db->one("SELECT `entityKey` FROM `entities` WHERE `entityKey` = ? LIMIT 1;", [$entityKey], __FILE__, __LINE__);
    if ($existing) {
        $db->exec(
            "UPDATE `entities` SET `entityType` = 'json', `jsonValue` = ?, `updaterId` = ? WHERE `entityKey` = ?;",
            [$json, $actorId, $entityKey],
            __FILE__,
            __LINE__
        );
        return;
    }
    $db->exec(
        "INSERT INTO `entities` (`entityKey`, `entityType`, `jsonValue`, `updaterId`) VALUES (?, 'json', ?, ?);",
        [$entityKey, $json, $actorId],
        __FILE__,
        __LINE__
    );
}

function fleetReadInventoryCatalog(DB $db): array {
    $data = fleetEntityRead($db, FLEET_INVENTORY_CATALOG_KEY);
    return is_array($data) ? $data : [];
}

function fleetWriteInventoryCatalog(DB $db, array $data, string $actorId): void {
    fleetEntityWrite($db, FLEET_INVENTORY_CATALOG_KEY, $data, $actorId);
}

function fleetReadInventoryYards(DB $db): array {
    $data = fleetEntityRead($db, FLEET_INVENTORY_YARDS_KEY);
    return is_array($data) ? $data : [];
}

function fleetWriteInventoryYards(DB $db, array $data, string $actorId): void {
    fleetEntityWrite($db, FLEET_INVENTORY_YARDS_KEY, $data, $actorId);
}

function fleetInventoryImageUrl(string $fileId): string {
    global $publicBucket;
    return "https://{$publicBucket}.s3.us-west-1.amazonaws.com/{$fileId}";
}

function fleetSaveInventoryImage(DB $db, string $hashKey, array $file, string $path, string $actorId): string {
    global $publicBucket;

    if (!isset($file["tmp_name"]) || !$file["tmp_name"]) {
        jsonResponse(400, ["msg" => "Missing upload file."]);
    }

    $uuid = trim($path) !== "" ? trim($path) : md5(rand());
    $fileName = trim((string)($file["name"] ?? $uuid));
    $fileType = trim((string)($file["type"] ?? "application/octet-stream"));
    $fileSize = (int)($file["size"] ?? 0);
    $lastModifiedAt = date("Y-m-d H:i:s");

    $existing = $db->one("SELECT `id` FROM `fileInfo` WHERE `id` = ? LIMIT 1;", [$uuid], __FILE__, __LINE__);
    if ($existing) {
        $db->exec(
            "UPDATE `fileInfo` SET `name` = ?, `type` = ?, `size` = ?, `lastModifiedAt` = ?, `updaterId` = ?, `public` = TRUE, `status` = 'uploaded' WHERE `id` = ?;",
            [$fileName, $fileType, $fileSize, $lastModifiedAt, $actorId, $uuid],
            __FILE__,
            __LINE__
        );
    } else {
        $db->exec(
            "INSERT INTO `fileInfo` (`id`, `name`, `type`, `size`, `lastModifiedAt`, `description`, `creatorId`, `updaterId`, `status`, `public`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'uploaded', TRUE);",
            [$uuid, $fileName, $fileType, $fileSize, $lastModifiedAt, $hashKey, $actorId, $actorId],
            __FILE__,
            __LINE__
        );
    }

    if (!uploadFile($publicBucket, $uuid, $file["tmp_name"])) {
        jsonResponse(500, ["msg" => "Failed to upload fleet inventory item image."]);
    }

    return fleetInventoryImageUrl($uuid);
}

function fleetDeleteInventoryImage(DB $db, string $path): void {
    global $publicBucket;
    if (trim($path) === "") {
        return;
    }
    deleteFile($publicBucket, trim($path));
    $db->exec("DELETE FROM `fileInfo` WHERE `id` = ?;", [trim($path)], __FILE__, __LINE__);
}
