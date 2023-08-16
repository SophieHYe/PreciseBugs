<?php
if (!defined('WC_BASE')) define('WC_BASE', dirname(__FILE__));

include WC_BASE . "/config/conf.php";
include WC_BASE . "/lib/crypto.php";
include WC_BASE . "/lib/log.php";

session_name('web-cyradm-session');
session_start();

$session_ok = $_SESSION['session_ok'];

$login = $_POST['login'];
$password = $_POST['login_password'];
$LANG = $_POST['LANG'];

if ($login && $password){
     // Log access
     logger(sprintf("LOGIN : %s %s %s %s %s%s", $_SERVER['REMOTE_ADDR'], $login, $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_REFERER'], $_SERVER['REQUEST_METHOD'], "\n"));

     $pwd=new password;
     $result=$pwd->check("adminuser",$login,$password,$CRYPT);

     if ($result){
    	     
          // Log successfull login
	  logger(sprintf("PASS : %s %s %s %s %s%s", $_SERVER['REMOTE_ADDR'], $login, $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_REFERER'], $_SERVER['REQUEST_METHOD'], "\n"));

          $_SESSION['session_ok'] = TRUE;
	  $_SESSION['timestamp'] = time();
	  $_SESSION['user'] = $login;
	  $_SESSION['LANG'] = $LANG;
	  $_SESSION['init'] = 'init';

          header ("Location: index.php");

     } else {
          // Log login failure
	  logger(sprintf("FAIL : %s %s %s %s %s%s", $_SERVER['REMOTE_ADDR'], $login, $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_REFERER'], $_SERVER['REQUEST_METHOD'], "\n"),"WARN");
	  
	   $_SESSION = array();
	   
	   #include ("failed.php");
	   header ("Location: failed.php?LANG=".$LANG);
	
     }
} else {
     print "<center><h4><font face=Verdana,Geneva,Arial,Helvetica,sans-serif>"
	   ._("Web-cyradm is for authorized users only."). 
           "<br>"._("Make sure you entered the right password.").
           "<br>"._("Push the back button in your browser to try again.").
           "<br>"._(" Your attempt to login has been stored.")."</font></h4></center>";
}

?>

<!-- ###################################### End auth.inc.php ################################################ --!>
