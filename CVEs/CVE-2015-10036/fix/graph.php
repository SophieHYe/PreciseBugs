<?php

include_once '__parse_query.php';



// this cannot return meaningful results with a location id
if (!$_POST['location_id']) {
	return;
}


// visits for location
$query = "SELECT
	DATE(start_date) AS date, SUM(duration) AS duration
	FROM grouped_point gp
	WHERE location_id = ?";


// CONSTRUCT WHERE CLAUSE
include '__build_query.php';

// group by comes after where clause
$query .= " GROUP BY DATE(start_date)";

$results = $db->rawQuery($query, Array($_POST['location_id']));


// convert seconds to hours, and make sure that no day is longer than 24 hours, by rolling extra hours over to the next record
foreach($results as $index => &$result) {
	$duration = $result['duration'];

	if ($duration > 86400) {
		if (isset($results[$index + 1])) {
			$results[$index + 1]['duration'] += $duration - 86400;
		}
		$duration = 86400;

	}

	$result['duration'] = round($duration / 3600, 1);
}
unset($value);


echo json_encode($results);




?>
