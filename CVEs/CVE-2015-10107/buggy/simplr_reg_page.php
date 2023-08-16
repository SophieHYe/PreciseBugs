<?php
/*
Plugin Name: Simplr User Registration Form Plus
Version: 2.3.4
Description: This a simple plugin for adding a custom user registration form to any post or page using shortcode.
Author: Mike Van Winkle
Author URI: http://www.mikevanwinkle.com
Plugin URI: http://www.mikevanwinkle.com/wordpress/how-to/custom-wordpress-registration-page/
License: GPL
Text Domain: simplr-reg
Domain Path: /lang/
*/
//constants
define("SIMPLR_URL", rtrim(WP_PLUGIN_URL,'/') . '/'.basename(dirname(__FILE__)) );
define("SIMPLR_DIR", rtrim(dirname(__FILE__), '/'));

//setup options global
global $simplr_options;
$simplr_options = get_option('simplr_reg_options');

//Includes
include_once(SIMPLR_DIR.'/lib/fields.class.php');
include_once(SIMPLR_DIR.'/lib/fields-table.class.php');
include_once(SIMPLR_DIR.'/simplr_form_functions.php');
require_once(SIMPLR_DIR.'/lib/profile.php');
//require_once(SIMPLR_DIR.'/lib/login.php');

//API
add_action('wp_print_styles','simplr_reg_styles');
add_action('admin_init','simplr_admin_style');
add_action('init','simplr_admin_scripts');
add_action('admin_menu','simplr_reg_menu');
add_shortcode('register', 'sreg_figure');
add_shortcode('Register', 'sreg_figure');
add_shortcode('login_page','simplr_login_page');
add_shortcode('profile_page','simplr_profile_page');
add_action('admin_init','simplr_action_admin_init');
add_action('admin_head','simplr_reg_scripts',100);
//add_action('init','simplr_reg_default_fields');
register_activation_hook(__FILE__, 'simplr_reg_install');
add_action('wp','simplr_fb_auto_login',0);
add_action('login_head','simplr_fb_auto_login');
add_filter('login_message','get_fb_login_btn');
add_action('login_head','simplr_fb_login_style');
add_action('init','simplr_register_redirect');
//add_action('template_redirect','simplr_includes');
add_action('login_footer','simplr_fb_login_footer_scripts');
add_action('wp','simplr_profile_redirect',10);

if( is_admin() ) {
	add_action( 'show_user_profile', 'simplr_reg_profile_form_fields' );
	add_action( 'edit_user_profile', 'simplr_reg_profile_form_fields' );
}

//moderation related hooks
if( @$simplr_options->mod_on == 'yes' ) {
	add_action('admin_action_sreg-activate-selected', 'simplr_activate_users');
	add_action('admin_action_sreg-resend-emails', 'simplr_resend_emails' );
	if( $simplr_options->mod_activation == 'auto' ) {
		add_action('wp','simplr_activation_listen');
	}
}

/*
**
** Plugin Activation Hook
**
**/

function simplr_reg_install() {
		//validate
	global $wp_version;
	$exit_msg = "Dude, upgrade your stinkin Wordpress Installation.";

	if(version_compare($wp_version, "2.8", "<"))
		exit($exit_msg);

	//setup some default fields
	simplr_reg_default_fields();
}

/**
**
** Load Settings Page
**
**/

function simplr_reg_set() {
	include_once(SIMPLR_DIR.'/lib/form.class.php');
	include_once( SIMPLR_DIR . '/main_options_page.php' );
} //End Function



/**
**
** Add Settings page to admin menu
**
**/

function simplr_reg_menu() {
	$page = add_submenu_page('options-general.php','Registration Forms', __('Registration Forms', 'simplr-reg'), 'manage_options','simplr_reg_set', 'simplr_reg_set');
	add_action('admin_print_styles-' . $page, 'simplr_admin_style');
	register_setting ('simplr_reg_options', 'sreg_admin_email', '');
	register_setting ('simplr_reg_options', 'sreg_email', '');
	register_setting ('simplr_reg_options', 'sreg_style', '');
	register_setting ('simplr_reg_options', 'simplr_profile_fields', 'simplr_fields_settings_process');
}


/**
**
** Add Settings link to the main plugin page
**/

