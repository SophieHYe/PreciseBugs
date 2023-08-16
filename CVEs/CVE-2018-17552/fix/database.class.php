<?php
/**
 * Navigate CMS database functions
 * 
 * @copyright Copyright (C) 2010-2018 Naviwebs. All rights reserved.
 * @author Naviwebs (http://www.naviwebs.com)
 * @license GPLv2
 */

class database
{
	private $lastError;
	private $lastAffectedRows;
	private $lastResult;
	private $db;
	private $prepared_statement;
	public $queries_count;
	
	/**
	 * Prepare a database object and assign default settings
	 */
	public function __construct()
	{
		$this->lastError = '';
		$this->lastAffectedRows = '';
		$this->lastResult = "";
		$this->fetchAs = PDO::FETCH_OBJ;
		$this->db = NULL;
		$this->queries_count = 0;
	}

	/**
	 * Try to connect to the database
	 *
	 * @return boolean True if the connection could be established
	 */	
	public function connect()
	{
		$this->lastError = '';
		switch(PDO_DRIVER)
		{
			case 'mysql':
				try 
				{
                    if(PDO_HOSTNAME!="")
                        $dsn = "mysql:host=".PDO_HOSTNAME.";port=".PDO_PORT.";charset=utf8mb4;dbname=".PDO_DATABASE;
                    else
                        $dsn = "mysql:unix_socket=".PDO_SOCKET.";charset=utf8mb4;dbname=".PDO_DATABASE;
					$this->db = new PDO($dsn, PDO_USERNAME, PDO_PASSWORD);
                    $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                    $this->db->exec('SET NAMES utf8mb4');
				}
				catch(PDOException $e)
				{
					$this->lastError = $e->getMessage();
				}	
				break;
			
			default:
				$this->lastError = 'NO PDO DRIVER';
		}
		
		if($this->db)
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING); // PDO::ERRMODE_EXCEPTION
		
