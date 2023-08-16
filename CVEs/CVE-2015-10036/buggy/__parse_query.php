<?php

include_once '../models/Location_History.php';


// limit number of results
$limit = null;
if ($_POST['limit']) {
	$limit = $_POST['limit'];
}


// select only a certain range of dates
$dayFilter = null;
if (isset($_POST['dayFilter'])) {
	$dayFilter = implode(',', $_POST['dayFilter']);
}


// filter out certain days of the week
$dateFilter = array('', '');
if (isset($_POST['dateFilter'])) {
	$dateFilter = $_POST['dateFilter'];
}



?>
