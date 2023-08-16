<?php
/*
  @package Freshdesk Official
  @version 1.8
*/
/*
  Plugin Name: Freshdesk Official
  Plugin URI: 
  Description: Freshdesk Official is a seamless way to add your helpdesk account to your website. Supports various useful functions.
  Author: hjohnpaul,sathishfreshdesk,balakumars,shreyasns
  Version: 1.8
  Author URI: http://freshdesk.com/
*/


if ( ! defined( 'ABSPATH' ) ) {
	die(); //Die if accessed directly.
}

#include freshdesk api class.
define( 'FD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FD_PAGE_BASENAME', 'freshdesk-menu-handle' );
define( 'DOMAIN_REGEX', '/\bhttps?:\/\/([-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|])/i' );
require_once( plugin_dir_path( __FILE__ ) . 'freshdesk-plugin-api.php' );

add_action( 'init', 'fd_login' ); //Sso handler and comment action handler
add_action( 'admin_menu', 'freshdesk_plugin_menu' ); //Plugin Menu
add_filter( 'comment_row_actions', 'freshdesk_comment_action', 10, 2 ); // This adds the comment action menu.
add_action( 'wp_ajax_fd_ticket_action', 'fd_action_callback' ); // This is the ajax action handler
add_filter( 'login_redirect', 'fd_login_redirect', 10, 3 ); // This is used to redirect to Freshdesk on SSO.
add_filter( 'login_message', "show_login_message" );
// admin messages hook!
add_action( 'admin_notices', 'freshdesk_admin_msgs' );
?>
<?php
function show_login_message() {
	$freshdesk_options= get_option('freshdesk_options');
	if ( $freshdesk_options['freshdesk_enable_sso'] != 'checked' && $_GET['action'] == 'freshdesk-login' ){
		//ToDO change the login message
		return "<div style='padding:12px;border-left:4px solid #feba11; background:#fff; margin-bottom:4px'>We are not able to log you in, please contact your Wordpress administrator to enable SSO in your account.</div>";
	}
}
function fd_login_redirect( $url, $request, $user ) {
	parse_str( $request, $params );
	$fd_redirect_to = $params['fd_redirect_to'];
	if ( ! $fd_redirect_to ) {
		return $url;
	}
	$redirect_url = get_redirect_url( $fd_redirect_to );

	// For handling Redirect to Freshdesk on login.
	if ( $_REQUEST['wp-submit'] == "Log In" && is_a( $user, 'WP_User' ) && $redirect_url ) {
		$freshdesk_options = get_option( 'freshdesk_options' );		
		
		$user_name = $user->data->display_name;
		$secret = $freshdesk_options['freshdesk_sso_key'];
		$data = $user_name.$user->data->user_email.time();
		$hash_key = hash_hmac("md5", $data, $secret);
		$ssl_url = $redirect_url."/login/sso?name=".urlencode($user->data->display_name)."&email=".urlencode($user->data->user_email)."&timestamp=".time()."&hash=".urlencode($hash_key);
		sleep(1); // holding a bit so that it falls within FD 30 mins bar.
		header("Location: ".$ssl_url);
		die();
	}
	return  $request;
}

function get_redirect_url($host_url) {
	$freshdesk_options = get_option( 'freshdesk_options' );

	//Stripping protocols from urls to match the host url correctly.
	$host_url = preg_replace( DOMAIN_REGEX, "$1", trim($host_url) );
	$domains = split( ",", $freshdesk_options['freshdesk_cname'] );
	array_push( $domains, $freshdesk_options['freshdesk_domain_url'] );

	//Checking the host url against the provided helpdesk/portal url to avoid Open-redirect vulnerability
	foreach ( $domains as $domain ) {
		$domain = trim($domain);
		$url = preg_replace( DOMAIN_REGEX, "$1", $domain);
		if ( $url == $host_url ) {
			return $domain;
		}
	}
}
function freshdesk_plugin_menu() {
	add_menu_page( 'Freshdesk Settings', 'Freshdesk', 'manage_options', 'freshdesk-menu-handle', 'freshdesk_settings_page');
	add_action( 'admin_init', 'freshdesk_settings_init' );
}

