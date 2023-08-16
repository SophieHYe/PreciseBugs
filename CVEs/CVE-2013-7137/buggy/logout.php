<?php

//Burden, Copyright Josh Fradley (http://github.com/joshf/Burden)

if (!file_exists("config.php")) {
    die("Error: Config file not found! Please reinstall Burden.");
}

require_once("config.php");

session_start();

session_unset("burden_user");

if (isset($_COOKIE["burden_user_rememberme"])) {
	setcookie("burden_user_rememberme", "", time()-86400);
}

header("Location: login.php?logged_out=true");

exit;

?>