function simplr_plugin_link( $links, $file ) {
	if ( $file == plugin_basename( dirname(__FILE__).'/simplr_reg_page.php' ) ) {
		$links[] = '<a href="' . admin_url( 'options-general.php?page=simplr_reg_set' ) . '">'.__( 'Settings' ).'</a>';
	}
	return $links;
}
add_filter( 'plugin_action_links', 'simplr_plugin_link', 10, 2 );


/**
**
** Process Saved Settings (Deprecated)
**
**/

function simplr_fields_settings_process($input) {
	if($input[aim][name] && $input[aim][label] == '') {$input[aim][label] = 'AIM';}
	if($input[yim][name] && $input[yim][label] == '') {$input[yim][label] = 'YIM';}
	if($input[website][name] && $input[website][label] == '') {$input[website][label] = __('Website', 'simplr-reg');}
	if($input[nickname][name] && $input[nickname][label] == '') {$input[nickname][label] = __('Nickname', 'simplr-reg');}
	return $input;
}

/**
**
** Register and enqueue plugin styles
**
**/

function simplr_reg_styles() {
	$options = get_option('simplr_reg_options');
	if( is_object($options) && isset($options->styles) && $options->styles != 'yes') {
		if( @$options->style_skin ) {
			$src = SIMPLR_URL .'/assets/skins/'.$options->style_skin;
		} else {
			$src = SIMPLR_URL .'/assets/skins/default.css';
		}
		wp_register_style('simplr-forms-style',$src);
		wp_enqueue_style('simplr-forms-style');
	} elseif(is_object($options) || !empty($options->stylesheet)) {
		$src = $options->stylesheet;
		wp_register_style('simplr-forms-custom-style',$src);
		wp_enqueue_style('simplr-forms-custom-style');
	} else {
		wp_register_style('simplr-forms-style', SIMPLR_URL .'/assets/skins/default.css');
		wp_enqueue_style('simplr-forms-style');
	}
}

/**
 * Handle admin styles and JS
 */
function simplr_admin_style() {
	$src = SIMPLR_URL . '/assets/admin-style.css';
	$url = parse_url($_SERVER['REQUEST_URI']);
	$parts = explode('/', trim($url['path']));
	if(is_admin())
	{
		if( isset($_GET['page']) AND $_GET['page'] == 'simplr_reg_set' ) {
			wp_register_style('chosen',SIMPLR_URL.'/assets/js/chosen/chosen/chosen.css');
			wp_register_script('chosen',SIMPLR_URL.'/assets/js/chosen/chosen/chosen.jquery.js',array('jquery'));
			add_action('admin_print_footer_scripts','simplr_footer_scripts');
			wp_enqueue_style('chosen');
			wp_enqueue_script('chosen');

			wp_register_style('simplr-admin-style',$src);
			wp_enqueue_style('simplr-admin-style');
		 } elseif( end($parts) == 'users.php' ) {
			add_action('admin_print_footer_scripts','simplr_footer_scripts');
		}
	}
}

/*
 * Print Admin Footer Scripts
 */
function simplr_footer_scripts() {
	$screen = get_current_screen();
	if( $screen->id == 'users' AND @$_GET['view_inactive'] == 'true' ) {
	?>
		<script>
			jQuery(document).ready(function($) {
				//add bulk actions
				$('input[name="simplr_resend_activation"]').click( function(e) { e.preventDefault(); });
				$('select[name="action"]').append('<option value="sreg-activate-selected"><?php _e('Activate', 'simplr-reg'); ?></option>\n<option value="sreg-resend-emails"><?php _e('Resend Email', 'simplr-reg'); ?></option>').after('<input name="view_inactive" value="true" type="hidden" />');
			});

		</script>
	<?php
	} else {
	?>
		<script>
			jQuery(document).ready(function($) {
				$('.chzn').chosen();
			});
		</script>
	<?php
	}
}

/**
**
** Enqueue Scripts
**
**/

function simplr_admin_scripts() {
	if(is_admin() AND @$_REQUEST['page'] == 'simplr_reg_set')
	{
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-sortable');
	}
}

