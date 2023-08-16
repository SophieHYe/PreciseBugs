<?php
//----------------------------------------------------------------------
// Copyright (c) 2011-2016 Raytheon BBN Technologies
//
// Permission is hereby granted, free of charge, to any person obtaining
// a copy of this software and/or hardware specification (the "Work") to
// deal in the Work without restriction, including without limitation the
// rights to use, copy, modify, merge, publish, distribute, sublicense,
// and/or sell copies of the Work, and to permit persons to whom the Work
// is furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be
// included in all copies or substantial portions of the Work.
//
// THE WORK IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
// NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
// HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE WORK OR THE USE OR OTHER DEALINGS
// IN THE WORK.
//----------------------------------------------------------------------
?>
<?php
require_once("settings.php");
require_once('portal.php');
require_once("user.php");
require_once("file_utils.php");
require_once("sr_client.php");
require_once("sr_constants.php");
require_once("am_client.php");
require_once("am_map.php");
require_once("sa_client.php");
require_once("print-text-helpers.php");
require_once("logging_client.php");
require_once("header.php");
$user = geni_loadUser();
if (!isset($user) || is_null($user) || ! $user->isActive()) {
  relative_redirect('home.php');
}

function no_slice_error() {
  header('HTTP/1.1 404 Not Found');
  print 'No slice id specified.';
  exit();
}

function no_invocation_id_error() {
  header('HTTP/1.1 404 Not Found');
  print 'No omni invocation id and/or user ID specified.';
  exit();
}

// redirect if no attributes passed in
if (! count($_REQUEST)) {
  no_slice_error();
}

// set user ID and invocation
if(array_key_exists("invocation_id", $_REQUEST) &&
        array_key_exists("invocation_user", $_REQUEST)) {
    $invocation_user = $_REQUEST['invocation_user'];
    $invocation_id = $_REQUEST['invocation_id'];
}
else {
    no_invocation_id_error();
}

// set slice/AM information
unset($slice);
unset($am);
include("tool-lookupids.php");
if (! isset($slice)) {
  no_slice_error();
}

// redirect if slice has expired
if (isset($slice_expired) && convert_boolean($slice_expired)) {
  if (! isset($slice_name)) {
    $slice_name = "";
  }
  $_SESSION['lasterror'] = "Slice " . $slice_name . " is expired.";
  relative_redirect('dashboard.php#slices');
}

// redirect if user isn't allowed to look up slice
if(!$user->isAllowed(SA_ACTION::LOOKUP_SLICE, CS_CONTEXT_TYPE::SLICE, $slice_id)) {
  relative_redirect('home.php');
}

// show header and breadcrumbs
show_header('GENI Portal: Add Resources to Slice (Results)');
include("tool-breadcrumbs.php");
include("tool-showmessage.php");

// check that invocation ID actually points to a directory
$dir_to_check = get_invocation_dir_name($invocation_user, $invocation_id);
if(!is_dir($dir_to_check)) {
    echo "<h1>Add Resources to GENI Slice <i>$slice_name</i> (Results)</h1>";
    echo "<p class='error'>Files and process data related to omni request";
    echo " <b>$invocation_user-$invocation_id</b> not found.";
    echo " Older files are periodically removed from the Portal, so it is possible that the files";
    echo " and process-related data have been deleted as part of routine maintenance.";
    echo "<br><br>";
    echo "Please <a href='contact-us.php'>contact us</a> if you require further assistance.</p>";

    echo '<form method="GET" action="back">';
    echo '<input type="button" value="Back" onClick="history.back(-1)"/>';
    echo '</form>';
    include("footer.php");
    error_log("sliceresource.php: Failed to find directory with invocation ID " .
        "$invocation_id and user $invocation_user.");
    exit;
}

// set e-mail footer message
$bug_report_msg1 = "Attached is a problem report about reserving resources generated from the GENI Portal (https://portal.geni.net/). This problem report contains process-related information such as log files, resource specifications (RSpecs) and metadata.<br><br>User message:";
$bug_report_msg2 = "Thanks,<br>" . $user->prettyName();
$bug_report_subject = "GENI Portal Reservation Problem Report";

