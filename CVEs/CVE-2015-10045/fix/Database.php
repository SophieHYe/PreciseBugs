<?php 
require_once '/../config/define.php';

class Database 
{
	private $connection = null;

	function connect() 
	{
		$conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE) or die('Failed to connect to database: ' . mysqli_connect_error());
		if(mysqli_connect_error()) {
			return null;
		}
		else {
			$this->connection = $conn;
			return $conn;
		}
	}

	function getAffectedRows()
	{
		return mysqli_affected_rows($this->connection);
	}

	function sqlInjectionPrevent($value) {
		return mysqli_real_escape_string($this->connection, $value);
	}

	function query($sql) {
		$queryData = $this->connection->query($sql);
		if(!$this->getAffectedRows()) return false;
		if($queryData === true || $queryData === false) return $queryData;
		$result = array();
		if($queryData->num_rows > 0) {
			while($rows = $queryData->fetch_assoc()) {
				$result[] = $rows;
			}
		}
		return $result;
	}

	function where($where = array()) 
	{
		if(count($where)) {
			$arrTempWhere = array();
			foreach($where as $key => $value) {
				if(is_string($value)) $arrTempWhere[] = '' . $key . " = '" . $this->sqlInjectionPrevent($value) . "'";
				else $arrTempWhere[] = '' . $key . " = " . $value;
			}
			$strWhere = implode(' AND ', $arrTempWhere);
			return ' WHERE ' . $strWhere;
		}
		else return '';		
	}

	function select($columns = array(), $table = '', $where = array())
	{
		$sql = 'SELECT ';
		if($columns === array()) $sql .= '*';
		else $sql .= implode(',', $columns);
		$sql .= ' FROM ' . $table . ' ' . $this->where($where);
		var_dump($sql);
		return $this->query($sql);
	}

	function insert($values = array(), $table = '') 
	{
		$sql = "INSERT INTO $table (";
		$arrKeys = array();
		$arrValues = array();
		foreach($values as $key => $value) {
			$arrKeys[] = $key;
			if(is_string($value)) $arrValues[] = " '" . $this->sqlInjectionPrevent($value) . "' ";
			else $arrValues[]  = "$value";
		}
		$sql .= implode(',', $arrKeys) . ') VALUES (' . implode(',', $arrValues) . ')';
		return $this->query($sql);
	}

	function delete($table = '', $where = array()) 
	{
		$sql = "DELETE FROM $table ";
		$sql .= $this->where($where);
		return $this->query($sql);
	}

	function update($values = array(), $table = '', $where = array())
	{
		$arrTempValue = array();
		foreach($values as $key => $value) {
			if(is_string($value)) $arrTempValue[] = "$key = '" . $this->sqlInjectionPrevent($value) . "' ";
			else $arrTempValue[]  = "$key = $value";
		}
		$strValue = implode(',', $arrTempValue);
		$sql = "UPDATE $table SET $strValue " . $this->where($where);
		return $this->query($sql);
	}

	function disconnect()
	{
		if(is_resource($this->connection) && get_resource_type($this->connection) === 'mysql link') $this->connection->close();
	}
}