/**
**
** Load language files for frontend and backend
**/
function simplr_load_lang() {
	load_plugin_textdomain( 'simplr-reg', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
}
add_action('plugins_loaded', 'simplr_load_lang');


/**
**
** Set plugin location for tinyMCE access
**
**/

function simplr_reg_scripts() {
	?>
	<script type="text/javascript">
	//<![CDATA[
		userSettings.simplr_plugin_dir = '<?php echo SIMPLR_URL; ?>/';
	//]]>
</script>
	<?php
}

/**
 * Media Buttons
 */

add_action('media_buttons', 'simplr_media_button', 100);
function simplr_media_button() {
	wp_enqueue_script('simplr-reg', plugins_url('assets/simplr_reg.js',__FILE__), array('jquery'));
	?>
	<a id="insert-registration-form" class="button" title="<?php esc_html_e( 'Add Registration Form', 'simplr-reg' ); ?>" data-editor="content" href="#">
		<span class="jetpack-contact-form-icon"></span> <?php esc_html_e( 'Add Registration Form', 'simplr-reg' ); ?>
	</a>
	<?php
}


/**
**
** Add TinyMCE Button
**
**/
function simplr_action_admin_init() {
	global $simplr_options;

	if( @$simplr_options->mod_on == 'yes')
	{
		//only add these hooks if moderation is on
		$mod_access = false;

		//if roles haven't been saved use default
		if( empty($simplr_options->mod_roles) )
			$simplr_options->mod_roles = array('administrator');

		foreach( $simplr_options->mod_roles as $role ) {
			if( $mod_access) continue;
			$mod_access = current_user_can($role);
		}

		if( $mod_access ) {
			require_once(SIMPLR_DIR.'/lib/mod.php');
			add_action('views_users', 'simplr_views_users');
			add_action('pre_user_query','simplr_inactive_query');
			add_filter('bulk_actions-users','simplr_users_bulk_action');
		}
	}

	add_filter('manage_users_columns', 'simplr_column');
	add_filter('manage_users_custom_column','simplr_column_output',10,3);
	add_filter('manage_users_sortable_columns','simplr_sortable_columns');
	add_filter('pre_user_query','simplr_users_query');
}

/**
 * Adds default fields upon installation
*/

function simplr_reg_default_fields() {
	if(!get_option('simplr_reg_fields')) {
		$fields = new StdClass();
		$custom = array(
			'first_name'=>array('key'=>'first_name','label'=> __('First Name', 'simplr-reg'),'required'=>false,'type'=>'text'),
			'last_name'=>array('key'=>'last_name','label'=> __('Last Name', 'simplr-reg'),'last_name'=> __('Last Name', 'simplr-reg'),'required'=>false,'type'=>'text')
		);
		$fields->custom = $custom;
		update_option('simplr_reg_fields',$fields);
	}

	//unset profile from free version
	if(get_option('simplr_profile_fields')) {
		delete_option('simplr_profile_fields');
	}

}

/*
**
** Facebook Autologin
**
*/

function simplr_fb_auto_login() {
	global $simplr_options;
	//require_once(SIMPLR_DIR.'/lib/login.php');
	global $facebook;
	if( isset($simplr_options->fb_connect_on)
		AND $simplr_options->fb_connect_on == 'yes'
		AND !is_user_logged_in()
		AND !current_user_can('administrator')) {
		require_once(SIMPLR_DIR .'/lib/facebook.php');
		include_once(SIMPLR_DIR .'/lib/fb.class.php');
		$facebook = new Facebook(Simplr_Facebook::get_fb_info());
		try {
			$uid = $facebook->getUser();
			$user = $facebook->api('/me');
		} catch (FacebookApiException $e) {}
		$auth = (isset($user))?simplr_fb_find_user($user):false;
		$first_visit = get_user_meta($auth->ID,'first_visit',true);
		if(isset($user) && (@$_REQUEST['loggedout'] == 'true' OR @$_REQUEST['action'] == 'logout')) {
			wp_redirect($facebook->getLogoutUrl(array('next'=>get_bloginfo('url'))));
		} elseif(isset($user) AND !is_wp_error($auth) ) {
			wp_set_current_user($auth->ID, $auth->user_login);
			wp_set_auth_cookie($auth->ID);
			if(isset($simplr_options->thank_you) AND !is_page($simplr_options->thank_you)  ) {
				update_user_meta($auth->ID,'first_visit',date('Y-m-d'));
				$redirect = $simplr_options->thank_you != ''?get_permalink($simplr_options->thank_you):home_url();
				wp_redirect($redirect);
			} elseif(isset($simplr_options->thank_you) AND is_page($simplr_options->thank_you)) {
				//do nothing
			} elseif(isset($first_visit)) {
				wp_redirect(!$simplr_options->fb_login_redirect?get_bloginfo('url'):$simplr_options->register_redirect);
			}
		} elseif(isset($user) AND is_wp_error($auth)) {
			global $error;
			$error = __($auth->get_error_message(),'simplr-reg');
		} else {

			return;
		}
	} else {
		return;
	}
}


/*
**
** Find Facebook User
**
*/

function simplr_fb_find_user($fb_obj) {
	global $wpdb,$simplr_options;
	$query = $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'fbuser_id' AND meta_value = %d", $fb_obj['id'] );
	$user_id = $wpdb->get_var($query);

	if(empty($user_id) AND isset($simplr_options->fb_auto_register)) {
		$user_id = simplr_fb_auto_register();
	}

	$user_obj = get_userdata($user_id);
	if(empty($user_obj)) {
		return new WP_Error( 'login-error', __('No facebook account registered with this site', 'simplr-reg') );
	} else {
		return $user_obj;
	}
}

function simplr_fb_auto_register() {
	global $simplr_options;
	require_once(SIMPLR_DIR .'/lib/facebook.php');
	include_once(SIMPLR_DIR .'/lib/fb.class.php');
	$facebook = new Facebook(Simplr_Facebook::get_fb_info());
	try {
		$uid = $facebook->getUser();
		$user = $facebook->api('/me');
	} catch (FacebookApiException $e) {}

	if(!empty($user)) {
		$userdata = array(
			'user_login' 	=> $user['username'],
			'first_name' 	=> $user['first_name'],
			'last_name' 	=> $user['last_name'],
			'user_pass' 	=> wp_generate_password( 12, false ),
			'user_email' 	=> 'fb-'.$user['id']."@website.com",
		);

		// create user
		$user_id = wp_insert_user( $userdata );
		update_user_meta($user_id, 'fbuser_id', $user['id']);
		update_user_meta($user_id, 'fb_object', $user);
		if(!is_wp_error($user_id)) {
			//return the user
			wp_redirect($simplr_options->fb_login_redirect?$simplr_options->fb_login_redirect:home_url());
		}
	}

}

/*
**
** Facebook Login Button
**
*/

function get_fb_login_btn($content) {
	$option = get_option('simplr_reg_options');
	if( isset($option->fb_connect_on) AND $option->fb_connect_on == 'yes') {
		$out = '';
		require_once(SIMPLR_DIR .'/lib/facebook.php');
		include_once(SIMPLR_DIR .'/lib/fb.class.php');
		global $facebook;
		$login_url = $facebook->getLoginUrl();
		$perms = implode(',',$option->fb_request_perms);
		$out .= '<fb:login-button scope="'.$perms.'"></fb:login-button>';
		//$out = '<p><div id="fblogin"><a href="'.$login_url.'"><img src="'.plugin_dir_url(__FILE__).'assets/images/fb-login.png" /></a></div></p>';
		echo $out;
	}
	return $content;
}

/*
**
** Facebook Login Button Styles
**
*/

function simplr_fb_login_style() {
	?>
	<style>
	a.fb_button {
		margin:10px 0px 10px 240px;

	}
	</style>
	<?php
}

/*
**
** Login Footer Script
**
*/

function simplr_fb_login_footer_scripts() {
	$option = get_option('simplr_reg_options');
	if(isset($option->fb_connect_on) AND $option->fb_connect_on == 'yes') {
		require_once(SIMPLR_DIR .'/lib/facebook.php');
		include_once(SIMPLR_DIR .'/lib/fb.class.php');
		$ap_info = Simplr_Facebook::get_fb_info();
		?>
		<div id="fb-root"></div>
		<script>
		window.fbAsyncInit = function() {
			FB.init({
				appId  : '<?php echo $ap_info['appId']; ?>',
				status : true, // check login status
				cookie : <?php echo $ap_info['cookie']; ?>, // enable cookies to allow the server to access the session
				xfbml  : true,  // parse XFBML
				oauth : true //enables OAuth 2.0
			});

			FB.Event.subscribe('auth.login', function(response) {
				window.location.reload();
			});
			FB.Event.subscribe('auth.logout', function(response) {
				window.location.reload();
			});
		};
		(function() {
			var e = document.createElement('script');
			e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
			e.async = true;
			document.getElementById('fb-root').appendChild(e);
		}());
		</script>
	<?php
	}
}

/*
**
** Add Fields to Profile Page
**
*/
function simplr_reg_profile_form_fields($user) {
	if(!class_exists('Form')) {
		include_once(SIMPLR_DIR.'/lib/form.class.php');
	}
	$custom = new SREG_Fields();
	if(!current_user_can('promote_users')) {
		$fields = simplr_filter_profile_fields($custom->get_custom());
	} else {
		$fields = $custom->get_custom();
	}
	?>
	<link href="<?php echo SIMPLR_URL; ?>/assets/admin-style.css" rel="stylesheet" ></link>
	<h3><?php _e('Other Information', 'simplr-reg'); ?></h3>
	<?php
	foreach($fields as $field) {
		if(!in_array($field['key'] ,array('first_name','last_name', 'user_login','username'))) {
			$out = '';
			if($field['key'] != '') {
				$args = array(
					'name'		=>$field['key'],
					'label'		=>$field['label'],
					'required'	=>$field['required']
					);
				//setup specific field values for date and callback
				if($field['type'] == 'callback') {
					$field['options_array'][1] = array( get_user_meta($user->ID,$field['key'],true) ) ;
					SREG_Form::$field['type']( $args, get_user_meta($user->ID,$field['key'],true), '', $field['options_array']);
				} elseif($field['type'] != '') {
					SREG_Form::$field['type']($args, get_user_meta($user->ID,$field['key'],true), '', $field['options_array']);
				}
			}
		}
	}
}


/*
**
** Save Fields in Profile Page
**
*/
add_action( 'personal_options_update', 'simplr_reg_profile_save_fields' );
add_action( 'edit_user_profile_update', 'simplr_reg_profile_save_fields' );

function simplr_reg_profile_save_fields($user_id ) {
	$custom = new SREG_Fields();
	$data = $_POST;
	$fields = $custom->fields->custom;
	foreach($fields as $field):
		if(!in_array($field['key'] , simplr_get_excluded_profile_fields() )) {
			if($field['type'] == 'date')
			{
				$dy = $data[$field['key'].'-dy'];
				$mo = $data[$field['key'].'-mo'];
				$yr = $data[$field['key'].'-yr'];
				$dateinput = implode('-', array($yr,$mo,$dy));
				update_user_meta($user_id,$field['key'],$dateinput);
			} else {
				update_user_meta($user_id, $field['key'], $data[$field['key']]);
			}
		}
	endforeach;
}


/*
**
** Exclude Fields From Profile
**
*/
function simplr_get_excluded_profile_fields() {
	$fields = array(
		'about_you','first_name','last_name','aim','yim','jabber','nickname','display_name','user_login','username','user_email',
	);
	return apply_filters('simplr_excluded_profile_fields', $fields);
}

/*
**
** Register Redirect Function
**
*/

function simplr_register_redirect() {
	$file = parse_url($_SERVER['REQUEST_URI']);
	$path = explode('/',@$file['path']);
	global $simplr_options;
	parse_str(@$file['query']);
	if( @$simplr_options->login_redirect ) {
		$post = get_post($simplr_options->login_redirect);
		set_transient('login_post_data',$post);
	}
	if( ((end($path) == 'wp-login.php' AND @$_GET['action'] == 'register') OR (end($path) == 'wp-signup.php')) AND $simplr_options->register_redirect != '' ) {
		wp_redirect(get_permalink($simplr_options->register_redirect));
	} elseif(end($path) == 'profile.php' AND $simplr_options->profile_redirect != '') {
		if(!current_user_can('administrator')) {
			wp_redirect(get_permalink($simplr_options->profile_redirect.'?'.$file['query']));
		}
	} else {

	}
}

function simplr_profile_redirect() {
	global $simplr_options,$wpdb;
	if ( is_object($simplr_options) &&  isset($simplr_options->profile_redirect) ) {
		$profile = $wpdb->get_var($wpdb->prepare("SELECT post_name FROM {$wpdb->prefix}posts WHERE ID = %d",$simplr_options->profile_redirect));
	}
	$file = parse_url($_SERVER['REQUEST_URI']);
	$path = explode('/',@$file['path']);
	if(isset($profile) AND end($path) == $profile) {
		if(!is_user_logged_in()) {
			wp_redirect(home_url('/wp-login.php?action=register'));
		}
	}
	wp_deregister_script('password-strength-meter');
	do_action('simplr_profile_actions');
}


/*
**
** Ajax save sort
**
*/
add_action('wp_ajax_simplr-save-sort','simplr_save_sort');
function simplr_save_sort() {
	extract($_REQUEST);
	if(isset($sort) and $page = 'simple_reg_set') {
		update_option('simplr_field_sort',$sort);
	}
	// debugging code as the response.
	echo "php sort: ";
	print_r($sort);
	die;
}

/*
** Print admin messages
**
*/

function simplr_print_message() {
	$simplr_messages = @$_COOKIE['simplr_messages'] ? $_COOKIE['simplr_messages'] : false;
	$messages = stripslashes($simplr_messages);
	$messages = str_replace('[','',str_replace(']','',$messages));
	$messages = json_decode($messages);
	if(!empty($messages)) {
		if(count($messages) > 1) {
			foreach($messages as $message) {
				?>

				<?php
			}
		} else {
		?>
			<div id="message" class="<?php echo $messages->class; ?>"><p><?php echo $messages->content; ?></p></div>
		<?php
		}
	}
}


/*
** Set Admin Messages
**
*/

function simplr_set_message($class,$message) {
	if(!session_id()) { session_start(); }

	$messages = $_COOKIE['simplr_messages'];
	$messages = stripslashes($simplr_messages);
	$messages = str_replace('[','',str_replace(']','',$messages));
	$messages = json_decode($messages);
	$new = array();
	$new['class'] = $class;
	$new['content'] = $message;
	$messages[] = $new;
	setcookie('simplr_messages',json_encode($messages),time()+10,'/');
	return true;
}

/*
** Process admin forms
**	@TODO consolidate steps
*/
add_action('admin_init','simplr_admin_actions');
function simplr_admin_actions() {
	if(isset($_GET['page']) AND $_GET['page'] == 'simplr_reg_set') {

		$data = $_POST;
		$simplr_reg = get_option('simplr_reg_options');

		//
		if(isset($data['recaptcha-submit'])) {

			if(!wp_verify_nonce(-1, $data['reg-api']) && !current_user_can('manage_options')){ wp_die('Death to hackers!');}
			$simplr_reg->recap_public = $data['recap_public'];
			$simplr_reg->recap_private = $data['recap_private'];
			$simplr_reg->recap_on = $data['recap_on'];
			update_option('simplr_reg_options',$simplr_reg);
		} elseif(isset($data['fb-submit'])) {
			if(!wp_verify_nonce(-1, @$data['reg-fb']) && !current_user_can('manage_options')){ wp_die('Death to hackers!');}
			$simplr_reg->fb_connect_on = $data['fb_connect_on'];
			$simplr_reg->fb_app_id = @$data['fb_app_id'];
			$simplr_reg->fb_app_key = @$data['fb_app_key'];
			$simplr_reg->fb_app_secret = @$data['fb_app_secret'];
			$simplr_reg->fb_login_allow = @$data['fb_login_allow'];
			$simplr_reg->fb_login_redirect = @$data['fb_login_redirect'];
			$simplr_reg->fb_request_perms = @$data['fb_request_perms'];
			$simplr_reg->fb_auto_register = @$data['fb_auto_register'];
			update_option('simplr_reg_options',$simplr_reg);
			simplr_set_message('updated notice is-dismissible', __("Your settings were saved.", 'simplr-reg') );
			wp_redirect($_SERVER['REQUEST_URI']);
		}

		if(isset($data['main-submit'])) {
			//security check
			if(!wp_verify_nonce(-1, $data['reg-main']) && !current_user_can('manage_options')){ wp_die('Death to hackers!');}

			$simplr_reg->email_message = $data['email_message'];
			$simplr_reg->default_email = $data['default_email'];
			$simplr_reg->stylesheet = $data['stylesheet'];
			$simplr_reg->styles = $data['styles'];
			$simplr_reg->style_skin = @$data['style_skin'] ? $data['style_skin'] : 'default.css';
			$simplr_reg->register_redirect = $data['register_redirect'];
			$simplr_reg->thank_you = $data['thank_you'];
			$simplr_reg->profile_redirect = $data['profile_redirect'];
			update_option('simplr_reg_options',$simplr_reg);
			simplr_set_message('updated notice is-dismissible', __("Your settings were saved.", 'simplr-reg') );
			wp_redirect($_SERVER['REQUEST_URI']);

		}

		if(@$_GET['action'] == 'delete') {

			/*Security First*/
			if( !check_admin_referer('delete','_wpnonce') ) { wp_die('Death to hackers'); }
			$del = new SREG_Fields();
			$del->delete_field($_GET['key']);
			simplr_set_message('updated notice is-dismissible', __("Field deleted.", 'simplr-reg') );
			wp_redirect(remove_query_arg('action'));

		} elseif(isset($_POST['mass-submit'])) {

			if(!check_admin_referer(-1,'_mass_edit')) { wp_die('Death to hackers'); }
			foreach($_POST['field_to_delete'] as $key):
				$del = new SREG_Fields();
				$del->delete_field($key);
			endforeach;
			simplr_set_message('updated notice is-dismissible', __("Fields were deleted.", 'simplr-reg') );
			wp_redirect(remove_query_arg('action'));

		}

		if(isset($_POST['submit-field'])) {
			if( !check_admin_referer(-1, 'reg-field' ) ) wp_die("Death to Hackers");
			$new = new SREG_Fields();
			$key = $_POST['key'];
			$response = $new->save_custom($_POST);
			simplr_set_message('updated notice is-dismissible', __("Your Field was saved.", 'simplr-reg') );
			wp_redirect(remove_query_arg('action'));

		}

		add_action('admin_notices','simplr_print_message');
	}

}

/*
 * Activate a user(s)
 * @params $ids (array) | an array of user_ids to activate.
 */
function simplr_activate_users( $ids = false ) {
	if( !$ids ) {
		if( @$_REQUEST['action'] == 'sreg-activate-selected' AND !empty($_REQUEST['users']) ) {
			simplr_activate_users( $_REQUEST['users'] );
		}
	}
	else {
		global $wpdb,$simplr_options;
		foreach( $ids as $id )  {
			$return = $wpdb->update( $wpdb->users, array( 'user_status'=> 0 ), array( 'ID' => $id ), array('%d'), array('%d') );
			if( !$return ) {
				return new WP_Error( "error", __("Could not activate requested user.", 'simplr-reg') );
			}
			$userdata = get_userdata( $id );
			$data = (array) $userdata;
			$data = (array) $data['data'];
			$data['blogname'] = get_option('blogname');
			$data['username'] = $userdata->user_login;
			do_action('simplr_activated_user', $data);
			$subj = simplr_token_replace( $simplr_options->mod_email_activated_subj, $data );
			$content = simplr_token_replace( $simplr_options->mod_email_activated, $data );
			if ( isset( $simplr_options->default_email ) ) {
				$from = $simplr_options->default_email;
			} else {
				$from = get_option('admin_email');
			}
			$headers = "From: " . $data['blogname'] . " <$from>\r\n";
			wp_mail( $data['user_email'], $subj, $content, $headers);
			return $return;
		}
	}
}

/*
 * Sends user moderation emails to selected users
 */
function simplr_resend_emails() {
	if( @$_REQUEST['action'] == 'sreg-resend-emails' AND !empty($_REQUEST['users']) ) {
		include_once(SIMPLR_DIR.'/lib/mod.php');
		foreach( $_REQUEST['users'] as $user ) {
			simplr_resend_email($user);
			simplr_set_notice('success', __("Emails resent", 'simplr-reg') );
		}
	}
}

/*
 * Activation Listener
 */
function simplr_activation_listen() {
	if( isset( $_REQUEST['activation_key'] ) ) {
		wp_enqueue_script('simplr-mod', SIMPLR_URL.'/assets/mod.js', array('jquery') );
		wp_enqueue_style('simplr-mod', SIMPLR_URL.'/assets/mod.css');
		global $wpdb,$sreg;
		$user_id = $wpdb->get_var($wpdb->prepare("SELECT ID from $wpdb->users WHERE `user_activation_key` = %s", $_REQUEST['activation_key']));
		$done = simplr_activate_users( array($user_id) );
		if ( !$user_id OR is_wp_error($done) ) {
			wp_localize_script('simplr-mod', 'sreg', array('state'=>'failure', 'message'=>__("Sorry, We could not find the requested account.",'simplr-reg')) );
		} else {
			wp_localize_script('simplr-mod', 'sreg', array('state'=>'success', 'message'=>__("Congratulations! Your Account was activated!",'simplr-reg')) );
		}
	}
}


function simplr_set_notice( $class, $message ) {
	add_action( "admin_notices" , create_function('',"echo '<div class=\"updated notice is-dismissible $class\"><p>$message</p></div>';") );
}

/**
 * Filter custom column output
 * @params $out string (optional) | received output from the wp hook
 * @params $column_name string (required) | unique column name corresponds to the field name
 * @params $user_id INT
 */
if(!function_exists('simplr_column_output')):
	function simplr_column_output($out='',$column_name,$user_id) {
		$out = get_user_meta($user_id, $column_name,true);
		return $out;
	}
endif;

/**
 * Add custom columns
 * @params $columns (array) | received from manage_users_columns hook
 */
if(!function_exists('simplr_column')):
	function simplr_column($columns) {
		$cols = new SREG_Fields();
		$cols = $cols->fields->custom;
		foreach( $cols as $col ) {
			if( @$col['custom_column'] != 'yes' ) continue;
			$columns[$col['key']] = $col['label'];
		}
		return $columns;
	}
endif;

/**
 * Filter sortable columns
 * @params $columns (array) | received from manage_users_sortable_columns hook
*/
if( !function_exists('simplr_sortable_columns') ) {
	function simplr_sortable_columns($columns) {
		$cols = new SREG_Fields();
		$cols = $cols->fields->custom;
		unset($columns['posts']);
		foreach( $cols as $col ) {
			if( @$col['custom_column'] != 'yes' ) continue;
			$columns[$col['key']] = $col['key'];
		}
		$columns['post'] = 'Posts';
		return $columns;
	}
}

/**
 * Modify the users query to sort columns on custom fields
 * @params $query (array) | passed by pre_user_query hook
*/
if(!function_exists('simplr_users_query')):
	function simplr_users_query($query) {
		//if not on the user screen lets bail
		$screen = get_current_screen();
		if( !is_admin() ) return $query;
		if( $screen->base != 'users' ) return $query;

		$var = @$_REQUEST['orderby'] ? $_REQUEST['orderby'] : false;
		if( !$var ) return $query;
		//these fields are already sortable by wordpress
		if( in_array( $var, array('first_name','last_name','email','login','name') ) ) return $query;
		$order = @$_REQUEST['order'] ? esc_attr($_REQUEST['order']) : '';
		//get our custom fields
		$cols = new SREG_Fields();
		$cols = $cols->fields->custom;
		if( array_key_exists( $var, $cols ) ) {
			global $wpdb;
			$query->query_from .= $wpdb->prepare(" LEFT JOIN {$wpdb->usermeta} um ON um.user_id = ID AND `meta_key` = %s", $var);
			$query->query_orderby = " ORDER BY um.meta_value $order";
		}
		return $query;
	}
endif;

//add_filter('query','simplr_log');
function simplr_log($query) {
	if( @$_REQUEST['debug'] == 'true' )
		print $query;
	return $query;
}

add_filter('wp_authenticate_user','simplr_disable_login_inactive', 0);
function simplr_disable_login_inactive($user) {

	if( empty($user) || is_wp_error($user) )
		return $user;

	if( $user->user_status == 2 )
		return new WP_Error("error", __("<strong>ERROR</strong>: This account has not yet been approved by the moderator", 'simplr-reg') );

	return $user;
}
