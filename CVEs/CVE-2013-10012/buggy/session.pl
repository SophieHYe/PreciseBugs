#08/22/03 - JM - Changed some prepare/execute sql statements to $dbh->do.
#                Fixed broken URL in session_expired.

use warnings;
use strict;

use DBI;

require "cook.pl";

# STACK: $dbh, $uid
sub new_session {
  my ($dbh, $uid) = @_;

#  print "<p>Starting new session for uid $uid.</p>\n";

  # Make sequence numbers random.
  my $magic = int rand(2147483648);

  $dbh->do("update users set magic=$magic where id=$uid");
  $dbh->do("update users set session_stamp=now() where id=$uid");

  return $magic;
}

# STACK:  $dbh, $q, $time
# RETURN: New session number on success, 0 on fail.
sub get_session {
  my ($dbh, $q, $view_time) = @_;
  my $session_timeout = 60 * 60 * 24 * 7; # 7 day session timeout (in seconds)

  my $uid = cook_int($q->param('uid'));
  my $cgi_magic = cook_int($q->param('magic'));

#  print "<p>PASSED uid, magic: $uid, $cgi_magic</p>";

  my $sth = $dbh->prepare("select magic, UNIX_TIMESTAMP(now())-UNIX_TIMESTAMP(session_stamp) as elapsed from users where id=$uid");
  $sth->execute;
  my ($db_magic, $elapsed) = $sth->fetchrow_array;

#  print "<p>FOUND magic, elapsed: $db_magic, $elapsed</p>\n";

  if (($db_magic == $cgi_magic) and ($elapsed < $session_timeout)) {
    # Set a new session timestamp, update magic.
    my $new_magic = int rand(2147483648);
    $dbh->do("update users set session_stamp=now(), magic=$new_magic where id=$uid");

    # Put magic into cgi query.
#    my $vars = $q->Vars;
#    $vars->{'magic'} = $new_magic;
    # Successfully continued session...
    return 1;
  }
  else {
    # Couldn't get a session.
    return 0;
  }
}

# Stack: $dbh, $q, $view_time
# Return: access string. 'user' 'gate' 'admin'
sub get_access {
  my ($dbh, $q, $view_time) = @_;
  my $uid = cook_int($q->param('uid'));

  my $sth = $dbh->prepare("select access from users where id=$uid");
  $sth->execute;
  my ($access) = $sth->fetchrow_array;

  return $access;
}

sub no_access {
  my ($dbh, $q, $view_time) = @_;
  my $uid = cook_int($q->param('uid'));
  my $action = cook_word($q->param('action'));

  # Log it.
  my $sth = $dbh->prepare("insert into log (user,action,cdata1) values($uid,'accessdenied','$action')");
  $sth->execute;

  # Notify the user.
  print <<EOT;
    <p> You do not have access to this page. Please let an
    administrator know what you did to reach this message; you have
    likely found a bug in the system. Your session has been reset;
    return to the <a href="/ups/index.html">top.</a></p>
EOT
}

# Stack: $dbh, $q, $view_time
# Return: String with hidden input tags for session tokens (uid, magic).
sub get_session_info {
  my ($dbh, $q, $view_time) = @_;

  my $uid = cook_int($q->param('uid'));
  my $magic = cook_int($q->param('magic'));

  my $sth = $dbh->prepare("select magic from users where id=$uid");
  $sth->execute;
  my ($nextmagic) = $sth->fetchrow_array;

  return "<input type='hidden' name='uid' value='$uid'>\n<input type='hidden' name='magic' value='$nextmagic'>\n";
}

sub session_expired {
  print <<EOT;
  <p>Your session has expired.</p>
  <p> While navigating this system
    you must not use the back or forward buttons on your browser.
    To keep your session current you must use only the links provided on any
    page. There are a few things that will less commonly break your session: </p>

  <ul>
   <li>Clicking a submit button multiple times,
   <li>Trying to load a bookmark to a previous query,
   <li>Pressing the 'reload' button on your web browser,
   <li>Printing, from some browsers.
  </ul>

  <p> Sorry for the inconvenience, but this system keeps malicious
  users from hijacking your sessions and messing up your info. Please
  <a href="/">start over</a>.</p>

EOT
}

1;

