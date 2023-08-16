<?php
  require_once "../configuration.php";
  require_once "../includes/class.inc.php";
  newClass();
  //Preset variables
  /**
  * Is register succeeded?
  * @var bool
  */
  $register = False;
  /**
  * Is register username or mail already in use?
  * @var bool
  */
  $in_use = False;
  //Checks if variables are set
  if(isset($_POST["U"])&&isset($_POST["M"])&&isset($_POST["P"])&&isset($_POST["PR"])){
    //Checks if both passwords are the same
    if($_POST["P"]==$_POST["PR"]){
      //Checks if the mailadress is valid
      if(preg_match('/^[^\x00-\x20()<>@,;:\\".[\]\x7f-\xff]+(?:\.[^\x00-\x20()<>@,;:\\".[\]\x7f-\xff]+)*\@[^\x00-\x20()<>@,;:\\".[\]\x7f-\xff]+(?:\.[^\x00-\x20()<>@,;:\\".[\]\x7f-\xff]+)+$/i', $_POST["M"])){
        //Checks if the username is valid
        if(preg_match('/^[a-z0-9A-Z.]{3,15}$/',$_POST["U"])){
          //Checks if the password is valid
          if(preg_match('/^[a-z0-9A-Z.:,;]{8,25}$/',$_POST["P"])){
            $register = True;
            $sql = "SELECT * FROM User";
            $db_erg = mysqli_query($U->db_link, $sql);
            while ($row = mysqli_fetch_array($db_erg, MYSQLI_ASSOC))
            {
              //Checks if username or mail are in use
              if(strtolower($row["Username"]) == strtolower($_POST["U"])||strtolower($row["Mail"])==strtolower($_POST["M"])){
                $register = False;
                $in_use = True;
              }
            }
          }else{
            echo str_replace("%a",$U->getLang("login.password"),$U->getLang("login.invalid"));
          }
        }else{
          echo str_replace("%a",$U->getLang("login.username"),$U->getLang("login.invalid"));
        }
      }else{
        echo str_replace("%a",$U->getLang("login.mail"),$U->getLang("login.invalid"));
      }
    }else{
      echo $U->getLang("login.fail_same_password");
    }
  }else{
    echo $U->getLang("login.fillout");
  }
  //Checks if register is cloded
  if($U->getSetting("login.register_open")=="0"  || $U->getSetting("login.login_open") == "0"){
    echo $U->getLang("register.closed");
    $register = False;
  }
  if($register){
    //Register succeeded:
    //Register user
    $sql = 'INSERT INTO User (Username, Mail, Password, Type) VALUES ('."'".$_POST["U"]."'".','."'".$_POST["M"]."'".','."'".password_hash($_POST["P"],PASSWORD_DEFAULT)."'".',0);';
    if($db_erg = mysqli_query($U->db_link, $sql)){
      //Database register is succeeded
      echo $U->getLang("register.succeed");
      header("Location: ".$USOC["DOMAIN"]);
    }else{
      //Database register is failed
      echo mysqli_error($U->db_link);
    }
  }
  if($in_use){
    //Username or mail already in use:
    echo $U->getLang("register.in_use");
  }
?>
