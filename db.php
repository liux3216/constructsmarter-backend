<?php

/* stmt: statement -> 预处理语句 (PreparedStatement) */
/* PDO（PHP Data Objects）-> MySQL、PostgreSQL、SQLite */
/* dsn: Data Source Name (DSN) */

class DB {
    private PDO $pdo;
    public function __construct($host, $user, $pass, $dbname){
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /* ---------------- transaction ---------------- */

    public function begin(): void {
        $this->pdo->beginTransaction();
    }

    public function commit(): void {
        $this->pdo->commit();
    }

    public function rollback(): void {
        if($this->pdo->inTransaction()){
            $this->pdo->rollBack();
        }
    }

    /* ---------------- core ---------------- */

    private function fail(Throwable $e, string $sql = '', array $params = [], $file = "", $line = ""): never {
        if($e->errorInfo[1] !== 1062){
            error_log(
                "[DB ERROR] $file $line :\n".$e->getMessage(). "\n".
                "SQL: {$sql}\n" .
                "PARAMS: ".json_encode($params)
            );
            http_response_code(500);
            exit(json_encode(["msg" => "Database Error: ".$e->errorInfo[2]]));
        }else{
            http_response_code(409);
            exit(json_encode(["msg" => $e->errorInfo[2]]));
        }
    }

    public function one(string $sql, array $params = [], $file = "", $line = ""): ?array {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            return $row === false ? null : $row;
        } catch (Throwable $e){
            $this->fail($e, $sql, $params, $file, $line);
        }
    }

    public function all(string $sql, array $params = [], $file = "", $line = ""): array {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Throwable $e){
            $this->fail($e, $sql, $params, $file, $line);
        }
    }

    public function exec(string $sql, array $params = [], $file = "", $line = ""): int {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (Throwable $e){
            $this->fail($e, $sql, $params, $file, $line);
        }
    }

    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }

    public function syncJunction(
        string $table,
        string $foreignKey,
        mixed $foreignValue,
        string $itemKey,
        array $newIds
    ): void {
        $existing = $this->all(
            "SELECT `$itemKey` FROM `$table` WHERE `$foreignKey` = ?",
            [$foreignValue],
            __FILE__, __LINE__
        );
        $existingIds = array_column($existing, $itemKey);
        $toAdd    = array_diff($newIds, $existingIds);
        $toRemove = array_diff($existingIds, $newIds);
        if ($toRemove) {
            $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
            $this->exec(
                "DELETE FROM `$table` WHERE `$foreignKey` = ? AND `$itemKey` IN ($placeholders)",
                [$foreignValue, ...$toRemove],
                __FILE__, __LINE__
            );
        }
        if ($toAdd) {
            $placeholders = implode(',', array_fill(0, count($toAdd), '(?, ?)'));
            $values = [];
            foreach ($toAdd as $id) {
                $values[] = $foreignValue;
                $values[] = $id;
            }
            $this->exec(
                "INSERT INTO `$table` (`$foreignKey`, `$itemKey`) VALUES $placeholders",
                $values,
                __FILE__, __LINE__
            );
        }
    }
}
/* -------------------------- */
function jsonResponse(int $status, array $data): never {
    http_response_code($status);
    header("Content-Type: application/json");
    exit(json_encode($data));
}

function secureId(): string {
    return bin2hex(random_bytes(16));
}

function requireDriverLicense(
    array $src,
    string $key,
    bool $required = false,
    int $minLength = 5,
    int $maxLength = 20
): ?string {
    if(!array_key_exists($key, $src)){
        if($required){
            jsonResponse(409, ["msg" => "Missing field: {$key}"]);
        }
        return null;
    }
    if(is_array($src[$key]) || is_object($src[$key])){
        jsonResponse(409, ["msg" => "{$key} must be a string"]);
    }
    $value = strtoupper(trim((string)$src[$key]));
    if($value === ""){
        if($required){
            jsonResponse(409, ["msg" => "{$key} cannot be empty"]);
        }
        return null;
    }
    $len = strlen($value);
    if($len < $minLength || $len > $maxLength){
        jsonResponse(409, ["msg" => "{$key} must be {$minLength}-{$maxLength} characters"]);
    }
    // Allow letters, numbers, and dashes only
    if(!preg_match("/^[A-Z0-9-]+$/", $value)){
        jsonResponse(409, ["msg" => "{$key} must contain only letters, numbers, or '-'"]);
    }
    return $value;
}

