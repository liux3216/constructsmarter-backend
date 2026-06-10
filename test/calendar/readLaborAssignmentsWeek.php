<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
header("Content-Type: application/json");
$today = date("Y-m-d");
$accessRow = $db->one(
    "SELECT `calendar` FROM `users` WHERE `id` = ? LIMIT 1;",
    [$userId],
    __FILE__,
    __LINE__
);
$calendarAccess = (string)($accessRow["calendar"] ?? "no");
$params = [$today, $today];
$userFilterSql = "";
$scope = "self";
if($calendarAccess === "all"){
    $scope = "all";
}else{
    $userFilterSql = " AND `a`.`userId` = ?";
    $params[] = $userId;
}
$row = $db->one(
    "SELECT
        COALESCE(MIN(DATE(`w`.`startTime`)), ?) AS `minTime`,
        COALESCE(MAX(DATE(`w`.`startTime`)), ?) AS `maxTime`
     FROM `assignments` `a`
     JOIN `works` `w` ON `w`.`id` = `a`.`workId`
     WHERE `a`.`void` = 'no'
       AND `w`.`void` = 'no'" . $userFilterSql . ";",
    $params,
    __FILE__,
    __LINE__
);
if(!$row){
    $row = [
        "minTime" => $today,
        "maxTime" => $today,
    ];
}
$row["scope"] = $scope;
exit(json_encode($row));
