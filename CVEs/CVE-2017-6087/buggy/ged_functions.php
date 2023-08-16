<?php
/*
#########################################
#
# Copyright (C) 2016 EyesOfNetwork Team
# DEV NAME : Quentin HOARAU
# VERSION : 5.0
# APPLICATION : eonweb for eyesofnetwork project
#
# LICENCE :
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
#########################################
*/

include("../../include/config.php");
include("../../include/arrays.php");

function getEventState($event)
{
	switch ($event->state) {
		case 0: $event_state = "OK";		break;
		case 1: $event_state = "WARNING";	break;
		case 2: $event_state = "CRITICAL";	break;
		case 3: $event_state = "UNKNOWN";	break;
	}

	return $event_state;
}

function getClassRow($event_state)
{
	switch ($event_state) {
		case "OK"		: $row_class = "success";	break;
		case "WARNING"	: $row_class = "warning";	break;
		case "CRITICAL"	: $row_class = "danger"; 	break;
		case "UNKNOWN"	: $row_class = "info"; 		break;
	}

	return $row_class;
}

function createTableRow($event, $event_state, $queue)
{
	global $dateformat;
	global $ged_prefix;
	
	foreach ($event as $key => $value) {
		$class = "";

		if($key == "equipment"){
			$url_host = preg_replace("/^".$ged_prefix."/","",$value,1);
			$thruk_url = urlencode("/thruk/cgi-bin/extinfo.cgi?type=1&host=$url_host");
			$value = '<a href="../module_frame/index.php?url='.$thruk_url.'">'.$value.'</a>';
			$class = 'class="host"';
		}
		if($key == "service"){
			$url_host = preg_replace("/^".$ged_prefix."/","",$event->equipment,1);
			$thruk_url = urlencode("/thruk/cgi-bin/extinfo.cgi?type=2&host=".$url_host."&service=$value");
			$value = '<a href="../module_frame/index.php?url='.$thruk_url.'">'.$value.'</a>';
			$class = 'class="service"';
		}
		if ($key == "state" || $key == "comments") {
			continue;
		}
		if($key == "o_sec" || $key == "l_sec"){
			if($queue == "active"){
				$value = time() - $value;
				$value = round($value/60);
				$value .= " min";
			} else {
				$value = date($dateformat, $value);
			}
		}
		if($key == "id"){
			$value = "<input type='hidden' value='".$value."'>";
			$class = 'class="text-center"';
			if($event->comments != ""){
				$value .= ' <i class="glyphicon glyphicon-comment"></i>';
			}
			if($event->owner != ""){
				$value .= ' <i class="glyphicon glyphicon-floppy-disk"></i>';
			}
		}

		echo "<td $class>$value</td>";
	}	
}

function createSelectClause($ged_type, $queue)
{
	global $array_ged_packets;
	global $database_ged;

	$sql = "SELECT id,";
	foreach ($array_ged_packets as $key => $value) {
		if($value["col"] == true){
			if(isset($value["db_col"])){
				$sql .= $value["db_col"].',';
			} else {
				$sql .= $key.',';
			}
		}
	}
	$sql .= "comments";
	//$sql = trim($sql, ",");
	$sql .= " FROM ".$ged_type."_queue_".$queue;
	$sql .= " WHERE id > 0";

	return $sql;
}