		return !empty($this->db);		
	}

	/**
	 * Close the connection to the database
	 */		
	public function disconnect()
	{
		unset($this->db);
	}

	/**
	 * Refresh the connection to the database
	 * 
	 * @return boolean True if connection could be established
	 */	
    public function reconnect()
    {
        $this->disconnect();
        return $this->connect();
    }

	/**
	 * Send a SQL query to the database
	 *
	 * Given a certain ID, function t returns the associated string
	 * in the current active language.
	 *
	 * @param string $sql The complete SQL query
	 * @param string $fetch_mode How to retrieve the data: "object" or "array"
     * @param array $parameters SQL query parameters associative array
	 * @return boolean True if the query was executed without errors
	 */	
	public function query($sql, $fetch_mode='object', $parameters=array())
	{
		$this->lastError = '';
		$this->lastResult = '';

		switch($fetch_mode)
		{
			case 'array':
				$fetch = PDO::FETCH_ASSOC;
				break;
			
			case 'object':
			default:
				$fetch = PDO::FETCH_OBJ;		
				break;	
		}

		try
		{
		    if(empty($parameters))
            {
			    $statement = $this->db->query($sql);
            }
            else
            {
                $statement = $this->db->prepare($sql);
                $statement->execute($parameters);
            }
			$this->queries_count++;

			// avoid firing a fatal error exception when the result is NULL
            // and the query is not malformed
			if(!$statement)
                return false;

			$statement->setFetchMode($fetch);
			$this->lastResult = $statement->fetchAll();
			$statement->closeCursor();
			unset($statement);
		}
		catch(PDOException $e)
		{
			$this->lastError = $e->getMessage();
		}
		catch(Exception $e)
		{
			return false;
		}		
		
		return empty($this->lastError);	
	}

	/**
	 * Send a SQL query to the database and get the first value of the first column of the resultset
	 *
	 * @param string $column Name of the column to retrieve
	 * @param string $table Table name to get the data from
	 * @param string $where SQL conditions in the WHERE clause
	 * @param string $order SQL order conditions in the ORDER BY clause
     * @param array $parameters SQL query parameters associative array
	 * @return string|integer Value of the first column of the first row of the resultset
	 */		
	public function query_single($column, $table, $where = '1=1', $order = '', $parameters=array())
	{
	    $rs = null;
        if(!empty($order))
            $order = ' ORDER BY '.$order;

        $sql = 'SELECT ' . $column . ' FROM ' . $table . ' WHERE ' . $where . $order . ' LIMIT 1';

        try
        {
            if(empty($parameters))
            {
                $stm = $this->db->query($sql);
            }
            else
            {
                $stm = $this->db->prepare($sql);
                $stm->execute($parameters);
            }
            $this->queries_count++;
            $stm->setFetchMode(PDO::FETCH_NUM);
            $rs = $stm->fetchAll();
            $stm->closeCursor();
            unset($stm);
        }
        catch(Exception $e)
        {
            return NULL;
        }

		if(empty($rs))
		{
            return NULL;
        }
		else
        {
            return $rs[0][0];
        }
	}


	/**
	 * Send a SQL query to the database and get the resulset with an offset and a limit
	 *
	 * @param string $cols Comma separated names of the columns to retrieve
	 * @param string $table Table name to get the data from
	 * @param string $where SQL conditions in the WHERE clause
	 * @param string $order SQL order clause
	 * @param integer $offset The number of rows to skip of the resultset
	 * @param integer $max How many rows will be returned of the resultset (after applying offset)
	 * @return boolean True if the query could be executed without errors
	 */	
	public function	queryLimit($cols, $table, $where="1=1", $order="", $offset=0, $max=100, $parameters=array())
	{		
		$this->lastError = '';
		$this->lastResult = '';	
		$fetch = PDO::FETCH_ASSOC;

		try
		{
			$sql = ' SELECT SQL_CALC_FOUND_ROWS '.$cols.'
					   FROM '.$table.'
					  WHERE '.$where.'
 				   ORDER BY '.$order.' 
					  LIMIT '.$max.'
					 OFFSET '.$offset;

			if(empty($parameters))
            {
                $statement = $this->db->query($sql);
            }
            else
            {
                $statement = $this->db->prepare($sql);
                $statement->execute($parameters);
            }

            $this->queries_count++;
			$statement->setFetchMode($fetch);
			$this->lastResult = $statement->fetchAll();
			$statement->closeCursor();
			unset($statement);
		}
		catch(PDOException $e)
		{
			$this->lastError = $e->getMessage();
		}
		catch(Exception $e)
		{
			return false;
		}		
		
		return empty($this->lastError);			
	}


	/**
	 * Execute a SQL command to the database (excluding SELECT).
	 * It has two modes: direct SQL or prepared statement.
	 *
	 * Example 1: $DB->execute("INSERT INTO recipes (id_recipe, title) VALUES (0, 'Coffee and toasts')");
	 *
	 * Example 2: $DB->execute("INSERT INTO recipes (id_recipe, title) VALUES (?, ?)", array(0, 'Coffee and toasts') );
	 *
	 * Example 3: $DB->execute("UPDATE recipes SET visits = visits + 1 WHERE id_recipe = :id_recipe", array(":id_recipe" => 30) );
	 *
	 * @param string $sql SQL command to execute, if it's a prepared statement you can use "?" placeholders
	 * @param array $prepared Array of values in the order of the "?" used in the SQL command or an associative array ":column" => "value"
	 *
	 * @return boolean True if the query could be executed without errors
	 */	
	public function execute($sql, $prepared=array())
	{
		$this->lastAffectedRows = NULL;
		$this->lastError = '';
        $this->lastResult = '';

		try
		{
			if(empty($prepared))
            {
                $this->lastAffectedRows = $this->db->exec($sql);
            }
			else
			{			
				if(is_array($prepared))
				{
					// we have the parameters as an array, use it with the prepared statement
					$stm = $this->db->prepare($sql);
                    $this->queries_count++;

					if($stm->execute($prepared))
                    {
                        $this->lastAffectedRows = $stm->rowCount();
                        @$stm->closeCursor();
                        unset($stm);
                    }
                    else
                    {
                        $error = $stm->errorInfo();
                        unset($stm);
                        throw new Exception('SQL '.$error[0].'/'.$error[1].': '.$error[2].' / '.$sql);
                    }
				}
				else // boolean true, prepared statement mode 2
				{
					// we don't have a parameters array, so execute it as is
					$stm = $this->db->prepare($sql);
                    $this->queries_count++;
					if($stm->execute()) 
						$this->lastAffectedRows = $stm->rowCount();
                    @$stm->closeCursor();
                    unset($stm);
				}
			}						
		}
		catch(PDOException $e)
		{
			$this->lastError = $e->getMessage();	
		}
		catch(Exception $e)
		{
			$this->lastError = $e->getMessage();
		}

		return empty($this->lastError);
	}

	
	/**
	 * Get the last id generated for an INSERT
	 *
	 * @return integer Integer value of the last id generated for an INSERT
	 */		
	public function get_last_id()
	{
		 return $this->db->lastInsertId();			
	}

	/**
	 * Get the number of affected rows by the last SQL command executed
	 *
	 * @return integer number of affected rows by the last SQL command executed
	 */			
	public function get_affected_rows()
	{
		return $this->lastAffectedRows;
	}


	/**
	 * Return the last error given by the database
	 *
	 * @return string Last database error
	 */		
	public function get_last_error()
	{
		return $this->lastError;	
	}

	/**
	 * Return the last error given by the database
	 *
	 * @return string Last database error
	 */		
	public function error()
	{
		return $this->lastError;	
	}	
	

	/**
	 * Return the result of the last query sent to database
	 *
	 * @param string $column Return only the values of the given column
	 * @return array Last query resultset
	 */		
	public function result($column="")
	{
		if(!empty($column))
		{
			$result = array();
			$total = count($this->lastResult);
			for($i=0; $i < $total; $i++)
			{
				if(is_array($this->lastResult[$i]))
					array_push($result, $this->lastResult[$i][$column]);
				else if(is_object($this->lastResult[$i]))
					array_push($result, $this->lastResult[$i]->$column);
			}
			return $result;			
		}
		else
			return $this->lastResult;
	}
			

	/**
	 * Return the first row of the last query sent to database
	 *
	 * @return array|object The first row or object of the last query sent to database
	 */				
	public function first()
	{
		return $this->lastResult[0];
	}
	

	/**
	 * Count how many rows meet the conditions of the last query sent to database
	 *
	 * @return integer Integer with the number of rows that meet the conditions of the last query sent to database
	 */		
	public function foundRows()
	{
		$stm = $this->db->query('SELECT FOUND_ROWS();');
		$stm->setFetchMode(PDO::FETCH_NUM);
		$rs = $stm->fetchAll();
		unset($stm);
		return intval($rs[0][0]);		
	}
	

	/**
	 * Prepare a string to be sent to the database with security
	 *
	 * @param string $str String to protect
	 * @param string $wrapped_by Surround the input string with "double" or 'single' quotes (default is "double")
	 * @return string Database protected string
	 */		
	public function protect($str, $wrapped_by="") 
	{
	    // remove some characters to prevent SQL attacks
        $str = mb_escape($str);

		if(is_integer($str))
		{
			// do nothing
		}
		else if($wrapped_by=='double') 		
		{
			$str = str_replace('"', "\\".'"', $str);
		}
		else if($wrapped_by=='single') 	
		{
			$str = str_replace("'", "\\"."'", $str);
		}
		else
		{
			$str = str_replace('"', "\\".'"', $str);
			$str = '"'.$str.'"';
		}

		return $str;
	}
	

	/**
	 * Sets an attribute on the database handle.
	 *
	 * Example: setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true)
	 * Check PHP Manual to get more info: http://php.net/manual/en/pdo.setattribute.php
	 *
	 * @param string $attribute String to protect
	 * @param string $value Surround the input string with "double" or 'single' quotes (default is "double")
	 * @return boolean TRUE on success or FALSE on failure
	 */			
	public function setAttribute($attribute, $value)
	{
		return $this->db->setAttribute($attribute, $value);	
	}

	/**
	 * Initiates a transaction, turning off autocommit
	 *
	 * @return boolean TRUE on success or FALSE on failure
	 */			
	public function beginTransaction()
	{
		return $this->db->beginTransaction();	
	}
	
	/**
	 * Commits a transaction
	 *
	 * @return boolean TRUE on success or FALSE on failure
	 */	
	public function commit()
	{
		return $this->db->commit();
	}
	
	/**
	 * Rolls back a transaction 
	 *
	 * @return boolean TRUE on success or FALSE on failure
	 */
	public function rollback()
	{
		return $this->db->rollBack();
	}
}

?>