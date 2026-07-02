<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

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
$targetAreas = parseTargetAreas($_POST["targetAreas"] ?? "", $_POST["targetAreaIds"] ?? "");
$db->exec("INSERT INTO `workOutSettings` (`userId`, `name`, `description`, `mode`) VALUES (?, ?, ?, ?);", [$userId, $name, $description, $mode], __FILE__, __LINE__);
$id = (int)($db->one("SELECT LAST_INSERT_ID() AS `id`", [], __FILE__, __LINE__)["id"] ?? 0);
syncTargetAreas($db, $id, $targetAreas);
exit((string)$id);
