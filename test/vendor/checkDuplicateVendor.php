<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$id = requireField($_POST, "id", 0, 32, true);
$vendorName = requireField($_POST, "vendorName", 1, 150, true);
$website = requireWebsite($_POST, "website", 0, 255, true);
$params = ["vendorName" => $vendorName];
$where = ["`vendorName` = :vendorName"];
if($website){
    $params["website1"] = $website;
    $params["website2"] = $website;
    $where[] = "(`website` IS NOT NULL AND (`website` LIKE CONCAT(:website1, '%') OR :website2 LIKE CONCAT(`website`, '%')))";
}
if($id){
    $params["id"] = $id;
    $limit = " AND `id` <> :id";
}else{
    $limit = "";
}
$rows = $db->all(
    "SELECT `id`, `vendorName`, `website`
    FROM `vendors`
    WHERE (".implode(" OR ", $where).")$limit;",
    $params,
    __FILE__,
    __LINE__
);
exit(json_encode($rows));
