<?php
/*
Plugin Name: IP Blacklist Cloud
Plugin URI: http://wordpress.org/extend/plugins/ip-blacklist-cloud/
Description: Blacklist IP Addresses from visiting your WordPress website and block usernames from spamming. View details of all failed login attempts.
Version: 3.42
Author: Adeel Ahmed
Author URI: http://www.ip-finder.me/
*/




if ( !defined('ABSPATH') )
	die ( 'No direct script access allowed' );


	global $check_all_url_open;
	$check_all_url_open = ini_get('allow_url_fopen');

	$this_plugin_url=plugins_url( '/', __FILE__ );

	global $this_plugin_url;


function ip_added()
{
	global $wpdb,$found,$IP_global, $IP_error;

	$IP=sanitize_text_field($_GET['blacklist']);

	//---check if IP is valid IPV4 or not before checking already added---
	if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	{
  
		$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));

		$found=false;

		if(!$IP_in_DP)
		{

			//---check if IP is valid IPV4 or not before adding to database---
			if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
			{

				$table=$wpdb->prefix."IPBLC_blacklist";
				$time=time();

				$wpdb->query($wpdb->prepare("INSERT INTO $table (IP,timestamp,visits,lastvisit) VALUES(%s,%d,%d,%d)",
					array($IP,$time,0,0)));

				//---post blacklist data to ip-finder.me
				post_blacklist_add($IP);
			}
			else
			{
				$found=true;
				$IP_error=true;
			}	
		}
		else
		{
			$found=true;
		}

		$IP_global=$IP;
	}
	else
	{
		$found=true;
		$IP_error=true;	
	}

}


function user_added()
{
	global $wpdb,$found,$USER_global, $USER_error;

	$USER=$_POST['blacklistuser'];
	$USER=sanitize_text_field($USER);

	$USER_in_DB=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_usernames WHERE USERNAME=%s",$USER));

	$found=false;

	if(!$USER_in_DB)
	{

		if($USER)
		{
			$table=$wpdb->prefix."IPBLC_usernames";
			$time=time();

			$wpdb->query($wpdb->prepare("INSERT INTO $table (USERNAME,timestamp,visits,lastvisit) VALUES(%s,%d,%d,%d)",
			array($USER,$time,0,0)));

			//---post blacklist data to ip-finder.me
			$USER2=urlencode($USER);
			post_blacklist_add_user($USER2);
		}
		else
		{
			$found=true;
			$USER_error=true;
		}	
	}
	else
	{
		$found=true;
	}

	$USER_global=$USER;
}



function ip_added_message()
{

	global $found,$IP_global,$IP_error;
	$IP=$IP_global;


	if(!$found)
	{

?>
<style>
#IPBLC_message_blacklist
{

	position: fixed;
	bottom: 8px;
	float: right;
	right: 8px;
	border:1px solid #99FF99;
	background-color: #DDFFDD;
	font-size: 20px;
	//font-weight: bold;
	padding: 12px;
	color: #000000;
}
</style>

<?php

	echo "<div id=\"IPBLC_message_blacklist\">$IP added to blacklist. Please comment on <a href=\"http://www.ip-finder.me/$IP/\" target=\"_blank\">IP-FINDER.ME</a></div>";

	}
	else
	{
?>

<style>
#IPBLC_message_blacklist
{
	position: fixed;
	bottom: 8px;
	float: right;
	right: 8px;
	border:1px solid #FF9999;
	background-color: #FFDDDD;
	font-size: 20px;
	//font-weight: bold;
	padding: 12px;
	color: #000000;
}
</style>

<?php

		if(!$IP_error)
		{
			echo "<div id=\"IPBLC_message_blacklist\">$IP already added to blacklist.</div>";
		}
		else
		{
			echo "<div id=\"IPBLC_message_blacklist\">$IP is not valid IP Address.</div>";
		}

	}

	echo "<script>jQuery('#IPBLC_message_blacklist').delay(8500).fadeOut(6000); jQuery('#IPBLC_message_blacklist').click(function(){ jQuery(this).hide(); });</script>";

}


function user_added_message()
{

	global $found,$USER_global,$USER_error;
	$USER=$USER_global;


	$USER=str_replace('\"','"',$USER);
	$USER=str_replace("\'","'",$USER);
	$USER=str_replace("\\\'","'",$USER);

	if(!$found)
	{

?>
<style>
#IPBLC_message_blacklist
{

	position: fixed;
	bottom: 8px;
	float: right;
	right: 8px;
	border:1px solid #99FF99;
	background-color: #DDFFDD;
	font-size: 20px;
	//font-weight: bold;
	padding: 12px;
	color: #000000;
}
</style>

<?php

	echo "<div id=\"IPBLC_message_blacklist\">$USER added to blacklist.</a></div>";

	}
	else
	{
?>

<style>
#IPBLC_message_blacklist
{
	position: fixed;
	bottom: 8px;
	float: right;
	right: 8px;
	border:1px solid #FF9999;
	background-color: #FFDDDD;
	font-size: 20px;
	//font-weight: bold;
	padding: 12px;
	color: #000000;
}
</style>

<?php

		if(!$USER_error)
		{
			echo "<div id=\"IPBLC_message_blacklist\">$USER already added to blacklist.</div>";
		}

	}

	echo "<script>jQuery('#IPBLC_message_blacklist').delay(8500).fadeOut(6000); jQuery('#IPBLC_message_blacklist').click(function(){ jQuery(this).hide(); });</script>";

}


function blacklist_failed_login()
{

	include "blacklist-failed-login.php";
}

function page_IPBLC_actions() 
{  


	global $this_plugin_url;

	global $wpdb;
	$newFailed=0;
	$table1=$wpdb->prefix."IPBLC_login_failed";
	$table2= $wpdb->prefix."IPBLC_blacklist";
	$countMain="";
	$countOld="";
	$time=time();
	$days_90=$time-(90*24*60*60);


	$rr=$wpdb->get_results("SELECT * FROM $table1 t1 WHERE NOT EXISTS (SELECT 1 FROM $table2 t2 WHERE t1.IP = t2.IP)");
	$old=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table2 WHERE timestamp<=%d && lastvisit<=%d",array($days_90,$days_90)));

	//print_r($rr);

	if($rr)
	{
		$newFailed=count($rr);
		$countMain='<span class="awaiting-mod count-'.$newFailed.'"><span class="pending-count">'.$newFailed.'</span></span>';
	}

	if($newFailed==0)
	{
		$newFailed="";
		$countMain="";
	}

	if($old)
	{
		$countOld='<span class="awaiting-mod count-'.count($old).'"><span class="pending-count">'.count($old).'</span></span>';

	}

	if($newFailed>0)
	{
	add_menu_page( "IP Blacklist", "IP Blacklist $countMain", "manage_options", "wp-IPBLC","",$this_plugin_url."icon.png");
	}
	else
	{
	add_menu_page( "IP Blacklist", "IP Blacklist", "manage_options", "wp-IPBLC","",$this_plugin_url."icon.png");
	}
	add_submenu_page( "wp-IPBLC", "Settings", "Settings", "manage_options", "wp-IPBLC", "blacklist_settings" );

	add_submenu_page( "wp-IPBLC", "IP Blacklist", "IP Blacklist", "manage_options", "wp-IPBLC-list", "blacklist_tool" );
	add_submenu_page( "wp-IPBLC", "Add IP to Blacklist", "Add IP to Blacklist", "manage_options", "wp-IPBLC-Add", "blacklist_add" );

	add_submenu_page( "wp-IPBLC", "Username Blacklist", "User Blacklist", "manage_options", "wp-IPBLC-list-user", "blacklist_tool_user" );
	add_submenu_page( "wp-IPBLC", "Add username to Blacklist", "Add User to Blacklist", "manage_options", "wp-IPBLC-Add-User", "blacklist_add_user" );

	add_submenu_page( "wp-IPBLC", "Auto Block", "Auto Block", "manage_options", "wp-IPBLC-auto-block", "auto_block" );
	if($newFailed>0)
	{
	add_submenu_page( "wp-IPBLC", "Failed Login", "<font color=red>Failed Login ($newFailed)</font>", "manage_options", "wp-IPBLC-failed-login", "blacklist_failed_login" );
	}
	else
	{
	add_submenu_page( "wp-IPBLC", "Failed Login", "Failed Login", "manage_options", "wp-IPBLC-failed-login", "blacklist_failed_login" );
	}
	//add_submenu_page( "wp-IPBLC", "Blacklist Statistics", "Blacklist Statistics", "manage_options", "wp-IPBLC-stats", "blacklist_stats" );
	add_submenu_page( "wp-IPBLC", "Whitelist", "Whitelist", "manage_options", "wp-IPBLC-whitelist", "blacklist_whitelist" );

	add_submenu_page( "wp-IPBLC", "EXTRA SECURITY", "EXTRA SECURITY", "manage_options", "wp-IPBLC-extra", "blacklist_extra" );
	add_submenu_page( "wp-IPBLC", "Fixes", "Fixes", "manage_options", "wp-IPBLC-fixes", "blacklist_fixes" );

	if($countOld)
	{
	add_submenu_page( "wp-IPBLC", "Old IPs", "Old IPs $countOld", "manage_options", "wp-IPBLC-old-ip", "blacklist_old_ip" );

	}
	else
	{
	add_submenu_page( "wp-IPBLC", "Old IPs", "Old IPs", "manage_options","wp-IPBLC-old-ip", "blacklist_old_ip" );
	}


	add_submenu_page( "wp-IPBLC", "Support", "Support", "manage_options", "wp-IPBLC-support", "blacklist_support" );



	$IPBLC_cloud_email=get_option('IPBLC_cloud_email');
	$IPBLC_cloud_key=get_option('IPBLC_cloud_key');

	if($IPBLC_cloud_email && $IPBLC_cloud_key)
	{
		//---post blacklist data to ip-finder.me

		$data = array('test' => '1');

		$contextData = array ( 
                'method' => 'POST',
		'content' => http_build_query($data),
		'header' => "Connection: close\r\n". 
                        "Content-Type: application/x-www-form-urlencoded\r\n".

		"Referer: ".site_url()."\r\n");

		$context = stream_context_create (array ( 'http' => $contextData ));


		$email2=urlencode($IPBLC_cloud_email);

$link="http://www.ip-finder.me/wp-content/themes/ipfinder/cloudaccount_status.php?email=$email2&website=".urlencode(site_url())."&website_name=".urlencode(get_bloginfo('name'))."&cloudkey=".$IPBLC_cloud_key;

		$post_to_cloud =  file_get_contents (
		$link,
		false,
		$context);
		//echo $post_to_cloud;


		if($post_to_cloud!="-1" && $post_to_cloud!="-2")
		{
	//add_submenu_page( "wp-IPBLC", "Cloud Account Service", "Cloud Account Service", "manage_options", "wp-IPBLC-premium", "blacklist_premium" );

		}
	}
} 

