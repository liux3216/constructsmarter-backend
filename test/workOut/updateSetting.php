<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
require_once __DIR__ . "/settingProfile.php";
require_once __DIR__ . "/settingLink.php";

function normalizePercentage($value) {
    if ($value === null || $value === "") return 100;
    $number = (float)$value;
    if ($number < 0) return 0;
    if ($number > 100) return 100;
    return $number;
}

function parseTargetAreas($areasValue, $idsValue) {
    $decoded = json_decode((string)$areasValue, true);
    if (is_array($decoded)) {
        $items = [];
        foreach ($decoded as $area) {
            if (!is_array($area) || !isset($area["id"])) continue;
            $id = (int)$area["id"];
            if ($id <= 0) continue;
            $items[$id] = ["id" => $id, "percentage" => normalizePercentage($area["percentage"] ?? 100)];
        }
        return array_values($items);
    }

    if ($idsValue === null || $idsValue === "") return [];
    $ids = preg_split('/\s*,\s*/', (string)$idsValue, -1, PREG_SPLIT_NO_EMPTY);
    $items = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id > 0) $items[$id] = ["id" => $id, "percentage" => 100];
    }
    return array_values($items);
}

function syncTargetAreas($db, $settingId, $targetAreas) {
    $db->exec("DELETE FROM `workOutSettingTargetAreas` WHERE `workOutSettingId` = ?;", [$settingId], __FILE__, __LINE__);
    foreach ($targetAreas as $targetArea) {
        $db->exec(
            "INSERT INTO `workOutSettingTargetAreas` (`workOutSettingId`, `targetAreaId`, `percentage`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `percentage` = VALUES(`percentage`);",
            [$settingId, $targetArea["id"], $targetArea["percentage"]], __FILE__, __LINE__
        );
    }
}

$name = $_POST["name"];
$description = $_POST["description"];
$mode = $_POST["mode"];
$id = $_POST["id"];
$existing = $db->one("SELECT `profileId`, `linkUrl` FROM `workOutSettings` WHERE `id` = ? AND `userId` = ?;", [$id, $userId], __FILE__, __LINE__);
if (!$existing) {
    http_response_code(404);
    exit(json_encode(["msg" => "Exercise not found."]));
}
$linkUrl = array_key_exists("linkUrl", $_POST) ? validateSettingLink($_POST["linkUrl"]) : $existing["linkUrl"];
$profileId = array_key_exists("profileId", $_POST)
    ? validateSettingProfile($db, $userId, $_POST["profileId"])
    : $existing["profileId"];
$targetAreas = parseTargetAreas($_POST["targetAreas"] ?? "", $_POST["targetAreaIds"] ?? "");
$db->begin();
$db->exec(
    "UPDATE `workOutSettings` SET `name` = ?, `description` = ?, `mode` = ?, `profileId` = ?, `linkUrl` = ? WHERE `id` = ? AND `userId` = ?;",
    [$name, $description, $mode, $profileId, $linkUrl, $id, $userId], __FILE__, __LINE__
);
syncTargetAreas($db, $id, $targetAreas);
$db->commit();
exit();
