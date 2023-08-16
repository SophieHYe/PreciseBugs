<?php

include_once '../models/Location_History.php';


// range of dates
if ($dateFilter[0]) {
	$query .= " AND start_date >= STR_TO_DATE('" . $db->mysqli()->real_escape_string($dateFilter[0]) . "', '%m/%d/%Y')";
}

if ($dateFilter[1]) {
	$query .= " AND start_date <= STR_TO_DATE('" . $db->mysqli()->real_escape_string($dateFilter[1]) . "', '%m/%d/%Y')";
}


// filtered-out days
if (isset($dayFilter)) {
	$query .= " AND DAYOFWEEK(start_date) NOT IN (" . $dayFilter . ")";
}


?>
