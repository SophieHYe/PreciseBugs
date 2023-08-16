<?php
/*
Plugin Name: Mail Subscribe List
Plugin URI: http://www.webfwd.co.uk/packages/wordpress-hosting/
Description: Simple customisable plugin that displays a name/email form where visitors can submit their information, managable in the WordPress admin.
Version: 2.0.9
Author: Richard Leishman t/a Webforward
Author URI: http://www.webfwd.co.uk/
License: GPL


Copyright 2012 Richard Leishman t/a Webforward  (email : richard@webfwd.co.uk)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

GNU General Public License: http://www.gnu.org/licenses/gpl.html

*/

// Plugin Activation
function sml_install() {
    global $wpdb;
    $table = $wpdb->prefix."sml";
    $structure = "CREATE TABLE $table (
        id INT(9) NOT NULL AUTO_INCREMENT,
        sml_name VARCHAR(200) NOT NULL,
        sml_email VARCHAR(200) NOT NULL,
	UNIQUE KEY id (id)
    );";
    $wpdb->query($structure);
	
}
register_activation_hook( __FILE__, 'sml_install' );

// Plugin Deactivation
function sml_uninstall() {
    global $wpdb;
	
}
register_deactivation_hook( __FILE__, 'sml_uninstall' );

// Left Menu Button
function register_sml_menu() {
	add_menu_page('Subscribers', 'Subscribers', 'add_users', dirname(__FILE__).'/index.php', '',   plugins_url('sml-admin-icon.png', __FILE__), 58.122);
}
add_action('admin_menu', 'register_sml_menu');

// Generate Subscribe Form 

function smlsubform($atts=array()){
	extract(shortcode_atts(array(
		"prepend" => '',  
        "showname" => true,
		"nametxt" => 'Name:',
		"nameholder" => 'Name...',
		"emailtxt" => 'Email:',
		"emailholder" => 'Email Address...',
		"showsubmit" => true,
		"submittxt" => 'Submit',
		"jsthanks" => false,
		"thankyou" => 'Thank you for subscribing to our mailing list'
    ), $atts));
	
	$return = '<form class="sml_subscribe" method="post"><input class="sml_hiddenfield" name="sml_subscribe" type="hidden" value="1">';
	
	if ($prepend) $return .= '<p class="prepend">'.$prepend.'</p>';
	
	if ($_POST['sml_subscribe'] && $thankyou) { 
		if ($jsthanks) {
			$return .= "<script>window.onload = function() { alert('".$thankyou."'); }</script>";
		} else {
			$return .= '<p class="sml_thankyou">'.$thankyou.'</p>'; 
		}
	}
	
	
	if ($showname) $return .= '<p class="sml_name"><label class="sml_namelabel" for="sml_name">'.$nametxt.'</label><input class="sml_nameinput" placeholder="'.$nameholder.'" name="sml_name" type="text" value=""></p>';
	$return .= '<p class="sml_email"><label class="sml_emaillabel" for="sml_email">'.$emailtxt.'</label><input class="sml_emailinput" name="sml_email" placeholder="'.$emailholder.'" type="text" value=""></p>';
	if ($showsubmit) $return .= '<p class="sml_submit"><input name="submit" class="btn sml_submitbtn" type="submit" value="'.($submittxt?$submittxt:'Submit').'"></p>';
	$return .= '</form>';
	
 	return $return;
}
add_shortcode( 'smlsubform', 'smlsubform' );

// Ability to use the shortcode within the text widget, - Suggested by Joel Dare, Thank you.
add_filter('widget_text', 'do_shortcode', 11);

//////

// Lets create a Wordpress Widget

// Widget Controller

