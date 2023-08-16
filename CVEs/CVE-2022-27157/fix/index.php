<?php
/*
   +----------------------------------------------------------------------+
   | PEAR Web site version 1.0                                            |
   +----------------------------------------------------------------------+
   | Copyright (c) 2001-2005 The PHP Group                                |
   +----------------------------------------------------------------------+
   | This source file is subject to version 2.02 of the PHP license,      |
   | that is bundled with this package in the file LICENSE, and is        |
   | available at through the world-wide-web at                           |
   | http://www.php.net/license/2_02.txt.                                 |
   | If you did not receive a copy of the PHP license and are unable to   |
   | obtain it through the world-wide-web, please send a note to          |
   | license@php.net so we can mail you a copy immediately.               |
   +----------------------------------------------------------------------+
   | Authors: Martin Jansen <mj@php.net>                                  |
   +----------------------------------------------------------------------+
   $Id$
*/

redirect_to_https();
auth_require(true);
require_once 'HTML/Table.php';

if (!empty($_GET['phpinfo'])) {
    phpinfo();
    exit();
}

class BorderBox
{
    function __construct($title, $width = '90%', $indent = '', $cols = 1,
        $open = false
    ) {
        $this->title  = $title;
        $this->width  = $width;
        $this->indent = $indent;
        $this->cols   = $cols;
        $this->open   = $open;
        $this->start();
    }

    function start()
    {
        $title = $this->title;
        if (is_array($title)) {
            $title = implode('</th><th>', $title);
        }
        $i = $this->indent;
        echo "<!-- border box starts -->\n";
        echo "$i<table cellpadding=\"0\" style=\"width: $this->width; border: 0px;\">\n";
        echo "$i <tr>\n";
        echo "$i  <td>\n";
        echo "$i   <table cellpadding=\"2\" style=\"width: 100%; border: 0px;\">\n";
        echo "$i    <tr style=\"background-color: #CCCCCC;\">\n";
        echo "$i     <th";
        if ($this->cols > 1) {
            echo " colspan=\"$this->cols\"";
        }
        echo ">$title</th>\n";
        echo "$i    </tr>\n";
        if (!$this->open) {
            echo "$i    <tr style=\"background-color: #FFFFFF;\">\n";
            echo "$i     <td>\n";
        }
    }

    function end()
    {
        $i = $this->indent;
        if (!$this->open) {
            echo "$i     </td>\n";
            echo "$i    </tr>\n";
        }
        echo "$i   </table>\n";
        echo "$i  </td>\n";
        echo "$i </tr>\n";
        echo "$i</table>\n";
        echo "<!-- border box ends -->\n";
    }

    function horizHeadRow($heading /* ... */)
    {
        $i = $this->indent;
        echo "$i    <tr>\n";
        echo "$i     <th style=\"vertical-align: top; background-color: #CCCCCC;\">$heading</th>\n";
        for ($j = 0; $j < $this->cols-1; $j++) {
            echo "$i     <td style=\"vertical-align: top; background-color: #E8E8E8\">";
            $data = @func_get_arg($j + 1);
            echo !isset($data) ? "&nbsp;" : $data;
            echo "</td>\n";
        }
        echo "$i    </tr>\n";

    }

    function headRow()
    {
        $i = $this->indent;
        echo "$i    <tr>\n";
        for ($j = 0; $j < $this->cols; $j++) {
            echo "$i     <th style=\"vertical-align: top; background-color: #FFFFFF;\">";
            $data = @func_get_arg($j);
            if (empty($data)) {
                echo '&nbsp;';
            } else {
                print $data;
            }
            echo "</th>\n";
        }
        echo "$i    </tr>\n";
    }

    function plainRow(/* ... */)
    {
        $i = $this->indent;
        echo "$i    <tr>\n";
        for ($j = 0; $j < $this->cols; $j++) {
            echo "$i     <td style=\"vertical-align: top; background-color: #FFFFFF;\">";
            $data = @func_get_arg($j);
            if (empty($data)) {
                echo '&nbsp;';
            } else {
                print $data;
            }
            echo "</td>\n";
        }
        echo "$i    </tr>\n";
    }