function blacklist_premium()
{
	//include "blacklist-premium.php";
}

function blacklist_support()
{
	include "blacklist-support.php";
}

function blacklist_extra()
{
	include "blacklist-extra.php";
}

function blacklist_fixes()
{
	include "blacklist-fixes.php";
}


function blacklist_tool()
{
	include "blacklist-list.php";
}

function blacklist_old_ip()
{
	include "blacklist-old-list.php";
}

function blacklist_tool_user()
{
	include "blacklist-list-user.php";
}


function blacklist_settings()
{

	include "blacklist-settings.php";

}
function auto_block()
{
	include "auto-block.php";
}


function blacklist_add()
{
	include "blacklist-add.php";
}
function blacklist_add_user()
{
	include "blacklist-add-user.php";
}

function blacklist_whitelist()
{
	include "whitelist.php";
}

function blacklist_stats()
{
	//---post data to ip-finder.me


		$data = array('test' => '1');

		$contextData = array ( 
                'method' => 'POST',
		'content' => http_build_query($data),
		'header' => "Connection: close\r\n". 
                        "Content-Type: application/x-www-form-urlencoded\r\n".

		"Referer: ".site_url()."\r\n");

	$context = stream_context_create (array ( 'http' => $contextData ));

 	$link="http://www.ip-finder.me/analysis";
	$analysis =  file_get_contents (
	$link,
	false,
	$context);

	echo $analysis;
}





function IPBLC_IP_column( $columns )
{

	$columns['IPBLC_IP'] = __( 'Details' );
	$columns['IPBLC_IP_status'] = __( 'Status' );
	$columns['IPBLC_IP_spam'] = __( 'Spam Percentage' );
	return $columns;
}

function IPBLC_IP_value( $column, $comment_ID )
{

	global $wpdb;

	if ( 'IPBLC_IP' == $column )
	{

		$IP=get_comment_author_IP($comment_ID);
		$authorName=get_comment_author($comment_ID);

		echo '<a href="http://www.ip-finder.me/'.$IP.'/" target="_blank" title="IP Details on IP-Finder.me">'."$IP</a>";

		echo '<div class="row-actions">
	<span class="edit">
		<a href="http://www.ip-finder.me/'.$IP.'/" target="_blank" title="IP Details on IP-Finder.me">IP Details</a>
	</span> | 
	<span class="edit">';

	$my_IP=$_SERVER['REMOTE_ADDR'];

		if($my_IP!=$IP)
		{
		echo '<a href="javascript: blacklist_IP(\''.$IP.'\','.$comment_ID.');" title="Blacklist IP">Blacklist IP</a>';
		}
		else
		{
				echo "<b style=\"color:#000099\"> YOUR IP</b>";
		}
		echo'
	</span> | 

	<span class="edit">';


		if($my_IP!=$IP)
		{
		
		echo '<a href="javascript: blacklist_USER(\''.urlencode($authorName).'\','.$comment_ID.');" title="Blacklist Username">Blacklist Username</a>';
		}
		

		echo '
	</span>

		</div>
	'; //---echo end----

	}

	if ( 'IPBLC_IP_status' == $column )
	{

		$IP=get_comment_author_IP($comment_ID);
		$authorName=get_comment_author($comment_ID);


		$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));

		if($IP_in_DP)
		{
			echo "IP: <span id=\"IPBlack"."$comment_ID\"><b style=\"color:#FF0000\"> Blacklisted</b></span><BR>";
		}
		else
		{
			echo "IP: <span id=\"IPBlack"."$comment_ID\"><b style=\"color:#009900\"> Neutral</b></span><BR>";
		}

/*
			$authorName=str_replace("\'","'",$authorName);

			$authorName=str_replace("\\\'","'",$authorName);
			$authorName=str_replace("\\\"",'\"',$authorName);
			$authorName=str_replace("\\\"",'"',$authorName);
			$authorName2=str_replace("\"","&quot;",$authorName);

*/
			$authorName2=sanitize_text_field($authorName2);

		$USERNAME_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_usernames WHERE USERNAME=%s",$authorName2));

		if($USERNAME_in_DP)
		{
			echo "Username: <span id=\"UserBlack"."$comment_ID\"><b style=\"color:#FF0000\"> Blacklisted</b></span>";
		}
		else
		{
			echo "Username: <span id=\"UserBlack"."$comment_ID\"><b style=\"color:#009900\"> Neutral</b></span>";
		}

	}

	if ( 'IPBLC_IP_spam' == $column )
	{

			echo "<div id=\"IPSpam-$comment_ID\" class=\"IPSpam\">N/A</div>";
			echo "<a href=\"#\" id=\"IPSpamAction-$comment_ID\" class=\"IPSpamAction\" name=\"$comment_ID\">Calculate</a>";
	}

}

function create_sql()
{

	global $wpdb;

	if(!($wpdb->query("SELECT * FROM ".$wpdb->prefix."IPBLC_login_failed"))) 
	{

		$sql = "CREATE TABLE ".$wpdb->prefix."IPBLC_login_failed (
		id INT(60) UNSIGNED NOT NULL AUTO_INCREMENT,
		IP VARCHAR(25) NOT NULL, 
		useragent VARCHAR( 225 ) NOT NULL, 
		variables TEXT NOT NULL, 
		timestamp INT(30) NOT NULL,
		UNIQUE KEY id (id)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}




	if(!($wpdb->query("SELECT * FROM ".$wpdb->prefix."IPBLC_blacklist"))) 
	{

		$sql = "CREATE TABLE ".$wpdb->prefix."IPBLC_blacklist (
		id INT(60) UNSIGNED NOT NULL AUTO_INCREMENT,
		IP VARCHAR(25) NOT NULL, 
		timestamp INT( 30 ) NOT NULL,
		visits INT(50) NOT NULL, 
		lastvisit INT(50) NOT NULL, 
		UNIQUE KEY id (id)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	if(!($wpdb->query("SELECT * FROM ".$wpdb->prefix."IPBLC_usernames"))) 
	{

		$sql = "CREATE TABLE ".$wpdb->prefix."IPBLC_usernames (
		id INT(60) UNSIGNED NOT NULL AUTO_INCREMENT,
		USERNAME TEXT NOT NULL, 
		timestamp INT(30) NOT NULL,
		visits INT(50) NOT NULL,  
		lastvisit INT(50) NOT NULL,  
		UNIQUE KEY id (id)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}


	if(isset($_GET['blacklist']))
	{


		$referer=sanitize_text_field($_SERVER['HTTP_REFERER']);
		if(strpos($referer,"edit-comments.php")>0)
		{
			ip_added();
		}
	}



	if(isset($_GET['blacklistuser']))
	{

		//print_r($_SERVER);

		$referer=sanitize_text_field($_SERVER['HTTP_REFERER']);
		if(strpos($referer,"edit-comments.php")>0)
		{
			user_added();
		}
	}


	$IPBLC_auto_comments=get_option('IPBLC_auto_comments');
	if(!$IPBLC_auto_comments)
	{
		update_option('IPBLC_auto_comments','2');
		$IPBLC_auto_comments=get_option('IPBLC_auto_comments');
	}


	$IPBLC_protected=get_option('IPBLC_protected');
	if(!$IPBLC_protected)
	{
		update_option('IPBLC_protected','2');
		$IPBLC_protected=get_option('IPBLC_protected');
	}



}





function IPBLC_blockip()
{

	global $wpdb;

	//---check last update with ip-finder.me site--
	$time=time();
	$lastU=get_option('IPBLC_last_update');

	$diff=$time-$lastU;

	//---send website link to ip-finder.me after every 2 days. (verification of data)
	if($diff>172800)
	{
		update_option('IPBLC_last_update',$time);

		$data = array('test' => '1');


		$contextData = array ( 
			'method' => 'POST',
			'content' => http_build_query($data),
			'header' => "Connection: close\r\n". 
			"Content-Type: application/x-www-form-urlencoded\r\n".
			"Referer: ".site_url()."\r\n");

			$context = stream_context_create(array( 'http' => $contextData ));
$link="http://www.ip-finder.me/lastUpdate.php?website=".urlencode(site_url());
					$post_to_cloud =  file_get_contents (
			                  $link,  // page url
			                  false,
			                  $context);
	}
	//--- END -- check last update with ip-finder.me site--

	$IP="";

	if(isset($_SERVER['REMOTE_ADDR']))
	{
		$IP=sanitize_text_field($_SERVER['REMOTE_ADDR']);
	}

	if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	{

		$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));
		if($IP_in_DP)
		{
			$visits=$wpdb->get_var($wpdb->prepare("SELECT visits FROM ".$wpdb->prefix."IPBLC_blacklist WHERE id=%d",$IP_in_DP));
			$visits=$visits+1;

			$t=time();

			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."IPBLC_blacklist SET `visits`=%d WHERE id=%d",array($visits,$IP_in_DP)));
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."IPBLC_blacklist SET `lastvisit`=%d WHERE id=%d",array($t,$IP_in_DP)));
		

			//show 404 error
			header("Status: 404 Not Found");
			header("HTTP/1.0 404 Not Found");

	?>



