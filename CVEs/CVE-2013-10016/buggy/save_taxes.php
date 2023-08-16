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
?>
<?php
//
// This file is part of « Webfinance »
//
// Copyright (c) 2004-2006 NBI SARL
// Author : Nicolas Bouthors <nbouthors@nbi.fr>
//
// You can use and redistribute this file under the term of the GNU GPL v2.0
//

// $Id: save_taxes.php 531 2007-06-13 12:32:31Z thierry $

require("../inc/main.php");
must_login();

if ($_GET['action'] == "delete") {
  mysql_query("DELETE FROM webfinance_pref WHERE id_pref=".$_GET['id']);
  $_SESSION['message'] = _('Tax deleted');
  header("Location: preferences.php?tab=Taxes");
  exit;
}

foreach ($_POST['taxes'] as $id=>$data) {
  if ($id == "new") {
    if (!empty($data['taxe']) ) {
      $q = sprintf("INSERT INTO webfinance_pref SET type_pref='taxe_%s', value='%s'" , $data['taxe'], $data['value']);
      $_SESSION['message'] = _('Tax added');
    }
  } else {
    $q = sprintf("UPDATE webfinance_pref SET type_pref='taxe_%s', value='%s' WHERE id_pref=%d " , $data['taxe'], $data['value'] , $id );
    $_SESSION['message'] = _('Tax updated');
  }
  if(isset($q) AND !empty($q))
    mysql_query($q) or wf_mysqldie();
}

header("Location: preferences.php?tab=Taxes");
exit;


?>