    function fullRow($text)
    {
        $i = $this->indent;
        echo "$i    <tr>\n";
        echo "$i     <td style=\"background-color: #E8E8E8;\"";
        if ($this->cols > 1) {
            echo " colspan=\"$this->cols\"";
        }
        echo ">$text</td>\n";
        echo "$i    </tr>\n";

    }
}

response_header('PEAR Administration');

// {{{ adding and deleting notes
if (!empty($_REQUEST['cmd'])) {
    if ($_REQUEST['cmd'] == "Add note" && !empty($_REQUEST['note']) && !empty($_REQUEST['id'])) {
        include_once 'pear-database-note.php';
        note::add($_REQUEST['id'], $_REQUEST['note']);
        unset($_REQUEST['cmd']);

    } elseif ($_REQUEST['cmd'] == "Delete note" && !empty($_REQUEST['id'])) {
        include_once 'pear-database-note.php';

        // Delete note
        note::remove($_REQUEST['id']);

    } elseif ($_REQUEST['cmd'] == "Open Account" && !empty($_REQUEST['uid'])) {
        /**
         * Open account
         */

        $karmalevel = (empty($_REQUEST['karma'])) ? 'pear.pepr' : $_REQUEST['karma'];
        // another hack to remove the temporary "purpose" field
        // from the user's "userinfo"
        include_once 'pear-database-user.php';
        if (user::activate($_REQUEST['uid'], $karmalevel)) {
            $uid = strip_tags(htmlspecialchars($_REQUEST['uid']));
            print "<p>Opened account $uid...</p>\n";
        }

    } elseif ($_REQUEST['cmd'] == "Reject Request" && !empty($_REQUEST['uid'])) {
        // Reject account request
        include_once 'pear-database-user.php';
        if (is_array($_REQUEST['uid'])) {
            foreach ($_REQUEST['uid'] as $uid) {
                user::rejectRequest((int) $uid, $_REQUEST['reason']);
                echo 'Account rejected: ' . (int) $uid . '<br />';
            }

        } elseif (user::rejectRequest($_REQUEST['uid'], $_REQUEST['reason'])) {
            print "<p>Rejected account request for $uid...</p>\n";
        }

    } elseif ($_REQUEST['cmd'] == "Delete Request" && !empty($_REQUEST['uid'])) {
        // Delete account request
        include_once 'pear-database-user.php';
        if (is_array($_REQUEST['uid'])) {
            foreach ($_REQUEST['uid'] as $uid) {
                user::remove((int) $uid);
                echo 'Account request deleted: ';
                echo filter_var($uid, FILTER_SANITIZE_STRING) . '<br />';
            }


        } elseif (user::remove((int) $_REQUEST['uid'])) {
            print "<p>Deleted account request for \"$uid\"...</p>";
        }
    } elseif ($_REQUEST['cmd'] == 'Move'  && !empty($_REQUEST['acreq'])
        && isset($_REQUEST['from_site'])
        && in_array($_REQUEST['from_site'], ['pear', 'pecl'])
    ) {
        include_once 'pear-database-user.php';
        $data = array(
            'handle'    => $_REQUEST['acreq'],
            'from_site' => $_REQUEST['from_site'],
        );

        $res = user::update($data);
        if (DB::isError($res)) {
            echo 'DB error: ' .  $res->getMessage();
        } else {
            $to = strtoupper($_REQUEST['from_site']);
            echo 'User has been moved to ' . $to;
        }
    }
}

// }}}

// {{{ javascript functions

?>
<script type="text/javascript">
<!--

function confirmed_goto(url, message) {
    if (confirm(message)) {
        location = url;
    }
}

function confirmed_submit(button, action, required, errormsg) {
    if (required && required.value == '') {
        alert(errormsg);
        return;
    }
    if (confirm('Are you sure you want to ' + action + '?')) {
        button.form.cmd.value = button.value;
        button.form.submit();
    }
}

function updateRejectReason(selectObj) {
    if (selectObj.selectedIndex != 0) {
        document.forms['account_form'].reason.value = selectObj.options[selectObj.selectedIndex].value;
    }
    selectObj.selectedIndex = 0;
}
// -->
</script>
<?php

// }}}

