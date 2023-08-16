<?php

include("../../../include/config.php");

$action = isset($_GET['action']) ? $_GET['action'] : false;
$bp_name = isset($_GET['bp_name']) ? $_GET['bp_name'] : false;
$host_name = isset($_GET['host_name']) ? $_GET['host_name'] : false;
$service = isset($_GET['service']) ? $_GET['service'] : false;
$new_services = isset($_GET['new_services']) ? $_GET['new_services'] : false;

$uniq_name = isset($_GET['uniq_name']) ? $_GET['uniq_name'] : false;
$uniq_name_orig = isset($_GET['uniq_name_orig']) ? $_GET['uniq_name_orig'] : false;
$process_name = isset($_GET['process_name']) ? $_GET['process_name'] : false;
$display = isset($_GET['display']) ? $_GET['display'] : false;
$url = isset($_GET['url']) ? $_GET['url'] : false;
$command = isset($_GET['command']) ? $_GET['command'] : false;
$type = isset($_GET['type']) ? $_GET['type'] : false;
$min_value = isset($_GET['min_value']) ? $_GET['min_value'] : false;

try {
	$bdd = new PDO('mysql:host=localhost;dbname='.$database_nagios, $database_username, $database_password);
} catch(Exception $e) {
	echo "Connection failed: " . $e->getMessage();
	exit('Impossible de se connecter à la base de données.');
}

if($action == 'verify_services'){
    verify_services($bp_name,$host_name,$bdd);
}

elseif($action == 'delete_bp'){
    delete_bp($bp_name,$bdd);
}

elseif($action == 'list_services'){
    list_services($host_name);
}

elseif($action == 'list_process'){
	list_process($bp_name,$display,$bdd);
}

elseif ($action == 'add_services'){
	add_services($bp_name,$new_services,$bdd);
}

elseif ($action == 'add_process'){
    add_process($bp_name,$new_services,$bdd);
}

elseif ($action == 'add_application'){
	add_application($uniq_name_orig,$uniq_name,$process_name,$display,$url,$command,$type,$min_value,$bdd);
}

elseif ($action == 'build_file'){
	build_file($bdd);
}

elseif ($action == 'info_application'){
	info_application($bp_name,$bdd);
}

elseif ($action == 'check_app_exists'){
	check_app_exists($uniq_name, $bdd);
}

function verify_services($bp,$host,$bdd){
	$sql = "select COUNT(*),service from bp_services where bp_name = '" . $bp . "' and host = '". $host . "'";
	$req = $bdd->query($sql);
	$informations = $req->fetch();
	$number_services = intval($informations['COUNT(*)']);
	$service = $informations['service'];

	echo $bp . "::" . $host . "::" . $number_services . "::" . $service;
}

function delete_bp($bp,$bdd){
	$sql = "delete from bp where name = ?";
	$req = $bdd->prepare($sql);
	$req->execute(array($bp));

	$sql = "delete from bp_services where bp_name = ?";
	$req = $bdd->prepare($sql);
	$req->execute(array($bp));

	$sql = "delete from bp_links where bp_name = ?";
	$req = $bdd->prepare($sql);
	$req->execute(array($bp));
	
	$sql = "delete from bp_links where bp_link = ?";
	$req = $bdd->prepare($sql);
	$req->execute(array($bp));
}

function list_services($host_name){
	$path_nagios_ser = "/srv/eyesofnetwork/nagios/etc/objects/services.cfg";

	$tabServices = array() ;
	$tabServices['service'] = array() ;
    $lignes = file($path_nagios_ser);
	$hasMatch = 0;
	
    foreach( $lignes as $ligne) {

        if ( preg_match("/$host_name$/", trim($ligne), $match)) {  //Get Host name
            $hasMatch = 1;
        }
        elseif ( preg_match("#^service_description#", trim($ligne))) {
			$service = preg_split("/[\s]+/", trim($ligne));
            if ($hasMatch)
                $tabServices['service'][] = $service[1];
            $hasMatch = 0;
        }
    }
	natcasesort($tabServices['service']);
	array_unshift($tabServices['service'],"Hoststatus");
	echo json_encode($tabServices);
}

function list_process($bp,$display,$bdd){
	$sql = "select name from bp where is_define = 1 and name!=? and priority = ?";
	$req = $bdd->prepare($sql);
	$req->execute(array($bp,$display));
	$process = $req->fetchall();

    echo json_encode($process);
}

