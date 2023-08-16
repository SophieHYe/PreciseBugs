<?php
/*
 Copyright (C) 2004-2006 NBI SARL, ISVTEC SARL

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
?>
<?php

include("../inc/main.php");
must_login();

if ($_POST['action'] == "create") {

  $_SESSION['tmp_message'] = $_SESSION['message'];

  $q = sprintf("INSERT INTO webfinance_personne (nom,prenom,email,tel,mobile,client,fonction,date_created,note) VALUES ('%s', '%s', '%s', '%s', '%s', %d, '%s', now(),'%s')",
       mysql_real_escape_string($_POST['nom']),
       mysql_real_escape_string($_POST['prenom']),
       mysql_real_escape_string($_POST['email']),
       mysql_real_escape_string(removeSpace($_POST['tel'])),
       mysql_real_escape_string(removeSpace($_POST['mobile'])),
       mysql_real_escape_string($_POST['client']),
       mysql_real_escape_string($_POST['fonction']),
       mysql_real_escape_string($_POST['note'] ));

  mysql_query($q)
    or die("QUERY ERROR: $q ".mysql_error());

  $_SESSION['message'] = _("Contact added");

  logmessage(_('Add contact')." ".$_POST['nom']." ".$_POST['prenom']. " ( client:".$_POST['client'].")",$_POST['client']);

} elseif ($_POST['action'] == "save") {

  $q = sprintf("UPDATE webfinance_personne SET nom='%s',prenom='%s',email='%s',tel='%s',mobile='%s',fonction='%s',note='%s' WHERE id_personne=%d",
       mysql_real_escape_string($_POST['nom']),
       mysql_real_escape_string($_POST['prenom']),
       mysql_real_escape_string($_POST['email']),
       mysql_real_escape_string(removeSpace($_POST['tel'])),
       mysql_real_escape_string(removeSpace($_POST['mobile'])),
       mysql_real_escape_string($_POST['fonction']),
       mysql_real_escape_string($_POST['note']),
       mysql_real_escape_string($_POST['id_personne']));

  mysql_query($q)
       or die("QUERY ERROR: $q ".mysql_error());

  $_SESSION['message'] = _("Contact updated");

  $res=mysql_query("SELECT client FROM webfinance_personne WHERE id_personne=".$_POST['id_personne']) or die("QUERY ERROR: ".mysql_error());
  list($client)=mysql_fetch_array($res);

  logmessage(_('Update contact')." ".$_POST['nom']." ".$_POST['prenom']." ( client:$client)", $client);

} elseif ($_POST['action'] == "delete") {
  $res=mysql_query("SELECT nom, prenom, client FROM webfinance_personne WHERE id_personne=".$_POST['id_personne'])  or die("QUERY ERROR: ".mysql_error());
  list($nom, $prenom,$client)=mysql_fetch_array($res);
  logmessage(_('Delete contact')." $nom $prenom client:$client ", $client);

  mysql_query("DELETE FROM webfinance_personne WHERE id_personne=".$_POST['id_personne']);

  $_SESSION['message'] = _("Contact deleted");

} else {
  die(_("Don't know what to do with posted data"));
}

?>
<script>
popup = window.parent.document.getElementById('inpage_popup');
popup.style.display = 'none';
// Reload parent window to update contacts
page = '/prospection/fiche_prospect.php?id=<?= $_POST['client'] ?>&onglet=contacts&foobar='+100*Math.random(); // Random to force reload
window.parent.location = page;
</script>
