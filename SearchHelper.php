<?php
class SearchHelper {
    private array $where = [];
    private array $params = [];
    private string $alias;
    public function __construct(string $alias = "") {
        $this->alias = $alias ? "`$alias`." : "";
    }
    public function when($condition, callable $callback) {
        if ($condition) {
            $callback($this);
        }
        return $this;
    }
    public function defaultEquals(string $field, $value) {
        $this->where[] = "{$this->alias}`$field` = ?";
        $this->params[] = $value;
        return $this;
    }
    public function equals(string $field, $value) {
        if ($value !== null && $value !== "") {
            $this->where[] = "{$this->alias}`$field` = ?";
            $this->params[] = $value;
        }
        return $this;
    }
    public function like(string $field, $value) {
        if ($value !== null && $value !== "") {
            $this->where[] = "{$this->alias}`$field` LIKE ?";
            $this->params[] = "%$value%";
        }
        return $this;
    }
    public function between(string $field, $type = "number") {
        $min = array_key_exists($field."From", $_POST) ? $_POST[$field."From"]: null;
        $max = array_key_exists($field."To", $_POST) ? $_POST[$field."To"]: null;
        if ($min !== null && $min !== "" && $max !== null && $max !== ""){
            $this->where[] = "{$this->alias}`$field` BETWEEN ? AND ?";
            if($type === "datetime"){
                $min .= " 00:00:00";
                $max .= " 23:59:59";
            }
            $this->params[] = $min;
            $this->params[] = $max;
        } elseif ($min !== null && $min !== "") {
            if($type === "datetime"){
                $min .= " 00:00:00";
            }
            $this->where[] = "{$this->alias}`$field` >= ?";
            $this->params[] = $min;
        } elseif ($max !== null && $max !== "") {
            if($type === "datetime"){
                $max .= " 23:59:59";
            }
            $this->where[] = "{$this->alias}`$field` <= ?";
            $this->params[] = $max;
        }
        return $this;
    }
    public function raw(string $sql, array $bindings = []) {
        $this->where[] = $sql;
        $this->params  = array_merge($this->params, $bindings);
        return $this;
    }
    public function getWhereSql(): string {
        return $this->where ? " WHERE ".implode(" AND ", $this->where) : "";
    }
    public function getParams(): array {
        return $this->params;
    }
}