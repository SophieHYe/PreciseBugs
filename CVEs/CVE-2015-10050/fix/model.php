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
		$results = $this->db->prepare("SELECT * FROM `mirna` WHERE Name = ?");
		$results->bindParam(1,$new);
		$results->execute();
		} catch(Exception $e)
		{
			echo"Could not query the database.";
			exit;
		}
	$mirnas = $results->fetchAll(PDO::FETCH_ASSOC);
	return $mirnas;
	}

	function count_rna($name,$tissue,$onc=''){
		try {
		  if($tissue == "all"){
		  	$total = $this->db->prepare("SELECT count(*) FROM mirna WHERE Name LIKE ? AND Cancer_Effect LIKE ?");
		  	$total->bindValue(1,"%" . $name . "%");
		  	$total->bindValue(2,"%" . $onc . "%");
		  	$total->execute();
			} else {
				$total = $this->db->prepare("SELECT count(*) FROM mirna WHERE Name LIKE ? AND tissue = ? AND Cancer_Effect LIKE ?");
		  		$total->bindValue(1,"%" . $name . "%");
		  		$total->bindParam(2,$tissue);
		  		$total->bindValue(3,"%" . $onc . "%");
		  		$total->execute();
		  }
		} catch(Exception $e){
			echo"Could not query the database.";
			exit;
		} 
		$arr = $total->fetchAll(PDO::FETCH_ASSOC);
		$total_n = intval($arr[0]["count(*)"]);
		return $total_n;
	}
	function select_rnas($name,$tissue,$records_perpage=100,$c_p=0,$onc=''){
		try {
		  if($tissue == "all"){
			$results = $this->db->prepare("SELECT * FROM mirna WHERE Name LIKE ? AND Cancer_Effect LIKE ? ORDER BY databaseid ASC LIMIT ? OFFSET ?");
		  	$results->bindValue(1,"%" . $name . "%");
		  	$results->bindValue(2,"%" . $onc . "%");
		  	$results->bindParam(3,$records_perpage,PDO::PARAM_INT);
		  	$results->bindParam(4,$c_p,PDO::PARAM_INT);
		  	$results->execute();
			} else {
		  		$results = $this->db->prepare("SELECT * FROM mirna WHERE Name LIKE ? AND tissue = ? AND Cancer_Effect LIKE ? ORDER BY databaseid ASC LIMIT ? OFFSET ?");
		  		$results->bindValue(1,"%" . $name . "%");
		  		$total->bindParam(2,$tissue);
		  		$results->bindValue(3,"%" . $onc . "%");
		  		$results->bindParam(4,$records_perpage,PDO::PARAM_INT);
		  		$results->bindParam(5,$c_p,PDO::PARAM_INT);
		  		$results->execute();
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