function createWhereClause($owner, $filter, $search, $daterange, $ok, $warning, $critical, $unknown)
{
	$where_clause = "";
	
	// owner
	if($owner == "owned"){ $where_clause .= " AND owner != ''"; }
	elseif($owner == "not owned"){ $where_clause .= " AND owner = ''"; }

	// advanced search (with *)
	if($search != ""){
		$like = "";
		if( substr($search, 0, 1) === '*' ){
			$like .= "%";
		}
		$like .= trim($search, '*');
		if ( substr($search, -1) === '*' ) {
			$like .= "%";
		}

		$where_clause .= " AND $filter LIKE '$like'";
	}

	// daterange
	if($daterange != ""){
		$daterange_parts = explode(" - ", $daterange);
		$start = $daterange_parts[0];
		$end = $daterange_parts[1];

		// modify start and end timestamp (1 Jan 1970 = -3600).
		// perhaps a little bug from DateRangePicker
		$start = strtotime($start);
		$start += 3600;
		$end = strtotime($end);
		$end += 86400 + 3600;
		$where_clause .= " AND o_sec > $start AND o_sec < $end";
	}

	// states
	$states_list = "";
	if($ok != "")		{ $states_list .= "0,"; }
	if($warning != "")	{ $states_list .= "1,"; }
	if($critical != "")	{ $states_list .= "2,"; }
	if($unknown != "")	{ $states_list .= "3,"; }
	$states_list = trim($states_list, ",");
	
	if($states_list != ""){
		$where_clause .= " AND state IN ($states_list)";
	}

	$where_clause .= " ORDER BY o_sec DESC LIMIT 500";
	return $where_clause;
}

function createDetailRow($event, $db_col_name, $row_name)
{
	global $dateformat;

	// display a good date format
	if($db_col_name == "o_sec" || $db_col_name == "l_sec" || $db_col_name == "a_sec"){
		if($db_col_name == "a_sec" && $event["queue"] == "a"){
			return false;
		}
		if($db_col_name == "a_sec" && $event[$db_col_name] == 0){
			$event[$db_col_name] = "";
		}
		$event[$db_col_name] = date($dateformat, $event[$db_col_name]+0);
	}

	// display a good state format
	if($db_col_name == "state"){
		switch($event[$db_col_name]){
			case 0: $event[$db_col_name] = "OK"; break;
			case 1: $event[$db_col_name] = "WARNING"; break;
			case 2: $event[$db_col_name] = "CRITICAL"; break;
			case 3: $event[$db_col_name] = "UNKNOWN"; break;
		}
	}

	echo '<tr>';
		echo '<th scope="row">'.getLabel($row_name).'</th>';
		echo '<td>'.$event[$db_col_name].'</td>';
	echo '</tr>';
}

function details($selected_events, $queue)
{
	global $database_ged;

	// get all needed infos into variables
	$value_parts = explode(":", $selected_events);
	$id = $value_parts[0];
	$ged_type = $value_parts[1];

	$sql = "SELECT * FROM ".$ged_type."_queue_".$queue." WHERE id = $id";
	$result = sqlrequest($database_ged, $sql);
	$event = mysqli_fetch_assoc($result);

	// display event's details
	echo '<table class="table table-hover table-condensed">';
		echo '<tbody>';
			createDetailRow($event, "equipment", "label.host");
			createDetailRow($event, "host_alias", "label.host_alias");
			createDetailRow($event, "ip_address", "label.ip_address");
			createDetailRow($event, "service", "label.service");
			createDetailRow($event, "state", "label.state");
			createDetailRow($event, "description", "label.desc");
			createDetailRow($event, "occ", "label.occurence");
			createDetailRow($event, "o_sec", "label.o_time");
			createDetailRow($event, "l_sec", "label.l_time");
			createDetailRow($event, "a_sec", "label.a_time");
			createDetailRow($event, "hostgroups", "label.hostgroups");
			createDetailRow($event, "servicegroups", "label.servicegroups");
			createDetailRow($event, "src", "label.source");
			createDetailRow($event, "owner", "label.owner");
			createDetailRow($event, "comments", "label.comments");
		echo '</tbody>';
	echo '</table>';
}

function edit($selected_events, $queue)
{
	global $database_ged;

	// get all needed infos into variables
	$value_parts = explode(":", $selected_events);
	$id = $value_parts[0];
	$ged_type = $value_parts[1];

	$sql = "SELECT comments FROM ".$ged_type."_queue_".$queue." WHERE id = $id";
	$result = sqlrequest($database_ged, $sql);
	$event = mysqli_fetch_assoc($result);

	$event["comments"] = str_replace("\'", "'", $event["comments"]);
	$event["comments"] = str_replace("\#", "#'", $event["comments"]);

	echo "
	<form id='edit-event-form'>
		<div class='form-group'>
			<label>".getLabel("label.add_comment")."</label>
			<textarea id='event-comments' class='form-control textarea' rows='10'>".$event["comments"]."</textarea>
		</div>
	</form>";
}

