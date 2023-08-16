#!/usr/bin/perl
#
#this is a test of many things, the perl interpreter being one of them.

use warnings;
use strict;

use CGI;
use DBI;

require "session.pl";
require "main_menu.pl";
require "cook.pl";
require "styles.pl";
require "db.pl";

# Log into the database,
# Retrieve cgi buffer,
# Check authentication,
# Start a new session,
# Jump to the main menu.

my $dbh = get_db();

my $q = new CGI;
print $q->header;

my $login = lc cook_word($q->param('login'));
my $pass = cook_word($q->param('pass'));

print <<EOT;
<head><title>Universal Point System</title>
EOT

print_styles();

print <<EOT;
</head>

<body>
EOT

print "<p>Trying to log in using login $login</p>\n";

#User names always lowercased.
#Check if login is an alias
my $sth = $dbh->prepare("select name from users where aliases like '%$login %'");
$sth->execute;
if ($sth->rows) {
  my ($name) = $sth->fetchrow_array;
  print "Cannot login using your alias, login with $name instead";
#  invalid_login($q);
}

#login is real, check the password
$sth = $dbh->prepare("select id from users where name='$login' and pass=PASSWORD('$pass')");
$sth->execute;

my $valid_login = $sth->rows;
my ($uid) = $sth->fetchrow_array;

if ($valid_login) {
  # Login was valid, get the current time.
  my $sth = $dbh->prepare("select unix_timestamp(now())");
  $sth->execute;
  my ($time) = $sth->fetchrow_array;

  my $magic = new_session($dbh, $uid);
  my $CGI_params = $q->Vars;
  $CGI_params->{'magic'} = $magic;
  $CGI_params->{'uid'} = $uid;
  #print "<p>Adding to CGI buffer: magic, $magic; uid, $uid</p>\n";

  main_menu($dbh, $q, time);
}
else {
  invalid_login($q);
}

sub invalid_login {
  my ($q) = @_;

  print <<EOT;
  <p>You entered an invalid username/password pair.</p>
  <p>If you haven't had your account password set, or if this problem continues,
     speak to an administrator. </p>
  <p><A HREF="/">Back to the top</a></p>
EOT

  print $q->end_html;
}
