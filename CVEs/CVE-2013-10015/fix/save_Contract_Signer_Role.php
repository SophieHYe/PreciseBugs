<?php
/*
   This file is part of Webfinance.

    Webfinance is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    Webfinance is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Webfinance; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require("../inc/main.php");
must_login();

if ($_GET['action'] == "delete") {
  $_GET['id'] = mysql_real_escape_string($_GET['id']);
  mysql_query("DELETE FROM contract_signer_role WHERE id=".$_GET['id']);
  header("Location: preferences.php?tab=Contract_Signer_Role");
  exit;
}

foreach ($_POST['cat'] as $id=>$data) {
  if ($id == "new") {
    if ($data['role'] != "") {
      $q = "INSERT INTO contract_signer_role ";
      $f = "(";
      $values = "VALUES(";
      foreach ($data as $n=>$v) {
        $f .= sprintf("%s,", mysql_real_escape_string($n));
        $values .= sprintf("'%s',", mysql_real_escape_string($v));
      }
      $f = preg_replace("!,$!", ") ", $f);
      $values = preg_replace("!,$!", ") ", $values);
      $q .= $f.$values;
      $_SESSION['message'] = _('Added');
    }
  } else {
    $q = "UPDATE contract_signer_role SET ";
    foreach ($data as $n=>$v) {
      $q .= sprintf("%s='%s',", $n, $v);
    }
    $q = preg_replace("!,$!", " WHERE id=$id", $q);
    $_SESSION['message'] = _('Updated');
  }
  //  echo $q;
  mysql_query($q) or wf_mysqldie();
}

header("Location: preferences.php?tab=Contract_Signer_Role");
exit;

?>
