<html>
<head>

<title>Hacker City | <?php 
if (empty($_GET['m'])){
	if (empty($_GET['a'])) {
		echo "Index";
	}
	else{
		$url = $_GET['a'];
		switch($url){
			case "accountdetails": echo "Account Details";break;
			case "jail": echo "Jail";break;
			case "smalljobs": echo "Small Jobs";break;
			case "statistics": echo "Player Stats";break;
			case "bank": echo "Personal Bank";break;
			case "softwareshop": echo "Software Shop";break;
			case "hardwareshop": echo "Hardware Shop";break;
			case "isp": echo "Internet Provider";break;
			case "h4h": echo "Hacker 4 Hire";break;
			case "cryptocurrency": echo "Cryptocurrency";break;
			case "leaderboard": echo "Leaderboard";break;
			case "cryptowallet": echo "Cryptowallet";break;
			case "internet": echo "Internet";break;
			case "publicftp": echo "Public FTP Servers";break;
			case "support": echo "Support Tickets";break;
			case "chat": echo "Chat";break;
			case "rules": echo "Rules";break;
			case "system": echo "System";break;
			case "syslogs": echo "System Logs";break;
			case "email": echo "Email Client";break;
			case "storage": echo "Storage";break;
			case "cmd": echo "Command Prompt";break;
			case "prison": echo "Prison";break;
			case "fbimostwanted": echo "FBI Most Wanted List";break;
			case "techspecs": echo "Server Resources";break;
			case "clanbank": echo "Clan Bank";break;
			case "clanroster": echo "Clan Roster";break;
			case "profile": echo "Player Profile";break;
		};
	};
}
else {
	$urlm = $_GET['m'];
	switch($urlm){
		case "logs": echo "Game Logs";break;
		case "banklogs"; echo "Bank Transactions";break;
		case "tickets"; echo "Ticket Management";break;
		case "usermanagement"; echo "User Accounts";break;
	};
}


?></title>
<link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Ek+Mukta">
<link rel="stylesheet" type="text/css" href="/css/materialize.css">
<link rel="stylesheet" type="text/css" href="/css/style.css">

<link rel="shortcut icon" href="/images/favicon.ico" type="image/x-icon">
<link rel="icon" href="/images/favicon.ico" type="image/x-icon">
<script src="/js/materialize.js"></script>
<!-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>-->
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.3/jquery.min.js"></script>
<script src="/js/gameclock.js"></script>
<script src="/js/countdown.js"></script>

</head>

<?php

$mem = new Memcache();
$mem->addServer("127.0.0.1", 11211);
require 'sql/connect.php';
require 'conf/global.php';

session_start();
$start_time = microtime(TRUE);
$ip = $_SERVER['REMOTE_ADDR'];
$time = $_SERVER['REQUEST_TIME'];
$timeout_duration = 1800;


if (isset($_SESSION['LAST_ACTIVITY']) && ($time - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
	
	$username = $_SESSION['login_user'];
	$userid = $_SESSION['userid'];
	$sql = "INSERT INTO gamelogs(userid,username,type) VALUES('$userid', '$username', 'Logged out due to inactivity')";
	$result = $conn->query($sql);
	session_unset();
	session_destroy();
	session_start();
};


// REBUILD MODE

if($adminrestrict == true && $adminip != $ip ){
	include 'layout/guest/header.htm';
	
	include "game/guest/rebuild.htm";
	
	include "layout/guest/footer.htm";
	
}
else {

	if(!isset($_SESSION['login_user'])){

		include 'layout/guest/header.htm';
	
		$a = 'guest';
		$disallowed_paths = array('header', 'footer');
		if (!empty($_GET['a'])) {
			$tmp_action = basename($_GET['a']);
			// Checks if action is allowed and file exists, then sets the action
			if (!in_array($tmp_action, $disallowed_paths) && file_exists("game/guest/{$tmp_action}.htm")){
				$a = $tmp_action;
			};
		};
	
	include "game/guest/{$a}.htm";
	
	include "layout/guest/footer.htm";
	}
	else {
		$username = $_SESSION['login_user'];
		$userid = $_SESSION['userid'];
		$_SESSION['LAST_ACTIVITY'] = $time;
		$jailtime = '';
		date_default_timezone_set('America/New_York');
		$date = date('Y-m-d H-i-s');
		$sql = "UPDATE accounts SET lastseen='$date' WHERE username='$username'";
		$result = $conn->query($sql);
		
		$sql = "SELECT * FROM accounts WHERE id ='$userid'";
		$result = $conn->query($sql);
		while ($row = $result->fetch_assoc()){
			$currenttime = $row['lastseen'];
		};
		
		$sql93 = "SELECT * FROM jail WHERE userid ='$userid' AND active = '1'";
		$result12 = $conn->query($sql93);
		
		$num_rows = mysqli_num_rows($result12);
		
		if($num_rows == 1) {
		
			while ($row1 = $result12->fetch_assoc()){
				$jailtime = $row1['jailrelease'];
			};
		};		
		$jailoverridepaths = array('accountdetails','jailed','chat','leaderboard','support','ticketcreation','logout','rules','statistics','subcom','subti','ticketclose','userselfdelete','viewmessage','view','profile');

		include '/usr/share/nginx/html/layout/main/header.htm';
		echo 'System Time: <div id="clockbox"></div>';
		echo 'Small Hacks: <div id="countdown"></div>';
		
		$sql = "SELECT * FROM accounts WHERE id = '$userid'";
		$result = $conn->query($sql);
			while($row = $result->fetch_assoc()){
					$cooldown = $row['smallhackcd'];
			}; 
		
		$disallowed_paths = array('header', 'footer');
		if (!empty($_GET['m'])){
			$management = basename($_GET['m']);
			if (!in_array($management, $disallowed_paths) && file_exists("/usr/share/nginx/html/game/main/admin/{$management}.htm")){
				$m = $management;
				if ($jailtime != ''){
					if ($jailtime > $currenttime && !in_array($management, $jailoverridepaths)){
						header("location: ?a=jailed");
					};
				};
				include ("/usr/share/nginx/html/game/main/admin/{$m}.htm");
			}
		};
		if (empty($_GET['m'])){
			$a = 'home';
			$disallowed_paths = array('header', 'footer');
			if (!empty($_GET['a'])) {
				$tmp_action = basename($_GET['a']);
				// Checks if action is allowed and file exists, then sets the action
				if (!in_array($tmp_action, $disallowed_paths) && file_exists("/usr/share/nginx/html/game/main/user/{$tmp_action}.htm")){
					$a = $tmp_action;
				};
			};
			if(isset($a)){
				include "/usr/share/nginx/html/game/main/user/{$a}.htm";
			};
		}
			include "/usr/share/nginx/html/layout/main/footer.htm";
		}
		}
?>