<?php
//load dependencies:
require_once "/opt/bitnami/apache/htdocs/test/constants.php"; // $testerEmails, $sqlInfo
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//------------------------------------------------------------
if(!in_array($email, $testerEmails)) exit();
//------------------------------------------------------------
$action = $_POST["action"];
$needle = array_key_exists("needle", $_POST)?$_POST["needle"]:NULL;
$sql = array_key_exists("sql", $_POST)?$_POST["sql"]:NULL;
$selection = array_key_exists("selection", $_POST)?$_POST["selection"]:NULL;
$table =  array_key_exists("table", $_POST)?$_POST["table"]:NULL;
$limit =  array_key_exists("limit", $_POST)?$_POST["limit"]:NULL;
$key = array_key_exists("key", $_POST)?$_POST["key"]:NULL;
$value = array_key_exists("value", $_POST)?$_POST["value"]:NULL;
$primaryKeyValue = array_key_exists("primaryKeyValue", $_POST)?$_POST["primaryKeyValue"]:NULL;
$where =  array_key_exists("where", $_POST)?$_POST["where"]:NULL;
$order =  array_key_exists("order", $_POST)?$_POST["order"]:NULL;
$existingPrimaryKey =  array_key_exists("primaryKey", $_POST)?$_POST["primaryKey"]:NULL;
//------------------------------------------------------------
$valueSQL = addslashes($value);
$primaryKeyValueSQL = addslashes($primaryKeyValue);
//------------------------------------------------------------
if($action === "Search In All"){
    $output = [];
    $database = $sqlInfo["database"];
    $rowTables = $db->all("SHOW TABLES;", [], __FILE__, __LINE__);
    foreach($rowTables as $rowTable){
        $table = $rowTable["Tables_in_$database"];
        $rowCols = $db->all("SHOW COLUMNS FROM `$table`;", [], __FILE__, __LINE__);
        $columns = [];
        $params = [];
        foreach($rowCols as $rowCol){
            $columns[] = "INSTR(`".$rowCol["Field"]."` , ?) > 0";
            $params[] = $needle;
        }
        $columnsString = implode(" OR ", $columns);
        $rows = $db->all("SELECT * FROM `$table` WHERE $columnsString;", $params, __FILE__, __LINE__);
        foreach($rows as $row){
            $row["table"] = $table;
            $output[] = $row;
        }
    }
    exit(json_encode($output));
}else if($action === "Read Columns"){
    $rows = $db->all("SHOW COLUMNS FROM `$table`;", [], __FILE__, __LINE__); /* no SQL Injection Control */
    $primaryKey = "";
    $columns = [];
    foreach($rows as $row){
        $columns[] = $row;
        if($row["Key"] === "PRI"){
            $primaryKey = $row["Field"];
        }
    }
    exit(json_encode([
        "columns" => $columns,
        "primaryKey" => $primaryKey
    ]));
}else if($action === "Read"){
    //-----------------------------------------------------------------------
    $totalRows = 0;
    if(isset($existingPrimaryKey)){
        $row = $db->one("SELECT COUNT(`$existingPrimaryKey`) FROM `$table` $where;", [], __FILE__, __LINE__); /* no SQL Injection Control */
        $totalRows = $row["COUNT(`$existingPrimaryKey`)"];
    }
    //----------------------------
    $rows = $db->all("SELECT $selection FROM `$table` $where $order LIMIT $limit ;", [], __FILE__, __LINE__);
    exit(json_encode([
        "rows" => $rows,
        "totalRows" => $totalRows
    ]));
}else if($action === "Update"){
    $db->exec("UPDATE `$table` SET `$key` = \"$valueSQL\" WHERE `$existingPrimaryKey` = \"$primaryKeyValueSQL\";", [], __FILE__, __LINE__); /* no SQL Injection Control */
}else if($action === "Delete"){
    $db->exec("DELETE FROM `$table` WHERE `$existingPrimaryKey` = \"$primaryKeyValueSQL\";", [], __FILE__, __LINE__); /* no SQL Injection Control */
}else if($action === "SQL"){
    $conn = new mysqli(
        $sqlInfo["hostname"], 
        $sqlInfo["username"], 
        $sqlInfo["password"], 
        $sqlInfo["database"]
    );
    if($conn->connect_error){
        http_response_code(500);
        error_log(basename(__FILE__)." ".__LINE__." ".$email." ".$conn->connect_error);
        exit(json_encode(["msg" => "Connection failed: ".$conn->connect_error]));
    }
    $results = $conn->query($sql); /* no SQL Injection Control */
    if($results === false){
        http_response_code(500);
        exit(json_encode(["msg" => "Database Error: ".$conn->error]));
    }else if($results === true){
        exit();
    }else{
        if($results->num_rows === 0){
            $results_array = [];
        }else{
            while($row = $results->fetch_assoc()){
                $results_array[] = $row;
            }
        }
        exit(json_encode($results_array));
    }
}else if($action === "Read Tables"){
    $database = $sqlInfo["database"];
    $rows = $db->all("SHOW TABLES;", [], __FILE__, __LINE__);
    $results_array = [];
    foreach($rows as $row){
        $results_array[] = $row["Tables_in_$database"];
    }
    exit(json_encode($results_array));
}
exit();