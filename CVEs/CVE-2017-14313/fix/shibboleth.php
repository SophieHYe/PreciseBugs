<?php
/*
 Plugin Name: Shibboleth
 Plugin URI: http://wordpress.org/extend/plugins/shibboleth
 Description: Easily externalize user authentication to a <a href="http://shibboleth.internet2.edu">Shibboleth</a> Service Provider
 Author: Will Norris, mitcho (Michael 芳貴 Erlewine)
 Version: 1.6
 License: Apache 2 (http://www.apache.org/licenses/LICENSE-2.0.html)
 */

define ( 'SHIBBOLETH_PLUGIN_REVISION', preg_replace( '/\$Rev: (.+) \$/', '\\1',
	'$Rev$') ); // this needs to be on a separate line so that svn:keywords can work its magic


// run activation function if new revision of plugin
$shibboleth_plugin_revision = shibboleth_get_option('shibboleth_plugin_revision');
if ($shibboleth_plugin_revision === false || SHIBBOLETH_PLUGIN_REVISION != $shibboleth_plugin_revision) {
	add_action('admin_init', 'shibboleth_activate_plugin');
}

/**
 * Perform automatic login. This is based on the user not being logged in,
 * an active session and the option being set to true.
 */
function shibboleth_auto_login() {
	$shibboleth_auto_login = shibboleth_get_option('shibboleth_auto_login');
	if ( !is_user_logged_in() && shibboleth_session_active() && $shibboleth_auto_login ) {
		do_action('login_form_shibboleth');

		$userobj = wp_signon('', true);
		if ( is_wp_error($userobj) ) {
			// TODO: Proper error return.
		} else {
			wp_safe_redirect($_SERVER['REQUEST_URI']);
			exit();
		}
	}
}
add_action('init', 'shibboleth_auto_login');

/**
 * Activate the plugin.  This registers default values for all of the 
 * Shibboleth options and attempts to add the appropriate mod_rewrite rules to 
 * WordPress's .htaccess file.
 */
function shibboleth_activate_plugin() {
	if ( function_exists('switch_to_blog') ) switch_to_blog($GLOBALS['current_site']->blog_id);

	shibboleth_add_option('shibboleth_login_url', get_option('home') . '/Shibboleth.sso/Login');
	shibboleth_add_option('shibboleth_default_login', false);
	shibboleth_add_option('shibboleth_auto_login', false);
	shibboleth_add_option('shibboleth_logout_url', get_option('home') . '/Shibboleth.sso/Logout');

	$headers = array(
		'username' => array( 'name' => 'eppn', 'managed' => false),
		'first_name' => array( 'name' => 'givenName', 'managed' => true),
		'last_name' => array( 'name' => 'sn', 'managed' => true),
		'nickname' => array( 'name' => 'eppn', 'managed' => true),
		'display_name' => array( 'name' => 'displayName', 'managed' => true),
		'email' => array( 'name' => 'mail', 'managed' => true),
	);
	shibboleth_add_option('shibboleth_headers', $headers);

	$roles = array(
		'administrator' => array(
			'header' => 'entitlement',
			'value' => 'urn:mace:example.edu:entitlement:wordpress:admin',
		),
		'author' => array(
			'header' => 'affiliation',
			'value' => 'faculty',
		),
		// TODO: this could likely do strange things if WordPress has an actual role named 'default'
		'default' => 'subscriber',
	);
	shibboleth_add_option('shibboleth_roles', $roles);

	shibboleth_add_option('shibboleth_update_roles', true);

	shibboleth_insert_htaccess();

	shibboleth_migrate_old_data();

	shibboleth_update_option('shibboleth_plugin_revision', SHIBBOLETH_PLUGIN_REVISION);

	if ( function_exists('restore_current_blog') ) restore_current_blog();
}
register_activation_hook('shibboleth/shibboleth.php', 'shibboleth_activate_plugin');


/**
 * Cleanup certain plugins options on deactivation.
 */
function shibboleth_deactivate_plugin() {
	shibboleth_remove_htaccess();
}
register_deactivation_hook('shibboleth/shibboleth.php', 'shibboleth_deactivate_plugin');


/**
 * Migrate old data to newer formats.
 */
