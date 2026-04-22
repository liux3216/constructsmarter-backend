<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = requireInt($_POST, "id", null, null, false);
$params = [
    "website1" => requireWebsite($_POST, "website", 1, 255, true),
    "website2" => requireWebsite($_POST, "website", 1, 255, true),
];
if($id){
    $limit = " AND `id` <> :id";
    $params["id"] = $id;
}else{
  	$limit = "";
}
$rows = $db->all(
    "SELECT `id`, `name`, `website`
    FROM `organizations` 
    WHERE `website` IS NOT NULL AND 
    (`website` LIKE CONCAT(:website1, '%') OR :website2 LIKE CONCAT(`website`, '%'))$limit;", $params, __FILE__, __LINE__
);
exit(json_encode($rows));
