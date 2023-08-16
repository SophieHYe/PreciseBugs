<?php
/*
Plugin Name: CP Appointment Calendar
Plugin URI: http://wordpress.dwbooster.com/calendars/cp-appointment-calendar
Description: This plugin allows you to easily insert appointments forms into your WP website.
Version: 1.1.5
Author: CodePeople.net
Author URI: http://codepeople.net
License: GPL
*/


/* initialization / install / uninstall functions */


define('DEX_APPOINTMENTS_DEFAULT_CALENDAR_LANGUAGE', 'EN');
define('DEX_APPOINTMENTS_DEFAULT_CALENDAR_DATEFORMAT', '0');
define('DEX_APPOINTMENTS_DEFAULT_CALENDAR_MILITARYTIME', '1');
define('DEX_APPOINTMENTS_DEFAULT_CALENDAR_WEEKDAY', '0');
define('DEX_APPOINTMENTS_DEFAULT_CALENDAR_MINDATE', 'today');
define('DEX_APPOINTMENTS_DEFAULT_CALENDAR_MAXDATE', '');
define('DEX_APPOINTMENTS_DEFAULT_CALENDAR_PAGES', 1);

define('DEX_APPOINTMENTS_DEFAULT_ENABLE_PAYPAL', 1);
define('DEX_APPOINTMENTS_DEFAULT_PAYPAL_EMAIL','put_your@email_here.com');
define('DEX_APPOINTMENTS_DEFAULT_PRODUCT_NAME','Consultation');
define('DEX_APPOINTMENTS_DEFAULT_COST','25');
define('DEX_APPOINTMENTS_DEFAULT_OK_URL',get_site_url());
define('DEX_APPOINTMENTS_DEFAULT_CANCEL_URL',get_site_url());
define('DEX_APPOINTMENTS_DEFAULT_CURRENCY','USD');
define('DEX_APPOINTMENTS_DEFAULT_PAYPAL_LANGUAGE','EN');

define('DEX_APPOINTMENTS_DEFAULT_SUBJECT_CONFIRMATION_EMAIL', 'Thank you for your request...');
define('DEX_APPOINTMENTS_DEFAULT_CONFIRMATION_EMAIL', "We have received your request with the following information:\n\n%INFORMATION%\n\nThank you.\n\nBest regards.");
define('DEX_APPOINTMENTS_DEFAULT_SUBJECT_NOTIFICATION_EMAIL','New appointment requested...');
define('DEX_APPOINTMENTS_DEFAULT_NOTIFICATION_EMAIL', "New appointment made with the following information:\n\n%INFORMATION%\n\nBest regards.");


define('DEX_APPOINTMENTS_TABLE_NAME_NO_PREFIX', "dex_appointments");
define('DEX_APPOINTMENTS_TABLE_NAME', @$wpdb->prefix . DEX_APPOINTMENTS_TABLE_NAME_NO_PREFIX);

define('DEX_APPOINTMENTS_CALENDARS_TABLE_NAME_NO_PREFIX', "appointment_calendars_data");
define('DEX_APPOINTMENTS_CALENDARS_TABLE_NAME', @$wpdb->prefix ."appointment_calendars_data");

define('DEX_APPOINTMENTS_CONFIG_TABLE_NAME_NO_PREFIX', "appointment_calendars");
define('DEX_APPOINTMENTS_CONFIG_TABLE_NAME', @$wpdb->prefix ."appointment_calendars");

// calendar constants

define("TDE_APP_DEFAULT_CALENDAR_ID","1");
define("TDE_APP_DEFAULT_CALENDAR_LANGUAGE","EN");