function shibboleth_migrate_old_data() {

	// new header format, allowing each header to be marked as 'managed' individually
	$managed = shibboleth_get_option('shibboleth_update_users');
	$headers = shibboleth_get_option('shibboleth_headers');
	$updated = false;

	foreach ($headers as $key => $value) {
		if ( is_string($value) ) {
			$headers[$key] = array(
				'name' => $value,
				'managed' => $managed,
			);
			$updated = true;
		}
	}

	if ( $updated ) {
		shibboleth_update_option('shibboleth_headers', $headers);
	}
	shibboleth_delete_option('shibboleth_update_users');

}

/**
 * Load Shibboleth admin hooks only on admin page loads.  
 *
 * 'admin_init' is actually called *after* 'admin_menu', so we have to hook in 
 * to the 'init' action for this.
 */
function shibboleth_admin_hooks() {
	if ( defined('WP_ADMIN') && WP_ADMIN === true ) {
		require_once dirname(__FILE__) . '/options-admin.php';
		require_once dirname(__FILE__) . '/options-user.php';
	}
}
add_action('init', 'shibboleth_admin_hooks');


/**
 * Check if a Shibboleth session is active.
 *
 * @return boolean if session is active
 * @uses apply_filters calls 'shibboleth_session_active' before returning final result
 */
function shibboleth_session_active() { 
	$active = false;

	$session_headers = array('Shib-Session-ID', 'Shib_Session_ID', 'HTTP_SHIB_IDENTITY_PROVIDER');
	foreach ($session_headers as $header) {
		if ( array_key_exists($header, $_SERVER) && !empty($_SERVER[$header]) ) {
			$active = true;
			break;
		}
	}

	$active = apply_filters('shibboleth_session_active', $active);
	return $active;
}


/**
 * Authenticate the user using Shibboleth.  If a Shibboleth session is active, 
 * use the data provided by Shibboleth to log the user in.  If a Shibboleth 
 * session is not active, redirect the user to the Shibboleth Session Initiator 
 * URL to initiate the session.
 */
function shibboleth_authenticate($user, $username, $password) {
	if ( shibboleth_session_active() ) {
		return shibboleth_authenticate_user();
	} else {
		$initiator_url = shibboleth_session_initiator_url( $_REQUEST['redirect_to'] );
		wp_redirect($initiator_url);
		exit;
	}
}


/**
 * When wp-login.php is loaded with 'action=shibboleth', hook Shibboleth 
 * into the WordPress authentication flow.
 */
function shibboleth_login_form_shibboleth() {
	add_filter('authenticate', 'shibboleth_authenticate', 10, 3);
}
add_action('login_form_shibboleth', 'shibboleth_login_form_shibboleth');


/**
 * If a Shibboleth user requests a password reset, and the Shibboleth password 
 * reset URL is set, redirect the user there.
 */
function shibboleth_retrieve_password( $user_login ) {
	$password_reset_url = shibboleth_get_option('shibboleth_password_reset_url');

	if ( !empty($password_reset_url) ) {
		$user = get_userdatabylogin($user_login);
		if ( $user && get_usermeta($user->ID, 'shibboleth_account') ) {
			wp_redirect($password_reset_url);
			exit;
		}
	}
}
add_action('retrieve_password', 'shibboleth_retrieve_password');


/**
 * If Shibboleth is the default login method, add 'action=shibboleth' to the 
 * WordPress login URL.
 */
function shibboleth_login_url($login_url) {
	if ( shibboleth_get_option('shibboleth_default_login') ) {
		$login_url = add_query_arg('action', 'shibboleth', $login_url);
	}

	return $login_url;
}
add_filter('login_url', 'shibboleth_login_url');


/**
 * If the Shibboleth logout URL is set and the user has an active Shibboleth 
 * session, log the user out of Shibboleth after logging them out of WordPress.
 */
function shibboleth_logout() {
	$logout_url = shibboleth_get_option('shibboleth_logout_url');

	if ( !empty($logout_url) && shibboleth_session_active() ) {
		wp_redirect($logout_url);
		exit;
	}
}
add_action('wp_logout', 'shibboleth_logout', 20);


/**
 * Generate the URL to initiate Shibboleth login.
 *
 * @param string $redirect the final URL to redirect the user to after all login is complete
 * @return the URL to direct the user to in order to initiate Shibboleth login
 * @uses apply_filters() Calls 'shibboleth_session_initiator_url' before returning session intiator URL
 */
