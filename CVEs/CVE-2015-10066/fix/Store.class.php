<?php

class Store{
	private static $pdo = null;

	public function __construct(){
		if(Store::$pdo === null)
			$this->init();
	}

	private function init(){
		$host = 'host=' . Config::$PDO_HOST;
		$db = 'dbname=' . Config::$PDO_DATABASE;
		$charset = 'charset=' . Config::$PDO_CHARSET;
		$user = Config::$PDO_USER;
		Store::$pdo = new PDO(
			'mysql:' . implode(';', array($host, $db, $charset) ),
			Config::$PDO_USER,
			Config::$PDO_PASSWORD
		);
	}

	private function packValue($value){
		if(is_bool($value)){
			$value = ($value === true) ? '1' : '0';
		}elseif(is_numeric($value)){
			$value = $value;
		}else{
			$value = '"' . $value . '"';
		}
		return $value;
	}

	public function getById($table, $id){
		$query = 'SELECT * FROM `wuersch`.`' . $table . '` WHERE ';
		if(!is_numeric($id) && strlen($id)==32){
			$query .= '`id_md5`=?';
		}else{
			$query .= '`id`=?';
		}
		$stmt = Store::$pdo->prepare($query . ';');
		$stmt->bindParam(1, $id);
		$stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_CLASS, ucfirst($table));
		if(count($result) === 1){
			return $result[0];
		}
		return null;
	}

	public function getByCustomQuery($query){
		$sth = Store::$pdo->prepare($query . ';');
		$sth->execute();
		$result = $sth->fetchAll(PDO::FETCH_ASSOC);
		return $result;
	}

	public function getByColumns($table, $columns, $combination = 'AND'){
		$query = 'SELECT * FROM `wuersch`.`' . $table . '` WHERE ';
		
		foreach($columns as $key=>$value){
			$query .= '`' . $key . '`=? ' . $combination . ' ';
		}
		$query = substr($query, 0, (-2-strlen($combination)));
		$stmt = Store::$pdo->prepare($query . ';');
		foreach(array_values($columns) as $i=>$value)
			$stmt->bindParam($i+1, $value);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_CLASS, ucfirst($table));
	}

	public function insert($table, $data){
		if(!is_array($data))
			return -1;
		$query = 'INSERT INTO `wuersch`.`' . $table . '` (';
		$query .= '`' . implode('`, `', array_keys($data)) . '`) VALUES (';
		for($i=0; $i<count($data); $i++){
			$query .= '?, ';
		}
		$query = substr($query, 0, -2) . ');';
		$stmt = Store::$pdo->prepare($query);
		foreach(array_values($data) as $i=>$value){
			$stmt->bindParam($i+1, $value);
		}
		
		$result = $stmt->execute();
		return Store::$pdo->lastInsertId();
	}

	public function update($table, $id, $data){
		if(!is_array($data))
			return false;
		$columns = '';
		$query = 'UPDATE `wuersch`.`' . $table . '` SET ';
		foreach($data as $name=>$value){
			$query .= '`' . $name . '`=?, ';
		}
		$query = substr($query, 0, -2);
		if(!is_numeric($id) && strlen($id)==32){
			$query .= ' WHERE `id_md5`=?;';
		}else{
			$query .= ' WHERE `id`=?;';
		}
		$stmt = Store::$pdo->prepare($query);
		foreach(array_values($data) as $i=>$value)
			$stmt->bindParam($i+1, $value);
		$stmt->bindParam(count($data)+1, $id);
		return $stmt->execute();
	}
}

?>