define("TDE_APP_CAL_PREFIX", "cal");
define("TDE_APP_CONFIG",DEX_APPOINTMENTS_CONFIG_TABLE_NAME);
define("TDE_APP_CONFIG_ID","id");
define("TDE_APP_CONFIG_TITLE","title");
define("TDE_APP_CONFIG_USER","uname");
define("TDE_APP_CONFIG_PASS","passwd");
define("TDE_APP_CONFIG_LANG","lang");
define("TDE_APP_CONFIG_CPAGES","cpages");
define("TDE_APP_CONFIG_TYPE","ctype");
define("TDE_APP_CONFIG_MSG","msg");
define("TDE_APP_CONFIG_WORKINGDATES","workingDates");
define("TDE_APP_CONFIG_RESTRICTEDDATES","restrictedDates");
define("TDE_APP_CONFIG_TIMEWORKINGDATES0","timeWorkingDates0");
define("TDE_APP_CONFIG_TIMEWORKINGDATES1","timeWorkingDates1");
define("TDE_APP_CONFIG_TIMEWORKINGDATES2","timeWorkingDates2");
define("TDE_APP_CONFIG_TIMEWORKINGDATES3","timeWorkingDates3");
define("TDE_APP_CONFIG_TIMEWORKINGDATES4","timeWorkingDates4");
define("TDE_APP_CONFIG_TIMEWORKINGDATES5","timeWorkingDates5");
define("TDE_APP_CONFIG_TIMEWORKINGDATES6","timeWorkingDates6");
define("TDE_APP_CALDELETED_FIELD","caldeleted");

define("TDE_APP_CALENDAR_DATA_TABLE",DEX_APPOINTMENTS_CALENDARS_TABLE_NAME);
define("TDE_APP_DATA_ID","id");
define("TDE_APP_DATA_IDCALENDAR","appointment_calendar_id");
define("TDE_APP_DATA_DATETIME","datatime");
define("TDE_APP_DATA_TITLE","title");
define("TDE_APP_DATA_DESCRIPTION","description");
// end calendar constants


register_activation_hook(__FILE__,'dex_appointments_install'); 
register_deactivation_hook( __FILE__, 'dex_appointments_remove' );

