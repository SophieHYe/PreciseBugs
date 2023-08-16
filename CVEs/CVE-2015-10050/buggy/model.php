<?php

class rna_database{
	public function __construct($db_name){
		try {
			$this->db = new PDO("mysql:host=localhost;dbname=$db_name;port=3306","hu","");
			$this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
			} catch(Exception $e){
			echo"Could not connect to the database.";
			exit;
		}
	}

	function select_single_rna($new){

	try {
		$results = $this->db->query("SELECT * FROM `mirna` WHERE Name = '$new'");
	
		} catch(Exception $e)
		{
			echo"Could not query the database.";
			exit;
		}
	$mirnas = $results->fetchAll(PDO::FETCH_ASSOC);
	return $mirnas;
	}

	function count_rna($name,$tissue){
		try {
		  if($tissue == "all"){
		  	$total = $this->db->query("SELECT count(*) FROM mirna WHERE Name LIKE '%$name%'");
		  
			} else {
				$total = $this->db->query("SELECT count(*) FROM mirna WHERE Name LIKE '%$name%' AND tissue = '$tissue'");
		  
		  }
		} catch(Exception $e){
			echo"Could not query the database.";
			exit;
		} 
		$arr = $total->fetchAll(PDO::FETCH_ASSOC);
		$total_n = intval($arr[0]["count(*)"]);
		return $total_n;
	}
	function select_rnas($name,$tissue,$records_perpage=100,$c_p=0){
		try {
		  if($tissue == "all"){
			$results = $this->db->query("SELECT * FROM mirna WHERE Name LIKE '%$name%' ORDER BY databaseid ASC LIMIT $records_perpage OFFSET $c_p");
		  
			} else {
		  		$results = $this->db->query("SELECT * FROM mirna WHERE Name LIKE '%$name%' AND tissue = '$tissue' ORDER BY databaseid ASC LIMIT $records_perpage OFFSET $c_p");
		  
		  }
		} catch(Exception $e){
			echo"Could not query the database.";
			exit;
			} 
		$mirnas = $results->fetchAll(PDO::FETCH_ASSOC);
		return $mirnas;

	}


}

function print_rna_link($mirnas){
	$link = "";
	foreach ($mirnas as $mirna) {
	$mirna_name = $mirna["Name"];
	$link .= "<a href = ";
	$link .='"mirna.php?new=';
    $link .= $mirna_name;
	$link .=' " class="mirnalist">'. $mirna_name."</a><br>";
	}
	return $link;

}