$self = htmlspecialchars($_SERVER['PHP_SELF']);
$acreq = isset($_GET['acreq']) ? strip_tags(htmlspecialchars($_GET['acreq'])) : '';
do {

    // {{{ "approve account request" form

    if (!empty($acreq)) {
        include_once 'pear-database-user.php';
        $requser = user::info($acreq, null, false);
        if (empty($requser['name']) || $requser['from_site'] == 'pecl') {
            break;
        }
        try {
            $uInfo = @unserialize($requser['userinfo'], ['allowed_classes' => false]);
            if ($uInfo !== false) {
                list($purpose, $moreinfo) = $uInfo;
            }
        } catch (Exception $ex) {
            $purpose = 'n/a';
            $moreinfo = 'n/a';
        }

        $bb = new BorderBox('Account request from ' . $requser['name'] . ' &lt;' . $requser['email'] . '&gt;', "100%", '', 2, true);
        $bb->horizHeadRow("Requested username:", $requser['handle']);
        $bb->horizHeadRow("Realname:", $requser['name']);
        $bb->horizHeadRow("Email address:", '<a href="mailto:' . $requser['email'] . '">' . $requser['email'] . "</a>");
        $bb->horizHeadRow("Purpose of account:", $purpose);
        $bb->horizHeadRow("More information:", $moreinfo);
        $bb->end();

        $i = "      ";
        print "<br />\n";
        print "$i<form action=\"$self\" method=\"POST\">\n";
        print $i . '   <input type="hidden" name="id" value="' . $requser['handle'] . "\" />\n";
        print "$i   <input type=\"hidden\" name=\"acreq\" value=\"$acreq\" />\n";
        print $i . ' <select name="from_site"> ' . "\n";
        print $i . '  <option value="pear">PEAR</option>' . "\n";
        print $i . '  <option value="pecl">PECL</option>' . "\n";
        print $i . ' </select> ' . "\n";
        print "$i   <input type=\"submit\" value=\"Move\" name=\"cmd\" />\n";
        print "$i</form>\n";
        print "<br />\n";
        $bb = new BorderBox('Notes for user ' . $requser['handle']);
        $notes = $dbh->getAssoc(
            "SELECT id,nby,UNIX_TIMESTAMP(ntime) AS ntime,note FROM notes ".
                    "WHERE uid = ? ORDER BY ntime", true,
            array($requser['handle'])
        );

        if (is_array($notes) && sizeof($notes) > 0) {
            print "$i<table cellpadding=\"2\" cellspacing=\"0\" border=\"0\">\n";
            foreach ($notes as $nid => $data) {
                list($nby, $ntime, $note) = $data;
                print "$i <tr>\n";
                print "$i  <td>\n";
                print "$i   <b>$nby " . date('H:i jS F Y', $ntime) . ":</b>";
                if ($nby == $auth_user->handle) {
                    $url = "$self?acreq=$acreq&cmd=Delete+note&id=$nid";
                    $msg = "Are you sure you want to delete this note?";
                    print "[<a href=\"javascript:confirmed_goto('$url', '$msg')\">delete your note</a>]";
                }
                print "<br />\n";
                print "$i   ".htmlspecialchars($note)."\n";
                print "$i  </td>\n";
                print "$i </tr>\n";
                print "$i <tr><td>&nbsp;</td></tr>\n";
            }
            print "$i</table>\n";
        } else {
            print "No notes.";
        }
        print "$i<form action=\"$self\" method=\"POST\">\n";
        print "$i<table cellpadding=\"2\" cellspacing=\"0\" border=\"0\">\n";
        print "$i <tr>\n";
        print "$i  <td>\n";
        print "$i   To add a note, enter it here:<br />\n";
        print "$i    <textarea rows=\"3\" cols=\"55\" name=\"note\"></textarea><br />\n";
        print "$i   <input type=\"submit\" value=\"Add note\" name=\"cmd\" />\n";
        print $i . '   <input type="hidden" name="id" value="' . $requser['handle'] . "\" />\n";
        print "$i   <input type=\"hidden\" name=\"acreq\" value=\"$acreq\" />\n";
        print "$i  </td>\n";
        print "$i </tr>\n";
        print "$i</table>\n";
        print "$i</form>\n";

        $bb->end();
?>

<form action="<?php echo $self; ?>" method="POST" name="account_form">
<input type="hidden" name="cmd" value="" />
<input type="hidden" name="uid" value="<?php echo $requser['handle'] ?>" />
<table cellpadding="3" cellspacing="0" border="0" width="90%">
 <tr>
 <td align="left" colspan="3">
 Select Karma Level:
 </td>
 </tr>
 <tr>
  <td align="left" colspan="3"><input type="radio" value="pear.pepr" name="karma" checked="checked" /> PEAR Proposer</td>
 </tr>
 <tr>
  <td align="left" colspan="3"><input type="radio" value="pear.dev" name="karma" /> PEAR Developer</td>
 </tr>
 <tr>
  <td align="left" colspan="3"><input type="radio" value="pear.voter" name="karma" /> PEAR Voter <strong>only</strong></td>
 </tr>
 <tr>
  <td align="center"><input type="button" value="Open Account" onclick="confirmed_submit(this, 'open this account')" /></td>
  <td align="center"><input type="button" value="Reject Request" onclick="confirmed_submit(this, 'reject this request', this.form.reason, 'You must give a reason for rejecting the request.')" /></td>
  <td align="center"><input type="button" value="Delete Request" onclick="confirmed_submit(this, 'delete this request')" /></td>
 </tr>
 <tr>
  <td colspan="3">
   If dismissing an account request, enter the reason here
   (will be emailed to <?php echo $requser['email'] ?>):<br />
   <textarea rows="3" cols="60" name="reason"></textarea><br />

<?php
$reasons = array("You don't need a PEAR account to use PEAR or PEAR packages.\n\n" .
                 "As part of our ongoing Quality Assurance we would be interested in\n" .
                 "hearing what could be added  on the form to prevent someone making a\n" .
                 "similar mistake.",

                 "Please fill out a bug report at http://" . PEAR_CHANNELNAME . "/bugs/ for all\n" .
                 "bugs or patches.",

                 "Please supply valid credentials, including your full name and a\n" .
                 "descriptive reason for an account."
                 );
?>

    <select onchange="return updateRejectReason(this)">
           <option>Select reason...</option>
<?php
foreach ($reasons as $reason) {
    echo "<option value=\"" . $reason . "\">" . substr($reason, 0, 90) . "</option>\n";
}
?>
   </select>

  </td>
</table>
</form>

<?php
    // }}}
    // {{{ admin menu
    } else {
    ?>
        <script type="text/javascript">
        <!--
            /**
            * This code is *nasty* (nastyCode)
            */

            function highlightAccountRow(spanObj)
            {
                var highlightColor = '#cfffb7';
                var mycolor = spanObj.parentNode.parentNode.childNodes[3].style.backgroundColor;

                if (typeof(arguments[1]) == 'undefined') {
                    if (mycolor.charAt(0) != '#') {
                        mycolor = mycolor.replace(/ /g,'');
                        mycolor = mycolor.toLowerCase();
                        var bits = /^rgb\((\d{1,3}),\s*(\d{1,3}),\s*(\d{1,3})\)$/.exec(mycolor);
                        r = parseInt(bits[1]);
                        g = parseInt(bits[2]);
                        b = parseInt(bits[3]);
                        r = r.toString(16);
                        g = g.toString(16);
                        b = b.toString(16);
                        if (r.length == 1) r = '0' + r;
                        if (g.length == 1) g = '0' + g;
                        if (b.length == 1) b = '0' + b;
                        mycolor = '#'+r+g+b;
                    }
                    if (mycolor != highlightColor) {
                        spanObj.parentNode.parentNode.childNodes[1].childNodes[0].checked = true;
                        action = true;
                    } else {
                        spanObj.parentNode.parentNode.childNodes[1].childNodes[0].checked = false;
                        action = false;
                    }
                } else {
                    action = !spanObj.parentNode.parentNode.childNodes[1].childNodes[0].checked;
                }

                if (document.getElementById) {
                    for (var i=0; i<spanObj.parentNode.parentNode.childNodes.length; i++) {
                        if (!spanObj.parentNode.parentNode.childNodes[i].style) {
                            continue;
                        }
                        if (action) {
                            spanObj.parentNode.parentNode.childNodes[i].style.backgroundColor = highlightColor;
                        } else {
                            spanObj.parentNode.parentNode.childNodes[i].style.backgroundColor = '#ffffff';
                        }
                    }
                }
                return true;
            }

            allSelected = false;

            function toggleSelectAll(linkElement)
            {
                tableBodyElement = linkElement.parentNode.parentNode.parentNode.parentNode;

                for (var i=0; i<tableBodyElement.childNodes.length; i++) {
                    if (tableBodyElement.childNodes[i].childNodes[0].childNodes[0].tagName == 'INPUT') {
                        highlightAccountRow(tableBodyElement.childNodes[i].childNodes[1].childNodes[0], !allSelected);
                    }
                }

                allSelected = !allSelected;
            }

            function setCmdInput(mode)
            {
                switch (mode) {
                    case 'reject':
                        if (document.forms['mass_reject_form'].reason.selectedIndex == 0) {
                            alert('Please select a reason to reject the accounts!');

                        } else if (confirm('Are you sure you want to reject these account requests ?')) {
                            document.forms['mass_reject_form'].cmd.value = 'Reject Request';
                            return true;
                        }
                        break;

                    case 'delete':
                        if (confirm('Are you sure you want to delete these account requests ?')) {
                            document.forms['mass_reject_form'].cmd.value = 'Delete Request';
                            return true;
                        }
                        break;
                }

                return false;
            }
        //-->
        </script>
        <form action="<?php echo $self; ?>" name="mass_reject_form" method="post">
        <input type="hidden" value="" name="cmd"/>
    <?php

        $table = new HTML_Table('style="width: 100%" cellspacing="2"');
        $table->setCaption('Account Requests', 'style="background-color: #CCCCCC;"');
        $requests = $dbh->getAssoc(
            "SELECT u.handle,u.name,n.note,u.userinfo FROM users u ".
                                   "LEFT JOIN notes n ON n.uid = u.handle ".
            "WHERE u.registered = 0"
        );
    if (is_array($requests) && sizeof($requests) > 0) {
        $head = array(
        "<a href=\"#\" onclick=\"toggleSelectAll(this)\">&#x2713;</a>",
        "Name", "Handle", "Account Purpose", "Status", "&nbsp;"
        );
        $table->addRow($head, null, 'th');

        foreach ($requests as $handle => $data) {
            list($name, $note, $userinfo) = $data;

                // Grab userinfo/request purpose
            if (@unserialize($userinfo, ['allowed_classes' => false])) {
                $userinfo = @unserialize($userinfo, ['allowed_classes' => false]);
                $account_purpose = $userinfo[0];
            } else {
                $account_purpose = $userinfo;
            }

            $rejected = (preg_match("/^Account rejected:/", $note));
            if ($rejected) {
                continue;
            }
            $table->addRow(
                array(
                '<input type="checkbox" value="' . $handle . '" name="uid[]" onclick="return highlightAccountRow(this)"/>',
                sprintf('<span style="cursor: hand" onclick="return highlightAccountRow(this)">%s</span>', $name),
                sprintf('<span style="cursor: hand" onclick="return highlightAccountRow(this)">%s</span>', $handle),
                sprintf('<span style="cursor: hand" onclick="return highlightAccountRow(this)">%s</span>', $account_purpose),
                sprintf('<span style="cursor: hand" onclick="return highlightAccountRow(this)">%s</span>', ($rejected ? "rejected" : "<font color=\"#c00000\"><strong>Outstanding</strong></font>")),
                sprintf('<span style="cursor: hand" onclick="return highlightAccountRow(this)">%s</span>', "<a onclick=\"event.cancelBubble = true\" href=\"$self?acreq=$handle\">" . make_image("edit.gif") . "</a>")
                )
            );
        }

    } else {
        print "No account requests.";
    }
        $table->setAllAttributes('style="vertical-align: top;"');
        echo $table->toHTML();

    ?>
        <br />
        <table align="center">
        <tr>
            <td>
                <select name="reason">
                    <option value="">Select rejection reason...</option>
                    <option value="Account not needed">Account not needed</option>
                </select>
            </td>
            <td><input type="submit" value="Reject selected accounts" onclick="return setCmdInput('reject')" /></td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td><input type="submit" value="Delete selected accounts" onclick="return setCmdInput('delete')" /></td>
        </tr>
        </table>

        </form>
<?php
    }

    // }}}

} while (false);

response_footer();