function dex_appointments_install() {
    global $wpdb;
    
    
    $table_name = $wpdb->prefix . DEX_APPOINTMENTS_TABLE_NAME_NO_PREFIX;
      
    $sql = "CREATE TABLE $table_name (
         id mediumint(9) NOT NULL AUTO_INCREMENT,
         time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
         booked_time VARCHAR(250) DEFAULT '' NOT NULL,
         name VARCHAR(250) DEFAULT '' NOT NULL,
         email VARCHAR(250) DEFAULT '' NOT NULL,
         phone VARCHAR(250) DEFAULT '' NOT NULL,
         question text,
         buffered_date text,
         UNIQUE KEY id (id)
         );";
         
    $sql .= "CREATE TABLE `".$wpdb->prefix.DEX_APPOINTMENTS_CONFIG_TABLE_NAME."` (`".TDE_APP_CONFIG_ID."` int(10) unsigned NOT NULL auto_increment,`".TDE_APP_CONFIG_TITLE."` varchar(255) NOT NULL default '',`".TDE_APP_CONFIG_USER."` varchar(100) default NULL,`".TDE_APP_CONFIG_PASS."` varchar(100) default NULL,`".TDE_APP_CONFIG_LANG."` varchar(5) default NULL,`".TDE_APP_CONFIG_CPAGES."` tinyint(3) unsigned default NULL,`".TDE_APP_CONFIG_TYPE."` tinyint(3) unsigned default NULL,`".TDE_APP_CONFIG_MSG."` varchar(255) NOT NULL default '',`".TDE_APP_CONFIG_WORKINGDATES."` varchar(255) NOT NULL default '',`".TDE_APP_CONFIG_RESTRICTEDDATES."` text,`".TDE_APP_CONFIG_TIMEWORKINGDATES0."` text,`".TDE_APP_CONFIG_TIMEWORKINGDATES1."` text,`".TDE_APP_CONFIG_TIMEWORKINGDATES2."` text,`".TDE_APP_CONFIG_TIMEWORKINGDATES3."` text,`".TDE_APP_CONFIG_TIMEWORKINGDATES4."` text,`".TDE_APP_CONFIG_TIMEWORKINGDATES5."` text,`".TDE_APP_CONFIG_TIMEWORKINGDATES6."` text,`".TDE_APP_CALDELETED_FIELD."` tinyint(3) unsigned default NULL,PRIMARY KEY (`".TDE_APP_CONFIG_ID."`)); ";
    $sql .= "CREATE TABLE `".$wpdb->prefix.DEX_APPOINTMENTS_CALENDARS_TABLE_NAME."` (`".TDE_APP_DATA_ID."` int(10) unsigned NOT NULL auto_increment,`".TDE_APP_DATA_IDCALENDAR."` int(10) unsigned default NULL,`".TDE_APP_DATA_DATETIME."`datetime NOT NULL default '0000-00-00 00:00:00',`".TDE_APP_DATA_TITLE."` varchar(250) default NULL,`".TDE_APP_DATA_DESCRIPTION."` text,PRIMARY KEY (`".TDE_APP_DATA_ID."`)) ;";
    $sql .= 'INSERT INTO `'.$wpdb->prefix.DEX_APPOINTMENTS_CONFIG_TABLE_NAME.'` (`'.TDE_APP_CONFIG_ID.'`,`'.TDE_APP_CONFIG_TITLE.'`,`'.TDE_APP_CONFIG_USER.'`,`'.TDE_APP_CONFIG_PASS.'`,`'.TDE_APP_CONFIG_LANG.'`,`'.TDE_APP_CONFIG_CPAGES.'`,`'.TDE_APP_CONFIG_TYPE.'`,`'.TDE_APP_CONFIG_MSG.'`,`'.TDE_APP_CONFIG_WORKINGDATES.'`,`'.TDE_APP_CONFIG_RESTRICTEDDATES.'`,`'.TDE_APP_CONFIG_TIMEWORKINGDATES0.'`,`'.TDE_APP_CONFIG_TIMEWORKINGDATES1.'`,`'.TDE_APP_CONFIG_TIMEWORKINGDATES2.'`,`'.TDE_APP_CONFIG_TIMEWORKINGDATES3.'`,`'.TDE_APP_CONFIG_TIMEWORKINGDATES4.'`,`'.TDE_APP_CONFIG_TIMEWORKINGDATES5.'`,`'.TDE_APP_CONFIG_TIMEWORKINGDATES6.'`,`'.TDE_APP_CALDELETED_FIELD.'`) VALUES("1","cal1","","","ENG","1","3","Please, select your appointment.","1,2,3,4,5","","","9:0,10:0,11:0,12:0,13:0,14:0,15:0,16:0","9:0,10:0,11:0,12:0,13:0,14:0,15:0,16:0","9:0,10:0,11:0,12:0,13:0,14:0,15:0,16:0","9:0,10:0,11:0,12:0,13:0,14:0,15:0,16:0","9:0,10:0,11:0,12:0,13:0,14:0,15:0,16:0","","0");';  

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);    
    
    add_option("dex_appointments_data", 'Default', '', 'yes'); // Creates new database field 
}

function dex_appointments_remove() {    
    delete_option('dex_appointments_data'); // Deletes the database field 
}


/* Filter for placing the maps into the contents */

add_filter('the_content','dex_appointments_filter_content');

function dex_appointments_filter_content($content) {
    
    if (strpos($content, "[APPOINTMENT_CALENDAR_FORM_WILL_APPEAR_HERE]") !== false) 
    {        
        ob_start();
        define('DEX_AUTH_INCLUDE', true);
        @include dirname( __FILE__ ) . '/dex_scheduler.inc.php';
        $buffered_contents = ob_get_contents();
        ob_end_clean();

        $content = str_replace("[APPOINTMENT_CALENDAR_FORM_WILL_APPEAR_HERE]", $buffered_contents, $content);
    }    
    return $content;
}


function dex_appointments_show_booking_form($id = "")
{
    if ($id != '')
        define ('DEX_CALENDAR_FIXED_ID',$id);
    define('DEX_AUTH_INCLUDE', true);
    @include dirname( __FILE__ ) . '/dex_scheduler.inc.php';    
}

/* Code for the admin area */

