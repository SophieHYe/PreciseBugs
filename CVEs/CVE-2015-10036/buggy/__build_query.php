<?php

include_once '../models/Location_History.php';



// range of dates
if ($dateFilter[0]) {
	$query .= " AND start_date >= STR_TO_DATE('" . htmlspecialchars($dateFilter[0]) . "', '%m/%d/%Y')";
}

if ($dateFilter[1]) {
	$query .= " AND start_date <= STR_TO_DATE('" . htmlspecialchars($dateFilter[1]) . "', '%m/%d/%Y')";
}


// filtered-out days
if (isset($dayFilter)) {
	$query .= " AND DAYOFWEEK(start_date) NOT IN (" . htmlspecialchars($dayFilter) . ")";
}


?>