function add_services($bp,$services,$bdd){
	$list_services = array();
	$old_list_services = array();
	
	if(is_array($services)) {
		foreach($services as $values){
			$value = explode("::", $values);
			$service = $value[1];
			$list_services[] = $service;
		}
	}

	$sql = "delete from bp_services where bp_name = ?";
	$req = $bdd->prepare($sql);
	$req->execute(array($bp));

	if(count($services) > 0){
		$sql = "update bp set is_define = 1 where name = ?";
		$req = $bdd->prepare($sql);
		$req->execute(array($bp));
	}
	else{
		$sql = "update bp set is_define = 0 where name = ?";
		$req = $bdd->prepare($sql);
		$req->execute(array($bp));
    }

	if(is_array($services)) {
		foreach($services as $values){
			$value = explode("::", $values);
			$host = $value[0];
			$service = $value[1];
			echo $service;
			$sql = "insert into bp_services (bp_name,host,service) values(?,?,?)";
			$req = $bdd->prepare($sql);
			$req->execute(array(trim($bp),$host,$service));
		}
	}
}

function add_process($bp,$process,$bdd){
	$sql = "delete from bp_links where bp_name = ?";
	$req = $bdd->prepare($sql);
	$req->execute(array($bp));
	$sql = "update bp set is_define = 0 where name = ?";
	$req = $bdd->prepare($sql);
	$req->execute(array($bp));

    if(count($process) > 0 and is_array($process)){
		$sql = "update bp set is_define = 1 where name = ?";
		$req = $bdd->prepare($sql);
		$req->execute(array($bp));
	
		foreach($process as $values){
			$value = explode("::", $values);
			$bp_link = $value[1];

			$sql = "insert into bp_links (bp_name,bp_link) values(?,?)";

			$req = $bdd->prepare($sql);
			$req->execute(array($bp,$bp_link));
		}	
	}
}

function check_app_exists($uniq_name, $bdd)
{
	$sql = "select count(*) from bp where name = ?;";
	$req = $bdd->prepare($sql);
	$req->execute(array($uniq_name));
	$bp_exist = $req->fetch(PDO::FETCH_NUM);
	
	if($bp_exist[0] == 1){
		echo "true";
	} else {
		echo "false";
	}
}

function add_application($uniq_name_orig,$uniq_name,$process_name,$display,$url,$command,$type,$min_value,$bdd){
	if($type != 'MIN'){
		$min_value = "";
	}
	$sql = "select count(*) from bp where name = ?;";
	$req = $bdd->prepare($sql);
	$req->execute(array($uniq_name));
	$bp_exist = $req->fetch();

	// add
	if($bp_exist[0] == 0 and empty($uniq_name_orig)){
		$sql = "insert into bp (name,description,priority,type,command,url,min_value) values(?,?,?,?,?,?,?)";
		$req = $bdd->prepare($sql);
		$req->execute(array($uniq_name,$process_name,$display,$type,$command,$url,$min_value));
	}
	// uniq name modification
	elseif($uniq_name_orig != $uniq_name) {
		if($bp_exist[0] != 0){
			// TODO QUENTIN
		} else {
			$sql = "update bp set name = ?,description = ?,priority = ?,type = ?,command = ?,url = ?,min_value = ? where name = ?";
			$req = $bdd->prepare($sql);
			$req->execute(array($uniq_name,$process_name,$display,$type,$command,$url,$min_value,$uniq_name_orig));
			$sql = "update bp_links set bp_name = ? where bp_name = ?";
			$req = $bdd->prepare($sql);
			$req->execute(array($uniq_name,$uniq_name_orig));	
			$sql = "update bp_links set bp_link = ? where bp_link = ?";
			$req = $bdd->prepare($sql);
			$req->execute(array($uniq_name,$uniq_name_orig));
			$sql = "update bp_services set bp_name = ? where bp_name = ?";					
			$req = $bdd->prepare($sql);
			$req->execute(array($uniq_name,$uniq_name_orig));	
		}
	}	
	// modification
	else{
		$sql = "update bp set name = ?,description = ?,priority = ?,type = ?,command = ?,url = ?,min_value = ? where name = ?";
		$req = $bdd->prepare($sql);
		$req->execute(array($uniq_name,$process_name,$display,$type,$command,$url,$min_value,$uniq_name));
	}
}