if ( is_admin() ) {
    add_action('media_buttons', 'set_dex_apps_insert_button', 100);
    add_action('admin_enqueue_scripts', 'set_dex_apps_insert_adminScripts', 1);
    add_action('admin_menu', 'dex_appointments_admin_menu');
    add_action('admin_init', 'register_mysettings' );
    
    $plugin = plugin_basename(__FILE__);        
    add_filter("plugin_action_links_".$plugin, 'dex_customAdjustmentsLink');    
    add_filter("plugin_action_links_".$plugin, 'dex_settingsLink');
    add_filter("plugin_action_links_".$plugin, 'dex_helpLink');
    
    
    

    function register_mysettings() { // whitelist options
      
      register_setting( 'dex-appointments-group', 'calendar_language' );
      register_setting( 'dex-appointments-group', 'calendar_dateformat' );
      register_setting( 'dex-appointments-group', 'calendar_militarytime' );
      register_setting( 'dex-appointments-group', 'calendar_weekday' );
      register_setting( 'dex-appointments-group', 'calendar_mindate' );
      register_setting( 'dex-appointments-group', 'calendar_maxdate' );        
      register_setting( 'dex-appointments-group', 'calendar_pages' );
        
      register_setting( 'dex-appointments-group', 'enable_paypal' );
      register_setting( 'dex-appointments-group', 'paypal_email' );
      register_setting( 'dex-appointments-group', 'request_cost' );
      register_setting( 'dex-appointments-group', 'paypal_product_name' );
      register_setting( 'dex-appointments-group', 'currency' );
      register_setting( 'dex-appointments-group', 'url_ok' );
      register_setting( 'dex-appointments-group', 'url_cancel' );
      register_setting( 'dex-appointments-group', 'paypal_language' );
      
      register_setting( 'dex-appointments-group', 'notification_from_email' );
      register_setting( 'dex-appointments-group', 'notification_destination_email' );
      register_setting( 'dex-appointments-group', 'email_subject_confirmation_to_user' );
      register_setting( 'dex-appointments-group', 'email_confirmation_to_user' );
      register_setting( 'dex-appointments-group', 'email_subject_notification_to_admin' );
      register_setting( 'dex-appointments-group', 'email_notification_to_admin' );
    }

    function dex_appointments_admin_menu() {                
        add_options_page('CP Appointment Calendar Options', 'CP Appointment Calendar', 'manage_options', 'dex_appointments', 'dex_appointments_html_post_page' );
    }
}

function dex_settingsLink($links) {
    $settings_link = '<a href="options-general.php?page=dex_appointments">'.__('Settings').'</a>'; 
	array_unshift($links, $settings_link);     
	return $links;
}

function dex_helpLink($links) {
    $help_link = '<a href="http://wordpress.dwbooster.com/calendars/cp-appointment-calendar">'.__('Help').'</a>'; 
	array_unshift($links, $help_link);     
	return $links;
}

function dex_customAdjustmentsLink($links) {
    $customAdjustments_link = '<a href="http://wordpress.dwbooster.com/contact-us">'.__('Request custom changes').'</a>'; 
	array_unshift($links, $customAdjustments_link);     
	return $links;
}

function dex_appointments_html_post_page() {
    @include_once dirname( __FILE__ ) . '/dex_appointments_admin_int.inc.php';

}

function set_dex_apps_insert_button() {
    print '<a href="javascript:dex_appointments_insertCalendar();" title="'.__('Insert Appointment Calendar').'"><img hspace="5" src="'.plugins_url('/images/dex_apps.gif', __FILE__).'" alt="'.__('Insert  Appointment Calendar').'" /></a>';    
} 

function set_dex_apps_insert_adminScripts($hook) { 
    if( 'post.php' != $hook  && 'post-new.php' != $hook )
        return;
    wp_enqueue_script( 'my_custom_script', plugins_url('/dex_script.js', __FILE__) );
}


/* hook for checking posted data for the admin area */

add_action( 'init', 'dex_appointments_check_posted_data', 11 );