function editEvent($selected_events, $queue, $comments)
{
	global $database_ged;

	// get all needed infos into variables
	$value_parts = explode(":", $selected_events);
	$id = $value_parts[0];
	$ged_type = $value_parts[1];

	// format comment string to avoid errors
	$comments = str_replace("'", "\'", $comments);
	$comments = str_replace("#", "\#", $comments);

	$sql = "UPDATE ".$ged_type."_queue_".$queue." SET comments='$comments' WHERE id = $id";
	$result = sqlrequest($database_ged, $sql);
	if($result){
		message(11, " : ".getLabel("message.event_edited"), "ok");
	} else {
		message(11, " : ".getLabel("message.event_edited_error"), "danger");
	}
}

function editAllEvents($selected_events, $queue, $comments)
{
	global $database_ged;

	$success = true;
	foreach ($selected_events as $key => $value) {
		// get all needed infos into variables
		$value_parts = explode(":", $value);
		$id = $value_parts[0];
		$ged_type = $value_parts[1];

		// format comment string to avoid errors
		$comments = str_replace("'", "\'", $comments);
		$comments = str_replace("#", "\#", $comments);

		$sql = "UPDATE ".$ged_type."_queue_".$queue." SET comments='$comments' WHERE id = $id";
		$result = sqlrequest($database_ged, $sql);
		if(!$result){
			$success = false;
		}
	}

	// display the final message
	if($success){
		message(11, " : ".getLabel("message.event_edited"), "ok");
	} else {
		message(11, " : ".getLabel("message.event_edited_error"), "danger");
	}
}

function ownDisown($selected_events, $queue, $global_action)
{
	global $database_ged;
	global $array_ged_packets;
	global $path_ged_bin;
	global $array_serv_system;

	if(exec($array_serv_system["Ged agent"]["status"])==NULL) {
		return message(0," : ged daemon must be dead","critical");
	}

	if($global_action == 2){
		$owner = $_COOKIE['user_name']."@".getenv("SERVER_NAME");
	} else {
		$owner = "";
	}

	foreach ($selected_events as $value) {
		$value_parts = explode(":", $value);
		$id = $value_parts[0];
		$ged_type = $value_parts[1];
		if($ged_type == "nagios"){ $ged_type_nbr = 1; }
		if($ged_type == "snmptrap"){ $ged_type_nbr = 2; }

		$sql = "SELECT * FROM ".$ged_type."_queue_".$queue." WHERE id = $id";
		$result = sqlrequest($database_ged, $sql);
		$event = mysqli_fetch_assoc($result);

		$ged_command = "-update -type $ged_type_nbr ";
		foreach ($array_ged_packets as $key => $value) {
			if($value["type"] == true){
				if($key == "owner"){
					$event[$key] = $owner;
				}
				$ged_command .= "\"".$event[$key]."\" ";
			}
		}
		$ged_command = trim($ged_command, " ");

		shell_exec($path_ged_bin." ".$ged_command);
		logging("ged_update",$ged_command);
	}
}

function acknowledge($selected_events, $queue)
{
	global $database_ged;
	global $array_ged_packets;
	global $path_ged_bin;
	global $array_serv_system;

	if(exec($array_serv_system["Ged agent"]["status"])==NULL) {
		return message(0," : ged daemon must be dead","critical");
	}

	$owner = $_COOKIE['user_name']."@".getenv("SERVER_NAME");

	foreach ($selected_events as $value) {
		$value_parts = explode(":", $value);
		$id = $value_parts[0];
		$ged_type = $value_parts[1];
		if($ged_type == "nagios"){ $ged_type_nbr = 1; }
		if($ged_type == "snmptrap"){ $ged_type_nbr = 2; }

		$event_to_delete = [];
		array_push($event_to_delete, $value);

		$sql = "SELECT * FROM ".$ged_type."_queue_".$queue." WHERE id = $id";
		$result = sqlrequest($database_ged, $sql);
		$event = mysqli_fetch_assoc($result);

		$ged_command = "-update -type $ged_type_nbr ";
		foreach ($array_ged_packets as $key => $value) {
			if($value["type"] == true){
				if($key == "owner"){
					$event[$key] = $owner;
				}
				$ged_command .= "\"".$event[$key]."\" ";
			}
		}
		$ged_command = trim($ged_command, " ");

		shell_exec($path_ged_bin." ".$ged_command);
		logging("ged_update",$ged_command);
		delete($event_to_delete, $queue);
	}
}

