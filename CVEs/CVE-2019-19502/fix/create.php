<?php

// Including the check_permission file, don't delete the following row!
require(__DIR__ . '/check_permission.php');

if(isset($_POST["username"]) or isset($_POST["password"])){
    // only allow alphanumeric, underscore, dot, and dash for username
    $tmpusername =  preg_replace("/[^a-zA-Z0-9_.\-]+/", "", $_POST["username"]);
    $tmppassword = md5($_POST["password"]);
    $data = '$username = \''.$tmpusername.'\'; $password = \''.$tmppassword.'\';'.PHP_EOL;
    $fp = fopen(__DIR__ . '/pluginconfig.php', 'a');
    fwrite($fp, $data);
    unlink(__DIR__ . "/new.php");
    unlink(__DIR__ . "/create.php");
    header("Location: imgbrowser.php");
}

