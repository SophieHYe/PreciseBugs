<?php

/**
 * 
 * @category  phplist
 * @package   submitByMail Plugin
 * @author    Arnold V. Lesikar
 * @copyright 2014 Arnold V. Lesikar
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 *
 * This file is a part of the submitByMailPlugin for Phplist
 *
 * The submitByMailPlugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 * For more information about how to use this plugin, see
 * http://resources.phplist.com/plugins/submitByMail .
 * 
 */

// This page presents a form allowing a mailing list to be configured for submission
// of messages by email. On submission, the form is validated for various issues
// but the form data is actually stored by the page configure_a_list.php

if (!defined('PHPLISTINIT')) die(); ## avoid pages being loaded directly
if (!isSuperUser()){
	print ("<p>You do not have sufficient privileges to view this page.</p>");
	return;
}

$sbm = $GLOBALS['plugins']['submitByMailPlugin'];

$editid = $_GET['eid'];
$listArray = $sbm->getTheLists();
$adrsList = array();
foreach ($listArray as $val)
	if ($val[1]) $adrsList[$val[1]] = $val[0];
	
// Set up defaults for form
$eml = $user = $pass = $msyes = $pipe = $cfmno = $queue = '';
$save = $pop = $cfmyes = $msno = $ckd = 'checked';
$tmplt = 0;
$footer = getConfig('messagefooter');

$query = sprintf("select * from %s where id=%d", $sbm->tables['list'], $editid);

if ($row = Sql_Fetch_Assoc_Query($query)) {
	$eml = $row['pop3server'];
	$user = $row['submissionadr'];
	$pass = $row['password'];

	if ($user) {
		$msyes = $ckd;
		$msno = '';
	} else {
		$msno = $ckd;
		$msyes = '';
	}
	if ($row['pipe_submission']) {
		$pipe = $ckd;
		$pop = '';
	} else {
		$pop = $ckd;
		$pipe = '';
	}
	if ($row['confirm']) {
		$cfmyes = $ckd;
		$cmno = '';
	} else {
		$cfmno = $ckd;
		$cfmyes = '';
	}
	if ($row['queue']) {
		$queue = $ckd;
		$save = '';
	} else {
		$save = $ckd;
		$queue = '';
	}
	$tmplt = $row['template'];
	$footer = stripslashes($row['footer']); // Magic quotes apparently! :-(
}
		
$req = Sql_Query("select id,title from {$GLOBALS['tables']['template']} order by listorder");
$templates_available = Sql_Num_Rows($req);
if ($templates_available) {
	$template_form = '<p><div class="field"><label for="template">Template to use for messages submitted through this address:</label><select name="template"><option value="0">-- Use None</option>';
	$req = Sql_Query("select id,title, listorder from {$GLOBALS['tables']['template']} order by listorder");
	while ($row = Sql_Fetch_Assoc($req)) {   // need to fix lines below
		if ($row["title"]) {
			$template_form .= sprintf('<option value="%d" %s>%s</option>',$row["id"], 
			$row["id"]==$tmplt?'selected="selected"':'',$row["title"]);
		}
	}
	$template_form .= '</select></div></p>';
} else
	$template_form = '';

$footer_form = '<p><div class="field"><label for="footer">Footer to be used for messages submitted through this address:</label><textarea name="footer" cols="65" rows="5">'. htmlspecialchars($footer).'</textarea></div></p>';
$dilg = '<div id="mydialog" title="Data Not Saved" style="text-align:center;"></div>'; // Space for modal dialogs using jQueryUI
// Add a confirmation dialog

