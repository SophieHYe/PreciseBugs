<?php

include_once '../models/Location_History.php';


// limit number of results
$limit = null;
if ($_POST['limit']) {
	$limit = $db->mysqli()->real_escape_string($_POST['limit']);
}


// select only a certain range of dates
$dayFilter = null;
if (isset($_POST['dayFilter'])) {
	$dayFilter = $db->mysqli()->real_escape_string(implode(',', $_POST['dayFilter']));
}


// filter out certain days of the week. the elements of this array are escaped in __build_query.php
$dateFilter = array('', '');
if (isset($_POST['dateFilter'])) {
	$dateFilter = $_POST['dateFilter'];
}



?>