function delete($selected_events, $queue)
{
	global $database_ged;
	global $array_ged_packets;
	global $path_ged_bin;
	global $array_serv_system;

	if(exec($array_serv_system["Ged agent"]["status"])==NULL) {
		return message(0," : ged daemon must be dead","critical");
	}

	$id_list = "";
	foreach ($selected_events as $value) {
		$value_parts = explode(":", $value);
		$id = $value_parts[0];
		$ged_type = $value_parts[1];
		$ged_type_nbr = 0;
		if($ged_type == "nagios"){ $ged_type_nbr = 1; }
		if($ged_type == "snmptrap"){ $ged_type_nbr = 2; }

		$sql = "SELECT * FROM ".$ged_type."_queue_".$queue." WHERE id = $id";
		$result = sqlrequest($database_ged, $sql);
		$event = mysqli_fetch_assoc($result);

		if($queue == "active"){
			$ged_command = "-drop -type $ged_type_nbr -queue $queue ";
			foreach ($array_ged_packets as $key => $value) {
				if($value["key"] == true){
					$ged_command .= "\"".$event[$key]."\" ";
				}
			}
			$ged_command = trim($ged_command, " ");

			shell_exec($path_ged_bin." ".$ged_command);
			logging("ged_update",$ged_command);
		} else {
			$id_list .= $id.",";
		}
	}

	if($queue == "history"){
		$id_list = trim($id_list, ",");
		$ged_command = "-drop -id ".$id_list." -queue history";

		shell_exec($path_ged_bin." ".$ged_command);
		logging("ged_update",$ged_command);
	}
}

// Open Xml function
function openXml($file=false)
{
	$dom = new DOMDocument("1.0","UTF-8");
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	if($file)
		$dom->load($file);
	return $dom;
}

function changeGedFilter($filter_name)
{
	$file="../../cache/".$_COOKIE["user_name"]."-ged.xml";

	if(file_exists($file)){
		$xmlfilters = new DOMDocument("1.0","UTF-8");
		$xmlfilters->load($file);

		$root = $xmlfilters->getElementsByTagName("ged")->item(0);
		$root->removeChild($root->getElementsByTagName('default')->item(0));
		$default = $xmlfilters->createElement("default");
		$default = $root->appendChild($default);
		$default = $root->getElementsByTagName("default")->item(0);
		$default->appendChild($xmlfilters->createTextNode($filter_name));
		$xmlfilters->save($file);
	}
}

// advanced search autocomplete
function advancedFilterSearch($queue, $filter)
{
	global $database_ged;
	$datas = array();

	if($filter == "description"){
		echo json_encode($datas);
		return false;
	}

	$gedsql_result1=sqlrequest($database_ged,"SELECT pkt_type_id,pkt_type_name FROM pkt_type WHERE pkt_type_id!='0' AND pkt_type_id<'100';");
	
	
	while($ged_type = mysqli_fetch_assoc($gedsql_result1)){
		$sql = "SELECT DISTINCT $filter FROM ".$ged_type["pkt_type_name"]."_queue_".$queue;

		$results = sqlrequest($database_ged, $sql);
		while($result = mysqli_fetch_array($results)){
			if( !in_array($result[$filter], $datas) && $result[$filter] != "" ){
				array_push($datas, $result[$filter]);
			}
		}
	}

	echo json_encode($datas);
}

?>
