<?php

include_once '__parse_query.php';



// this cannot return meaningful results without a location id
if (!$_POST['location_id']) {
	return;
}

$location_id = $_POST['location_id'];


// visits for location
$query = "SELECT
	DATE(gp.start_date) as start_date, TIME(gp.start_date) as start_time, TIME_TO_SEC(gp.start_date) as start_time_sec, TIME_TO_SEC(gp.end_date) as end_time_sec, duration
	FROM grouped_point gp
	WHERE location_id = " . htmlspecialchars($location_id);



// CONSTRUCT WHERE CLAUSE
include '__build_query.php';
include_once '../models/Circular.php';

$results = $db->rawQuery($query, null, false);


$start_times = array();
$end_times = array();

foreach($results as $result) {
	$start_times[] = $result['start_time_sec'];
	$end_times[] = $result['end_time_sec'];
}


echo json_encode(array
	("mean_times" => array
		("start" => round(circularMean($start_times, 86400)),
		"end" => round(circularMean($end_times, 86400))),
	"results" => $results));




?>