function freshdesk_settings_page() {
?>
	<div class="wrap">
		<h2><?php echo __("Freshdesk Settings") ?></h2>
		<form class="form-table" method="post" action="options.php"> 
			<?php settings_fields('freshdesk_options_group'); //setting fields group?>
			<?php do_settings_sections('freshdesk-menu-handle'); ?>
			<p class="submit"><input class="wp-core-ui button-primary" name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" /></p>
		</form>
	</div>
<?php
} //Function End

function freshdesk_settings_init() {
	register_setting( 'freshdesk_options_group', 'freshdesk_options', 'validate_freshdesk_settings' );
	add_settings_section( 'freshdesk_settings_section', '', '', 'freshdesk-menu-handle' );
	//Domain url.
	add_settings_field( 'freshdesk_domain_url', __('Helpdesk URL'), 'freshdesk_domain_callback' ,'freshdesk-menu-handle', 'freshdesk_settings_section' );
	//Host url
	add_settings_field( 'freshdesk_cname', __('Portal URLs'), 'freshdesk_cname_callback' ,'freshdesk-menu-handle', 'freshdesk_settings_section' );
	
	//Freshdesk Api Key.
	add_settings_field( 'freshdesk_api_key', 'API Key', 'freshdesk_api_callback' , 'freshdesk-menu-handle', 'freshdesk_settings_section' );
	
	//Enable SSO
	add_settings_field( 'freshdesk_enable_sso', '', 'freshdesk_enable_sso_callback' , 'freshdesk-menu-handle', 'freshdesk_settings_section' );
	
	//Enable/disable freshdesk widget code.
	register_setting( 'freshdesk_options_group','freshdesk_feedback_options','validate_freshdesk_fb_settings' );
	add_settings_field( 'freshdesk_enable_feedback', '', 'freshdesk_enable_fb_callback' , 'freshdesk-menu-handle', 'freshdesk_settings_section' );
}

// Callback Functions that constructs the UI.
function freshdesk_domain_callback() {
	$options = get_option( 'freshdesk_options' );
	echo "<input class='fd_ui_element' id='freshdesk_domain_url' name='freshdesk_options[freshdesk_domain_url]' size='72' type='text' value='{$options['freshdesk_domain_url']}' />";
	echo '<div class="info-data fd_ui_element">Eg: https://yourcompany.freshdesk.com</div>';
}

function freshdesk_cname_callback() {
	$options = get_option( 'freshdesk_options' );
	echo "<input class='fd_ui_element' id='freshdesk_cname' name='freshdesk_options[freshdesk_cname]' size='72' type='text' value='{$options['freshdesk_cname']}' />";
	echo '<div class="info-data fd_ui_element">Eg: https://support.yourdomain.com,https://support2.yourdomain.com</div>';
}

function freshdesk_enable_sso_callback() {
	$options = get_option( 'freshdesk_options' );
	echo '<tr><td colspan="2"><ul class="fd-form-table"><li><div><label><input class="fd_button" type="checkbox" name="freshdesk_options[freshdesk_enable_sso]" id="freshdesk_enable_sso" '.$options['freshdesk_enable_sso'].' /><span class="fd_ui_element fd-bold">Enable SSO Login </span></label><div><div class="info-data fd_lmargin">Enabling this will let your users to login using their wordpress credentials</div></li>';
	
	//SSO Secret
	echo '<div id="freshdesk_sso_options" style="display: none;padding-left:45px">';
	freshdesk_sso_callback();
	
	//Remote login and Logout Urls
	freshdesk_sso_urls_callback();
	echo '</div>';
	
}

function freshdesk_sso_callback() {
	$options = get_option( 'freshdesk_options' );
	echo "<div class='freshdesk_sso_settings' style='display: none;' ><div class='info-title'>".__("SSO Shared Secret")."</div><input class='fd_ui_element' id='freshdesk_sso_key' name='freshdesk_options[freshdesk_sso_key]' size='72' type='text' value='{$options['freshdesk_sso_key']}' />";
	echo '<div class="info-data fd_ui_element freshdesk_helpdesk_url">Enable SSO on your Helpdesk account and copy the <a href="'.$options['freshdesk_domain_url'].'/admin/security" target="_blank" >SSO shared secret</a> above.</div></div>';
}

