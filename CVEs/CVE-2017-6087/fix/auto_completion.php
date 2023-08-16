<?php

include("../../../include/config.php");

// Mot tapé par l'utilisateur
$q = $_GET['query'];
$table_name = $_GET['table_name'];

try {
	$bdd = new PDO('mysql:host=localhost;dbname='.$database_lilac, $database_username, $database_password);
} catch(Exception $e) {
	 echo "Connection failed: " . $e->getMessage();
	exit('Impossible de se connecter à la base de données.');
}

// Requête SQL
$requete = "SELECT name FROM " . $table_name .  " WHERE name LIKE '". $q ."%' LIMIT 0, 10";

foreach  ($bdd->query($requete) as $row) {
	$suggestions['suggestions'][] = $row['name'];
}
echo json_encode($suggestions);

?>
