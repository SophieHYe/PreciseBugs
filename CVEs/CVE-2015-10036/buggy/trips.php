<?php

include_once '__parse_query.php';
include_once '../models/Circular.php';


// this cannot return meaningful results without a location id
if (!$_POST['location_id']) {
	return;
}

$location_id = htmlspecialchars($_POST['location_id']);






/* trips starting at location
–––––––––––––––––––––––––––––––––––––––––––––––––– */

    /* aggregate
    –––––––––––––––––––––––––––––– */
$query = "SELECT COUNT(t.end_location_id) AS count_lid, t.end_location_id, l.name, AVG(t.duration) AS duration, 0 AS start_time, AVG(t.distance) AS distance
FROM trip t, location l
WHERE t.end_location_id = l.id
AND t.start_location_id = " . $location_id;

include '__build_query.php';
$query .= " GROUP BY t.end_location_id HAVING count_lid > 1 ORDER BY count_lid DESC";
$start_aggregate = $db->rawQuery($query, null, false);




    /* all
    –––––––––––––––––––––––––––––– */
$query = "SELECT t.end_location_id, l.name, DATE(t.start_date) AS start_date, TIME(t.start_date) AS start_time, TIME_TO_SEC(t.start_date) AS start_time_sec, t.duration, t.distance
FROM trip t, location l,
	(SELECT COUNT(t.end_location_id) AS count_lid, t.end_location_id
	FROM trip t
	WHERE t.start_location_id = " . $location_id;

include '__build_query.php';

$query .= " GROUP BY t.end_location_id) AS c
WHERE t.end_location_id = l.id
AND t.end_location_id = c.end_location_id
AND t.start_location_id = " . $location_id;

include '__build_query.php';
$query .= " ORDER BY c.count_lid DESC, t.end_location_id, t.start_date ASC";
$start_all = $db->rawQuery($query, null, false);




/* trips ending at location
–––––––––––––––––––––––––––––––––––––––––––––––––– */

    /* aggregate
    –––––––––––––––––––––––––––––– */
$query = "SELECT COUNT(t.start_location_id) AS count_lid, t.start_location_id, l.name, AVG(t.duration) AS duration, 0 AS end_time, AVG(t.distance) AS distance
FROM trip t, location l
WHERE t.start_location_id = l.id
AND t.end_location_id = " . $location_id;

include '__build_query.php';
$query .= " GROUP BY t.start_location_id HAVING count_lid > 1 ORDER BY count_lid DESC";
$end_aggregate = $db->rawQuery($query, null, false);




    /* all
    –––––––––––––––––––––––––––––– */
$query = "SELECT t.start_location_id, l.name, DATE(t.end_date) AS end_date, TIME(t.end_date) AS end_time, TIME_TO_SEC(t.end_date) AS end_time_sec, t.duration, t.distance
FROM trip t, location l,
	(SELECT COUNT(t.start_location_id) AS count_lid, t.start_location_id
	FROM trip t
	WHERE t.end_location_id = " . $location_id;

include '__build_query.php';

$query .= " GROUP BY t.start_location_id) AS c
WHERE t.start_location_id = l.id
AND t.start_location_id = c.start_location_id
AND t.end_location_id = " . $location_id;

include '__build_query.php';
$query .= " ORDER BY c.count_lid DESC, t.start_location_id, t.end_date ASC";
$end_all = $db->rawQuery($query, null, false);






/* all trips starting at and ending at this location
–––––––––––––––––––––––––––––––––––––––––––––––––– */

$query = "SELECT ls.name AS start_name, le.name AS end_name, SQ.* FROM
(SELECT t.start_location_id, t.end_location_id, DATE(t.start_date) AS start_date, TIME(t.start_date) AS start_time, t.duration, t.distance
FROM trip t
WHERE t.start_location_id = " . $location_id . " OR t.end_location_id = " . $location_id;

include '__build_query.php';

$query .= " ORDER BY t.start_date ASC) SQ, location ls, location le
WHERE ls.id = SQ.start_location_id AND le.id = SQ.end_location_id";

$start_end_all = $db->rawQuery($query, null, false);










function modify_avg_times($all, &$aggregate, $location_string = 'end', $time_string = 'start') {

	$old_id = '';
	$times = array();

	foreach ($all as $result) {
		$id = $result[$location_string . '_location_id'];

		if ($old_id !== $id) {
			if (isset($times[$old_id]) && count($times[$old_id]) == 1) {
				unset($times[$old_id]);
				break;
			}
			$times[$id] = array();
		}

		$old_id = $id;
		$times[$id][] = $result[$time_string . '_time_sec'];
	}


	foreach ($aggregate as &$result) {
		$result[$time_string . '_time'] = round(circularMean(
			$times[$result[$location_string . '_location_id']]
			, 86400));
	}
	unset($result);
}

modify_avg_times($start_all, $start_aggregate);
modify_avg_times($end_all, $end_aggregate, 'start', 'end');


echo json_encode(array(
	"start_aggregate" => $start_aggregate,
	"start_all" => $start_all,
	"end_aggregate" => $end_aggregate,
	"end_all" => $end_all,
	"start_end_all" => $start_end_all
	));





?>