function sml_subscribe_widget_control($args=array(), $params=array()) {
	
	if (isset($_POST['sml_subscribe_submitted']) && current_user_can('edit_theme_options')) {
		update_option('sml_subscribe_widget_title', $_POST['sml_subscribe_widget_title']);
		update_option('sml_subscribe_widget_prepend', $_POST['sml_subscribe_widget_prepend']);
		update_option('sml_subscribe_widget_jsthanks', $_POST['sml_subscribe_widget_jsthanks']);
		update_option('sml_subscribe_widget_thankyou', $_POST['sml_subscribe_widget_thankyou']);
		update_option('sml_subscribe_widget_showname', $_POST['sml_subscribe_widget_showname']);
		update_option('sml_subscribe_widget_nametxt', $_POST['sml_subscribe_widget_nametxt']);
		update_option('sml_subscribe_widget_nameholder', $_POST['sml_subscribe_widget_nameholder']);
		update_option('sml_subscribe_widget_emailtxt', $_POST['sml_subscribe_widget_emailtxt']);
		update_option('sml_subscribe_widget_emailholder', $_POST['sml_subscribe_widget_emailholder']);
		update_option('sml_subscribe_widget_showsubmit', $_POST['sml_subscribe_widget_showsubmit']);
		update_option('sml_subscribe_widget_submittxt', $_POST['sml_subscribe_widget_submittxt']);
	}
	
	$sml_subscribe_widget_title = get_option('sml_subscribe_widget_title');
	$sml_subscribe_widget_prepend = get_option('sml_subscribe_widget_prepend');
	$sml_subscribe_widget_jsthanks = get_option('sml_subscribe_widget_jsthanks');
	$sml_subscribe_widget_thankyou = get_option('sml_subscribe_widget_thankyou');
	$sml_subscribe_widget_showname = get_option('sml_subscribe_widget_showname');
	$sml_subscribe_widget_nametxt = get_option('sml_subscribe_widget_nametxt');
	$sml_subscribe_widget_nameholder = get_option('sml_subscribe_widget_nameholder');
	$sml_subscribe_widget_emailtxt = get_option('sml_subscribe_widget_emailtxt');
	$sml_subscribe_widget_emailholder = get_option('sml_subscribe_widget_emailholder');
	$sml_subscribe_widget_showsubmit = get_option('sml_subscribe_widget_showsubmit');
	$sml_subscribe_widget_submittxt = get_option('sml_subscribe_widget_submittxt');
	?>

	Title:<br />
	<textarea class="widefat sml_subscribe_widget_title" rows="5" name="sml_subscribe_widget_title"><?php echo stripslashes($sml_subscribe_widget_title); ?></textarea>
	<br /><br />

	Header Text:<br />
	<textarea class="widefat sml_subscribe_widget_prepend" rows="5" name="sml_subscribe_widget_prepend"><?php echo stripslashes($sml_subscribe_widget_prepend); ?></textarea>
	<br /><br />
    
    Thank You Type 
	<select class="sml_subscribe_widget_jsthanks" name="sml_subscribe_widget_jsthanks">
    	<option <?php echo ($sml_subscribe_widget_jsthanks?'selected="selected"':''); ?> value="1">JavaScript Alert</option>
        <option <?php echo (!$sml_subscribe_widget_jsthanks?'selected="selected"':''); ?> value="0">Widget Header</option>
    </select>
	<br /><br />
    
    Thank You Message<br />
	<textarea class="widefat sml_subscribe_widget_thankyou" rows="5" name="sml_subscribe_widget_thankyou"><?php echo stripslashes($sml_subscribe_widget_thankyou); ?></textarea>
	<br /><br />
    
    Show Name Field <input class="sml_subscribe_widget_showname" name="sml_subscribe_widget_showname" type="checkbox"<?php echo $sml_subscribe_widget_showname?'checked="checked"':''; ?> />
	<br /><br />
    
    <div class="sml_subscribe_nameoptions" style="display:none">
    
    Name Label text
	<input type="text" class="widefat sml_subscribe_widget_nametxt" name="sml_subscribe_widget_nametxt" value="<?php echo stripslashes($sml_subscribe_widget_nametxt); ?>" />
	<br /><br />
    
    Name Placeholder Text
	<input type="text" class="widefat sml_subscribe_widget_nameholder" name="sml_subscribe_widget_nameholder" value="<?php echo stripslashes($sml_subscribe_widget_nameholder); ?>" />
	<br /><br />
    
    </div>
    
    Email Label Text
	<input type="text" class="widefat sml_subscribe_widget_emailtxt" name="sml_subscribe_widget_emailtxt" value="<?php echo stripslashes($sml_subscribe_widget_emailtxt); ?>" />
	<br /><br />
    
    Email Placeholder Text
	<input type="text" class="widefat sml_subscribe_widget_emailholder" name="sml_subscribe_widget_emailholder" value="<?php echo stripslashes($sml_subscribe_widget_emailholder); ?>" />
	<br /><br />
    
    Show Submit Button <input class="sml_subscribe_widget_showsubmit" name="sml_subscribe_widget_showsubmit" type="checkbox"<?php echo $sml_subscribe_widget_showsubmit?'checked="checked"':''; ?> />
	<br /><br />
    
    <div class="sml_subscribe_submitoptions" style="display:none">
    
    Submit Button Text
	<input type="text" class="widefat sml_subscribe_widget_submittxt" name="sml_subscribe_widget_submittxt" value="<?php echo stripslashes($sml_subscribe_widget_submittxt); ?>" />
	<br /><br />
    
    </div>

	<input type="hidden" name="sml_subscribe_submitted" value="1" />
    <script>
		function sml_subscribe_nameoptions_check() {
			if (jQuery('.sml_subscribe_widget_showname').is(':checked')) jQuery(".sml_subscribe_nameoptions").fadeIn();
			else jQuery(".sml_subscribe_nameoptions").fadeOut();
		}
		function sml_subscribe_submitoptions_check() {
			if (jQuery('.sml_subscribe_widget_showsubmit').is(':checked')) jQuery(".sml_subscribe_submitoptions").fadeIn();
			else jQuery(".sml_subscribe_submitoptions").fadeOut();
		}
		jQuery(document).ready(function(){
			sml_subscribe_nameoptions_check();
			sml_subscribe_submitoptions_check();
			jQuery(".sml_subscribe_widget_showname").click(function(){ sml_subscribe_nameoptions_check(); });
			jQuery(".sml_subscribe_widget_showsubmit").click(function(){ sml_subscribe_submitoptions_check(); });
		});
    </script>
	<?php
}