function freshdesk_sso_urls_callback() {
	$options = get_option( 'freshdesk_options' );
	echo '<ul class="fd-content freshdesk_sso_settings" style="display: none;"><li><div class="info-title">'.__('Remote Login URL').'</div>';
	echo '<input class="fd-code" value="' . wp_login_url() . '?action=freshdesk-login" type="button"/>';
	echo '<div class="info-data freshdesk_helpdesk_url">'.__("Copy the above <i>Remote Login Url</i> to your").' <a href="'.$options['freshdesk_domain_url'].'/admin/security" target="_blank" >Single Sign On settings.</a></div></li>';
	echo '<li><div class="info-title">'.__('Remote Logout URL').'</div>';
	echo '<input class="fd-code" value="' . wp_login_url() . '?action=freshdesk-logout" type="button"/>';
	echo '<div class="info-data freshdesk_helpdesk_url" id="freshdesk_redirect_url">'.__("Copy the above <i>Remote Logout Url</i> to your").' <a href="'.$options['freshdesk_domain_url'].'/admin/security" target="_blank" >Single Sign On settings.</a></div></li></ul>';
}

function freshdesk_api_callback() {
	$options = get_option( 'freshdesk_options' );
	echo "<input class='fd_ui_element' type='text' name='freshdesk_options[freshdesk_api_key]' size='72' value='{$options['freshdesk_api_key']}' />";
	echo '<div class="info-data fd_ui_element">'.__("Your Helpdesk's Apikey will be available under Agent profile settings.").'</div>';
}

function freshdesk_enable_fb_callback() {
	$options = get_option( 'freshdesk_feedback_options' );
	echo '<tr><td colspan="2"><ul class="fd-form-table"><li><div><label><input class="fd_button" type="checkbox" name="freshdesk_feedback_options[freshdesk_enable_feedback]" id="freshdesk_enable_feedback" '.$options['freshdesk_enable_feedback'].' /><span class="fd_ui_element fd-bold">Show FeedBack Widget </span></label><div><div class="info-data fd_lmargin">This widget will be shown on your wordpress site for Visitors to post feedback</div></li>';
	freshdesk_fb_widget_callback();
}

function freshdesk_fb_widget_callback() {
	$options = get_option( 'freshdesk_feedback_options' );
	$fd_options = get_option( 'freshdesk_options' );
	
	echo '<li><div id="freshdesk_feedback_widget_id" style="display: none;"><div class="info-data  fd_text fd_ui_element freshdesk_widget_url"><a href="'.$fd_options['freshdesk_domain_url'].'/admin/widget_config" target="_blank">Copy feedback widget code</a> from your helpdesk and paste it below.</div>';
	echo '<textarea class="fd_ui_element fd_text" name="freshdesk_feedback_options[freshdesk_fb_widget_code]" id="freshdesk_fb_widget_code" rows="7">'.$options['freshdesk_fb_widget_code'].'</textarea></div></li></ul></td></tr>';
}

