<?php
    require 'mysqlcon.php';       
    $a = $_GET["golpe"];
    $ea = mysql_real_escape_string($a);
    $sth = mysql_query("SELECT `name` FROM `pokemon`.`golpes` where name like '" . $ea."%'");
    $rows = array();
    while($r = mysql_fetch_assoc($sth)) {
        $rows[] = $r;
    }
    print json_encode($rows);
?>

