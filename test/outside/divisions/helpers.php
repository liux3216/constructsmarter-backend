<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

const OUTSIDE_DIVISIONS_ENTITY_KEY = "outsideDivisions";

function outsideDivisionsSeed(): array {
    return [
        ["division" => "North Valley", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "North Area Region"],
        ["division" => "Sierra", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "North Area Region"],
        ["division" => "Sonoma", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "North Area Region"],
        ["division" => "Sacramento", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "North Area Region"],
        ["division" => "Vacaville", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "North Area Region"],
        ["division" => "North Bay", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "North Area Region"],
        ["division" => "Fresno", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "South Area Region"],
        ["division" => "Central Coast", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "South Area Region"],
        ["division" => "Los Padres", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "South Area Region"],
        ["division" => "Yosemite", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "South Area Region"],
        ["division" => "Stockton", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "South Area Region"],
        ["division" => "Bakersfield", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "South Area Region"],
        ["division" => "Hinkley", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "South Area Region"],
        ["division" => "Topock", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "South Area Region"],
        ["division" => "Diablo", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "Bay Area Region"],
        ["division" => "De Anza", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "Bay Area Region"],
        ["division" => "San Jose", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "Bay Area Region"],
        ["division" => "Mission", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "Bay Area Region"],
        ["division" => "East Bay", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "Bay Area Region"],
        ["division" => "Peninsula", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "Bay Area Region"],
        ["division" => "San Francisco", "supervisorName" => "", "supervisorEmail" => "", "role" => "", "supervisorPhone" => "", "region" => "Bay Area Region"],
    ];
}

function outsideDivisionsRead($db): array {
    $row = $db->one("SELECT `jsonValue` FROM `entities` WHERE `entityKey` = ? AND `entityType` = 'json'", [OUTSIDE_DIVISIONS_ENTITY_KEY], __FILE__, __LINE__);
    if (!$row) return [];
    $data = json_decode((string)($row["jsonValue"] ?? "[]"), true);
    return is_array($data) ? $data : [];
}

function outsideDivisionsWrite($db, array $divisions, string $actorId): void {
    $json = json_encode(array_values($divisions));
    $existing = $db->one("SELECT `entityKey` FROM `entities` WHERE `entityKey` = ?", [OUTSIDE_DIVISIONS_ENTITY_KEY], __FILE__, __LINE__);
    if ($existing) {
        $db->exec(
            "UPDATE `entities` SET `entityType` = 'json', `jsonValue` = ?, `updaterId` = ? WHERE `entityKey` = ?",
            [$json, $actorId, OUTSIDE_DIVISIONS_ENTITY_KEY],
            __FILE__,
            __LINE__
        );
        return;
    }
    $db->exec(
        "INSERT INTO `entities` (`entityKey`, `entityType`, `jsonValue`, `updaterId`) VALUES (?, 'json', ?, ?)",
        [OUTSIDE_DIVISIONS_ENTITY_KEY, $json, $actorId],
        __FILE__,
        __LINE__
    );
}

function outsideDivisionsEnsureSeed($db, string $actorId): array {
    $data = outsideDivisionsRead($db);
    if ($data) return $data;
    $seed = outsideDivisionsSeed();
    outsideDivisionsWrite($db, $seed, $actorId);
    return $seed;
}

function outsideDivisionsSort(array $divisions): array {
    usort($divisions, static fn($a, $b) => strcasecmp((string)($a['division'] ?? ''), (string)($b['division'] ?? '')));
    return $divisions;
}