/*
    since AM ID is optional for this page, it needs to be explicitly
    set if tool-lookupids.php hasn't already set it - there's no else clause
    in tool-lookupids.php because the assumption is that the page would have
    already shown an error earlier if $REQUEST['am_id'] hadn't been specified
*/
if(!isset($am_id)) {
    $am_id = NULL;
}

print '<script src="jacks-lib.js"></script>';
include("sliceresource.js");

echo "<h1>Add Resources to GENI Slice <i>$slice_name</i> (Results)</h1>";
echo "<div style='position:relative;'>";
echo "<p style='margin-left:0px;'>Total run time: <b><span id='total_run_time'></span></b> ";
echo "<br>Status: <span id='total_run_time_status'></span></p>";
echo "<div style='position:absolute;top:0px;right:0px;'>";
echo "<p style='margin:0px;text-align:right;'>Started at: <b><span id='start_time'></span></b><br><span id='last_updated_or_finished_text'>Last updated:</span> <b><span id='last_updated_or_finished_time'></span></b></p>";
echo "</div></div>";

$request_rspec_filename = $slice_name . "_request_rspec.xml";
$manifest_rspec_filename = $slice_name . "_manifest_rspec.xml";
?>

  <div id='tablist'>
		<ul class='tabs'>
			<li><a href='#tab_results'>Results</a></li>
			<li><a href='#tab_progress'>Detailed Progress</a></li>
			<li><a href='#tab_request_rspec'>Request RSpec</a></li>
			<li><a href='#tab_manifest_rspec'>Manifest RSpec</a></li>
			<li><a href='#tab_send_bug_report'>Send Problem Report</a></li>
			<li style="border-right: none"><a href='#tab_advanced'>Advanced</a></li>
		</ul>
  </div>

<!-- begin tab content -->
<div class='tabContent'>

<!-- resource 'tab' - this is empty so that the results (which always appear on
     each tab) will show at the top -->
<div id='tab_results'>
</div>

<!-- progress tab -->
<div id='tab_progress'>

<h2>Detailed Progress</h2>
<pre id='console_data_container' style="height:300px;">
<span id='console_data'></span>
</pre>
<p><button onClick="window.location='<?php echo "get_omni_invocation_data.php?invocation_user=$invocation_user&invocation_id=$invocation_id&slice_id=$slice_id&request=console&download=true&filename=detailed_progress.log";?>'" title='Download Detailed Progress Log' id='download_console'>Download Detailed Progress Log</button></p>
</div>

<!-- request RSpec tab -->
<div id='tab_request_rspec'>

<h2>Request RSpec</h2>
<pre id='requestrspec_container' style="height:300px;"><span id='requestrspec_data'></span></pre>
<p><button onClick="window.location='<?php echo "get_omni_invocation_data.php?invocation_user=$invocation_user&invocation_id=$invocation_id&slice_id=$slice_id&request=requestrspec&download=true&filename=$request_rspec_filename";?>'" title='Download Request RSpec' id='download_requestrspec'>Download Request RSpec</button></p>
</div>

<!-- manifest RSpec tab -->
<div id='tab_manifest_rspec'>

<h2>Manifest RSpec</h2>
<pre id='manifestrspec_container' style="height:300px;"><span id='manifestrspec_data'><i>Manifest RSpec empty</i></span></pre>
<p><button onClick="window.location='<?php echo "get_omni_invocation_data.php?invocation_user=$invocation_user&invocation_id=$invocation_id&slice_id=$slice_id&request=manifestrspec&download=true&filename=$manifest_rspec_filename";?>'" title='Download Manifest RSpec' id='download_manifestrspec' disabled='disabled'>Download Manifest RSpec</button></p>
</div>


<!-- send bug report tab -->
<div id='tab_send_bug_report'>

<script>
function validateBugReportSubmit()
{
  f1 = document.getElementById("f1");
  to = document.getElementById("to");
  message = document.getElementById("message");

  if (to.value && message.value) {
    f1.submit();
    return true;
  } else if(to.value && !(message.value)) {
    alert("Please add a message.");
    return false;
  } else if(!(to.value) && message.value) {
    alert("Please select a recipient e-mail address.");
    return false;
  } else {
    alert("Please select a recipient e-mail address and add a message.");
    return false;
  }
}
</script>

<h2>Send a Problem Report</h2>

<p>Ran into a problem or have a question? Search the
  <a target="_blank" href="https://groups.google.com/forum/#!forum/geni-users">
    GENI Users</a> archives for answers to similar questions.