$ln = listName($editid);
$infostr = <<<IOP
<h3><strong>This configuration applies for messages sent to a single individual list.</strong></h3><p style="font-size:14px; margin-top:15px;">Messages sent to multiple lists at the same time are not affected by the settings here. Such messages are always held for confirmation and then always saved as drafts after confirmation.</p>
IOP;
Info($infostr);
print('<noscript>');
Info ('<span style="font-size:14px;font-weight:bold;">You do not need to enter a password or POP3 server, if messages are to be collected through a pipe.</span>');
print('</noscript>');
// Now lay out the form
print ($sbm->myFormStart(PageURL2('configure_a_list'), 'name="sbmConfigEdit" class="submitByMailPlugin" id="sbmConfigEdit"'));

$mypanel = <<<EOD
<input type="hidden" name="listid" value=$editid><input type="hidden" name="update" value=0>
<p><label style="display:inline !important;">Submission by mail allowed:</label> <input type="radio" name="submitOK" value="Yes" $msyes /><label style="display:inline !important;">Yes</label>&nbsp;&nbsp;&nbsp;&nbsp;
	<input type="radio" name="submitOK" value="No" $msno /><label style="display:inline !important;">No</label>
</p>
<p>
<label style="display:inline !important;">Collection method:</label>&nbsp;&nbsp;<input type="radio" name="cmethod" value="POP" $pop/><label style="display:inline !important;">POP3 with SSL/TLS</label>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="cmethod" value="Pipe" $pipe/><label style="display:inline !important;">Pipe</label>
</p><p>
<label style="display:inline !important;">Submission Address:&nbsp;&nbsp;<input type="text" name="submitadr" style="width:200px !important; 
display:inline !important;" value="$user" maxlength="255" /></label><div id="pop" style="margin-top:-25px; margin-bottom: 5px;"><label style="display:inline !important;">Password:&nbsp;&nbsp;<input type="text" name="pw" 
style="width:125px !important; display:inline !important;" value="$pass" maxlength="255" /></label>
<label>Mail Submission POP3 Server (<span style="font-weight:bold; color:red;">Don't include a port number!</span>):<input type="text" name="pop3server" value="$eml" maxlength="255" /></label></div>
<div id="formbtm">
<label style="display:inline !important;">What to do with submitted message:</label>&nbsp;&nbsp;<input type="radio" name="mdisposal" 
value="Save" $save /><label style="display:inline !important;">Save</label>&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="mdisposal" value="Queue" $queue /><label style="display:inline !important;">Queue</label>
<br /><br /><label style="display:inline !important;">Confirm submission:</label>&nbsp;&nbsp;<input type="radio" name="confirm" value="Yes" $cfmyes /><label style="display:inline !important;">Yes</label>&nbsp;&nbsp;&nbsp;&nbsp;
<input type="radio" name="confirm" value="No" $cfmno /><label style="display:inline !important;">No</label>$template_form $footer_form
<input class="submit" type="submit" name="submitter" value="Save" />
EOD;

$mypanel .= PageLinkClass('configure_a_list','Cancel','','button cancel','Do not save, and go back to the lists');
$mypanel .= "</div></p>";

$panel = new UIPanel("Submit to List by Mail: <strong id=\"mylistname\">$ln</strong>", $mypanel);
print($panel->display());
print($dilg);
print("</form>\n");

print ('<script type="text/javascript">');
print ("var adrs = " . json_encode($adrsList) . ";\n");
if ($user)
	print ("var prevvals = true;\n");
else
	print ("var prevvals = false;\n");
	
// The following scripts makes sure that POP credentials can be entered only if the POP 
// radio button has been pressed. They also validate the form for various issues.
// The submission address is validated and the POP credentials are verified using ajax.
// See the page verify.php
$str = <<<EOS
$(document).ready(function () {
    toggleFields(); //call this first so we start out with the correct visibility depending on the selected form values
    //this will call our toggleFields function every time the POP or Pipe radio buttons change
    $( "input[type=radio]" ).change(function () {
        toggleFields();
    	});
    $("#mydialog").dialog({
    		modal: true,
    		autoOpen: false,
    		width: 500,
    	}); 
	$(".ui-dialog-titlebar-close").css("display","none");
	$(".ui-dialog-content").css("margin", "10px");
	$(".ui-dialog").css("border","3px solid DarkGray");
	$(".ui-dialog-content").css("font-size", "18px");
	});


//this toggles the visibility of our the fields for input of POP credentials depending on the currently 
//selected value of the 'Collection Method' radio buttons
function toggleFields() {
	if ($("input[name=cmethod]:checked").val() == "POP") {
		$("#formbtm").css("margin-top", "");
        $("#pop").show();
    } else {
        $("#formbtm").css("margin-top", "-20px");
       	$("#pop").hide();
    }
}

function mynotice(msg) {
	$("#mydialog").html(msg);
	$("#mydialog").dialog("option",{buttons:{}});
	$("#mydialog").dialog("open");
}

function myalert(msg) {
	$("#mydialog").html(msg);
	$("#mydialog").dialog("option",{buttons:{"OK": function() {
        				$(this).dialog("close");}}});
    $("#mydialog").dialog("open");
}

function mysubmit(upd) {
	var myform = document.getElementById("sbmConfigEdit");
    if (upd)
    	$("input[name=update]").val(1);
    myform.submit();
    	
}

function myconfirm(msg) {
	$("#mydialog").html(msg);
	$("#mydialog").dialog("option",
		{buttons:{"Yes": function()
			{
				mysubmit(1);
				$(this).dialog("close");
            }, 
        "No": function()
        	{
        		$(this).dialog("close");
        	}
        }
    });
    $("#mydialog").dialog("open");	
}

$("#sbmConfigEdit").submit(function( event ) {
	var srvr = $("input[name=pop3server]").val();
	var sadr = $("input[name=submitadr]").val();
	var pwd = $("input[name=pw]").val();
	var ln = $("#mylistname").text();
	var myjob = ($("input[name=cmethod]:checked").val() =="Pipe") ? "validate" : "verify";
		    		
	if ($("input[name=submitOK]:checked").val() == "No") {
		if (!prevvals) 
			mysubmit(0);
		else {
			myconfirm ("Are you <strong>absolutely sure</strong> that you want to delete email submission data for this list?");
			return false;
		}
	}
	if (sadr=='') {
		myalert("You cannot allow email submission of messages without specifying a submission address!");
		return false;
	}
	
	if ((adrs != null) && (adrs[sadr] != null) && (adrs[sadr] != ln)) {
		myalert("This submission address is already used by another list. <strong>Two lists cannot have the same submission address.</strong>");
		return false;
	}
	
	event.preventDefault();
	if (myjob == 'verify') {
		if ((srvr=='') || (pwd=='')) {
			myalert("You cannot collect messages with POP without specifying a server and a password!");
			return false;
		}
		mynotice('Verifying POP credentials<img style="width:40px; height:40px; display:block; margin-left:auto; margin-right:auto; margin-top: 10px;" src="images/busy.gif">');
	}
	
	$.post( "?pi=submitByMailPlugin&page=sbmajax&ajaxed=1", {job:myjob, server:srvr, user:sadr, pass:pwd}, function (data) { 
			if (data == 'OK') {
				if (($("input[name=mdisposal]:checked").val() == "Queue") && ($("input[name=confirm]:checked").val() == "No")) 
					myconfirm("Are you <strong>absolutely sure</strong> that you want to queue messages mailed in, without confirming with the list administrator?");
				else
					mysubmit(1);
			} else {
				if (data == 'NO')
					myalert ("User name, server, and password do not verify!");
				else
					myalert("Invalid email address for message submission!");
			}
		}, "text").fail( function() {
				myalert("Connection to server failed: cannot verify address!");
				}
			);
});
</script>
<style>
.ui-dialog{top:30% !important}
</style>
EOS;
// <style> above is there to allow vertical centering of these modal dialogs. Special thanks to 
// Mariela Zarate for coming up with this.
print($str);
?>