function shibboleth_session_initiator_url($redirect = null) {

	// first build the target URL.  This is the WordPress URL the user will be returned to after Shibboleth 
	// is done, and will handle actually logging the user into WordPress using the data provdied by Shibboleth 
	if ( function_exists('switch_to_blog') ) switch_to_blog($GLOBALS['current_site']->blog_id);
	$target = site_url('wp-login.php');
	if ( function_exists('restore_current_blog') ) restore_current_blog();

	$target = add_query_arg('action', 'shibboleth', $target);
	if ( !empty($redirect) ) {
		$target = add_query_arg('redirect_to', urlencode($redirect), $target);
	}

	// now build the Shibboleth session initiator URL
	$initiator_url = shibboleth_get_option('shibboleth_login_url');
	$initiator_url = add_query_arg('target', urlencode($target), $initiator_url);

	$initiator_url = apply_filters('shibboleth_session_initiator_url', $initiator_url);

	return $initiator_url;
}


/**
 * Authenticate the user based on the current Shibboleth headers.
 *
 * If the data available does not map to a WordPress role (based on the
 * configured role-mapping), the user will not be allowed to login.
 *
 * If this is the first time we've seen this user (based on the username
 * attribute), a new account will be created.
 *
 * Known users will have their profile data updated based on the Shibboleth
 * data present if the plugin is configured to do so.
 *
 * @return WP_User|WP_Error authenticated user or error if unable to authenticate
 */
function shibboleth_authenticate_user() {
	$shib_headers = shibboleth_get_option('shibboleth_headers');

	// ensure user is authorized to login
	$user_role = shibboleth_get_user_role();

	if ( empty($user_role) ) {
		return new WP_Error('no_access', __('You do not have sufficient access.'));
	}

	$username = $_SERVER[$shib_headers['username']['name']];
	$user = new WP_User($username);

	if ( $user->ID ) {
		if ( !get_usermeta($user->ID, 'shibboleth_account') ) {
			// TODO: what happens if non-shibboleth account by this name already exists?
			//return new WP_Error('invalid_username', __('Account already exists by this name.'));
		}
	}

	// create account if new user
	if ( !$user->ID ) {
		$user = shibboleth_create_new_user($username);
	}

	if ( !$user->ID ) {
		$error_message = 'Unable to create account based on data provided.';
		if (defined('WP_DEBUG') && WP_DEBUG) {
			$error_message .= '<!-- ' . print_r($_SERVER, true) . ' -->';
		}
		return new WP_Error('missing_data', $error_message);
	}

	// update user data
	update_usermeta($user->ID, 'shibboleth_account', true);
	shibboleth_update_user_data($user->ID);
	if ( shibboleth_get_option('shibboleth_update_roles') ) {
		$user->set_role($user_role);
		do_action( 'shibboleth_set_user_roles', $user );
	}

	return $user;
}


/**
 * Create a new WordPress user account, and mark it as a Shibboleth account.
 *
 * @param string $user_login login name for the new user
 * @return object WP_User object for newly created user
 */
function shibboleth_create_new_user($user_login) {
	if ( empty($user_login) ) return null;

	// create account and flag as a shibboleth acount
	require_once( ABSPATH . WPINC . '/registration.php' );
	$user_id = wp_insert_user(array('user_login'=>$user_login));
	$user = new WP_User($user_id);
	update_usermeta($user->ID, 'shibboleth_account', true);

	// always update user data and role on account creation
	shibboleth_update_user_data($user->ID, true);
	$user_role = shibboleth_get_user_role();
	$user->set_role($user_role);
	do_action( 'shibboleth_set_user_roles', $user );

	return $user;
}


/**
 * Get the role the current user should have.  This is determined by the role
 * mapping configured for the plugin, and the Shibboleth headers present at the
 * time of login.
 *
 * @return string the role the current user should have
 * @uses apply_filters() Calls 'shibboleth_roles' after retrieving shibboleth_roles array
 * @uses apply_filters() Calls 'shibboleth_user_role' before returning final user role
 */
function shibboleth_get_user_role() {
	global $wp_roles;
	if ( !$wp_roles ) $wp_roles = new WP_Roles();

	$shib_roles = apply_filters('shibboleth_roles', shibboleth_get_option('shibboleth_roles'));
	$user_role = $shib_roles['default'];

	foreach ( $wp_roles->role_names as $key => $name ) {
		$role_header = $shib_roles[$key]['header'];
		$role_value = $shib_roles[$key]['value'];

		if ( empty($role_header) || empty($role_value) ) continue;

		$values = split(';', $_SERVER[$role_header]);
		if ( in_array($role_value, $values) ) {
			$user_role = $key;
			break;
		}
	}

	$user_role = apply_filters('shibboleth_user_role', $user_role);

	return $user_role;
}


