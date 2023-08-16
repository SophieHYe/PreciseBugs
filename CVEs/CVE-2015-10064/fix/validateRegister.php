<?php
session_start();
require 'mysqlcon.php';

if(isset($_POST['submit'])){
    if(strcmp($_POST['password'], $_POST['repeatpassword'])){
        $_SESSION['error'] = 1;
        header("location:register.php");
    } else {   
        $user = $_POST['user'];
        $pass = hash('whirlpool', $_POST['password']);
        $email = $_POST['email'];
        tryRegister($user, $email, $pass);
    }
}
else {
    header("location:./");
}
function tryRegister($user, $email, $pass){   
    $euser = mysql_real_escape_string($user);
    $eemail = mysql_real_escape_string($email);
    $result = mysql_query("SELECT * FROM `pokemon`.`usuarios` WHERE user = '$euser' LIMIT 1");    

    if(mysql_num_rows($result) >= 1){ //Se ja existir o usuário
        $_SESSION['error'] = 2;
        header("location:register.php");
    } else {
        $result = mysql_query("SELECT * FROM `pokemon`.`usuarios` WHERE email = '$eemail' LIMIT 1");
        if(mysql_num_rows($result) >= 1){ //Se ja existir o email
            $_SESSION['error'] = 3;
            header("location:register.php");
        } else { //Se a conta estiver 100% apta ao registro
            //Inserimos a conta no banco de dados
            $result = mysql_query("INSERT INTO `pokemon`.`usuarios` (`user`, `email`, `password`) VALUES ('$euser', '$eemail', '$pass')");            
            $_SESSION['error'] = 2; //Apesar do nome da variável da sessão, é uma instrução apenas
            header("location:login.php");
        }
    }
}
?>
