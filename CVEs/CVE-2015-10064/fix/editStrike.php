<?php
session_start();
require 'mysqlcon.php';

if(isset($_POST['submit'])){    
    tryEditStrike($_POST['strikeid'], $_POST['newname'], $_POST['newdmg'], $_POST['newtype']);
}
else {
    header("location:./");
}
function tryEditStrike($id, $name, $damage, $type){ 
    $ename = mysql_real_escape_string($name);
    $edamage = mysql_real_escape_string($damage);
    $result = mysql_query("SELECT * FROM `pokemon`.`golpes` WHERE id = '$id' LIMIT 1");    

    if(mysql_num_rows($result) == 0){ //Se não existir golpe com este ID
        $_SESSION['error2'] = 3;
        header("location:acp.php");
    } else {
        $result = mysql_query("UPDATE `pokemon`.`golpes` SET `name` = '$ename', `damage` = '$edamage', `type` = '$type' WHERE `id` = '$id'");            
        $_SESSION['error2'] = 4; //Apesar do nome da variável da sessão, é uma instrução apenas
        header("location:acp.php");
    }
}
?>
