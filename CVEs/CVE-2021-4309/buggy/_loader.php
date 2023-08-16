<?PHP
/* 
	01ACP - Copyright 2008 by Michael Lorer - 01-Scripts.de
	Lizenz: Creative-Commons: Namensnennung-Keine kommerzielle Nutzung-Weitergabe unter gleichen Bedingungen 3.0 Deutschland
	Weitere Lizenzinformationen unter: http://www.01-scripts.de/lizenz.php
	
	Modul:		01ACP
	Dateiinfo:	Modulseiten laden
*/

// Config-Dateien
include("system/main.php");

if(!isset($_REQUEST['loadpage'])) $_REQUEST['loadpage'] = "";

$menuecat = $modul;
$sitetitle = $module[$modul]['instname'];
$filename = $_SERVER['PHP_SELF']."?modul=".$modul."&amp;loadpage=".$_REQUEST['loadpage'];

include("system/head.php");

if(isset($_GET['modul']) && $_GET['modul'] == "01acp")
	echo "<script>redirect(\"acp.php\");</script>";

// Sicherheitsabfrage: Login
if(isset($userdata['id']) && $userdata['id'] > 0 && $userdata[$modul] == 1){

//echo $modulpath.$loadfile[$_REQUEST['loadpage']];
if(isset($loadfile[$_REQUEST['loadpage']]) && file_exists($modulpath.$loadfile[$_REQUEST['loadpage']]) && !is_dir($modulpath.$loadfile[$_REQUEST['loadpage']]))
	include_once($modulpath.$loadfile[$_REQUEST['loadpage']]);
elseif(file_exists($modulpath.$loadfile['index']) && !is_dir($modulpath.$loadfile['index']))
	include_once($modulpath.$loadfile['index']);
else
	echo "<p class=\"meldung_error\">Fehler: Es konnte keine zum Modul <i>".$module[$modul]['instname']."</i>
			passende Datei geladen werden.</p>";




}else $flag_loginerror = true;
include("system/foot.php");

// 01ACP Copyright 2008 by Michael Lorer - 01-Scripts.de
?>