/* This is the validation(db before_save) callback */
function validate_freshdesk_settings( $input ) {
	$freshdesk_options = get_option( 'freshdesk_options' );
	$error = 0;
	$url=trim($input['freshdesk_domain_url']);
	if ( $url && ! preg_match( DOMAIN_REGEX, $url )  ) {
		add_settings_error(
			'freshdesk_domain_url', // setting title
			'fd_invalid_domain', // error ID
			"$url is an invalid  Helpdesk url", // error message
			'error' // type of message
		);
		$error=1;
	}
	$cnames = split( ",", trim($input['freshdesk_cname'] ) );
	foreach ( $cnames as $cname ) {
		$cname = trim($cname);
		if ( ! preg_match( DOMAIN_REGEX, $cname ) && $cname ) {
			add_settings_error(
				'freshdesk_cname', // setting title
				'fd_cname_invalid_domain', // error ID
				"$cname is an invalid  domain url", // error message
				'error' // type of message
			);
			$error=1;
			break;
		}
	}
	$cname = trim($input['freshdesk_cname'] );
	
	$sso_secret = $input['freshdesk_sso_key'];
	$api_key = $input['freshdesk_api_key'];
	$enable_feedback = validate_checkbox( $input['freshdesk_enable_feedback'] );
	$enable_sso = validate_checkbox( $input['freshdesk_enable_sso'] );
	
	if ( ! $url ) {
		add_settings_error(
			'freshdesk_domain_url', // setting title
			'fd_domain_url_not_present', // error ID
			'Helpdesk url cannot be blank', // error message
			'error' // type of message
		);
		$error=1;
	}
	
	if ( ! $api_key ) {
		add_settings_error(
			'freshdesk_api_key', // setting title
			'fd_api_key_not_present', // error ID
			'API key cannot be blank', // error message
			'error' // type of message
		);
		$error=1;
	}
	
	if ( $enable_sso == 'checked' && ! $sso_secret ) {
		add_settings_error(
			'freshdesk_sso_key', // setting title
			'fd_sso_key_not_present', // error ID
			'Please enter the sso secret to enable sso', // error message
			'error' // type of message
		);
		$error=1;
	}
	
	if ( $error ) {
		return $freshdesk_options;
	}
	$settings = array( 'freshdesk_domain_url' => $url, 'freshdesk_cname' => $cname, 'freshdesk_enable_sso' => $enable_sso, 'freshdesk_sso_key' => $sso_secret, 'freshdesk_api_key' => $api_key, 'freshdesk_enable_feedback' => $enable_feedback );
	
	return $settings;	
}

function validate_freshdesk_fb_settings( $input ) {
	$enable_feedback = validate_checkbox( $input['freshdesk_enable_feedback'] );
	$fb_widget_code = $input['freshdesk_fb_widget_code'];
	$settings = array( 'freshdesk_fb_widget_code' => $fb_widget_code, 'freshdesk_enable_feedback' => $enable_feedback );
	return $settings;
}

function validate_checkbox( $input ){
	if($input == 'on'){
		$input = 'checked';
	}
	return $input;
}
/* Validation callback End. */

/* Adding 'Create Ticket' Action for the Comments*/
function freshdesk_comment_action( $actions, $comment ) {
	$options = get_option( 'freshdesk_options' );
	if (current_user_can( 'administrator') ) {
		if( (trim( get_comment_meta( $comment->comment_ID, "fd_ticket_id", true) ) == false ) ){
			$actions['freshdesk'] = '<a class="fd_convert_ticket" href="#" domain_url='.$options['freshdesk_domain_url'].' id="' . $comment->comment_ID . '">' . __( 'Convert to Ticket', 'fd_ticket' ) . '</a>';
		}
		else {
			$actions['freshdesk'] = '<a class="fd_convert_ticket" href="#" title="hello" ticket_id="'.get_comment_meta($comment->comment_ID,"fd_ticket_id", true).'"domain_url='.$options['freshdesk_domain_url'].' id="' . $comment->comment_ID . '">' . __( 'View Ticket', 'fd_ticket_link' ) . '</a>';
		}
	}
	return $actions;
}


