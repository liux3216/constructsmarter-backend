<?php
function getPerDiemAccess($db, $userId){
    static $cache = null;
    if($cache !== null) return $cache;
    $row = $db->one("SELECT `perDiem` FROM `users` WHERE `id` = ?;", [$userId], __FILE__, __LINE__);
    $cache = $row["perDiem"] ?? "no";
    return $cache;
}
function perDiemScope(string $alias, string $userId, string $access): array {
    if($access === "editAll"){
        return ["1 = 1", []];
    }
    return [
        "(`$alias`.`requesterId` = ? OR `$alias`.`approverId` = ? OR `$alias`.`creatorId` = ?)",
        [$userId, $userId, $userId]
    ];
}
function perDiemProjectLabel(string $projectAlias = "pr", string $organizationAlias = "o"): string {
    return "CONCAT_WS(\" - \", `$projectAlias`.`projectNumber`, `$organizationAlias`.`name`, `$projectAlias`.`clientProjectNumber`)";
}
function perDiemCanEditRow(array $row, string $userId, string $access): bool {
    return $access === "editAll" || ($access === "edit" && in_array($userId, [$row["requesterId"], $row["creatorId"]], true));
}
function perDiemCanApproveRow(array $row, string $userId, string $access): bool {
    return $access === "editAll" || $row["approverId"] === $userId;
}
function perDiemRequirePost(string $key): string {
    if(!array_key_exists($key, $_POST)){
        http_response_code(400);
        exit(json_encode(["msg" => "Missing field: $key"]));
    }
    return trim((string)$_POST[$key]);
}