function dex_appointments_check_posted_data() {
	
    global $wpdb;
    
	if ( 'POST' != $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['dex_appointments_post'] ) )		
		return;

    $_POST["dateAndTime"] =  $_POST["selYearcal1"]."-".$_POST["selMonthcal1"]."-".$_POST["selDaycal1"]." ".$_POST["selHourcal1"].":".$_POST["selMinutecal1"];
    $_POST["Date"] = date("m/d/Y H:i",strtotime($_POST["dateAndTime"]));
    
   
    $buffer = $_POST["selYearcal1"].",".$_POST["selMonthcal1"].",".$_POST["selDaycal1"]."\n".
    $_POST["selHourcal1"].":".($_POST["selMinutecal1"]<10?"0":"").$_POST["selMinutecal1"]."\n".
    "Name: ".$_POST["name"]."\n".
    "Email: ".$_POST["email"]."\n".
    "Phone: ".$_POST["phone"]."\n".
    "Question: ".$_POST["question"]."\n".
    "*-*\n";
	
    $rows_affected = $wpdb->insert( DEX_APPOINTMENTS_TABLE_NAME, array( 'time' => current_time('mysql'), 
                                                                        'booked_time' => $_POST["Date"], 
                                                                        'name' => $_POST["name"], 
                                                                        'email' => $_POST["email"], 
                                                                        'phone' => $_POST["phone"], 
                                                                        'question' => $_POST["question"], 
                                                                        'buffered_date' => $_POST["dateAndTime"]
                                                                         ) );
    if (!$rows_affected)
    {
        echo 'Error saving data! Please try again.';
        exit;
    }
    
    
    $myrows = $wpdb->get_results( "SELECT MAX(id) as max_id FROM ".DEX_APPOINTMENTS_TABLE_NAME );                                                                     
     	
 	// save data here 	
    $item_number = $myrows[0]->max_id; 
    

?>
<html>
<head><title>Redirecting to Paypal...</title></head>
<body>
<form action="https://www.paypal.com/cgi-bin/webscr" name="ppform3" method="post">
<input type="hidden" name="cmd" value="_xclick" />
<input type="hidden" name="business" value="<?php echo get_option('paypal_email', DEX_APPOINTMENTS_DEFAULT_PAYPAL_EMAIL); ?>" />
<input type="hidden" name="item_name" value="<?php echo get_option('paypal_product_name', DEX_APPOINTMENTS_DEFAULT_PRODUCT_NAME); ?>" />
<input type="hidden" name="item_number" value="<?php echo $item_number; ?>" />
<input type="hidden" name="amount" value="<?php echo get_option('request_cost', DEX_APPOINTMENTS_DEFAULT_COST); ?>" />
<input type="hidden" name="page_style" value="Primary" />
<input type="hidden" name="no_shipping" value="1" />
<input type="hidden" name="return" value="<?php echo get_option('url_ok', DEX_APPOINTMENTS_DEFAULT_OK_URL); ?>">
<input type="hidden" name="cancel_return" value="<?php echo get_option('url_cancel', DEX_APPOINTMENTS_DEFAULT_CANCEL_URL); ?>" />
<input type="hidden" name="no_note" value="1" />
<input type="hidden" name="currency_code" value="<?php echo strtoupper(get_option('currency', DEX_APPOINTMENTS_DEFAULT_CURRENCY)); ?>" />
<input type="hidden" name="lc" value="<?php echo get_option('paypal_language', DEX_APPOINTMENTS_DEFAULT_PAYPAL_LANGUAGE); ?>" />
<input type="hidden" name="bn" value="NetFactorSL_SI_Custom" />
<input type="hidden" name="notify_url" value="<?php echo cp_appointment_get_FULL_site_url(); ?>/?ipncheck=1&itemnumber=<?php echo $item_number; ?>" />
<input type="hidden" name="ipn_test" value="1" />
<input class="pbutton" type="hidden" value="Buy Now" /></div>
</form>
<script type="text/javascript">
document.ppform3.submit();
</script>  
</body>
</html> 
<?php
        exit();

} 

add_action( 'init', 'dex_appointments_check_IPN_verification', 11 );

