<?php
include "/data/klattr.com/www/includes/auth";
if ($auth == 1) {
  $profileText = $_POST["profileText"];
  $profileURL = $_POST["webAddress"];
  $senduname = $_POST["senduname"];
  if(strpos($profileURL, 'http') !== 0) {
    $profileURL = "http://" . $profileURL;
  }
  if(!filter_var($profileURL, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED)) {
    $profileURL = "";
  }
/*Sanitize the vars that are going into the database*/
  $profileText = htmlspecialchars($profileText);
  $profileText = addslashes($profileText);
  $profileURL = htmlspecialchars($profileURL);
  $profileURL = addslashes($profileURL);


  $sql = "UPDATE Users SET Website_Addr='$profileURL', Profile_Text='$profileText' WHERE ID='$UID'";
  mysqli_query($con,$sql);
  header( "Location: https://klattr.com/$senduname" );
  } else {
  header( 'Location: https://klattr.com' );
}
?>
