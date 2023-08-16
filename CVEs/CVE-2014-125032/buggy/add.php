<?php
include_once "../../config.inc.php";
include_once($CONFIG["inc"]["connect"]);
include_once($CONFIG["inc"]["function"]);

$option = $_GET["option"];
$table_name = strtoupper($option);

session_start();
if ($_SESSION["login"]!="true" && $table_name!="REGISTER") {
  echo "Fuck You!!!!!";
  exit;
}

$m_id = $_SESSION["id"];

if ($table_name=="REGISTER") {
  $username = clean($_POST["user"]);
  $password = md5($_POST["pass"]);
  $repass = md5($_POST["repass"]);
  $name = clean($_POST["name"]);
  $email = clean($_POST["email"]);
  $all_score = 0;
  $image = $_FILES["image"];
  $profile = getFileType($image["name"]);

  $result["status"] = "failed";
  if (strlen($username)<=4 || strlen($_POST["pass"])<=4) {
    $result["data"] = "Username and Password should be longer than 4 character";
  }
  else if ($password!=$repass) {
    $result["data"] = "Re-Password or Password is Wrong";
  }
  else if (email($email)) {
    $result["data"] = "Email Wrong Format";
  }
  else if (!in_array($profile, $CONFIG["upload"]["type"])) {
    $result["data"] = "Dont Correct data type of image";
  } else {
    $sql = "insert into MEMBER values (member_seq.nextval, '$username', '$password', '$name', '$email', $all_score, '$profile')";
    $stid = oci_parse($db_conn, $sql);
    $r = oci_execute($stid);
    if ($r) {
      $stid = oci_parse($db_conn, "SELECT * FROM member where username='$username'");
      oci_execute($stid);
      $row = oci_fetch_assoc($stid);
      $id = $row['ID'];

      $target_dir = $CONFIG["path"]["root"]."/".$CONFIG["image"]["member"];
      $im = $target_dir.$id.".".$profile;
      if (!move_uploaded_file($image["tmp_name"], $im)) {
        $result["data"] = "Image Error";
      } else {
        $result["status"] = "success";
        $result["data"] = "";
      }
    } else {
      $result["data"] = "Can't Register";
    }
  }
}
else if ($table_name=="PHOTO") {
  $caotion = clean($_POST["caption"]);
  $owner = $m_id;
  $loc_id = $_POST["loc_id"];
  $timing_id = $_POST["timing_id"];
  $pos_id = $_POST["pos_id"];

  $sql = "insert into PHOTO values (member_seq.nextval, '$caption', $owner, $loc_id, $timing_id, $pos_id, systimestamp)";
  $stid = oci_parse($db_conn, $sql);
  $r = oci_execute($stid);

  if ($r) {
    $sql = "select b.* from BADGE b, BADGE_COLLECT c where c.MEMBER_ID=$m_id and c.BADGE_ID!=b.ID";
    $stid = oci_parse($db_conn, $sql);
    $r = oci_execute($stid);
    $nb = oci_fetch_all($stid, $badge, null, null, OCI_FETCHSTATEMENT_BY_ROW);

    $sql = "select p.LOC_ID, p.TIMING_ID, p.POS_ID, t.M_ID, w.THING_ID from PHOTO p, TAG t, PHOTO_WITH w where p.OWNER_ID=$m_id and t.P_ID=p.ID w.PHOTO_ID=p.ID";
    $stid = oci_parse($db_conn, $sql);
    $r = oci_execute($stid);
    oci_fetch_all($stid, $photo, null, null, OCI_FETCHSTATEMENT_BY_COLUMN);

    foreach($badge as $row) {
      $id = $row["ID"];
      $sql = "select THING_ID from BADGE_THING where BADGE_ID=$id";
      $stid = oci_parse($db_conn, $sql);
      $r = oci_execute($stid);
      $nb = oci_fetch_all($stid, $thing, null, null, OCI_FETCHSTATEMENT_BY_ROW);

      $sql = "select MEMBER_ID from BADGE_MEMBER where BADGE_ID=$id";
      $stid = oci_parse($db_conn, $sql);
      $r = oci_execute($stid);
      $nb = oci_fetch_all($stid, $member, null, null, OCI_FETCHSTATEMENT_BY_ROW);

      $sql = "select TIMING_ID from BADGE_TIMING where BADGE_ID=$id";
      $stid = oci_parse($db_conn, $sql);
      $r = oci_execute($stid);
      $nb = oci_fetch_all($stid, $timing, null, null, OCI_FETCHSTATEMENT_BY_ROW);

      $sql = "select POSTURE_ID from BADGE_POSTURE where BADGE_ID=$id";
      $stid = oci_parse($db_conn, $sql);
      $r = oci_execute($stid);
      $nb = oci_fetch_all($stid, $posture, null, null, OCI_FETCHSTATEMENT_BY_ROW);

      $sql = "select LOCATION_ID from BADGE_LOCATION where BADGE_ID=$id";
      $stid = oci_parse($db_conn, $sql);
      $r = oci_execute($stid);
      $nb = oci_fetch_all($stid, $location, null, null, OCI_FETCHSTATEMENT_BY_ROW);

      if (array_intersect($thing, $photo["THING_ID"]) == $thing and
          array_intersect($member, $photo["M_ID"]) == $member and
          array_intersect($timing, $photo["TIMING_ID"]) == $timing and
          array_intersect($posture, $photo["POS_ID"]) == $posture and
          array_intersect($location, $photo["LOC_ID"]) == $location) {

        $result["data"].push_back($row);
        $sql = "insert into BADGE_COLLECT values ($id, $m_id)";
        $stid = oci_parse($db_conn, $sql);
        $r = oci_execute($stid);
      }
    }
    $result["status"] = "success";
  } else {
    $e = oci_error($stid);
    $result["status"] = "failed";
    $result["data"] = $e["message"];
  }
}
else if ($table_name=="COMMENT") {
  $msg = $_POST["msg"];
  $p_id = $_POST["p_id"];

  $sql = "insert into COMMENT_PHOTO values (comment_photo_seq.nextval, $m_id, $p_id, '$msg', systimestamp)";
  $stid = oci_parse($db_conn, $sql);
  $r = oci_execute($stid);
  if ($r) {
    $result["status"] = "success";
    $result["data"] = "";
  } else {
    $e = oci_error($stid);
    $result["status"] = "failed";
    $result["data"] = $e["message"];
  }
}
else if ($table_name=="LIKE") {
  $p_id = $_POST["p_id"];
  $sql = "insert into LIKE_PHOTO values ($m_id, $p_id, systimestamp)";
  $stid = oci_parse($db_conn, $sql);
  $r = oci_execute($stid);
  if ($r) {
    $result["status"] = "success";
    $result["data"] = "";
  } else {
    $e = oci_error($stid);
    $result["status"] = "failed";
    $result["data"] = $e["message"];
  }
}
else if ($table_name=="MESSAGE") {
  $to = $_POST["to"];
  $msg = $_POST["msg"];
  $sql = "insert into MESSAGE values (message.nextval, $m_id, $to, msg, systimestamp)";
  $stid = oci_parse($db_conn, $sql);
  $r = oci_execute($stid);
  if ($r) {
    $sql = "update MEMBER set IS_UNREAD='t' where ID=$to";
    $stid = oci_parse($db_conn, $sql);
    $r = oci_execute($stid);
    $result["status"] = "success";
    $result["data"] = "";
  } else {
    $e = oci_error($stid);
    $result["status"] = "failed";
    $result["data"] = $e["message"];
  }
}

echo json_encode($result);

?>