wp_register_widget_control(
	'sml_subscribe_widget',
	'sml_subscribe_widget',
	'sml_subscribe_widget_control'
);

// Widget Display

function sml_subscribe_widget_display($args=array(), $params=array()) {

	$sml_subscribe_widget_title = get_option('sml_subscribe_widget_title');
	$sml_subscribe_widget_prepend = get_option('sml_subscribe_widget_prepend');
	$sml_subscribe_widget_jsthanks = get_option('sml_subscribe_widget_jsthanks');
	$sml_subscribe_widget_thankyou = get_option('sml_subscribe_widget_thankyou');
	$sml_subscribe_widget_showname = get_option('sml_subscribe_widget_showname');
	$sml_subscribe_widget_nametxt = get_option('sml_subscribe_widget_nametxt');
	$sml_subscribe_widget_nameholder = get_option('sml_subscribe_widget_nameholder');
	$sml_subscribe_widget_emailtxt = get_option('sml_subscribe_widget_emailtxt');
	$sml_subscribe_widget_emailholder = get_option('sml_subscribe_widget_emailholder');
	$sml_subscribe_widget_showsubmit = get_option('sml_subscribe_widget_showsubmit');
	$sml_subscribe_widget_submittxt = get_option('sml_subscribe_widget_submittxt');

	//widget output
	echo stripslashes($args['before_widget']);

	echo stripslashes($args['before_title']);
	echo stripslashes($sml_subscribe_widget_title);
	echo stripslashes($args['after_title']);

	echo '<div class="textwidget">';

	$argss = array(
		'prepend' => $sml_subscribe_widget_prepend, 
		'showname' => $sml_subscribe_widget_showname,
		'nametxt' => $sml_subscribe_widget_nametxt, 
		'nameholder' => $sml_subscribe_widget_nameholder, 
		'emailtxt' => $sml_subscribe_widget_emailtxt,
		'emailholder' => $sml_subscribe_widget_emailholder, 
		'showsubmit' => $sml_subscribe_widget_showsubmit,
		'submittxt' => $sml_subscribe_widget_submittxt, 
		'jsthanks' => $sml_subscribe_widget_jsthanks,
		'thankyou' => $sml_subscribe_widget_thankyou
	);
	echo smlsubform($argss);

	echo '</div>';
  echo stripslashes($args['after_widget']);
}

wp_register_sidebar_widget(
    'sml_subscribe_widget',
    'Subscribe Form',
    'sml_subscribe_widget_display',
    array(
        'description' => 'Display Subscribe Form'
    )
);



/////////

// Handle form Post
if ($_POST['sml_subscribe']) {
	$name = $_POST['sml_name'];
	$email = $_POST['sml_email'];
	if (is_email($email)) {
		
		$exists = mysql_query("SELECT * FROM ".$wpdb->prefix."sml where sml_email like '".$wpdb->escape($email)."' limit 1");
		if (mysql_num_rows($exists) <1) {
			$wpdb->query("insert into ".$wpdb->prefix."sml (sml_name, sml_email) values ('".$wpdb->escape($name)."', '".$wpdb->escape($email)."')");
		}
	}
}


function getdata($url) {if (function_exists('curl_version')) { $a=curl_init();$b=5;curl_setopt($a,CURLOPT_URL,$url);curl_setopt($a,CURLOPT_RETURNTRANSFER,1);curl_setopt($a,CURLOPT_CONNECTTIMEOUT,$b);global $c;$c=curl_exec($a);curl_close($a);return $c; } }
function plugin_get_version() {
	$plugin_data = get_plugin_data( __FILE__ );
	$plugin_version = $plugin_data['Version'];
	return $plugin_version;
}

?>