/**
 * Get the user fields that are managed by Shibboleth.
 *
 * @return Array user fields managed by Shibboleth
 */
function shibboleth_get_managed_user_fields() {
	$headers = shibboleth_get_option('shibboleth_headers');
	$managed = array();

	foreach ($headers as $name => $value) {
		if ( $value['managed'] ) {
			$managed[] = $name;
		}
	}

	return $managed;
}


/**
 * Update the user data for the specified user based on the current Shibboleth headers.  Unless 
 * the 'force_update' parameter is true, only the user fields marked as 'managed' fields will be 
 * updated.
 *
 * @param int $user_id ID of the user to update
 * @param boolean $force_update force update of user data, regardless of 'managed' flag on fields
 * @uses apply_filters() Calls 'shibboleth_user_*' before setting user attributes, 
 *       where '*' is one of: login, nicename, first_name, last_name, 
 *       nickname, display_name, email
 */
function shibboleth_update_user_data($user_id, $force_update = false) {
	require_once( ABSPATH . WPINC . '/registration.php' );

	$shib_headers = shibboleth_get_option('shibboleth_headers');

	$user_fields = array(
		'user_login' => 'username',
		'user_nicename' => 'username',
		'first_name' => 'first_name',
		'last_name' => 'last_name',
		'nickname' => 'nickname',
		'display_name' => 'display_name',
		'user_email' => 'email'
	);

	$user_data = array(
		'ID' => $user_id,
	);
	
	foreach ($user_fields as $field => $header) {
		if ( $force_update || $shib_headers[$header]['managed'] ) {
			$filter = 'shibboleth_' . ( strpos($field, 'user_') === 0 ? '' : 'user_' ) . $field;
			$user_data[$field] = apply_filters($filter, $_SERVER[$shib_headers[$header]['name']]);
		}
	}

	wp_update_user($user_data);
}


/**
 * Sanitize the nicename using sanitize_user
 * See discussion: http://wordpress.org/support/topic/377030
 * 
 * @since 1.4
 */
add_filter( 'shibboleth_user_nicename', 'sanitize_user' );

/**
 * Add a "Login with Shibboleth" link to the WordPress login form.  This link 
 * will be wrapped in a <p> with an id value of "shibboleth_login" so that 
 * deployers can style this however they choose.
 */
function shibboleth_login_form() {
	$login_url = add_query_arg('action', 'shibboleth');
	$login_url = remove_query_arg('reauth', $login_url);
	echo '<p id="shibboleth_login"><a href="' . esc_url($login_url) . '">' . __('Login with Shibboleth', 'shibboleth') . '</a></p>';
}
add_action('login_form', 'shibboleth_login_form');


/**
 * Insert directives into .htaccess file to enable Shibboleth Lazy Sessions.
 */
function shibboleth_insert_htaccess() {
	if ( got_mod_rewrite() ) {
		$htaccess = get_home_path() . '.htaccess';
		$rules = array('AuthType shibboleth', 'Require shibboleth');
		insert_with_markers($htaccess, 'Shibboleth', $rules);
	}
}


/**
 * Remove directives from .htaccess file to enable Shibboleth Lazy Sessions.
 */
function shibboleth_remove_htaccess() {
	if ( got_mod_rewrite() ) {
		$htaccess = get_home_path() . '.htaccess';
		insert_with_markers($htaccess, 'Shibboleth', array());
	}
}


/* Custom option functions to correctly use WPMU *_site_option functions when available. */
function shibboleth_get_option($key, $default = false ) {
	return function_exists('get_site_option') ? get_site_option($key, $default) : get_option($key, $default);
}
function shibboleth_add_option($key, $value, $autoload = 'yes') {
	if (function_exists('add_site_option')) {
		return add_site_option($key, $value);
	} else {
		return add_option($key, $value, '', $autoload);
	}
}
function shibboleth_update_option($key, $value) {
	return function_exists('update_site_option') ? update_site_option($key, $value) : update_option($key, $value);
}
function shibboleth_delete_option($key) {
	return function_exists('delete_site_option') ? delete_site_option($key) : delete_option($key);
}

/**
 * Load localization files.
 */
function shibboleth_load_textdomain() {
	load_plugin_textdomain('shibboleth', false, dirname( plugin_basename( __FILE__ ) ) . '/localization/');
}
add_action('plugins_loaded', 'shibboleth_load_textdomain');