<head><title><?php echo get_bloginfo('name'); ?></title></head>
<style>
#IPBLC_message_blacklist
{
	border:1px solid #FF9999;
	background-color: #FFDDDD;
	font-size: 20px;
	//font-weight: bold;
	padding: 12px;
	color: #000000;
	margin-top: 50px;
}

</style>
<center>
<div id="IPBLC_message_blacklist">Your IP <?php echo $IP; ?> has been blacklisted!</div><BR>
</center>
	<?php
		exit();
		}

	}

	$author="";
	if(isset($_POST['author']))
	{
		$author=$_POST['author'];
		$USER=sanitize_text_field($author);


		$USER_in_DB=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_usernames WHERE USERNAME=%s",$USER));
		if($USER_in_DB)
		{

			$author=str_replace('\"','"',$author);
			$author=str_replace("\'","'",$author);
			$author=str_replace("\\\'","'",$author);



		$visits=$wpdb->get_var($wpdb->prepare("SELECT visits FROM ".$wpdb->prefix."IPBLC_usernames WHERE id=%d",$USER_in_DB));
		$visits=$visits+1;
		$t=time();

		$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."IPBLC_usernames SET `visits`=%d WHERE id=%d",array($visits,$USER_in_DB)));
		$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->prefix."IPBLC_usernames SET `lastvisit`=%d WHERE id=%d",array($t,$USER_in_DB)));


		//show 404 error
		header("Status: 404 Not Found");
		header("HTTP/1.0 404 Not Found");

	?>

<head><title><?php echo get_bloginfo('name'); ?></title></head>
<style>
#IPBLC_message_blacklist
{
	border:1px solid #FF9999;
	background-color: #FFDDDD;
	font-size: 20px;
	//font-weight: bold;
	padding: 12px;
	color: #000000;
	margin-top: 50px;
}