</p>

<ul>
<li>The problem report will include your name, e-mail address, slice and project information, request RSpec, manifest RSpec(s), progress log, debug log, error log and process metadata.</li>
<li>The report will not include security-sensitive information such as slice credentials, certificates, private keys and SpeaksFor credentials.</li>
</ul>

<p>If you are not comfortable sharing your problem report publicly, email it to
  <a href="mailto:<?php echo $portal_help_email;?>">
          <?php echo $portal_help_email;?></a>.
</p>

<form id="f1" action="send_bug_report.php" method="post" enctype="multipart/form-data" onsubmit="return validateBugReportSubmit()">
<input type="hidden" name="invocation_id" id="invocation_id" value="<?php echo $invocation_id;?>"/>
<input type="hidden" name="invocation_user" id="invocation_user" value="<?php echo $invocation_user;?>"/>
<input type="hidden" name="slice_id" id="slice_id" value="<?php echo $slice_id;?>"/>

<table>
<tr>
<th>From</th>
<td><b><?php echo $user->prettyName() . " &lt;" . $user->email() . "&gt;"; ?></b> (Copy me on the problem report e-mail:<input type="checkbox" name="copy" id='copy' value="true"/>)</td>
</tr>
<tr>
<th>Subject</th>
<td><b><?php echo $bug_report_subject; ?></b></td>
</tr>
<tr>
<th>To<br><small>(Required)</small></th>
<td><b>Recipient e-mail:</b> <input type='text' name='to' id='to' size='30' value='geni-users@googlegroups.com'></input><br></td>
</tr>
<tr>
<th>Message<br><small>(Required)</small></th>
<td><tt><?php echo $bug_report_msg1;?></tt><br><textarea name='message' id='message' cols='60' rows='4'></textarea><br><br><tt><?php echo $bug_report_msg2;?></tt></td>
</tr>
</table>

<p><input type="submit" value="Submit Problem Report"/></p>


</form>

</div>

<!-- advanced tab -->
<div id='tab_advanced'>

<h2>Debug Log</h2>
<pre id='debug_data_container' style="height:300px;">
<span id='debug_data'></span>
</pre>
<p><button onClick="window.location='<?php echo "get_omni_invocation_data.php?invocation_user=$invocation_user&invocation_id=$invocation_id&slice_id=$slice_id&request=debug&download=true&filename=debug.log";?>'" title='Download Debug Log' id='download_debug'>Download Debug Log</button></p>

<h2>Error Log</h2>
<pre id='error_data_container'><span id='error_data'><i>Error log empty</i></span></pre>
<p><button onClick="window.location='<?php echo "get_omni_invocation_data.php?invocation_user=$invocation_user&invocation_id=$invocation_id&slice_id=$slice_id&request=error&download=true&filename=error.log";?>'" title='Download Error Log' id='download_error' disabled='disabled'>Download Error Log</button></p>

<h2>Command</h2>
<pre id='command_data_container'><span id='command_data'></span></pre>

</div>

<!-- end tab content -->
</div>

<!-- always show results -->
<h2>Results</h2>
<?php
// set AM name if it exists
if (isset($am_id) && $am_id) {
    $am_url = $am[SR_ARGUMENT::SERVICE_URL];
    $AM_name = am_name($am_url);
    print "<p>Resources on AM (<b>$AM_name</b>):</p>";
}
else {
    print "<p>Resources requested from RSpec:</p>";
}
?>

<div id='results_stop_msg'></div>
<div class='resources' id='prettyxml'><p><i>Pending... (See 'Detailed Progress' tab for more information.)</i></p></div>

<!-- Jacks container -->
<link rel="stylesheet" type="text/css" href="jacks-app.css" />
<link rel="stylesheet" type="text/css" href="jacks-editor-app.css" />
<script src="<?php echo $jacks_stable_url;?>"></script>
<div id='jacksContainer' class='jacks resources' style='background-color: white;display: none;'></div>

<div id='results_manifest_link'></div>
<p>
<a href="dashboard.php#slices">Back to All slices</a><br>
<a href="slice.php?slice_id=<?php echo $slice_id; ?>">Back to Slice <i><?php echo $slice_name; ?></i></a>
</p>

<?php

include "tabs.js";
include("footer.php");

?>