function dex_appointments_check_IPN_verification() {    
    
    global $wpdb;
  
	if ( ! isset( $_GET['ipncheck'] ) || $_GET['ipncheck'] != '1' ||  ! isset( $_GET["itemnumber"] ) )
		return;
		
    $item_name = $_POST['item_name'];
    $item_number = $_POST['item_number'];
    $payment_status = $_POST['payment_status'];
    $payment_amount = $_POST['mc_gross'];
    $payment_currency = $_POST['mc_currency'];
    $txn_id = $_POST['txn_id'];
    $receiver_email = $_POST['receiver_email'];
    $payer_email = $_POST['payer_email'];
    $payment_type = $_POST['payment_type'];
		
		
    if (strtolower($payment_status) != 'completed' && strtolower($payment_type) != 'echeck')
        return;
	    
    if (strtolower($payment_type) == 'echeck' && strtolower($payment_status) == 'completed')    
        return;            	

    dex_process_ready_to_go_appointment($_GET["itemnumber"], $payer_email);    
    
    echo 'OK';
    
    exit();
		    
}

function dex_process_ready_to_go_appointment($itemnumber, $payer_email = "")
{
   global $wpdb;
   
   $myrows = $wpdb->get_results( "SELECT * FROM ".DEX_APPOINTMENTS_TABLE_NAME." WHERE id=".$itemnumber );   
       
   $SYSTEM_EMAIL = get_option('notification_from_email', DEX_APPOINTMENTS_DEFAULT_PAYPAL_EMAIL);
   $SYSTEM_RCPT_EMAIL = get_option('notification_destination_email', DEX_APPOINTMENTS_DEFAULT_PAYPAL_EMAIL);
   
    
   $email_subject1 = get_option('email_subject_confirmation_to_user', DEX_APPOINTMENTS_DEFAULT_SUBJECT_CONFIRMATION_EMAIL);
   $email_content1 = get_option('email_confirmation_to_user', DEX_APPOINTMENTS_DEFAULT_CONFIRMATION_EMAIL);
   $email_subject2 = get_option('email_subject_notification_to_admin', DEX_APPOINTMENTS_DEFAULT_SUBJECT_NOTIFICATION_EMAIL);
   $email_content2 = get_option('email_notification_to_admin', DEX_APPOINTMENTS_DEFAULT_NOTIFICATION_EMAIL);
   
   $information = $myrows[0]->booked_time."\n".
                  $myrows[0]->name."\n".  
                  $myrows[0]->email."\n".  
                  $myrows[0]->phone."\n".  
                  $myrows[0]->question."\n";
   
   $email_content1 = str_replace("%INFORMATION%", $information, $email_content1);
   $email_content2 = str_replace("%INFORMATION%", $information, $email_content2);
   
   // SEND EMAIL TO USER 
   wp_mail($myrows[0]->email, $email_subject1, $email_content1,
            "From: \"$SYSTEM_EMAIL\" <".$SYSTEM_EMAIL.">\r\n".
            "Content-Type: text/plain; charset=utf-8\n".
            "X-Mailer: PHP/" . phpversion());
            
   if ($payer_email && $payer_email != $myrows[0]->email)         
       wp_mail($payer_email , $email_subject1, $email_content1,
                "From: \"$SYSTEM_EMAIL\" <".$SYSTEM_EMAIL.">\r\n".
                "Content-Type: text/plain; charset=utf-8\n".
                "X-Mailer: PHP/" . phpversion());

   // SEND EMAIL TO ADMIN
   wp_mail($SYSTEM_RCPT_EMAIL, $email_subject2, $email_content2,
            "From: \"$SYSTEM_EMAIL\" <".$SYSTEM_EMAIL.">\r\n".
            "Reply-To: \"".$myrows[0]->email."\" <".$myrows[0]->email.">\r\n".
            "Content-Type: text/plain; charset=utf-8\n".
            "X-Mailer: PHP/" . phpversion());      
            
            
    $rows_affected = $wpdb->insert( TDE_APP_CALENDAR_DATA_TABLE, array( 'appointment_calendar_id' => TDE_APP_DEFAULT_CALENDAR_ID, 
                                                                        'datatime' => date("Y-m-d H:i:s", strtotime($myrows[0]->buffered_date)), 
                                                                        'title' => $myrows[0]->email, 
                                                                        'description' => str_replace("\n","<br />", $information)
                                                                         ) );             
   
   // $fp = fopen(dirname( __FILE__ ) .'/TDE_AppCalendar/admin/database/cal1data.txt', 'a');                                                              
   // fwrite($fp, $myrows[0]->buffered_date);
   // fclose($fp);	 
    

}


