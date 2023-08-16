<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');
// Load config (for MySQL db)
include('config.php');
// Connect to MySQL database
$err_level = error_reporting(0);
$my = new mysqli($my_host, $my_user, $my_pass);
error_reporting($err_level);
if($my->connect_error)
	die("Datenbankverbindung (MySQL) nicht möglich.");
$my->set_charset('utf8');
$my->select_db($my_name);
// Start session
session_start();
// Process input
if(isset($_GET["login"]) && isset($_GET["user"]) && !empty($_GET["user"]) && (isset($_POST["password"]) || isset($_GET["password"])) && (!empty($_POST["password"]) || !empty($_GET["password"])) ) {
	if(isset($_POST["password"])) {
		$pw = $my->real_escape_string($_POST["password"]);
	} else {
		$pw = $my->real_escape_string($_GET["password"]);
	}
	$success = false;
	$query = "SELECT `password` FROM `admin_users` WHERE `name` = '".$_GET["user"]."';";
	$result = $my->query($query);
	if($result->num_rows >= 1){
		// possible multiple users with same name but different password -> multiple rows in MySQL db
		while($row = $result->fetch_assoc()){
			if(password_verify($pw, $row["password"])) {
				$success = true;
			}
		}
	}
	if($success) {
		$_SESSION["auth_user"] = "ok";
		$out = json_encode(array("success" => "User successfully authenticated"));
	} else {
		$out = json_encode(array("error" => "User or password incorrect"));
	}
} else if( // authenticated admin users can add new admin users
		isset($_SESSION["auth_user"]) && ($_SESSION["auth_user"]=="ok") 
		&& isset($_GET["new"]) && isset($_GET["user"]) && !empty($_GET["user"]) 
		&& isset($_GET["password"]) && !empty($_GET["password"])) 
{
	$pw = password_hash($my->real_escape_string($_GET["password"]), PASSWORD_DEFAULT);
	$query = "INSERT INTO `ibis_server-php`.`admin_users` (`id`, `name`, `password`, `created`) 
	VALUES (NULL, '".$my->real_escape_string($_GET["user"])."', '".$pw."', CURRENT_TIMESTAMP);";
	$result = $my->query($query);
	if($result) {
		$out = json_encode(array("success" => "User successfully created"));
	} else {
		$out = json_encode(array("error" => "User not created - Database problem :("));
	}
} else if(isset($_GET["status"])) { // is user logged in?
	if(isset($_SESSION["auth_user"]) && $_SESSION["auth_user"]=="ok") {
		$out = json_encode(array("status" => "ok"));
	} else {
		$out = json_encode(array("status" => "bad"));
	}
} else if(isset($_GET["signout"])) {
	if(isset($_SESSION["auth_user"]) && $_SESSION["auth_user"]=="ok" && session_destroy()) {
		$out = json_encode(array("success" => "User signed out"));
	} else {
		$out = json_encode(array("error" => "Sign out failed"));
	}
} else {
	$out = json_encode(array("error" => "Keine oder falsche Eingabe."));
}
echo($out);
$my->close();
?>