</style>
<center>
<div id="IPBLC_message_blacklist"> "<?php echo $USER; ?>" is blacklisted!</div><BR>
</center>
	<?php
		exit();
		}
	}
	//----end if isset $_POST['author'];



	//===================== GET ACTION FOR IP BLACKLIST CLOUD SERVER AND FAILED LOGIN EXTENDED PAGE=============

	if(isset($_GET['action']))
	{


		//-----function to validate js callback ----
		function valid_js_identifier( $callback ){
		return preg_replace("/[^][.\\'\\\"_A-Za-z0-9]/", '', $callback);
		}
		//----function ends----

		$callback=valid_js_identifier($_GET['callback']);
		


		//==========getFailedPagination=========

		if($_GET['action']=="getFailedPagination")
		{

			$IPBLC_cloud_password=get_option('IPBLC_cloud_password');
			$IPBLC_cloud_on=get_option('IPBLC_cloud_on');

			//-----IPBLC Server send password in urlencoded form. compare $pwd with $IPBLC_cloud_password-----

			$pwd=urldecode($_GET['pwd']);
			$result=array();

			header('Content-Type: application/json');
			$AllData=array();


	    		if($IPBLC_cloud_password && $IPBLC_cloud_on==2 && $IPBLC_cloud_password==$pwd)
			{
				$result['verify']="1";

				//-----------------------------------------PAGE SETTINGS----------------------------------------
				//--Posts per page
				$rowsPerPage = 30;
				// by default we show first page
				$pageNum = 1;
				// if $_GET['page'] defined, use it as page number
				if(isset($_GET['page_num']))
				{
					$pageNum = $_GET['page_num'];
				}
				// counting the offset
				$offset = ($pageNum - 1) * $rowsPerPage;
				$page_num=$pageNum;
				//---------------------------------------------------------------------------------

				$totalIP = $wpdb->query("SELECT DISTINCT(IP), id, COUNT(IP) as countx,timestamp FROM ".
					$wpdb->prefix."IPBLC_login_failed  GROUP BY IP  ORDER BY timestamp DESC");

				$resultX = $wpdb->get_results("SELECT  DISTINCT(IP), id, COUNT(IP) as countx, timestamp 
					FROM ".$wpdb->prefix."IPBLC_login_failed GROUP BY IP ORDER BY timestamp DESC LIMIT $offset, $rowsPerPage");

				$self="javascript: ";
				$maxPage = ceil($totalIP/$rowsPerPage);

				$nav  = "<BR>Page: ";
				// ... the previous code
				if($pageNum>10)
				{
					$xyzz=$pageNum-10;
					$nav .= " <a href=\"$self getFailedData($xyzz); getFailedPagination($xyzz); \">&lt;&lt;</a>- &nbsp; ";
				}

				for($page = 1; $page <= $maxPage; $page++)
				{
					if ($page == $pageNum)
					{
						$nav .= "<b><font color=red> $page </font></b> &nbsp; "; // no need to create a link to current page
					}
					else
					{
						if($page<$pageNum & $page>$pageNum-10)
						{
							$nav .= " <a href=\"$self getFailedData($page); getFailedPagination($page); \">$page</a>  &nbsp; ";
						}
						else if($page>$pageNum & $page<$pageNum+10)
						{
							$nav .= " <a href=\"$self getFailedData($page); getFailedPagination($page); \">$page</a>  &nbsp; ";
						}
					} 
				}
				if($pageNum<$maxPage-9)
				{
					$xyzzz=$pageNum+10;
					$nav .= "- <a href=\"$self getFailedData($xyzz); getFailedPagination($xyzz); \">&gt;&gt;</a> &nbsp; ";
				}
				$nav  .= '<BR>';



			}//-------passed cloud login-----
			else
			{
				$result['verify']="-1";
			}
			$result['PAGINATION']=$nav;
			echo $callback."(".json_encode($result).")";
			exit();
		}
		//==========getFailedPagination=========
		//==================END=================


		//==========getIPStatusJSON=========

		if($_GET['action']=="getIPStatusJSON")
		{
			$IPBLC_cloud_password=get_option('IPBLC_cloud_password');
			$IPBLC_cloud_on=get_option('IPBLC_cloud_on');

			$pwd=urldecode($_GET['pwd']);
			$result=array();

			header('Content-Type: application/json');
			$AllData=array();


			if($IPBLC_cloud_password && $IPBLC_cloud_on==2 && $IPBLC_cloud_password==$pwd)
			{
				$result['verify']="1";
				$IP="";

				if(isset($_GET['IP']))
				{
					$IP=sanitize_text_field($_GET['IP']);
				}

				if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
				{
					$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));
					if($IP_in_DP)
					{
						$status="<b style=\"color:#FF0000\"> Blacklisted</b>";
					}
					else
					{
						$status="<b style=\"color:#009900\"> Neutral</b>";
					}
				}
				else
				{
					$status="<b style=\"color:#ff7777\"> Invalid IP</b>";
				}

	    		}//-------passed cloud login-----
			else
			{
				$result['verify']="-1";
				$status="";
			}
			$result['STATUS']=$status;
			echo $callback."(".json_encode($result).")";
			exit();
		}
		//==========getIPStatusJSON=========
		//================END===============





		//==========getFailedPaginationSingle=========

		if($_GET['action']=="getFailedPaginationSingle")
		{
			$IPBLC_cloud_password=get_option('IPBLC_cloud_password');
			$IPBLC_cloud_on=get_option('IPBLC_cloud_on');

			$pwd=urldecode($_GET['pwd']);
			$result=array();

			header('Content-Type: application/json');
			$AllData=array();

			if($IPBLC_cloud_password && $IPBLC_cloud_on==2 && $IPBLC_cloud_password==$pwd)
			{
				$result['verify']="1";

				$IP="";

				if(isset($_GET['IP']))
				{
					$IP=sanitize_text_field($_GET['IP']);
				}

				if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
				{
					//-----------------------------------------PAGE SETTINGS----------------------------------------
					//--Posts per page
					$rowsPerPage = 30;
					// by default we show first page
					$pageNum = 1;
					// if $_GET['page'] defined, use it as page number
					if(isset($_GET['page_num']))
					{
						$pageNum = $_GET['page_num'];
					}
					// counting the offset
					$offset = ($pageNum - 1) * $rowsPerPage;
					$page_num=$pageNum;
					//---------------------------------------------------------------------------------

					$totalIP = $wpdb->query($wpdb->prepare( "SELECT * FROM ".$wpdb->prefix."IPBLC_login_failed WHERE 
					IP=%s GROUP BY IP  ORDER BY  timestamp DESC",$IP));

					$resultX = $wpdb->get_results($wpdb->prepare("SELECT *  FROM ".$wpdb->prefix."IPBLC_login_failed WHERE 
					IP=%s GROUP BY IP ORDER BY timestamp DESC LIMIT $offset, $rowsPerPage",$IP));

					$self="javascript: ";
					$maxPage = ceil($totalIP/$rowsPerPage);

					$nav  = "<BR>Page: ";
					// ... the previous code
					if($pageNum>10)
					{
						$xyzz=$pageNum-10;
						$nav .= " <a href=\"$self getFailedIPSingleWeb('$IP', $xyzz); \">&lt;&lt;</a>- &nbsp; ";
					}

					for($page = 1; $page <= $maxPage; $page++)
					{
						if ($page == $pageNum)
						{
							$nav .= "<b><font color=red> $page </font></b> &nbsp; "; // no need to create a link to current page
						}
						else
						{
							if($page<$pageNum & $page>$pageNum-10)
							{
								$nav .= " <a href=\"$self  getFailedIPSingleWeb('$IP',$page);\">$page</a>  &nbsp; ";
							}
							else if($page>$pageNum & $page<$pageNum+10)
							{
								$nav .= " <a href=\"$self  getFailedIPSingleWeb('$IP',$page); \">$page</a>  &nbsp; ";
							}
						} 
					}
					if($pageNum<$maxPage-9)
					{
						$xyzzz=$pageNum+10;
						$nav .= "- <a href=\"$self  getFailedIPSingleWeb('$IP',$xyzz); \">&gt;&gt;</a> &nbsp; ";
					}
					$nav  .= '<BR>';
					if($resultX)
					{

						foreach($resultX as $this_IP)
						{
							$userAgent=$this_IP->useragent;
							$vars=$this_IP->variables;
							$idd=$this_IP->id;
							$timeX=date("M d, Y",$this_IP->timestamp);

							$AllData[]=array("userAgent"=>$userAgent,"variables"=>$vars,"login_id"=>$idd,"date"=>$timeX);
						}
					}



				}
				else
				{
					$nav="";

				}
			}//-------passed cloud login-----
			else
			{
				$result['verify']="-1";
			}
			$result['PAGINATION']=$nav;
			$result['DATA']=$AllData;
			echo $callback."(".json_encode($result).")";

			exit();
		}
		//==========getFailedPaginationSingle=========
		//====================END=====================







		//==========getPendingComments=========

		if($_GET['action']=="getPendingComments")
		{
			$IPBLC_cloud_password=get_option('IPBLC_cloud_password');
			$IPBLC_cloud_on=get_option('IPBLC_cloud_on');

			$pwd=urldecode($_GET['pwd']);
			$result=array();

			header('Content-Type: application/json');
			$AllData=array();


			if($IPBLC_cloud_password && $IPBLC_cloud_on==2 && $IPBLC_cloud_password==$pwd)
			{
				$result['verify']="1";

				//-----------------------------------------PAGE SETTINGS----------------------------------------
				//--Posts per page
				$rowsPerPage = 30;
				// by default we show first page
				$pageNum = 1;
				// if $_GET['page'] defined, use it as page number
				if(isset($_GET['page_num']))
				{
					$pageNum = $_GET['page_num'];
				}
				// counting the offset
				$offset = ($pageNum - 1) * $rowsPerPage;
				$page_num=$pageNum;
				//---------------------------------------------------------------------------------

				$commentArgsTotal=array("status"=>"hold");
				$commentArgsCurrent=array("status"=>"hold","offset"=>$offset);
				$nav="";
				$totalComments = get_comments($commentArgsTotal);

				$endComment=($offset+$rowsPerPage)-1;
		
				$resultX=array();

				$start1 = 0;
				$end=$endComment;
		
				if($totalComments)
				{
					foreach($totalComments as $cc)
					{
						if($start1>=$offset && $start1<=$end)
						{
							$resultX[]=$cc;
						}
						$start1++;
					}
				}		

				if($resultX)
				{
					foreach($resultX as $sComment)
					{
						$cData=array();
						$comment_content="";

						foreach($sComment as $k=>$v)
						{
				
							$cData[$k]=$v;
							if($k=="comment_author_IP")
							{

								$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist 
										WHERE IP=%s",$v));
								if($IP_in_DP)
								{
									$status="<b style=\"color:#FF0000\"> Blacklisted</b>";
								}
								else
								{
									$status="<b style=\"color:#009900\"> Neutral</b>";
								}
								$cData['IP_status']=$status;
							}

							if($k=="comment_ID")
							{
								$comment_content= get_comment_excerpt($v);
							}
						}
						$cData['comment_content']=$comment_content;
						$AllData[]=$cData;
					}
				}


				$self="javascript: ";
				$maxPage = ceil(count($totalComments)/$rowsPerPage);



				$nav  = "<BR>Page: ";
				// ... the previous code
				if($pageNum>10)
				{
					$xyzz=$pageNum-10;
					$nav .= " <a href=\"$self getPendingCommentsSingle( $xyzz); \">&lt;&lt;</a>- &nbsp; ";
				}
				for($page = 1; $page <= $maxPage; $page++)
				{
					if ($page == $pageNum)
					{
						$nav .= "<b><font color=red> $page </font></b> &nbsp; "; // no need to create a link to current page
					}
					else
					{
						if($page<$pageNum & $page>$pageNum-10)
						{
							$nav .= " <a href=\"$self  getPendingCommentsSingle($page);\">$page</a>  &nbsp; ";
						}
						else if($page>$pageNum & $page<$pageNum+10)
						{
							$nav .= " <a href=\"$self  getPendingCommentsSingle($page); \">$page</a>  &nbsp; ";
						}
					} 
				}
				if($pageNum<$maxPage-9)
				{
					$xyzzz=$pageNum+10;
					$nav .= "- <a href=\"$self  getPendingCommentsSingle($xyzz); \">&gt;&gt;</a> &nbsp; ";
				}
				$nav  .= '<BR>';


			}//-------passed cloud login-----
			else
			{
				$result['verify']="-1";
				$nav="";
				$AllData=array();
			}
			$result['DATA']=$AllData;
			$result['PAGINATION']=$nav;
			echo $callback."(".json_encode($result).")";
			exit();
		}
		//==========getPendingComments=========
		//=================END=================





		//==========getFailedData=========

		if($_GET['action']=="getFailedData")
		{
			$IPBLC_cloud_password=get_option('IPBLC_cloud_password');
			$IPBLC_cloud_on=get_option('IPBLC_cloud_on');

			$pwd=urldecode($_GET['pwd']);
			$result=array();

			header('Content-Type: application/json');
			$AllData=array();


			if($IPBLC_cloud_password && $IPBLC_cloud_on==2 && $IPBLC_cloud_password==$pwd)
			{
				$result['verify']="1";

				//-----------------------------------------SETTINGS----------------------------------------
				//--Posts per page
				$rowsPerPage = 30;
				// by default we show first page
				$pageNum = 1;
				// if $_GET['page'] defined, use it as page number
				if(isset($_GET['page_num']))
				{
    					$pageNum = $_GET['page_num'];
				}
				// counting the offset
				$offset = ($pageNum - 1) * $rowsPerPage;
				$page_num=$pageNum;
				//---------------------------------------------------------------------------------

				$totalIP = $wpdb->query( "SELECT DISTINCT(IP), id, COUNT(IP) as countx,timestamp FROM ".
						$wpdb->prefix."IPBLC_login_failed  GROUP BY IP  ORDER BY timestamp DESC");

				$resultX = $wpdb->get_results( "SELECT  DISTINCT(IP), id, COUNT(IP) as countx, timestamp FROM ".
						$wpdb->prefix."IPBLC_login_failed GROUP BY IP ORDER BY timestamp DESC LIMIT $offset, $rowsPerPage");


				if($resultX)
				{
					foreach($resultX as $IPData)
					{
						$IP=$IPData->IP;
						$count=$IPData->countx;
						$timestamp=$IPData->timestamp;
						$date=date("M d, Y",$timestamp);
						$link="<a href=\"http://www.ip-finder.me/$IP/\" target=_blank>$IP</a>";
						$failedID=$IPData->id;


						$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));
						if($IP_in_DP)
						{
							$IP_status="Website: <span id=\"IPBlack_web_"."$failedID\">
								<b style=\"color:#FF0000\"> Blacklisted</b></span><BR>";
						}
						else
						{
							$IP_status="Website: <span id=\"IPBlack_web_"."$failedID\"><b style=\"color:#009900\"> Neutral</b></span><BR>";
						}

						$status_id="IPBlack_web_"."$failedID";

						$AllData[]=array("IP"=>$IP,"count"=>$count,"date"=>$date,"link"=>$link,
							"status_web"=>$IP_status,"id"=>$failedID,"status_id"=>$status_id);

					}

				}

	    		}//-------passed cloud login-----
	    		else
	    		{
				$result['verify']="-1";
			
	    		}
			$result['DATA']=$AllData;
			echo $callback."(".json_encode($result).")";
			exit();
		}

		//==========getFailedData=========
		//================END=============




		//==========getBlacklist=========

		if($_GET['action']=="getBlacklist")
		{
			$IPBLC_cloud_password=get_option('IPBLC_cloud_password');
			$IPBLC_cloud_on=get_option('IPBLC_cloud_on');

			$pwd=urldecode($_GET['pwd']);
			$result=array();

			header('Content-Type: application/json');
			$AllData=array();



			if($IPBLC_cloud_password && $IPBLC_cloud_on==2 && $IPBLC_cloud_password==$pwd)
			{
				$result['verify']="1";
				$IPs_in_DP=$wpdb->get_results("SELECT IP FROM ".$wpdb->prefix."IPBLC_blacklist ORDER BY id DESC");
				$IPx=array();
				$userxx=array();
				if($IPs_in_DP)
				{
					foreach($IPs_in_DP as $this_IP)
					{
						$IPx['IP']=$this_IP->IP;
						$AllData[]=$IPx;
					}
				}
			

			}//-------passed cloud login-----
			else
			{
				$result['verify']="-1";
			
			}
			$result['DATA']=$AllData;
			echo $callback."(".json_encode($result).")";
			exit();
		}
		//==========getBlacklist=========
		//===============END=============




		//==========addBlacklistIP=========

		if($_GET['action']=="addBlacklistIP")
		{
			$IPBLC_cloud_password=get_option('IPBLC_cloud_password');
			$IPBLC_cloud_on=get_option('IPBLC_cloud_on');

			$pwd=urldecode($_GET['pwd']);
			$result=array();

			header('Content-Type: application/json');
			$AllData=array();

			$IP="";
			if($_GET['IP'])
			{
					$IP=sanitize_text_field($_GET['IP']);
			}

			if($IPBLC_cloud_password && $IPBLC_cloud_on==2 && $IPBLC_cloud_password==$pwd)
			{
				$result['verify']="1";
				if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
				{
					$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));
					if(!$IP_in_DP)
					{
						$table=$wpdb->prefix."IPBLC_blacklist";
						$time=time();
						$wpdb->query($wpdb->prepare("INSERT INTO $table (IP,timestamp,visits,lastvisit) 
							VALUES(%s,%d,%d,%d)",array($IP,$time,0,0)));	

						post_blacklist_add($IP);
						$AllData[]=array("IP"=>$IP,"added"=>1);
					}
					else
					{
						$AllData[]=array("IP"=>$IP,"added"=>2);
					}

				}
			}//-------passed cloud login-----
			else
			{
				$result['verify']="-1";
			}
			$result['DATA']=$AllData;
			echo $callback."(".json_encode($result).")";
			exit();
		}
		//==========addBlacklistIP=========
		//================END==============


		//==========addBlacklistIP_multi=========

		if($_GET['action']=="addBlacklistIP_multi")
		{
			$IPBLC_cloud_password=get_option('IPBLC_cloud_password');
			$IPBLC_cloud_on=get_option('IPBLC_cloud_on');

			$pwd=urldecode($_GET['pwd']);
			$result=array();

			header('Content-Type: application/json');
			$AllData=array();
			
			if($IPBLC_cloud_password && $IPBLC_cloud_on==2 && $IPBLC_cloud_password==$pwd)
			{
				$result['verify']="1";
				$IPx="";
				if($_GET['IP'])
				{
					$IPx=sanitize_text_field($_GET['IP']);
				}

				$IPx=explode(",",$IPx);


				$IP="";
				$IPData=array();

				$request_IP=array();

				$sep="";
				$sep2="";

				foreach($IPx as $IP)
				{
					if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
					{
						$request_IP[]=$IP;
					}
				}

				if($request_IP)
				{

					post_blacklist_add_multi($request_IP);
					foreach($request_IP as $IP)
					{
						$table=$wpdb->prefix."IPBLC_blacklist";
						$time=time();

						$sql=$wpdb->prepare("SELECT IP,timestamp,visits,lastvisit FROM $table WHERE IP=%s",$IP);
						$checkX=$wpdb->query($sql);

						if($checkX==0)
						{
							$sql=$wpdb->prepare("INSERT INTO $table (IP,timestamp,visits,lastvisit) 
								VALUES(%s,%d,%d,%d)",array($IP,$time,0,0));
							$wpdb->query($sql);
						}
				
						$IPData[]=array("IP"=>$IP,"added"=>1);
					}
				}

			}//-------passed cloud login-----
			else
			{
				$result['verify']="-1";
			}
			$AllData['IPData']=$IPData;
			$result['DATA']=$AllData;
			echo $callback."(".json_encode($result).")";
			exit();
		}
		//==========addBlacklistIP_multi=========
		//===================END==================



		//==========deleteBlacklistIP=========

		if($_GET['action']=="deleteBlacklistIP")
		{
			$IPBLC_cloud_password=get_option('IPBLC_cloud_password');
			$IPBLC_cloud_on=get_option('IPBLC_cloud_on');

			$pwd=urldecode($_GET['pwd']);
			$result=array();

			header('Content-Type: application/json');
			$AllData=array();

			$IP="";
			if($_GET['IP'])
			{
					$IP=sanitize_text_field($_GET['IP']);
			}
			if($IPBLC_cloud_password && $IPBLC_cloud_on==2 && $IPBLC_cloud_password==$pwd)
			{
				$result['verify']="1";

				if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
				{
					$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));
					if($IP_in_DP)
					{
						$table=$wpdb->prefix."IPBLC_blacklist";
						$time=time();
						$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));

						$data = array('test' => '1');
						$contextData = array ( 
							'method' => 'POST',
							'content' => http_build_query($data),
							'header' => "Connection: close\r\n". 
							"Content-Type: application/x-www-form-urlencoded\r\n".
							"Referer: ".site_url()."\r\n");

					$context = stream_context_create(array
					( 'http' => $contextData ));
$link="http://www.ip-finder.me/wp-content/themes/ipfinder/blacklist_delete.php?IP=".$IP."&website=".urlencode(site_url())."&website_name=".urlencode(get_bloginfo('name'));					$post_to_cloud =  file_get_contents (
			                  $link,  // page url
			                  false,
			                  $context);

						$AllData[]=array("IP"=>$IP,"deleted"=>1);
					}
					else
					{
						$AllData[]=array("IP"=>$IP,"deleted"=>0);
					}
				}
			}//-------passed cloud login-----
			else
			{
				$result['verify']="-1";
			}
			$result['DATA']=$AllData;
			echo $callback."(".json_encode($result).")";
			exit();
		}
		//==========deleteBlacklistIP=========
		//===================END==============



		//==========verifyCloudLogin=========
		if($_GET['action']=="verifyCloudLogin")
		{
			$IPBLC_cloud_password=get_option('IPBLC_cloud_password');
			$IPBLC_cloud_on=get_option('IPBLC_cloud_on');

			$pwd=urldecode($_GET['pwd']);
			$result=array();

			header('Content-Type: application/json');
			$AllData=array();

			if($IPBLC_cloud_password && $IPBLC_cloud_on==2 && $IPBLC_cloud_password==$pwd)
			{
				$result['verify']=1;
			}//-------passed cloud login-----
			else
			{
				$result['verify']=-1;
			}
			echo $callback."(".json_encode($result).")";
			exit();
		}
		//==========verifyCloudLogin=========
		//=================END===============


		//==========failedDetails=========

		if($_GET['action']=="failedDetails")
		{
			if(current_user_can( 'manage_options' ))
			{
				$IP="";
				if($_GET['IP'])
				{
					$IP=sanitize_text_field($_GET['IP']);
				}
				if(!filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
				{
					echo "Invalid IP";
					exit();
				}
				$manage=1;
				include "failedDetails.php";

			}
			else
			{
				echo "You don't have permission to view details.";
			}
			exit();
		}
		//==========failedDetails=========
		//================END=============

		//==========spamcheck=========

		if($_GET['action']=="spamcheck")
		{
			include "SpamChecker.php";
			exit();
		}
		//==========spamcheck=========
		//=============END============
	}


	//===================== GET ACTION FOR IP BLACKLIST CLOUD SERVER AND FAILED LOGIN EXTENDED PAGE=============
	//===============================================END========================================================




	//================================== POST ACTIONS VIA AJAX ========================

	if(isset($_POST['action']))
	{
		//==========blacklistUSERJS========

		if($_POST['action']=="blacklistUSERJS")
		{
			if(current_user_can( 'manage_options' ))
			{

				$USER=$_POST['blacklistuser'];
				$USER=sanitize_text_field($USER);


				$sql=$wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_usernames WHERE USERNAME=%s",$USER);

				$USER_in_DB=$wpdb->get_var($sql);

				$found=false;

				if(!$USER_in_DB)
				{
					if($USER)
					{

						$table=$wpdb->prefix."IPBLC_usernames";
						$time=time();

						$sql=$wpdb->prepare("INSERT INTO $table (USERNAME,timestamp,visits,lastvisit) 
							VALUES(%s,%d,%d,%d)",array($USER,$time,0,0));
						$wpdb->query($sql);
						//---post blacklist data to ip-finder.me
						$USER2=urlencode($USER);
						post_blacklist_add_user($USER2);
					}
					else
					{
						$found=true;
						$USER_error=true;
					}	
				}
				$USER_in_DB="";

				//---delay
				for($d=0;$d<=100000;$d++)
				{
					//----
				}
				$sql=$wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_usernames WHERE USERNAME=%s",$USER);
				$USER_in_DB=$wpdb->get_var($sql);

				if($USER_in_DB)
				{
					echo "<b style=\"color: #FF0000;\"> Blacklisted</b>";
				}
				else
				{
					echo "<b style=\"color: #009900;\"> Neutral</b>";
				}

			}
			exit();
		}
		//==========blacklistUSERJS========
		//================END==============


		//==========blacklistIPJS========

		if($_POST['action']=="blacklistIPJS")
		{
			if(current_user_can( 'manage_options' ))
			{
				$IP="";
				if($_POST['blacklist'])
				{
					$IP=sanitize_text_field($_POST['blacklist']);
				}
				if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
				{
					$isIPSafe=isIpSafe($IP);
					if($isIPSafe==0)
					{
						$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));
						$found=false;
						if(!$IP_in_DP)
						{
							$table=$wpdb->prefix."IPBLC_blacklist";
							$time=time();
							$sql=$wpdb->prepare("INSERT INTO $table (IP,timestamp,visits,lastvisit) 
							VALUES(%s,%d,%d,%d)",array($IP,$time,0,0));
							$wpdb->query($sql);
							//---post data to ip-finder.me
							post_blacklist_add($IP);
						}


						$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));
						$found=false;

						if($IP_in_DP)
						{
							echo "<b style=\"color: #FF0000;\"> Blacklisted</b>
								<a href=\"http://www.ip-finder.me/$IP/\" target=_blank title=\"Leave Comment\">Why?</a>";
						}
						else
						{
							echo "<b style=\"color: #009900;\"> Neutral</b>";
						}
					}
					else
					{
						echo "<b style=\"color: #CC0099;\"> Whitelist</b>";
					}
				}
				else
				{
						echo "<b style=\"color: #ff9999;\"> Invalid IP</b>";
				}
			}
			exit();
		}
		//==========blacklistIPJS========
		//================END===========

	}
	//================================== POST ACTIONS VIA AJAX ========================
	//=========================================== END =================================

	//-----import and export Database-----

	if(isset($_REQUEST['action']))
	{
		//-----export-----

		if($_REQUEST['action']=="exportIPCloud")
		{
			if(current_user_can( 'manage_options' ))
			{
				$IPs_in_DP=$wpdb->get_results("SELECT IP FROM ".$wpdb->prefix."IPBLC_blacklist ORDER BY id DESC");
				$AllData=array();
				if($IPs_in_DP)
				{
					foreach($IPs_in_DP as $this_IP)
					{
						//$IPx['IP']=$this_IP->IP;
						$AllData[]=array("IP",$this_IP->IP);

					}
				}
				$USERs_in_DP=$wpdb->get_results("SELECT USERNAME FROM ".$wpdb->prefix."IPBLC_usernames ORDER BY id DESC");
				if($USERs_in_DP)
				{
					foreach($USERs_in_DP as $this_USER)
					{
						$USERx=$this_USER->USERNAME;
						$USERx=str_replace("&quot;",'"',$USERx);
						$USERx=urlencode($USERx);
						//$userxx['username']=$USERx;
						$AllData[]=array("USERNAME",$USERx);
					}
				}

				header( 'Content-Type: text/csv' );
				header( 'Content-Disposition: attachment;filename=IPBlacklistDB.csv');
				$fp = fopen('php://output', 'w');
				if($AllData)
				{
					foreach($AllData as $line)
					{
									
						fputcsv($fp, $line);	
					}

				}
				fclose($fp);

			}
			exit();
		}
		//------end Export-----

		//-----Import Database-----
		if($_REQUEST['action']=="importCSVIPCloud")
		{
			if(current_user_can( 'manage_options' ) && $_REQUEST['filename'])
			{
				$js_url =get_bloginfo('template_directory');
				$upload_d = wp_upload_dir();
				$upload_url=$upload_d['baseurl'];
				$upload_dir=$upload_d['basedir'];
				$filename= $upload_dir."/ipblc_csv/".$_REQUEST['filename'];
				//echo "file: $filename<BR>";
				$row = 1;
				if (($handle = fopen("$filename", "r")) !== FALSE)
				{
					$AllData=array();
					while (($data = fgetcsv($handle, 5242880, ",")) !== FALSE)
					{
						$field=$data[0];
						$value=$data[1];
						$AllData[]=array("$field"=>$value);
					}
					fclose($handle);
					echo json_encode($AllData);
				}
				else
				{
					echo "-1";
				}
			}
			exit();
		}
		//------end Import-----
	}
	//----- END import and export Database-----






	//----- start saving in Database-----
	if(isset($_POST['action']))
	{
		if($_POST['action']=="submitToDBIP")
		{
			if(current_user_can( 'manage_options' ))
			{
				$IP="";
				if($_POST['IP'])
				{
					$IP=sanitize_text_field($_POST['IP']);
				}
				$found="";
				if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
				{
					$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));
					$found=false;
					if(!$IP_in_DP)
					{
						$table=$wpdb->prefix."IPBLC_blacklist";
						$time=time();
						$sql=$wpdb->prepare("INSERT INTO $table (IP,timestamp,visits,lastvisit) VALUES(%s,%d,%d,%d)",array($IP,$time,0,0));
						$wpdb->query($sql);

						//---post data to ip-finder.me
						post_blacklist_add($IP);

					}
					echo "Done";
				}
			}
			exit();
		}

		//---------submitToDBUSER-----

		if($_POST['action']=="submitToDBUSER")
		{
			if(current_user_can( 'manage_options' ))
			{

				$USER=$_POST['USER'];
				$found="";
				$USER=sanitize_text_field($USER);

				if($USER)
				{
					$sql=$wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_usernames WHERE USERNAME=%s",$USER);
					$USER_in_DB=$wpdb->get_var($sql);
					$found=false;

					if(!$USER_in_DB)
					{

						$table=$wpdb->prefix."IPBLC_usernames";
						$time=time();

						$sql=$wpdb->prepare("INSERT INTO $table (USERNAME,timestamp,visits,lastvisit) 
							VALUES(%s,%d,%d,%d)",array($USER,$time,0,0));

						$wpdb->query($sql);

						//---post blacklist data to ip-finder.me
						$USER2=urlencode($USER);
						post_blacklist_add_user($USER2);

					}
					echo "Done";
				}
			}
			exit();
		}
		//-----END submitToDBUSER-----
	}
	//----- END start saving in Database-----
}
//----end function----


