<?php
require_once ('Plans.php');
new SessionBroker();
require ('functions-main.php');
require ("syntax-classes.php");
$idcookie = User::id();
$dbh = db_connect();
$page = new PlansPage('Plan', 'readplan', PLANSVNAME, 'read.php');
$searchnum = (isset($_GET['searchnum']) ? $_GET['searchnum'] : false);
$searchname = (isset($_GET['searchname']) ? $_GET['searchname'] : false);
if (User::logged_in()) {
    populate_page($page, $dbh, $idcookie);
} else
//begin guest user display
{
    populate_guest_page($page);
}
if (!$searchnum) //if no search number given
{
    if (isvaliduser($dbh, $searchname)) //if valid username, change to num
    {
        $searchnum = get_item($mydbh, "userid", "accounts", "username", $searchname);
    } else
    //if is not a valid username
    {
        if ($searchname) //if a searchname has been given
        {
            $searchname = htmlentities($searchname);
            if ($idcookie) {
                //if a searchname has been given, but there is no user with that exact name, search the usernames to see which if any users have that string in their username
                $partial_list = partial_search($dbh, "userid,username", "accounts", "username", $searchname, "username");
                $part_count = count($partial_list);
                if ($part_count == 0) //if no users have that string in username, tell user
                {
                    $nouser = new AlertText("User <b>$searchname</b> does not exist and there are no names with the term in them.", 'No such user');
                    $page->append($nouser);
                } else
                //but if there are usernames with string in them, display them
                {
                    $nouser = new AlertText("User <b>$searchname</b> does not exist.<br>However there are <b>$part_count</b> names with $searchname in them.<br>These names are:", 'No such user');;
                    $page->append($nouser);
                    $namelist = new WidgetList('partial_name_matches', true);
                    $page->append($namelist);
                    $o = 0;
                    while ($partial_list[$o][0]) //loop through displaying the usernames as links
                    {
                        $name = new PlanLink($partial_list[$o][1]);
                        $namelist->append($name);
                        $o++;
                    } //while ($partial_list [$o][0])
                    
                } //if partial names
                $searchlink = new Hyperlink('search_for_term', true, "search.php?mysearch=$searchname", "Search Plans for \"$searchname\"");
                $page->append($searchlink);
            } else {
                $err = new AlertText('There is either no plan with that name or it is not viewable to guests.' . ' Please <a href="index.php">Log in</a> or <a href="register.php">Register</a>.', 'Plan not available');
                $page->append($err);
            }
        } else {
            $err = new AlertText('Must enter a name', 'Input needed');
            $page->append($err);
        }
        interface_disp_page($page);
        db_disconnect($dbh);
        exit();
    } //if not valid username
    
} //$if (!$searchnum)
//begin displaying if there is a user with name or number given
// Create the new page
if (User::logged_in()) {
    //TODO add searchname instead?
    $page->url = add_param($page->url, 'searchnum', $searchnum);
    $addtolist = (isset($_POST['addtolist']) ? (bool)$_POST['addtolist'] : false);
    // if person is manipulating which tier this plan is on their autoread list
    if (isset($_POST['block_user'])) {
        if ($_POST['block_user'] == 1) {
            $user = User::get();
            if ($user->webview == 1) {
                $warning = new AlertText("Warning! Your plan is set to be viewable by guests. This will allow blocked users to read your plan
simply by logging out. If you would like to change this setting, please visit
<a href=\"/webview.php\">the guest settings page</a>.");
                $page->append($warning);
            }
            Block::addBlock($idcookie, $searchnum);
            $msg = new InfoText("<p>You have blocked this user. Blocking a user is one-directional. Selecting \"Block\" renders the contents of your plan unavailable to this user. Neither will see any [planlove] by the other, and any updates either make will not show up on each other’s planwatch.</p>

<p>If this block was made in error, please use the option at the bottom of the page to un-do.</p>");
        } else {
            Block::removeBlock($idcookie, $searchnum);
            $msg = new InfoText("User " . $planinfo[0][0] . " has been unblocked.");
        }
        $page->append($msg);
    } else if ($addtolist) {
        $privlevel = (isset($_POST['privlevel']) ? (int)$_POST['privlevel'] : 0);
        if ($privlevel == 0) {
            mysql_query("DELETE FROM autofinger WHERE owner = '$idcookie' and interest = '$searchnum'");
            $yay = new InfoText("User " . $planinfo[0][0] . " removed from your autoread list.");
        } else if ($privlevel > 0 && $privlevel <= 3) {
            mysql_query("INSERT INTO autofinger (owner, interest, priority) VALUES ('$idcookie', '$searchnum', '$privlevel') ON DUPLICATE KEY UPDATE priority=$privlevel");
            $yay = new InfoText("User " . $planinfo[0][0] . " is now on your autoread list with priority level of " . $privlevel . ".");
        }
        $page->append($yay);
    }
    // Update autofinger as read.
    $my_result = mysql_query("Select priority From autofinger where owner = '$idcookie' and interest = '$searchnum'");
    $onlist = mysql_fetch_array($my_result);
    if ($onlist) {
        update_read($dbh, $idcookie, $searchnum); //mark as having been read
        $myonlist = $onlist[0];
        // Repopulate the page to get updated autoread
        populate_page($page, $dbh, $idcookie);
    } else {
        $myonlist = "X"; //if not on autoread list, show is not on priority list
        
    }
}
//TODO should this go inside if(!$auth) ?
$guest_auth = false;
if ($guest_pass = $_GET['guest-pass']) {
    $real_pass = get_item($dbh, "guest_password", "accounts", "userid", $searchnum);
    //error_log("JLW real pass is $real_pass");
    if ($real_pass == '') {
        $guest_auth = false;
    } else if ($real_pass == $guest_pass) {
        $guest_auth = true;
    } else {
        $guest_auth = false;
    }
}
// Get the user and plan information
$q = Doctrine_Query::create()->from('Accounts a')->leftJoin('a.Plan p')->where('a.userid = ?', $searchnum)->orderBy('p.id ASC');
$user = $q->fetchOne();
if (!$user) {
    $page->append(new AlertText("Could not retrieve plan.", 'DB Error', true));
} else if (!$idcookie && $user->webview != 1 && !$guest_auth) {
    $page->append(new AlertText("There is either no plan with that name or it is not viewable to guests.", 'Plan not found', false));
} else {
    // Perform these queries once for the page
    $is_blocking_this_user = Block::isBlocking($idcookie, $searchnum);
    $this_user_is_blocking_you = Block::isBlocking($searchnum, $idcookie);

    if ($this_user_is_blocking_you && !User::is_admin()) {
        $blocked_msg = new AlertText("[$user->username] has enabled the block feature. This plan is not available.");
        $page->append($blocked_msg);
    } else {
        // If we're redirecting from edit.php, assure the user that their change was applied
        if ($_GET['edit_submit'] == 1) {
            $changed_msg = new InfoText('Plan changed successfully.');
            $page->append($changed_msg);
        }
        if ($is_blocking_this_user) {
            $blocked_msg = new InfoText("You have enabled the blocking feature for this user. This means the contents of your plan are unavailable to this user. Searching for your plan, quicklove, and planwatch are also disabled for this user. To unblock, use the button at the bottom of the page.");
            $page->append($blocked_msg);
        }
        // Get the plan text
        $plantext = $user->Plan->plan;
        // Jumble it if requested
        if ($_GET['jumbled'] == 'yes' || ($_COOKIE['jumbled'] == 'yes' && $_GET['jumbled'] != 'no')) {
            $plantext = jumble($plantext);
        }
        $plantext = new PlanText($plantext, false);
        $thisplan = new PlanContent($user->username, $user->pseudo, strtotime($user->login), strtotime($user->changed), $plantext);
        $page->append($thisplan);
    }
    $page->title = '[' . $user->username . "]'s Plan";
    if (User::logged_in()) //if is a valid user, give them the option of putting the plan on their autoread list, or taking it off, and also if plan is on their autoread list, mark as read and mark time
    {
        if (!($searchnum == $idcookie)) //if person is not looking at their own plan, give them a small form to set the priority of the persons plan on their autoread list
        {
            if (!$this_user_is_blocking_you && !$is_blocking_this_user) {
                $addform = new Form('autoreadadd', 'Set Priority');
                $thisplan->addform = $addform;
                $addform->action = "read.php?searchnum=$searchnum";
                $addform->method = 'POST';
                $item = new HiddenInput('addtolist', 1);
                $addform->append($item);
                $levels = new FormItemSet('readadd_levels', true);
                $addform->append($levels);
                for ($j = 0;$j < 4;$j++) {
                    $item = new RadioInput('privlevel', $j);
                    if ($j == 0) $item->description = 'X';
                    else $item->description = "$j";
                    $item->checked = ($myonlist == $item->description);
                    $levels->append($item);
                }
                $item = new SubmitInput('Set Priority');
                $addform->append($item);
            }

            $blocking = new Form('block', 'User blocking options');
            $page->append($blocking);
            if ($is_blocking_this_user) {
                $item = new HiddenInput('block_user', 0);
                $blocking->append($item);
                $item = new SubmitInput('Unblock this user');
                $blocking->append($item);
                $explanation = new InfoText("You have blocked [$user->username]. Blocking a user is one-directional. Searching for your plan, quicklove, and planwatch are also disabled for this user. <a href=\"/blocking-about.php\">See the FAQ for more information</a>.
                    <br /><br />If you wish to remove the block, select \"Unblock this user\".");
                $page->append($explanation);
            } else {
                $item = new HiddenInput('block_user', 1);
                $blocking->append($item);
                $item = new SubmitInput('Block this user');
                $blocking->append($item);
                $explanation = new InfoText('Blocking a user is one-directional. Selecting "Block this user" renders the contents of your plan unavailable to this user. Searching for your plan, quicklove, and planwatch will also be disabled for this user. <a href="/blocking-about.php">See the FAQ for more information</a>.');
                $page->append($explanation);
            }
        }
    }
}
interface_disp_page($page);
db_disconnect($dbh);
?>
