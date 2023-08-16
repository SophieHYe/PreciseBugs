<?php
ini_set('display_errors', 'Off');
$config['db']['host'] = "127.0.0.1";
$config['db']['username'] = "masterserver";
$config['db']['password'] = trim(file_get_contents('/var/www/masterserver1.talesofnewerth.com/dbp'));
$config['db']['database'] = "masterserver";
$config['db']['patchesdb'] = "patches";
$config['hash'] = "abcdefgh";
$config['public_url'] = "s2.michaelk.ch.localhost";
$patchesdbcon = NULL;
$dbcon = NULL;
?>
