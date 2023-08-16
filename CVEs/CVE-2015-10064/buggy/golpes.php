<?php
    require 'mysqlcon.php';     
    $a = $_GET["golpe"];
    $sth = mysql_query("SELECT `name` FROM `pokemon`.`golpes` where name like '" . $a."%'");
    $rows = array();
    while($r = mysql_fetch_assoc($sth)) {
        $rows[] = $r;
    }
    print json_encode($rows);
?>

