<?php

$accountLimit = 250;

if (isset($_COOKIE['session_auth'])) {
  header( 'Location: https://klattr.com' );
} else {


  include "/data/klattr.com/www/includes/open_db";
  $sql = "SELECT COUNT(*) AS Num FROM Users";
  $sql_result = mysqli_query($con,$sql);
  $row = mysqli_fetch_array($sql_result);
  if ($row['Num'] > $accountLimit) {
?>

<!DOCTYPE html>
<?php include "/data/klattr.com/www/includes/head"; ?>
  <body>
    <?php include "/data/klattr.com/www/includes/header"; ?>

<div id="body">
  <div class="body_center">
    <div class="welcome" style="padding:10px; top:-70px;height:120px">
      <h1> Sorry. This beta is limited to <?php echo $accountLimit; ?> accounts. Please try later.</h1>
    </div>
  </div>
</div>


    <?php include "/data/klattr.com/www/includes/footer"; ?>
  </body>
</html>
<?php
  } else {



 
    if (isset($_POST["name"]) && isset($_POST["email"]) && isset($_POST["password"]) && isset($_POST["username"])) {

      $name = $_POST["name"];
      $email = $_POST["email"];
      $password = $_POST["password"];
      $username = $_POST["username"];
      $sticky = $_POST["stay_signed_in"];
      $expire_time = 0;
      if ($sticky == "yes") {
        $expire_time = time()+2592000;
      }

      include "/data/klattr.com/www/includes/evaluate_signup";

      $e_n = evaluate_name($name);
      $e_e = evaluate_email($email);
      $e_p = evaluate_pass($password);
      $e_u = evaluate_uname($username);

      if ($e_n != 0 || $e_e != 0 || $e_p != 0 || $e_u != 0) {
        $exists = $e_e + $e_u;
        include "/data/klattr.com/www/includes/signup_page";
      } else {
        $enc_password = urlencode($password);
        $password_hash = crypt($enc_password);
#      $randkey = substr(str_shuffle(md5(time())),0,128);
#      $randkey = substr(sha1(rand()), 0, 128);
        $randomData = file_get_contents('/dev/urandom', false, null, 0, 128) . uniqid(mt_rand(), true);
        $randkey = substr(str_replace(array('/','=','+'),'', base64_encode($randomData)),0,128);

        $ip = $_SERVER['REMOTE_ADDR'];
        $sql = "INSERT INTO Users (Name, Email_Addr, Password_hash, Handle) VALUES ('$name','$email','$password_hash','$username')";
        mysqli_query($con,$sql);
        $sql = "SELECT ID FROM Users WHERE Handle='$username'";
        $sql_result = mysqli_query($con,$sql);
        $row = mysqli_fetch_array($sql_result);
        $UID = $row['ID'];
        $session_date = date("Y-m-d h:i:s");
        $sql = "INSERT INTO Session (SessionKey, UserID, IP, Date) VALUES ('$randkey','$UID','$ip','$session_date')";
        mysqli_query($con,$sql);
        setcookie ("session_auth", $randkey, $expire_time, "/", ".klattr.com");
        $uname = $username;
        $rname = $name;
        $uEmailAddr = $email;
        $first_mail = 1;
        include "/data/klattr.com/www/ht_docs/send_email.php";
        header( 'Location: https://klattr.com' );
//      include "/data/klattr.com/www/includes/signed_up";
      }
    } else {

  $name = $_POST["name"];
  $email = $_POST["email"];
  $password = $_POST["password"];
  $username = $_POST["username"];

  include "/data/klattr.com/www/includes/signup_page";
    }
  }
}
?>
