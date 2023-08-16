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

require_once("header.php");

$referer_key = 'HTTP_REFERER';
$referer = "";
if (key_exists($referer_key, $_SERVER)) {
  $referer = $_SERVER[$referer_key];
}
 $system_error = false;
if (key_exists("system_error", $_GET)) {
  $system_error = true;
}

show_header('GENI Portal: Home', false);
$header = "Error";
print "<h1>$header</h1>\n";
// print "Project name: <b>$slice_project_name</b><br/>\n";

$error_text = "";
if (key_exists("error", $_GET)) {
  $error_text = urldecode($_GET["error"]);
//  error_log("ET = " . $error_text);
//  if ($system_error) {
    $error_text = htmlentities($error_text);
//  }
  echo "<p class='warn'>" . $error_text . "</p><br/>\n";
} else {
  // error_log('$_SERVER = ' . print_r($_SERVER, true));

  foreach ($_GET as $line_num => $line) {
    //  error_log("LINE_NUM " . $line_num);
    $text = str_replace('_', ' ', htmlspecialchars(urldecode($line_num)));
    echo $text . "<br />\n";
  }
}

/* Get the user email if available. */
$user_email = 'Not Available';
if (array_key_exists('mail', $_SERVER)) {
  $user_email = $_SERVER['mail'];
}
/* Get the user eppn if available. */
$user_eppn = 'Not Available';
if (array_key_exists('eppn', $_SERVER)) {
  $user_eppn = $_SERVER['eppn'];
}
/* Use ISO 8601 formatting for date */
$error_date = gmdate("c");

$email_text = "Date: $error_date\n";
$email_text .= "Error: $error_text\n";
$email_text .= "HTTP REFERER: $referer\n";
$email_text .= "User email: $user_email\n";
$email_text .= "User eppn: $user_eppn\n";
$email_text .= "\nUser questions or comments (please add):\n";
$mailto_params = array('subject' => 'Portal Error',
                       'body' => $email_text);
$mailto_query_string = http_build_query($mailto_params);
/* In PHP 5.4 http_build_query can do this translation for us via
   RFC3986 encoding. */
$mailto_query_string = str_replace('+', '%20', $mailto_query_string);

print "Need help?\n";
print "<a href='contact-us.php'>";
print "Contact us</a>.\n";
print "<br/>\n";
print "<br/>\n";
print "<form method=\"GET\" action=\"back\">\n";
print "<input type=\"button\" value=\"Back\" onClick=\"history.back(-1)\"/>\n";
print "</form>\n";

include("footer.php");
?>
