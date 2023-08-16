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
			$query .= '`id_md5`="' . $id . '"';
		}else{
			$query .= '`id`=' . $id;
		}
		$sth = Store::$pdo->prepare($query . ';');
		$sth->execute();
		$result = $sth->fetchAll(PDO::FETCH_CLASS, ucfirst($table));
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
			$query .= '`' . $key . '`=';
			if(is_numeric($value))
				$query .= $value;
			else
				$query .= '"' . $value . '"';
			$query .= ' ' . $combination . ' ';
		}
		$query = substr($query, 0, (-2-strlen($combination)));
		$sth = Store::$pdo->prepare($query . ' LIMIT 100;');
		$sth->execute();
		return $sth->fetchAll(PDO::FETCH_CLASS, ucfirst($table));
	}

	public function insert($table, $data){
		if(!is_array($data))
			return -1;
		$columns = '`' . implode('`, `', array_keys($data)) . '`';
		$values = '';
		foreach($data as $value){
			$values .= $this->packValue($value) . ', ';
		}
		$values = substr($values, 0, -2);
		$result = Store::$pdo->exec('INSERT INTO `wuersch`.`' . $table . '`(' . $columns . ') VALUES(' . $values . ');');
		return Store::$pdo->lastInsertId();
	}

	public function update($table, $id, $data){
		if(!is_array($data))
			return false;
		$columns = '';
		foreach($data as $name=>$value){
			$columns .= '`' . $name . '`=' . $this->packValue($value) . ', ';
		}
		$columns = substr($columns, 0, -2);
		$query = 'UPDATE `wuersch`.`' . $table . '` SET ' . $columns . ' ';
		if(!is_numeric($id) && strlen($id)==32){
			$query .= 'WHERE `id_md5`="' . $id . '"';
		}else{
			$query .= 'WHERE `id`=' . $id;
		}
		return Store::$pdo->exec($query . ';');
	}
}

?>
