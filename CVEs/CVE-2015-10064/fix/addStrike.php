<?php
session_start();
require 'mysqlcon.php';

if(isset($_POST['submit'])){    
    tryAddStrike($_POST['name'], $_POST['dmg'], $_POST['type']);
}
else {
    header("location:./");
}
function tryAddStrike($name, $damage, $type){   
    $ename = mysql_real_escape_string($name);
    $edamage = mysql_real_escape_string($damage);
    $result = mysql_query("SELECT * FROM `pokemon`.`golpes` WHERE name = '$ename' LIMIT 1");    

    if(mysql_num_rows($result) >= 1){ //Se ja existir o golpe
        $_SESSION['error'] = 1;
        header("location:acp.php");
    } else {
        $result = mysql_query("INSERT INTO `pokemon`.`golpes` (`name`, `damage`, `type`) VALUES ('$ename', '$edamage', '$type')");            
        $_SESSION['error'] = 2; //Apesar do nome da variável da sessão, é uma instrução apenas
        header("location:acp.php");
    }
}
?>