//freshdesk login sso handler/feedback widget handler.
//and css/js loader for settings and comments page.
function fd_login() {
	global $pagenow, $display_name , $user_email;
	if ( 'wp-login.php' == $pagenow ){
		$freshdesk_options = get_option( 'freshdesk_options' );
		$domain = get_redirect_url($_REQUEST['host_url']);
		error_log("Domain : $domain ");
		if( ! $domain ) {
			return;
		}
		if ( $_GET['action'] == 'freshdesk-login' ) {
			// NOTE: is_user_logged_in dont't work during  [wp-submit] => Log In
			if( $freshdesk_options['freshdesk_enable_sso'] != 'checked' ){
				return;
			}
			if ( is_user_logged_in() ) {
				// For the case when user has already logged into Wordpress and then in another tab opens Freshdesk and click on login then he should be logged into FD with out entering credentials.
				$current_user = wp_get_current_user();
				$secret = $freshdesk_options['freshdesk_sso_key'];
				$user_name= $current_user->data->display_name;
				$user_email = $current_user->user_email;
				$data = $user_name.$user_email.time();
				$hash_key = hash_hmac( "md5", $data, $secret );
				$url = freshdesk_sso_login_url( $domain, $user_name, $user_email ,$hash_key );
				header( 'Location: '.$url ) ;	
				die();
			}
			else{ // if wordpress is not logged in.
				
				if (isset($domain)){
					header( "Location: " .wp_login_url()."?redirect_to=fd_redirect_to=".$domain );
					die();
				}
		 	}
		}
		if ( $_GET['action'] == 'freshdesk-logout' ) {
			wp_logout();
			header( 'Location: '.$domain );
			die();
		}
	}
	if ( 'edit-comments.php' == $pagenow ||  ( $_GET['page'] == 'freshdesk-menu-handle' ) ){
		if ( current_user_can( 'manage_options' ) ) {
			wp_enqueue_script( 'fd_plugin_js',FD_PLUGIN_URL . 'js/freshdesk_plugin_js.js', array( 'jquery' ) );
			wp_localize_script( 'fd_plugin_js', 'myAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
		}
	}
	wp_enqueue_style( 'fd_plugin_css',FD_PLUGIN_URL . 'css/freshdesk_plugin.css' );
	$feedback_options=get_option( 'freshdesk_feedback_options' );
	if ( $feedback_options['freshdesk_enable_feedback'] == "checked" ) {
		add_action( 'wp_footer', 'freshdesk_widget_code' );
	}
}

//Feedback widget code snippet include.
function freshdesk_widget_code() {
	$options = get_option( 'freshdesk_feedback_options' );
	echo $options['freshdesk_fb_widget_code'];
}

function freshdesk_handshake_secret() {
	
}

function freshdesk_sso_login_url($domain, $user_name, $email, $hash_key){
	return $domain."/login/sso?name=".urlencode($user_name)."&email=".urlencode($email)."&timestamp=".time()."&hash=".urlencode($hash_key);
}

//Ajax Action handler. Freshdesk Ticket creation handled here.
function fd_action_callback() {
	$id = $_POST['commentId'];	
	$comment = get_comment($id);
	$comment_link = get_comment_link( $comment, 'all' );
	$email = $comment->comment_author_email;
	$description = $comment->comment_content;
	$description = $description . "<br/><br/><a href=" . htmlentities($comment_link) . ">Go to comment</a>";
	$type = $comment->comment_type;
	$comment_meta = $comment->comment_agent;
	$comment_date = $comment->comment_date;
	$comment_post = $comment->comment_post_ID;
	$comment_author_name = $comment->comment_author;
	$subject = "comment id :".$id;
	$options = get_option( 'freshdesk_options' );
	$fd_api_handle = new Freshdesk_Plugin_Api( $options['freshdesk_api_key'], $options['freshdesk_domain_url'] );
	$result = $fd_api_handle->create_ticket( $email, $subject, $description );
	$response = array(
		'what'=>'helpdesk_ticket',
		'action'=>'create',
		'id'=>'1',
		'data'=>$result
	);
	
	$resp = add_comment_meta( $id, 'fd_ticket_id', $result, false );
	$xmlResponse = new WP_Ajax_Response( $response );
	$xmlResponse->send();
}


function freshdesk_show_msg( $message, $msgclass = 'info' ) {
	echo "<div id='message' class='$msgclass'>$message</div>";
}

function freshdesk_admin_msgs() {
	// check for our settings page - need this in conditional further down
	$wptuts_settings_pg = strpos( $_GET['page'], FD_PAGE_BASENAME );
	// collect setting errors/notices: //http://codex.wordpress.org/Function_Reference/get_settings_errors
	$set_errors = get_settings_errors();

	//display admin message only for the admin to see, only on our settings page and only when setting errors/notices are returned!
	if ( current_user_can ('manage_options') && $wptuts_settings_pg !== FALSE && ! empty( $set_errors ) ){
		// have our settings succesfully been updated?
		if ( $set_errors[0]['code'] == 'settings_updated' && isset( $_GET['settings-updated'] ) ) {
			freshdesk_show_msg("<p>" . $set_errors[0]['message'] . "</p>", 'updated');
		} else {
			// there maybe more than one so run a foreach loop.
			foreach ( $set_errors as $set_error ) {
				// set the title attribute to match the error "setting title" - need this in js file
				freshdesk_show_msg("<p class='setting-error-message' title='" . $set_error['setting'] . "'>" . $set_error['message'] . "</p>", 'error');
			}
		}
	}
}
?>
