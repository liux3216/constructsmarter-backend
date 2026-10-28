<?php
// Preserve the shared DB interface, but let transactions handle failures normally.
class LiveSmarterDB extends DB {
 private PDO $connection;
 public function __construct($host,$user,$password,$database){
  $this->connection=new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4",$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 }
 public function begin():void{$this->connection->beginTransaction();}
 public function commit():void{$this->connection->commit();}
 public function rollback():void{if($this->connection->inTransaction())$this->connection->rollBack();}
 private function statement(string $sql,array $params):PDOStatement{$statement=$this->connection->prepare($sql);$statement->execute($params);return $statement;}
 public function one(string $sql,array $params=[],$file="",$line=""):?array{$row=$this->statement($sql,$params)->fetch();return $row===false?null:$row;}
 public function all(string $sql,array $params=[],$file="",$line=""):array{return $this->statement($sql,$params)->fetchAll();}
 public function exec(string $sql,array $params=[],$file="",$line=""):int{return $this->statement($sql,$params)->rowCount();}
 public function lastInsertId():string{return $this->connection->lastInsertId();}
}