$found=false;
$IP_global="";
$IP_error=false;


$USER_global="";
$USER_error=false;

add_action('admin_menu', 'page_IPBLC_actions');  
add_action('admin_init', 'create_sql');
add_action( 'admin_notices', 'check_fixes' ); 


add_filter( 'manage_edit-comments_columns', 'IPBLC_IP_column' );
add_filter( 'manage_comments_custom_column', 'IPBLC_IP_value', 10, 2 );

add_action('init', 'IPBLC_blockIP');  


add_action('wp_login_failed','IPBLC_login_failed');

//add_action('comment_unapproved_to_spam', 'auto_blacklist_spam');
//add_action('comment_approved_to_spam', 'auto_blacklist_spam');

add_action('admin_init', 'auto_blacklist_spam_multi');


function IPBLC_login_failed(){

	global $wpdb;


	$postedData="";
	//print_r($_SERVER);
	$visitorIP=sanitize_text_field($_SERVER['REMOTE_ADDR']);
	$visitor_time=sanitize_text_field($_SERVER['REQUEST_TIME']);
	$visitor_user_agent=sanitize_text_field($_SERVER['HTTP_USER_AGENT']);

	$IPBLC_failedlogin_email=get_option('IPBLC_failedlogin_email');


	$isIPSafe=isIpSafe($visitorIP);
	if($isIPSafe==0)
	{
		if(isset($_POST))
		{
			foreach($_POST as $k=>$v)
			{
				$postedData.="<font color=green>$k</font> => <font color=red>$v</font><BR>";
			}
		}
		$table=$wpdb->prefix."IPBLC_login_failed";
		$wpdb->query($wpdb->prepare("INSERT INTO $table (IP,variables,useragent,timestamp) 
			VALUES(%s,%s,%s,%s)",
			array($visitorIP,$postedData,$visitor_user_agent,$visitor_time)));

		//-----check auto block username-----
		$login_user="";
		if(isset($_POST['log']))
		{
			$login_user=sanitize_text_field($_POST['log']);
		}

		$IPBLC_autoblock=get_option('IPBLC_autoblock');
		$username_block=0;
		$IPBLC_autoblock=str_replace("\r\n","\n",$IPBLC_autoblock);
		$IPBLC_autoblock=str_replace("\'","'",$IPBLC_autoblock);
		if($IPBLC_autoblock)
		{
			$IPBLC_autoblock_explode=explode("\n",$IPBLC_autoblock);
			if($IPBLC_autoblock_explode)
			{
				foreach($IPBLC_autoblock_explode as $user)
				{
					$user=str_replace("\n","",$user);
					if($user==$login_user && $user!="" && $login_user!="")
					{
						$username_block=1;
					}
				}
			}
		}

		if($username_block==1)
		{

			$time=time();
			$table2=$wpdb->prefix."IPBLC_blacklist";
			if(filter_var($visitorIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
			{
				$wpdb->query($wpdb->prepare("INSERT INTO $table2 (IP,timestamp,visits,lastvisit) 
					VALUES(%s,%d,%d,%d)",array($visitorIP,$time,0,0)));

				post_blacklist_add($visitorIP);
				if($IPBLC_failedlogin_email)
				{
					//echo "sending mail!!";
		$failedMessage="$visitorIP blacklisted on ".site_url()." due to login with username \"$login_user\"\r\n\r\nThank you for using IP Blacklist Cloud";
		wp_mail($IPBLC_failedlogin_email,"$visitorIP Blacklisted (Auto Block)",$failedMessage);
				}
			}
		}

		//-----check auto block username - END -----


		$IPBLC_failedlogin_max=get_option('IPBLC_failedlogin_max');
		if(!$IPBLC_failedlogin_max)
		{
			update_option('IPBLC_failedlogin_max','5');
			$IPBLC_failedlogin_max=get_option('IPBLC_failedlogin_max');

		}
		$IPBLC_failedlogin_time=get_option('IPBLC_failedlogin_time');
		if(!$IPBLC_failedlogin_time)
		{
			update_option('IPBLC_failedlogin_time','60');
			$IPBLC_failedlogin_time=get_option('IPBLC_failedlogin_time');
		}

		$time=time();
		$startTime=$time-($IPBLC_failedlogin_time*60);

		$failedTotal=$wpdb->get_results($wpdb->prepare("SELECT COUNT(*) as attempts FROM $table WHERE IP=%s AND timestamp>=%d AND timestamp<=%d",
			array($visitorIP,$startTime,$time)));

		if($failedTotal)
		{
			$attempts=$failedTotal[0]->attempts;
			//echo $attempts;
			if($attempts>=$IPBLC_failedlogin_max && filter_var($visitorIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
			{
				$table2=$wpdb->prefix."IPBLC_blacklist";
				$wpdb->query($wpdb->prepare("INSERT INTO $table2 (IP,timestamp,visits,lastvisit) 
					VALUES(%s,%d,%d,%d)",array($visitorIP,$time,0,0)));

				post_blacklist_add($visitorIP);

				if($IPBLC_failedlogin_email)
				{
					$resultX = $wpdb->get_results($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."IPBLC_login_failed  WHERE 
						IP=%s  ORDER BY timestamp desc LIMIT 5",$visitorIP));

					$failedDatax="";
					foreach($resultX as $this_IPx)
					{

						$userAgent=$this_IPx->useragent;
						$vars=$this_IPx->variables;
						$vars=str_replace("<BR>","\r\n",$vars);
						$vars=strip_tags($vars);

						$idd=$this_IPx->id;
						$timeX=date("M d, Y",$this_IPx->timestamp);
	
						$failedDatax.="$vars\r\n\r\n$userAgent\r\n\r\n$timeX\r\n\r\n---------------------------------------------\r\n\r\n";

					}

					$showData="Failed Attempts Details (Max 5)\r\n\r\n".$failedDatax;


					$failedMessage="$visitorIP blacklisted on ".site_url()." due to failed login attempts on following condition:\n\nAttempts: $IPBLC_failedlogin_max (max)\nTime for max attempts: $IPBLC_failedlogin_time"."\nTotal attempts made: $attempts"."\r\n\r\n".$showData."\r\n\r\nThank you for using IP Blacklist Cloud";


					wp_mail($IPBLC_failedlogin_email,"$visitorIP Blacklisted (Failed Login)",$failedMessage);
				}

			}
		}

	}	//$isIPSafe==0	

}
//----end function---



function load_custom_IPBLC_admin_style()
{

        echo "<style type=\"text/css\">\n
	.IPSpam, .IPSpamAction{ display: inline; margin: 0px 10px;}\n		
	</style>\n
	";

}



function IPJS()
{
	if(isset($_GET['blacklist']))
	{
		//print_r($_SERVER);
		$referer=sanitize_text_field($_SERVER['HTTP_REFERER']);

	
		if(strpos($referer,"edit-comments.php")>0)
		{
			ip_added_message();
		}

	}


	elseif(isset($_GET['blacklistuser']))
	{
		//print_r($_SERVER);
		$referer=sanitize_text_field($_SERVER['HTTP_REFERER']);
		if(strpos($referer,"edit-comments.php")>0)
		{
			user_added_message();
		}

	}


	$scriptname="";
	if(isset($_SERVER['SCRIPT_NAME']))
	{
		$scriptname=$_SERVER['SCRIPT_NAME'];
	}

	$page="";
	if(isset($_GET['page']))
	{
		$page=$_GET['page'];
	}

?>
<script type="text/javascript">
jQuery(".IPSpamAction").click(function(){

	var comment_ID=jQuery(this).attr('name');
	var spamperc=jQuery("#IPSpam-"+comment_ID);
	spamperc.css("color","#000000");
	spamperc.html("<img src=\"<?php echo site_url(); ?>/wp-admin/images/wpspin_light.gif\">");
<?php

	$SpamCheckerUrl=site_url()."?action=spamcheck";

?>



		var reRequest=jQuery.ajax({
			  type: "POST",
			  url: "<?php echo $SpamCheckerUrl; ?>",
			  data: {comment_ID: comment_ID},
  			dataType: "html"

			});

		reRequest.done(function(msg,response) {
			if(msg)
			{
				spamperc.css("color","#000000");
				spamperc.html(msg);
			}
		});

		reRequest.fail(function(jqXHR, textStatus) {
			spamperc.css("color","#FF0000");
			spamperc.html("<?php echo __("Error"); ?>");
		});

	return false;

});
</script>
<?php
}

function protected_comment_link()
{

	$IPBLC_protected=get_option('IPBLC_protected');
	if($IPBLC_protected=="2")
	{


	echo "Protected with <a href=\"http://www.ip-finder.me\"><img src=\"".plugins_url()."/ip-blacklist-cloud/icon.png\" style=\"display: inline;\" alt=\"IP Blacklist Cloud\"></a><a href=\"http://www.ip-finder.me\" title=\"IP Blacklist Cloud\">IP Blacklist Cloud</a>";


	}
	else
	{
		//echo "<div style=\"display: none;\">Protected with <a href=\"http://www.ip-finder.me\" title=\"IP Blacklist Cloud\">IP Blacklist Cloud</a></div>";
	}
}


function postToCloud($comment_id)
{

	$IPBLC_auto_comments=get_option('IPBLC_auto_comments');

	if($IPBLC_auto_comments=="2")
	{

		if(is_numeric($comment_id))
		{

			//------plugin url---
			$plugin_dir_name=plugin_dir_url(__FILE__);
			//------SpamCheckerfile---
			$SpamCheckerUrl=$plugin_dir_name."SpamChecker.php";


			//echo $SpamCheckerUrl;

			$handle = curl_init($SpamCheckerUrl);
			
			curl_setopt($handle, CURLOPT_POSTFIELDS,"comment_ID=$comment_id");
			curl_setopt($handle, CURLOPT_POST, 1);
			curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($handle, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
			curl_setopt($handle, CURLOPT_REFERER, site_url());
			curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $handle, CURLOPT_ENCODING, "" );
			curl_setopt( $handle, CURLOPT_AUTOREFERER, true );
			curl_setopt( $handle, CURLOPT_MAXREDIRS, 10 );
			curl_setopt($handle,CURLOPT_TIMEOUT,15);
			curl_setopt($handle,CURLOPT_CONNECTTIMEOUT,15);





			$header_info = curl_getinfo( $handle );
 
			$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);


			$response = curl_exec($handle);

			$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
			curl_close($handle);

			//echo $response;

		}
	}
}

//-----disable auto post to cloud-----------
//add_action('comment_post','postToCloud');


add_action( 'admin_enqueue_scripts', 'load_custom_IPBLC_admin_style' );
add_action('admin_footer', 'IPJS');
add_action( 'comment_form', 'protected_comment_link' );

add_action('wp_ajax_verifyCloudAccount', 'verifyCloudAccount_callback');



function verifyCloudAccount($echo=false)
{


	$IPBLC_cloud_email=get_option('IPBLC_cloud_email');
	$IPBLC_cloud_key=get_option('IPBLC_cloud_key');

	if($IPBLC_cloud_email && $IPBLC_cloud_key)
	{
		//---post blacklist data to ip-finder.me


$data = array('test' => '1');



$contextData = array ( 
                'method' => 'POST',
		'content' => http_build_query($data),
		'header' => "Connection: close\r\n". 
                        "Content-Type: application/x-www-form-urlencoded\r\n".

		"Referer: ".site_url()."\r\n");

 
		$context = stream_context_create (array ( 'http' => $contextData ));

		$email2=urlencode($IPBLC_cloud_email);

$link="http://www.ip-finder.me/wp-content/themes/ipfinder/cloudaccount_status.php?email=$email2&website=".urlencode(site_url())."&website_name=".urlencode(get_bloginfo('name'))."&cloudkey=".$IPBLC_cloud_key;


		$post_to_cloud =  file_get_contents (
		$link,
		false,
		$context);

		if($echo)
		{
			echo $post_to_cloud;
		}
		else
		{
			return $post_to_cloud;
		}

	}
	else
	{
		if($echo)
		{
			echo "-1";
		}
		else
		{
			return "-1";
		}
	}
}

add_action('admin_footer', 'IPJS2');


function IPJS2()
{

?>
<script>

var ajaxloader="<img src=\"<?php echo site_url(); ?>/wp-admin/images/wpspin_light.gif\">";


function blacklist_IP(IP,commentID)
{
	jQuery("#IPBlack"+commentID).html(ajaxloader);

		var reRequest=jQuery.ajax({
			  type: "POST",
			  url: "<?php echo get_bloginfo('url'); ?>/",
			data: {blacklist: IP, action: "blacklistIPJS"},
  			dataType: "html"
			});

		reRequest.done(function(msg) {
			result=msg;
			//alert(result);

			
		if(result)
		{
			jQuery("#IPBlack"+commentID).html(result);

		}
		else
		{
			jQuery("#IPBlack"+commentID).html("<font color=red>Error! Try Again.</font>");
		}

		});

		reRequest.fail(function(jqXHR, textStatus) {
			//msg_box.show();
			jQuery("#IPBlack"+commentID).html("<font color=red>Error! Try Again.</font>");
			result=false;	
		});



	
}

function blacklist_USER(USER,commentID)
{
	jQuery("#UserBlack"+commentID).html(ajaxloader);

		var reRequest=jQuery.ajax({
			  type: "POST",
			  url: "<?php echo get_bloginfo('url'); ?>/",
			data: {blacklistuser: USER, action: "blacklistUSERJS"},
  			dataType: "html"
			});

		reRequest.done(function(msg) {
			result=msg;
			//alert(result);

			
		if(result)
		{
			jQuery("#UserBlack"+commentID).html(result);

		}
		else
		{
			jQuery("#UserBlack"+commentID).html("<font color=red>Error! Try Again.</font>");
		}

		});

		reRequest.fail(function(jqXHR, textStatus) {
			//msg_box.show();
			jQuery("#UserBlack"+commentID).html("<font color=red>Error! Try Again.</font>");
			result=false;	
		});	
}



</script>

<?php
}


function isIpSafe($checkIP)
{


	$isIPSafe1=0;
	$IPBLC_whitelist=get_option('IPBLC_whitelist');
	$IPBLC_whitelist=str_replace('\r\n',"\r\n",$IPBLC_whitelist);
	$IPBLC_whitelist=str_replace("\r\n","\n",$IPBLC_whitelist);

	if($IPBLC_whitelist)
	{
		$IPBLC_whitelist_explode=explode("\n",$IPBLC_whitelist);


		foreach($IPBLC_whitelist_explode as $wIP)
		{
			$wIP=str_replace("\n","",$wIP);
			$wIP=str_replace(" ","",$wIP);

			if($wIP)
			{
			$wIP_range=explode("-",$wIP);
			//print_r($wIP_range);

			$range=0;
			if(count($wIP_range)>1)
			{
				$range=1;
			}
			
			if($range==0)
			{

				//echo "no range...<BR>";

				$wIP_explode=explode(".",$wIP);
				$wIP2=intval($wIP_explode[0]).".".intval($wIP_explode[1]).".".intval($wIP_explode[2]).".".intval($wIP_explode[3]);

				//echo "$visitorIP==$wIP2 -- ".ip2long($visitorIP)." -- ".ip2long($wIP2)."<BR>";

				//echo "OK: ".filter_var($wIP2, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)."<BR>";

				if(ip2long($checkIP)==ip2long($wIP2))
				{
					//echo "SAFE<BR>";
					$isIPSafe1=1;
				}
			}
			else
			{
				//echo "range...<BR>";


				$wIP_explode1=explode(".",$wIP_range[0]);
				$wIP_explode2=explode(".",$wIP_range[1]);
				$wIP2_1=intval($wIP_explode1[0]).".".intval($wIP_explode1[1]).".".intval($wIP_explode1[2]).".".intval($wIP_explode1[3]);
				$wIP2_2=intval($wIP_explode2[0]).".".intval($wIP_explode2[1]).".".intval($wIP_explode2[2]).".".intval($wIP_explode2[3]);

				//echo "$visitorIP==$wIP2 -- ".ip2long($visitorIP)." -- ".ip2long($wIP2)."<BR>";

				//echo "OK: ".filter_var($wIP2, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)."<BR>";
				//	echo ip2long($checkIP).">=".ip2long($wIP2_1)." && ".ip2long($checkIP)."<=".ip2long($wIP2_2)."<BR>";

				if(ip2long($checkIP)>=ip2long($wIP2_1) && ip2long($checkIP)<=ip2long($wIP2_2) )
				{
				//	echo ip2long($checkIP).">=".ip2long($wIP2_1)." && ".ip2long($checkIP)."<=".ip2long($wIP2_2);
				//	echo "SAFE";
					$isIPSafe1=1;
				}
			
			}

			}
		}
	}



	return $isIPSafe1;

}



function post_blacklist_add($IP)
{


$data = array('test' => '1');



$contextData = array ( 
                'method' => 'POST',
		'content' => http_build_query($data),
		'header' => "Connection: close\r\n". 
                        "Content-Type: application/x-www-form-urlencoded\r\n".

		"Referer: ".site_url()."\r\n");

 

// Create context resource for our request

$context = stream_context_create (array ( 'http' => $contextData ));
$link="http://www.ip-finder.me/wp-content/themes/ipfinder/blacklist_add.php?IP=".$IP."&website=".urlencode(site_url())."&website_name=".urlencode(get_bloginfo('name'));

$post_to_cloud =  file_get_contents (
                  $link,  // page url
                  false,
                  $context);


//	echo "return: $post_to_cloud";


}





function post_blacklist_add_multi($IPs)
{


$data = array('test' => '1');

//print_r($IPs);

if($IPs)
{

	$IP_var="";
	$sep="";
	
	foreach($IPs as $IPx)
	{
		$IP_var.=$sep.$IPx;
		$sep=",";
	}


$contextData = array ( 
                'method' => 'POST',
		'content' => http_build_query($data),
		'header' => "Connection: close\r\n". 
                        "Content-Type: application/x-www-form-urlencoded\r\n".

		"Referer: ".site_url()."\r\n");

 

// Create context resource for our request

$context = stream_context_create (array ( 'http' => $contextData ));
$link="http://www.ip-finder.me/wp-content/themes/ipfinder/blacklist_add_multi.php?IP=".$IP_var."&website=".urlencode(site_url())."&website_name=".urlencode(get_bloginfo('name'));


$post_to_cloud =  file_get_contents (
                  $link,  // page url
                  false,
                  $context);
}

}




function post_blacklist_add_user($user)
{


$data = array('test' => '1');



$contextData = array ( 
                'method' => 'POST',
		'content' => http_build_query($data),
		'header' => "Connection: close\r\n". 
                        "Content-Type: application/x-www-form-urlencoded\r\n".

		"Referer: ".site_url()."\r\n");

 

// Create context resource for our request

$context = stream_context_create (array ( 'http' => $contextData ));

$link="http://www.ip-finder.me/wp-content/themes/ipfinder/blacklist_user_add.php?USER=".$user."&website=".
urlencode(site_url())."&website_name=".urlencode(get_bloginfo('name'));

$post_to_cloud =  file_get_contents (
                  $link,  // page url
                  false,
                  $context);

}


function check_fixes()
{
	$IPBLC_fix=get_option('IPBLC_fixes');

	if($IPBLC_fix=="")
	{

?>
	<div class="error">
		<p><h4 style="color: #FF0000;">Action Required by IP Blacklist Cloud Plugin!</h4>
		<b>Please check <a href="admin.php?page=wp-IPBLC-fixes">fixes</a> page for database issues.</b></p>
	</div>
<?php
	}

}


function auto_blacklist_spam($comment_object)
{
	global $wpdb;

	$IP=$comment_object->comment_author_IP;

	$table=$wpdb->prefix."IPBLC_blacklist";
	$time=time();

	$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));

	$found=false;

//	print_r($IP_in_DP);

	if(!$IP_in_DP)
	{

		if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		{
//			echo "$IP"."\n";

			$wpdb->query($wpdb->prepare("INSERT INTO $table (IP,timestamp,visits,lastvisit) VALUES(%s,%d,%d,%d)",array($IP,$time,0,0)));
			post_blacklist_add($IP);
		}
	}	
}

function auto_blacklist_spam_multi()
{
	global $wpdb;

	$table=$wpdb->prefix."IPBLC_blacklist";
	$time=time();

	$IPs=array();


		$action1="";
		$action2="";
		$sp="";
		
		if(isset($_GET['action']))
		{
			$action1=$_GET['action'];
		}
		
		if(isset($_POST['action']))
		{
			$action1=$_POST['action'];
		}
		
		if(isset($_GET['action2']))
		{
			$action2=$_GET['action2'];
		}
		if(isset($_POST['action2']))
		{
			$action2=$_POST['action2'];
		}
		if(isset($_POST['spam']))
		{
			$sp=$_POST['spam'];
		}



	if($action1 || $action2)
	{
		if($action1=="spam" || $action2=="spam" || ($action1=="delete-comment" && $sp==1)  || ($action2=="delete-comment" && $sp==1) )
		{

			if(isset($_GET['delete_comments']))
			{
				$comments=$_GET['delete_comments'];


				foreach($comments as $commentID)
				{
					$commentX=get_comment(sanitize_text_field($commentID) );
					//print_r($commentX);
					$IP=$commentX->comment_author_IP;

					$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));

					if(!$IP_in_DP)
					{
						$IPs[]=$IP;
					}

				}

				if($IPs)
				{
					$IPs=array_unique($IPs);
					foreach($IPs as $IP)
					{
						$wpdb->query($wpdb->prepare("INSERT INTO $table (IP,timestamp,visits,lastvisit) 
							VALUES(%s,%d,%d,%d)",array($IP,$time,0,0)));

					}
					post_blacklist_add_multi($IPs);

				}

			}
			elseif(isset($_POST['id']))
			{
					$commentX=get_comment(sanitize_text_field($_POST['id']) );
					//print_r($commentX);
					$IP=$commentX->comment_author_IP;

					$IP_in_DP=$wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix."IPBLC_blacklist WHERE IP=%s",$IP));

					if(!$IP_in_DP)
					{
						$IPs[]=$IP;
					}

				if($IPs)
				{
					$IPs=array_unique($IPs);
					foreach($IPs as $IP)
					{
						$wpdb->query($wpdb->prepare("INSERT INTO $table (IP,timestamp,visits,lastvisit) 
							VALUES(%s,%d,%d,%d)",array($IP,$time,0,0)));

					}
					post_blacklist_add_multi($IPs);

				}
			}
		}

	}
}


function ipblc_notice()
{
	global $pagenow;
	$msg_array=array();
	$msg_array[]='Manage multiple WordPress sites with <a href="http://www.ip-finder.me/ipblc-server/">IPBLC Cloud Server</a> premium tool.';
	$msg_array[]='Tired of blocking same IP on multiple sites? <a href="http://www.ip-finder.me/ipblc-server/">IPBLC Cloud Server</a> is a solution.';
	$msg_array[]='<a href="http://www.ip-finder.me/ipblc-server/">IPBLC Cloud Server</a> - Synchronize IP addresses on multiple sites.';

	$show=0;
	if($pagenow == 'edit-comments.php')
	{
		$show=1;
			//---set emptry array---
			$start=0;
			$end=15;
			for($i=$start;$i<=$end;$i++)
			{
				$msg_array[]="";
			}

			echo '<div class="error mob_error"><p><b>IP Blacklist</b> - Columns visibility problem on mobile.</p></div>';
?>
<style>
@media screen and (min-width: 782px)
{
	.mob_error
	{
		display: none;
	}
}
</style>
<?php
	}
	elseif($pagenow == 'admin.php')
	{
		if(isset($_GET['page']))
		{
			if($_GET['page']=="wp-IPBLC-list" || $_GET['page']=="wp-IPBLC-Add")
			{
				$show=1;
			}
		}
	}

	$rand=array_rand($msg_array,2);
	$msg=$msg_array[$rand[0]];

	if($show==1 && $msg!="")
	{
		echo '<div class="updated"><p><b>'.$msg.'</b></p></div>';
	}
}
add_action('admin_notices', 'ipblc_notice');

?>