add_action( 'init', 'dex_appointments_calendar_load', 11 );
add_action( 'init', 'dex_appointments_calendar_load2', 11 );
add_action( 'init', 'dex_appointments_calendar_update', 11 );
add_action( 'init', 'dex_appointments_calendar_update2', 11 );

function dex_appointments_calendar_load() {        
    global $wpdb;  
	if ( ! isset( $_GET['calendar_load'] ) || $_GET['calendar_load'] != '1' )
		return;
    @ob_clean();
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");        
    $calid = str_replace  (TDE_APP_CAL_PREFIX, "",$_GET["id"]);
    $query = "SELECT * FROM ".TDE_APP_CONFIG." where ".TDE_APP_CONFIG_ID."='".$calid."'";    
    $row = $wpdb->get_results($query,ARRAY_A);
    if ($row[0])
    {
        echo $row[0][TDE_APP_CONFIG_WORKINGDATES].";";
        echo $row[0][TDE_APP_CONFIG_RESTRICTEDDATES].";";
        echo $row[0][TDE_APP_CONFIG_TIMEWORKINGDATES0].";";
        echo $row[0][TDE_APP_CONFIG_TIMEWORKINGDATES1].";";
        echo $row[0][TDE_APP_CONFIG_TIMEWORKINGDATES2].";";
        echo $row[0][TDE_APP_CONFIG_TIMEWORKINGDATES3].";";
        echo $row[0][TDE_APP_CONFIG_TIMEWORKINGDATES4].";";
        echo $row[0][TDE_APP_CONFIG_TIMEWORKINGDATES5].";";
        echo $row[0][TDE_APP_CONFIG_TIMEWORKINGDATES6].";";
    }
    
    exit();		    
}

function dex_appointments_calendar_load2() {        
    global $wpdb;  
	if ( ! isset( $_GET['calendar_load2'] ) || $_GET['calendar_load2'] != '1' )
		return;
    @ob_clean();
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");      
    $calid = str_replace  (TDE_APP_CAL_PREFIX, "",$_GET["id"]);
    $query = "SELECT * FROM ".TDE_APP_CALENDAR_DATA_TABLE." where ".TDE_APP_DATA_IDCALENDAR."='".$calid."'";
    $row_array = $wpdb->get_results($query,ARRAY_A);
    foreach ($row_array as $row)
    {
        echo $row[TDE_APP_DATA_ID]."\n";
        $dn =  explode(" ", $row[TDE_APP_DATA_DATETIME]);
        $d1 =  explode("-", $dn[0]);
        $d2 =  explode(":", $dn[1]);
        
        echo intval($d1[0]).",".intval($d1[1]).",".intval($d1[2])."\n";
        echo intval($d2[0]).":".($d2[1])."\n";
        echo $row[TDE_APP_DATA_TITLE]."\n";
        echo $row[TDE_APP_DATA_DESCRIPTION]."\n*-*\n";
    }
  
    exit();		    
}

function dex_appointments_calendar_update() {                
    global $wpdb, $user_ID;   
    
    if ( ! current_user_can('manage_options') )
        return;
            
	if ( ! isset( $_GET['calendar_update'] ) || $_GET['calendar_update'] != '1' )
		return;
    @ob_clean();
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");      
    if ( $user_ID )
    {  
        $calid = str_replace  (TDE_APP_CAL_PREFIX, "",$_GET["id"]);
        $wpdb->query("update  ".TDE_APP_CONFIG." set ".TDE_APP_CONFIG_WORKINGDATES."='".$_POST["workingDates"]."',".TDE_APP_CONFIG_RESTRICTEDDATES."='".$_POST["restrictedDates"]."',".TDE_APP_CONFIG_TIMEWORKINGDATES0."='".$_POST["timeWorkingDates0"]."',".TDE_APP_CONFIG_TIMEWORKINGDATES1."='".$_POST["timeWorkingDates1"]."',".TDE_APP_CONFIG_TIMEWORKINGDATES2."='".$_POST["timeWorkingDates2"]."',".TDE_APP_CONFIG_TIMEWORKINGDATES3."='".$_POST["timeWorkingDates3"]."',".TDE_APP_CONFIG_TIMEWORKINGDATES4."='".$_POST["timeWorkingDates4"]."',".TDE_APP_CONFIG_TIMEWORKINGDATES5."='".$_POST["timeWorkingDates5"]."',".TDE_APP_CONFIG_TIMEWORKINGDATES6."='".$_POST["timeWorkingDates6"]."'  where ".TDE_APP_CONFIG_ID."=".$calid);   
    }
    
    exit();		    
}