function requireEmail(
    array $src,
    string $key,
    bool $required = false,
    int $maxLength = 255
): ?string {
    if(!array_key_exists($key, $src)){
        if($required){
            jsonResponse(409, ["msg" => "Missing field: {$key}"]);
        }
        return null;
    }
    if(is_array($src[$key]) || is_object($src[$key])){
        jsonResponse(409, ["msg" => "{$key} must be a valid email"]);
    }
    $value = trim((string)$src[$key]);
    if($value === ""){
        if($required){
            jsonResponse(409, ["msg" => "{$key} cannot be empty"]);
        }
        return null;
    }
    if(strlen($value) > $maxLength){
        jsonResponse(409, ["msg" => "{$key} must not exceed {$maxLength} characters"]);
    }
    $value = strtolower($value);
    if(!filter_var($value, FILTER_VALIDATE_EMAIL)){
        jsonResponse(409, ["msg" => "{$key}: Invalid email format"]);
    }
    return $value;
}

function requirePhone334(
    array $src, 
    string $key,  
    bool $required = false
): ?string {
    if(!array_key_exists($key, $src)){
        if($required){
            jsonResponse(409, ["msg" => "Missing field: {$key}"]);
        }
        return null;
    }
    if(is_array($src[$key]) || is_object($src[$key])){
        jsonResponse(409, ["msg" => "{$key} must be a phone number"]);
    }
    $value = trim((string)$src[$key]);
    if($value === ""){
        if($required){
            jsonResponse(409, ["msg" => "{$key} cannot be empty"]);
        }
        return null;
    }
    if(!preg_match("/^\d{3}-\d{3}-\d{4}$/", $value)){
        jsonResponse(409, ["msg" => "{$key} must be in format XXX-XXX-XXXX"]);
    }
    return $value;
}

function requireExtension(
    array $src,
    string $key,
    bool $required = false,
    int $minLength = 0,
    int $maxLength = 8
): ?string {
    if(!array_key_exists($key, $src)){
        if($required){
            jsonResponse(409, ["msg" => "Missing field: {$key}"]);
        }
        return null;
    }
    if(is_array($src[$key]) || is_object($src[$key])){
        jsonResponse(409, ["msg" => "{$key} must be a string"]);
    }
    $value = trim((string)$src[$key]);
    if($value === ""){
        if($required){
            jsonResponse(409, ["msg" => "{$key} cannot be empty"]);
        }
        return null;
    }
    if(!ctype_digit($value)){
        jsonResponse(409, ["msg" => "{$key} must contain digits only"]);
    }
    $len = strlen($value);
    if($len < $minLength || $len > $maxLength){
        jsonResponse(409, ["msg" => "{$key} must be {$minLength}-{$maxLength} digits"]);
    }
    return $value;
}

function requireSSN(
    array $src,
    string $key,
    bool $required = false
): ?string {
    if(!array_key_exists($key, $src)){
        if($required){
            jsonResponse(409, ["msg" => "Missing field: {$key}"]);
        }
        return null;
    }
    if(is_array($src[$key]) || is_object($src[$key])){
        jsonResponse(409, ["msg" => "{$key} must be a valid SSN"]);
    }
    $value = trim((string)$src[$key]);
    if($value === ""){
        if($required){
            jsonResponse(409, ["msg" => "{$key} cannot be empty"]);
        }
        return null;
    }
    if(!preg_match("/^\d{3}-\d{2}-\d{4}$/", $value)){
        jsonResponse(409, ["msg" => "{$key} must be in format XXX-XX-XXXX"]);
    }
    [$area, $group, $serial] = explode("-", $value);
    if(
        $area === "000" ||
        $area === "666" ||
        (int)$area >= 900 ||
        $group === "00" ||
        $serial === "0000"
    ){
        jsonResponse(409, ["msg" => "{$key} is invalid"]);
    }
    return $value;
}

function requireField(
    array $src,
    string $key,
    int | string $minLength = "min",
    int | string $maxLength = "max",
    bool $required = false
): ?string {
    if(!array_key_exists($key, $src)){
        if($required){
            jsonResponse(409, ["msg" => "Missing field: {$key}"]);
        }
        return null;
    }
    if(is_array($src[$key]) || is_object($src[$key])){
        jsonResponse(409, ["msg" => "{$key} must be a string"]);
    }
    $value = trim((string)$src[$key]);
    if($value === ""){
        if($required){
            jsonResponse(409, ["msg" => "{$key} cannot be empty"]);
        }
        return null;
    }
    $len = strlen($value);
    if(($minLength !== "min" && $len < $minLength) || ($maxLength !== "max" && $len > $maxLength)){
        jsonResponse(409, ["msg" => "{$key} must be {$minLength}-{$maxLength} characters"]);
    }
    return $value;
}

