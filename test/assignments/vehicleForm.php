<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
// set up
$id = $_POST["assignmentId"];
$preOrPost = $_POST["preOrPost"];
$foreignKey = array_key_exists("foreignKey", $_POST)?$_POST["foreignKey"]:NULL;
$isDriver = $_POST["isDriver"];
$truckNumber = array_key_exists("truckNumber", $_POST)?$_POST["truckNumber"]:NULL;
$truck = $_POST["truck"];
$truckSQL = addslashes($truck);
$now = date("Y-m-d H:i:s");
//-------------------------------------------------
if($foreignKey && $foreignKey !== "no"){
    $foreignKey = "`${foreignKey}ForeignKey` = \"$foreignKey\",";
}else{
    $foreignKey = "";
}
$db->exec(
    "UPDATE `asignments` SET 
    $foreignKey 
    `${preOrPost}Truck` =  \"$truckSQL\",
    `${preOrPost}Driver` =  \"$isDriver\",
    `${preOrPost}DriverSubmitTime` = \"$now\",
    `${preOrPost}TruckNumber` = \"$truckNumber\"
    WHERE `id` = ?;", [$id], __FILE__, __LINE__
);