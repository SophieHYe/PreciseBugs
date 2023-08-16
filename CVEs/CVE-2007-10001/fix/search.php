<?php
if (!defined('WC_BASE')) define('WC_BASE', dirname(__FILE__));
$ref=WC_BASE."/index.php";
if ($ref!=$_SERVER['SCRIPT_FILENAME']){
	header("Location: index.php");
	exit();
}
?>
<!-- #################################### Start search ################################# -->
<tr>
	<td width="10">&nbsp; </td>
	<td valign="top">

<?php
if ($authorized) {
	$cyr_conn = new cyradm;
	$error = $cyr_conn->imap_login();

	if ($error != 0){
		die ("IMAP Error: ".$cyr_conn->geterror());
	}

	if ($_SESSION['admintype'] == 0) {
		$allowed_domains1="('1'='1";
		$allowed_domains3="('1'='1";
	} else {
		$allowed_domains1="(a.domain_name='";
		$allowed_domains3="(virtual.username='";
		foreach($_SESSION['allowed_domains'] as $allowed_domain) {
			$allowed_domains1 .= $allowed_domain."' OR a.domain_name='";
			$allowed_domains3 .= $allowed_domain."' OR virtual.username='";
		}
	}


	#####  Show matching Domains first #######
	$query = "SELECT * FROM domain AS a WHERE domain_name LIKE '%".addslashes($_GET['searchstring'])."%' AND ".$allowed_domains1."') ORDER BY domain_name";
	$result = $handle->query($query);
	$cnt = $result->numRows();

	print "<tr>";
	print "<td width=\"10\">&nbsp; </td>";
	print "<td valign=\"top\"><h3>"._("Total domains matching").": ".$cnt."</h3>";
	print "<table border=0>";
	print "<tbody>";
	print "<tr>";
	print ($_SESSION['admintype']==0)?"<th colspan=\"4\">":"<th colspan=\"2\">";
	print _("action")."</th>";
	print "<th>". _("domainname")."</th>";
	if (!$DOMAIN_AS_PREFIX ) {
	    print "<th>"._("prefix")."</th>";
	}
	print "<th>"._("max Accounts")."</th>";
	print "<th>"._("Domain quota")."</th>";
	print "<th>"._("default quota per user")."</th>";
	print "</tr>";

	for ($c=0;$c<$cnt;$c++) {
		if ($c%2==0){
			$cssrow="row1";
		} else {
			$cssrow="row2";
		}

		$row=$result->fetchRow(DB_FETCHMODE_ASSOC,$c);
		$domain = $row['domain_name'];

		print "<tr class=\"$cssrow\"> \n";
		if ($_SESSION['admintype']==0) {
			print '<td><a href="index.php?action=editdomain&domain='.$domain.'">'. _("Edit Domain")."</a></td>\n";
			print '<td><a href="index.php?action=deletedomain&domain='.$domain.'">'. _("Delete Domain")."</a></td>\n";
		}
		if ($row['transport'] == 'cyrus') {
			print '<td><a href="index.php?action=accounts&domain='.$domain.'">'. _("accounts")."</a></td>\n";
		} else {
			print "<td>"._("accounts")."</td>\n";
		}
		if ($row['transport'] == 'cyrus') {
			print '<td><a href=\"index.php?action=aliases&domain='.$domain.'">'._("Aliases")."</a></td>\n";
		} else {
			print "<td>"._("Aliases")."</td>\n";
		}
		print "<td>";
		print $domain;
		print "</td>\n<td>";
		if (!$DOMAIN_AS_PREFIX) {
			# Print the prefix
			print $row['prefix'];
			print "</td>\n<td align=\"right\">";
		}
		# Print the maxaccount
		print $row['maxaccounts'];
		print "</td>\n<td align=\"right\">";
		if (! $row['domainquota'] == 0) {
			echo $row['domainquota'];
		} else {
			print _("Quota not set");
		}
		print "</td>\n<td align=\"right\">";
		# Print the quota
		print $row['quota'];
		print "</td>\n</tr>\n";
	}
	print "</tbody>";
	print "</table>";

	############ And now show the users matching the search query ###########
	$query = "SELECT DISTINCT a.username, a.domain_name FROM virtual as v, accountuser as a WHERE ((v.username LIKE '%".addslashes($_GET['searchstring'])."%') OR (v.alias LIKE '%".addslashes($_GET['searchstring'])."%')) AND (v.username=a.username) AND ".$allowed_domains1."') ORDER BY username";
	$result = $handle->query($query);
	$total = $result->numRows();

	print "<h3>"._("Total users matching").": ".$total."</h3>";
	if (empty($row_pos)) {
		$row_pos = 0;
	}
        $query = "SELECT DISTINCT a.* FROM virtual as v, accountuser as a WHERE ((v.username LIKE '%".$_GET['searchstring']."%') OR (v.alias LIKE '%".addslashes($_GET['searchstring'])."%')) AND (v.username=a.username) AND ".$allowed_domains1."') ORDER BY username";
	$result = $handle->limitQuery($query,$row_pos,10);
	$cnt = $result->numRows();

	print "<h4>"._("Displaying from position").": ".$row_pos."</h4>";
	if ($cnt!=0) {
		print "<table cellspacing=\"2\" cellpadding=\"0\"><tr>";

		$prev = $row_pos -10;
		$next = $row_pos +10;

		if ($row_pos<10) {
			print "<td class=\"navi\"><a class=\"navilink\" href=\"#\">"._("Previous 10 entries")."</a></td>";
		} else {
			print "<td class=\"navi\"><a class=\"navilink\" href=\"index.php?action=search&row_pos=".$prev."&searchstring=".$searchstring."\">"._("Previous 10 entries")."</a></td>";
		}
		if ($next>$total) {
			print "<td class=\"navi\"><a class=\"navilink\" href=\"#\">"._("Next 10 entries")."</a></td>";
		} else {
			print "<td class=\"navi\"><a class=\"navilink\" href=\"index.php?action=search&row_pos=".$next."&searchstring=".$searchstring."\">"._("Next 10 entries")."</a></td>";
		}
		print "</tr></table><p>";

		print "<table border=\"0\">\n";
		print "<tbody>";
		print "<tr>";
		print "<th colspan=\"5\">"._("action")."</th>";
		print "<th>"._("Email address")."</th>";
		print "<th>"._("Username")."</th>";
		print "<th>"._("Last login")."</th>";
		print "<th>"._("Quota used")."</th>";
		print "<th>"._("services")."</th>";
		print "</tr>";

		for ($c=0;$c<$cnt;$c++) {
			if ($c%2==0){
				$cssrow="row1";
			} else {
				$cssrow="row2";
			}

			$row=$result->fetchRow(DB_FETCHMODE_ASSOC,$c);
			$username=$row['username'];
			$domain=$row['domain_name'];
			$services = array();
			$services['imap'] = $row['imap'];
			$services['pop'] = $row['pop'];
			$services['sieve'] = $row['sieve'];
			$services['smtpauth'] = $row['smtpauth'];
			print "\n<tr class=\"$cssrow\">";
			print "\n<td valign=\"middle\"><a href=\"index.php?action=editaccount&domain=".$domain."&username=".$username."\">"._("Edit email addresses")."</a></td>";
			print "\n<td valign=\"middle\"><a href=\"index.php?action=manageaccount&domain=".$domain."&username=".$username."\">"._("Edit account")."</a></td>";
			print "\n<td valign=\"middle\"><a href=\"index.php?action=forwardaccount&domain=".$domain."&username=".$username."\">". _("Forward")."</a></td>";
			print "\n<td valign=\"middle\"><a href=\"index.php?action=deleteaccount&domain=".$domain."&username=".$username."\">"._("Delete account")."</a></td>";
			print "\n<td valign=\"middle\"><a href=\"index.php?action=catch&domain=".$domain."&username=".$username."\">"._("Set catch all")."</a></td>";
			print "\n<td valign=\"middle\">";
			$query2 = "SELECT alias,status FROM virtual WHERE username='".$username."'";
			$result2 = $handle->query($query2);
			$cnt2 = $result2->numRows();

			for ($c2=0;$c2<$cnt2;$c2++){
				# Print All Emailadresses found for the account
				$row2 = $result2->fetchRow(DB_FETCHMODE_ASSOC, $c2);
				print $row2['alias']."<br>";
			}
			$query3 = "SELECT dest FROM virtual WHERE alias='".$username."' AND username=''";
			$result3 = $handle->query($query3);
			$row = $result3->fetchRow(DB_FETCHMODE_ASSOC, 0);
			if (is_array($row)) {
				print "<br><b>"._("Forwards").":</b><br><br>";
				$forwards_tmp = preg_split('|,\s*|', stripslashes($row['dest']));
				$forwards = array();
				while (list(, $forward) = each($forwards_tmp)){
					if (strtolower($forward) != strtolower($username)){
						$forwards[] = htmlspecialchars(trim($forward));
					} else {
						$forwards[] = "<b>".htmlspecialchars(trim($forward))."</b>";
					}
				}
				echo implode("<br>", $forwards);
			}
			print "</td>\n<td valign=\"middle\">";
			print $username;
			print "</td>\n<td align=\"center\" valign=\"middle\">";
			$query3 = "SELECT * FROM log WHERE user='".$username."' ORDER BY time DESC";
			$result3 = $handle->query($query3);
			if (! DB::isError($result3)){
				$row3 = $result3->fetchRow(DB_FETCHMODE_ASSOC, 0);
				$lastlogin = $row3['time'];
			} else {
				$lastlogin = '';
			}
			if ($lastlogin == ''){
				$lastlogin=_("n/a");
			}
			print $lastlogin;
			print "</td>\n<td valign=\"middle\">";
			if ($DOMAIN_AS_PREFIX){
	    			$quota = $cyr_conn->getquota("user/" . $username);
			} else {
	    			$quota = $cyr_conn->getquota("user." . $username);
			}

			if ($quota['used'] != "NOT-SET"){
				$q_used  = $quota['used'];
				$q_total = $quota['qmax'];
				if (!$q_total == 0) {
					$q_percent = 100*$q_used/$q_total;
					if ($q_percent >= $_SESSION['warnlevel']){
						printf ("<font color=red>");
					}
					printf ("%d KBytes %s %d KBytes (%.2f%%)",
						$quota['used']/1024, _("out of"),
						$quota['qmax']/1024, $q_percent);
					if ($q_percent >= $_SESSION['warnlevel']){
						printf ("</font>");
					}
				} else {
					print _("Unable to retrieve quota");
				}
			} else { 
				print _("Quota not set");
			}  
			print "&nbsp;</td>\n";
			print '<td valign="middle">';
			print '<table border=0 align="center">';
			if($services['imap']==1){
				print "<tr><td>imap</td><td><img src=\"images/checked.png\" alt=\"yes\" border=0></td></tr>";
			}
			else {
				print "<tr><td>imap</td><td><img src=\"images/false.png\" alt=\"no\" border=0></td></tr>";
			}
			if($services['pop']==1){
				print "<tr><td>pop</td><td><img src=\"images/checked.png\" alt=\"yes\" border=0></td></tr>";
			}
			else{
				print "<tr><td>pop</td><td><img src=\"images/false.png\" alt=\"no\" border=0></td></tr>";
			}
			if($services['sieve']==1){
				print "<tr><td>sieve</td><td><img src=\"images/checked.png\" alt=\"yes\" border=0></td></tr>";
			}
			else{
				print "<tr><td>sieve</td><td><img src=\"images/false.png\" alt=\"no\" border=0></td></tr>";
			}
			if($services['smtpauth']==1){
				print "<tr><td>smtpauth</td><td><img src=\"images/checked.png\" alt=\"yes\" border=0></td></tr>";
			}
			else{
				print "<tr><td>smtpauth</td><td><img src=\"images/false.png\" alt=\"no\" border=0></td></tr>";
			}
			if($row2['status']==1){
				print "<tr><td>smtp</td><td><img src=\"images/checked.png\" alt=\"yes\" border=0></td></tr>";
			}
			else{
				print "<tr><td>smtp</td><td><img src=\"images/false.png\" alt=\"no\" border=0></td></tr>";
			}
			print "</table>\n</td>\n";
			print "</tr>\n";
		}
		print "\n</tbody>\n";
		print "</table>\n";
	}
	else{
		print "\n"._("No accounts found")."\n<p>";
	}

	################ And now show the matching aliases #######################
	$query3 = "SELECT DISTINCT alias, username FROM virtual WHERE (((dest LIKE '%".$_GET['searchstring']."%') OR (alias LIKE '%".$_GET['searchstring']."%')) AND (dest <> username) AND (username<>'')) AND ".$allowed_domains3."') ORDER BY username";	
	$result3 = $handle->query($query3);
	$total = $result3->numRows();
	print "<h3>"._("Total aliases matching").": ".$total."</h3>";
	if ($total == 0) {
		print _("No aliases found");
	} else {
?>
        <table border="0">
                <tbody>
                <tr>
                        <th colspan="2"><?php print _("action");?></th>
                        <th><?php print _("Email address"); ?></th>
                        <th><?php print _("Destination"); ?></th>
                </tr>
<?php 
		for ($c = 0; $c < $total; $c++){
			if ($c%2==0){
				$cssrow="row1";
			} else {
				$cssrow="row2";
			}
			$row = $result3->fetchRow( DB_FETCHMODE_ASSOC, $c);
			$alias = $row['alias'];
			$domain = $row['username'];
?>
			<tr class="<?php echo $cssrow; ?>">
                        <td><a href="index.php?action=editalias&alias=<?php echo $alias;?>&domain=<?php echo $domain;?>"><?php print _("Edit Alias"); ?></a></td>
                        <td><a href="index.php?action=deletealias&alias=<?php echo $alias;?>&domain=<?php echo $domain;?>"><?php print _("Delete Alias"); ?></a></td>
                        <td><?php echo $alias; ?></td>
                        <td>
<?php
			$query4 = "SELECT dest FROM virtual WHERE alias='".$alias."' AND username='".$domain."'";
			$result4 = $handle->limitQuery($query4, 0, 3);
			$num_dest = $result4->numRows ($result4);
			for ($d=0; $d<$num_dest; $d++) {
				$row2 = $result4->fetchRow (DB_FETCHMODE_ASSOC, $d);
				if ($d!= 0) {
					echo ", ";
				}
				echo $row2['dest'];
			}
			$query5 = "SELECT COUNT(dest) FROM virtual WHERE alias='".$alias."' AND username='".$domain."'";
			$num_dests = $handle->getOne($query5);
			if ($num_dests>3) {
				print ", ... ";
			}
?>
			</td></tr>
<?php
		}
?>
	</table>
	<br>
<?php
	}
} else {
?>
	<h3>
		<?php print $err_msg;?>
	</h3>
	<a href="index.php?action=browse"><?php print _("Back");?></a>
<?php
}
?>
	</td>
</tr>
<!-- ##################################### End search.php #################################### -->