function requireDate(
    array $src,
    string $key,
    bool $required = false,
    ?string $minDate = null,
    ?string $maxDate = null
): ?string {
    if(!array_key_exists($key, $src)){
        if($required){
            jsonResponse(409, ["msg" => "Missing field: {$key}"]);
        }
        return null;
    }
    if(is_array($src[$key]) || is_object($src[$key])){
        jsonResponse(409, ["msg" => "{$key} must be a date"]);
    }
    $value = trim((string)$src[$key]);
    if($value === ""){
        if($required){
            jsonResponse(409, ["msg" => "{$key} cannot be empty"]);
        }
        return null;
    }
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)){
        jsonResponse(409, ["msg" => "{$key} must be in format YYYY-MM-DD"]);
    }
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    if(!checkdate($month, $day, $year)){
        jsonResponse(409, ["msg" => "{$key} is not a valid calendar date"]);
    }
    if($minDate !== null && $value < $minDate){
        jsonResponse(409, ["msg" => "{$key} must be on or after {$minDate}"]);
    }
    if($maxDate !== null && $value > $maxDate){
        jsonResponse(409, ["msg" => "{$key} must be on or before {$maxDate}"]);
    }
    return $value;
}

function requireEnum(
    array $src,
    string $key,
    array $allowed,
    bool $required = false,
    bool $caseInsensitive = false
): ?string {
    if(!array_key_exists($key, $src)){
        if($required){
            jsonResponse(409, ["msg" => "Missing field: {$key}"]);
        }
        return null;
    }
    if(is_array($src[$key]) || is_object($src[$key])){
        jsonResponse(409, ["msg" => "{$key} must be a valid value"]);
    }
    $value = trim((string)$src[$key]);
    if($value === ""){
        if($required){
            jsonResponse(409, ["msg" => "{$key} cannot be empty"]);
        }
        return null;
    }
    if($caseInsensitive){
        $value = strtolower($value);
        $allowed = array_map('strtolower', $allowed);
    }
    if(!in_array($value, $allowed, true)){
        jsonResponse(409, ["msg" => "{$key} must be one of: " . implode(", ", $allowed)]);
    }
    return $value;
}

function requireWebsite(
    array $source,
    string $key,
    int $min = 0,
    int $max = 255,
    bool $required = false
): ?string {
    $value = trim($source[$key] ?? "");
    if($value === ""){
        if($required){
            jsonResponse(422, ["msg" => "$key is required."]);
        }
        return null;
    }
    if(!preg_match("~^https?://~i", $value)){
        $value = "https://".$value;
    }
    if(!filter_var($value, FILTER_VALIDATE_URL)){
        jsonResponse(422, ["msg" => "Invalid website format."]);
    }
    $parsed = parse_url($value);
    if(!isset($parsed["host"])){
        jsonResponse(422, ["msg" => "Invalid website host."]);
    }
    // Normalize
    $host = strtolower($parsed["host"]);
    $scheme = $parsed["scheme"];
    $path = rtrim($parsed["path"] ?? "", "/");
    $value = $scheme."://".$host.$path;
    $len = strlen($value);
    if($len < $min || $len > $max){
        jsonResponse(422, ["msg" => "$key length invalid."]);
    }
    return $value;
}

function requireInt(
    array $source,
    string $key,
    ?int $min = null,
    ?int $max = null,
    bool $required = false
): ?int {
    $raw = trim($source[$key] ?? "");
    if ($raw === "" || $raw === null) {
        if ($required) {
            jsonResponse(422, ["msg" => "$key is required."]);
        }
        return null;
    }
    if (!preg_match('/^-?\d+$/', $raw)) {
        http_response_code(422);
        exit(json_encode(["msg" => "$key must be a valid integer."]));
    }
    $value = (int)$raw;
    if ($min !== null && $value < $min) {
        jsonResponse(422, ["msg" => "$key must be >= $min."]);
    }
    if ($max !== null && $value > $max) {
        jsonResponse(422, ["msg" => "$key must be <= $max."]);
    }
    return $value;
}

function requireZipCode(
    array $source,
    string $key,
    bool $required = false
): ?string {
    $value = $source[$key] ?? null;
    if ($value === null || trim($value) === '') {
        if ($required) {
            jsonResponse(422, ["msg" => "Zip code is required."]);
        }
        return null;
    }
    $value = trim($value);
    if (strlen($value) < 5 || strlen($value) > 10) {
        jsonResponse(422, ["msg" => "Invalid zip code length."]);
    }
    // US ZIP: 12345 or 12345-6789
    if (!preg_match('/^\d{5}(-\d{4})?$/', $value)) {
        jsonResponse(422, ["msg" => "Invalid zip code format."]);
    }
    return $value;
}

function requireJSON(
    array $src,
    string $key,
    bool $required = false
): string|null {
    if (!array_key_exists($key, $src)) {
        if ($required) {
            jsonResponse(409, ["msg" => "Missing field: {$key}"]);
        }
        return null;
    }
    $raw = $src[$key];
    if (is_array($raw)) {
        return json_encode($raw);
    }
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(409, ["msg" => "{$key} is not valid JSON"]);
        }
        return $raw;
    }
    jsonResponse(409, ["msg" => "{$key} must be a JSON string or array"]);
}