function dex_appointments_calendar_update2() {        
    global $wpdb, $user_ID;  
    
    if ( ! current_user_can('manage_options') )
        return;
            
	if ( ! isset( $_GET['calendar_update2'] ) || $_GET['calendar_update2'] != '1' )
		return;
    @ob_clean();
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");  
    if ( $user_ID )
    {   
        if ($_GET["act"]=='del')
        {
            $calid = str_replace  (TDE_APP_CAL_PREFIX, "",$_GET["id"]);
            $wpdb->query("delete from ".TDE_APP_CALENDAR_DATA_TABLE." where ".TDE_APP_DATA_IDCALENDAR."=".$calid." and ".TDE_APP_DATA_ID."=".$_POST["sqlId"]);
            
        }
        else if ($_GET["act"]=='edit')
        {
            $calid = str_replace  (TDE_APP_CAL_PREFIX, "",$_GET["id"]);
            $data = explode("\n", $_POST["appoiments"]);
            $d1 =  explode(",", $data[0]);
            $d2 =  explode(":", $data[1]);
	        $datetime = $d1[0]."-".$d1[1]."-".$d1[2]." ".$d2[0].":".$d2[1];
	        $title = $data[2];
            $description = "";
            for ($j=3;$j<count($data);$j++)
            {
                $description .= $data[$j];
                if ($j!=count($data)-1)
                    $description .= "\n";
            }
            $wpdb->query("update  ".TDE_APP_CALENDAR_DATA_TABLE." set ".TDE_APP_DATA_DATETIME."='".$datetime."',".TDE_APP_DATA_TITLE."='".esc_sql($title)."',".TDE_APP_DATA_DESCRIPTION."='".esc_sql($description)."'  where ".TDE_APP_DATA_IDCALENDAR."=".$calid." and ".TDE_APP_DATA_ID."=".$_POST["sqlId"]);
        }
        else if ($_GET["act"]=='add')
        {
            $calid = str_replace  (TDE_APP_CAL_PREFIX, "",$_GET["id"]);
            $data = explode("\n", $_POST["appoiments"]);
            $d1 =  explode(",", $data[0]);
            $d2 =  explode(":", $data[1]);
	        $datetime = $d1[0]."-".$d1[1]."-".$d1[2]." ".$d2[0].":".$d2[1];
	        $title = $data[2];
            $description = "";
            for ($j=3;$j<count($data);$j++)
            {
                $description .= $data[$j];
                if ($j!=count($data)-1)
                    $description .= "\n";
            }
            $wpdb->query("insert into ".TDE_APP_CALENDAR_DATA_TABLE."(".TDE_APP_DATA_IDCALENDAR.",".TDE_APP_DATA_DATETIME.",".TDE_APP_DATA_TITLE.",".TDE_APP_DATA_DESCRIPTION.") values(".$calid.",'".$datetime."','".esc_sql($title)."','".esc_sql($description)."') "); 
            echo  $wpdb->insert_id;
            
        }
    }
    
    exit();		    
}


function cp_appointment_get_site_url()
{
    $url = parse_url(get_site_url());
    $url = rtrim($url["path"],"/");
    return $url;
}

function cp_appointment_get_FULL_site_url()
{
    $url = parse_url(get_site_url());
    $url = rtrim($url["path"],"/");
    $pos = strpos($url, "://");    
    if ($pos === false)
        $url = 'http://'.$_SERVER["HTTP_HOST"].$url;
    return $url;
}

?>