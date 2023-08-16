<?php
if (isset($_COOKIE['session_auth'])) {
  include "/data/klattr.com/www/includes/open_db";
  include "/data/klattr.com/www/includes/evaluate_cookie";
  $ip = $_SERVER['HTTP_X_REAL_IP'];
  if ($ip == "") {
    $ip = $_SERVER['REMOTE_ADDR'];
  }
  $cookie_key = $_COOKIE['session_auth'];
  $UID = evaluate_cookie($cookie_key, $ip);
  if ($UID !== 0) {
    $profileText = $_POST["profileText"];
    $profileURL = $_POST["webAddress"];
    $senduname = $_POST["senduname"];
    $profileText = htmlspecialchars($profileText);
    $profileText = addslashes($profileText);
    if(strpos($profileURL, 'http') !== 0) {
      $profileURL = "http://" . $profileURL;
    }
    if(!filter_var($profileURL, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED)) {
      $profileURL = "";
    }
    $sql = "UPDATE Users SET Website_Addr='$profileURL', Profile_Text='$profileText' WHERE ID='$UID'";
    mysqli_query($con,$sql);
    header( "Location: https://klattr.com/$senduname" );
  }
} else {
  header( 'Location: https://klattr.com' );
}
?>