function build_file($bdd){
	
	$bp_sons=array();
	
	$sql = "SELECT * FROM bp where is_define ='1'";
	$req = $bdd->query($sql);
	$bps_informations = $req->fetchall();
	$file = "../../../../nagiosbp/etc/nagios-bp.conf";
	$backup_file = "../../../../nagiosbp/etc/nagios-bp.conf_old";
	copy($file,$backup_file);
	$bp_file = fopen($file, "w");
	fputs($bp_file, "#\n");
	fputs($bp_file, "# EyesOfNetwork\n");
	fputs($bp_file, "#\n");
	
	foreach($bps_informations as $bp_informations){
		if(!in_array($bp_informations['name'],$bp_sons,true)) {
			$bp_sons=build_file_recursive($bdd,$bp_file,$bp_informations,$bp_sons);
		}
	}
	fclose($bp_file);
}

function build_file_recursive($bdd,$bp_file,$bp_informations,$bp_sons){

	$sql = "SELECT bp_link FROM bp_links where bp_name=?";
	$req = $bdd->prepare($sql);
	$req->execute(array($bp_informations['name']));
	if($req->rowCount() == 0) {
		$bp_sons[]=$bp_informations['name'];
		build_file_bp($bdd,$bp_file, $bp_informations);
	} else {
		$bp_links = $req->fetchall();
		foreach($bp_links as $bp_link){
			$sql = "SELECT * FROM bp where is_define ='1' and name=?";
			$req = $bdd->prepare($sql);
			$req->execute(array($bp_link["bp_link"]));
			$bps_sons_informations = $req->fetchall();
			foreach($bps_sons_informations as $bp_sons_informations){
				if(!in_array($bp_sons_informations['name'],$bp_sons,true)) {
					$bp_sons=build_file_recursive($bdd,$bp_file,$bp_sons_informations,$bp_sons);
				}
			}
		}
		$bp_sons[]=$bp_informations['name'];
		build_file_bp($bdd,$bp_file, $bp_informations);
	}
	return $bp_sons;
}

function build_file_bp($bdd,$bp_file, $bp_informations){
	fputs($bp_file, $bp_informations['name'] . " = ");
	if($bp_informations['type'] == 'ET'){
		$type = "&";
	}
	elseif($bp_informations['type'] == 'OU'){
		$type = "|";
	}
	else{
		$type = "+";
		fputs($bp_file, $bp_informations['min_value'] . " of: ");
	}
	$sql = "select host,service from bp_services where bp_name = ?";
	$req = $bdd->prepare($sql);
	$req->execute(array($bp_informations['name']));
	$host_services = $req->fetchall();

	$counter1 = count($host_services);
	$counter2 = 0;

	foreach($host_services as $services){
		fputs($bp_file,$services['host'] . ";" . $services['service']);
		$counter2 += 1;

		if($counter2 < $counter1){
			fputs($bp_file, " " . $type . " ");
		}
	}

	$sql = "select bp_link from bp_links where bp_name = ?";
	$req = $bdd->prepare($sql);
	$req->execute(array($bp_informations['name']));
	$link_informations = $req->fetchall();

	$counter1 = count($link_informations);
	$counter2 = 0;

	foreach($link_informations as $link_infos){
		fputs($bp_file,$link_infos['bp_link']);
		$counter2 += 1;

		if($counter2 < $counter1){
			fputs($bp_file, " " . $type . " ");
		}
	}

	fputs($bp_file, "\n");

	fputs($bp_file, "display " . $bp_informations['priority'] . ";" . $bp_informations['name'] . ";" . $bp_informations['description'] . "\n");

	if(! empty($bp_informations['url'])){
		fputs($bp_file, "info_url " . $bp_informations['name'] . ";" . $bp_informations['url'] . "\n");
	}

	if(! empty($bp_informations['command'])){
		fputs($bp_file, "external_info " . $bp_informations['name'] . ";" . $bp_informations['command'] . "\n");
	}
}

function info_application($bp_name, $bdd){
	$sql = "select * from bp where name = ?";
	$req = $bdd->prepare($sql);
	$req->execute(array($bp_name));
	$info = $req->fetch();
	echo json_encode($info);
}

?>
