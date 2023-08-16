<?php
/**
 * Plugin Name: Mark User as Spammer
 * Plugin URI: http://korobochkin.com/
 * Description: The ability to mark specific users as spammers like on Multisite install.
 * Author: Kolya Korobochkin
 * Author URI: http://korobochkin.com/
 * Version: 1.0.1
 * Text Domain: mark_user_as_spammer
 * Domain Path: /languages/
 * Requires at least: 4.1.1
 * Tested up to: 4.1.1
 * License: GPLv2 or later
 */
class Mark_User_As_Spammer {

	/*
	 * @var array $selectors Indexed or empty array with users IDs.
	 * @see Mark_User_As_Spammer::user_row_actions()
	 * @see Mark_User_As_Spammer::admin_footer()
	 */
	public static $selectors = array();

	/*
	 * Do nothing.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/*
	 * Run this stuff at the end of the file.
	 *
	 * @since 1.0.0
	 */
	public static function run() {
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
		add_filter( 'authenticate', array( __CLASS__, 'authenticate' ), 99 );
		if ( is_admin() ) {
			add_filter( 'user_row_actions', array( __CLASS__, 'user_row_actions' ), 10, 2);
			add_action( 'load-users.php', array( __CLASS__, 'load_users_page' ) );
			add_action( 'admin_notices',  array( __CLASS__, 'admin_notices' ) );
		}
	}

	/*
	 * Load textdomain.
	 *
	 * @since 1.0.0
	 */
	public static function plugins_loaded() {
		load_plugin_textdomain( 'mark_user_as_spammer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/*
	 * If user have meta mark_user_as_spammer (meta_key) and this meta equal === '1' (meta_value)
	 * we don't allow to auth on site. It's the same method like Multisite uses.
	 *
	 * @since 1.0.0
	 * @param object $user WordPress user object
	 * @return object WordPress user object or WP_Error object with error description.
	 */
	public static function authenticate( $user ) {
		if ( $user instanceof WP_User ) {
			$meta = get_user_meta( $user->ID, 'mark_user_as_spammer', true);
			if ( $meta === '1' ) {
				// Text copied from wp-includes/user.php (line 217)
				return new WP_Error( 'spammer_account', __( '<strong>ERROR</strong>: Your account has been marked as a spammer.' ) );
			}
		}
		// Return $user if object is not an instantiated object of a WP_User class
		return $user;
	}

	/*
	 * Add link (<a href...) to user actions (on /wp-admin/users.php)
	 * which can contain Ban or Unban actions (with nonces for protect site).
	 *
	 * @since 1.0.0
	 * @param array $actions Array of actions (links) for each user.
	 * @param object $user_object WordPress user object
	 */
	public static function user_row_actions( $actions, $user_object ) {
		$meta = get_user_meta( $user_object->ID, 'mark_user_as_spammer', true);

		$is_spammer = false;
		if ( $meta === '1' ) {
			$is_spammer = true;
			self::$selectors[] = $user_object->ID;
		}
		unset( $meta );

		$url = add_query_arg(
			array(
				'mark_user_as_spammer_action' => $is_spammer ? 'unban' : 'ban',
				'user_id' => $user_object->ID
			)
		);

		$url = wp_nonce_url(
			$url,
			( $is_spammer ? 'mark_user_as_spammer_unban_' : 'mark_user_as_spammer_ban_' ) . $user_object->ID,
			'mark_user_as_spammer_nonce'
		);

		$actions['spammer'] = '<a href="'
			. site_url( $url )
			. '" class="mark-user-as-spammer" title="' . (
				$is_spammer ?
					esc_attr_x ('Unban user. He will be able to log in on site.', 'Verb. Mark user (account) like non spammer account', 'mark_user_as_spammer')
					:
					esc_attr_x ('Ban user. He will not be able to log in on site and get an error that his account marked as spammer.', 'Verb. Mark user (account) like spammer account', 'mark_user_as_spammer')
			) .'">'
			. (
				$is_spammer ?
					_x ('Unban', 'Verb. Mark user (account) like non spammer account', 'mark_user_as_spammer')
					:
					_x ('Ban', 'Verb. Mark user (account) like spammer account', 'mark_user_as_spammer')
			)
		    . '</a>';
		return $actions;
	}

	/*
	 * During load users page add admin_footer action for output styles for banned users (they marked with red background).
	 * Check if current request contain an information related to this plugin.
	 * If yes we trying to ban or unban user.
	 * If request with update_user_meta return an error our plugin output the red (error) notice on page.
	 *
	 * @since 1.0.0
	 */
	public static function load_users_page() {
		// Styles for banned accounts (red background)
		add_action( 'admin_footer', array( __CLASS__, 'admin_footer' ) );

		// Current request related to this plugin?
		if (
			! empty( $_GET['mark_user_as_spammer_action'] )
			&&
			in_array( $_GET['mark_user_as_spammer_action'], array ('unban', 'ban'))
			&&
			! empty( $_GET['user_id'] )
		) {
			$user_id = absint( $_GET['user_id'] );

			if( $user_id > 0) {
				// No sanitize because we use in_array above
				$action = $_GET['mark_user_as_spammer_action'];

				// Only users with promote_users cap can do this (by default admin and super admin)
				if( !current_user_can( 'promote_users' ) ) {
					wp_die( __( 'You do not have the permission to do that!', 'mark_user_as_spammer' ) );
				}

				// Check nonce (WordPress dies if nonce not valid and return 403)
				check_admin_referer( 'mark_user_as_spammer_' . $action . '_' .  $user_id,  'mark_user_as_spammer_nonce' );

				switch ($action) {
					case 'ban':
						$user_meta = '1';
						$message = 'spammed';
						break;

					case 'unban':
					default:
						$user_meta = '0';
						$message = 'unspammed';
						break;
				}

				// Update user meta in DB
				$update = update_user_meta(
					$user_id,
					'mark_user_as_spammer',
					$user_meta
				);

				$message = array( 'mark_user_as_spammer' => $message );
				if( !$update ) {
					$message['failed'] = '1';
				}

				// Delete args from URL and do redirect to current page with args with results of operation
				wp_safe_redirect(
					add_query_arg(
						$message, remove_query_arg( array ( 'mark_user_as_spammer_action', 'mark_user_as_spammer_nonce' ) )
					)
				);
				exit();
			}
		}
	}

	/*
	 * Shows up the message block which inform about success or failure on block (unblock) user
	 * Show up the error if update_user_meta return an error (checkout load_users_page() function above).
	 *
	 * @since 1.0.0
	 */
	public static function admin_notices() {
		// Logic grabbed from bbpress/includes/admin/topics.php
		if(
			!empty( $_GET['mark_user_as_spammer'])
			&&
			in_array( $_GET['mark_user_as_spammer'], array( 'spammed', 'unspammed' ) )
			&&
			!empty( $_GET['user_id'] )
		) {
			$user_id = absint( $_GET['user_id'] );

			if( $user_id > 0 ) {
				$is_failure = !empty( $_GET['failed'] ) ? true : false; // Was that a failure?
				$action = sanitize_text_field( $_GET['mark_user_as_spammer'] ); // Armor

				switch( $action ) {
					case 'spammed':
						if( $is_failure ) {
							$message = sprintf(
								_x( 'An error occured during blocking account with ID <code>%1$d</code>.', '%1$s - the account (user) ID (number)', 'mark_user_as_spammer' ),
								$user_id
							);
						}
						else {
							$message = sprintf(
								_x( 'Account with ID <code>%1$d</code> have been successfully banned and no longer log in.', '%1$s - the account (user) ID (number)', 'mark_user_as_spammer' ),
								$user_id
							);
						}
						break;

					case 'unspammed':
						if( $is_failure ) {
							$message = sprintf(
								_x( 'An error occured during unblocing account with ID <code>%1$d</code>.', '%1$s - the account (user) ID (number)', 'mark_user_as_spammer' ),
								$user_id
							);
						}
						else {
							$message = sprintf(
								_x( 'Account with ID <code>%1$d</code> have been successfully unbanned and now can log in.', '%1$s - the account (user) ID (number)', 'mark_user_as_spammer' ),
								$user_id
							);
						}
						break;

					default:
						$message = __( 'An error occured during something in Mark User as Spammer plugin.', 'mark_user_as_spammer' );
						break;
				}
				?>
				<div class="<?php echo $is_failure == true ? 'error' : 'updated'; ?> fade notice is-dismissible">
					<p><?php echo $message; ?></p>
				</div>
				<?php
			}
		}
	}

	/*
	 * Highlight blocked (banned) users with red background (like Multisite).
	 *
	 * @since 1.0.0
	 */
	public static function admin_footer() {
		if( !empty( self::$selectors ) ) {
			?>
			<style media="all" type="text/css">
				<?php foreach( self::$selectors as $selector) {
					echo '#user-' . $selector . ',';
				} ?> .mark-user_as_spammer_spammer { background: #faafaa; }
			</style>
			<?php
		}
	}

	/*
	 * Uninstall action callback.
	 *
	 * @since 1.0.0
	 */
	public static function on_uninstall() {
		// The uninstall plugin must be this file
		// The current user can activate plugins
		if( __FILE__ != WP_UNINSTALL_PLUGIN || ! current_user_can( 'activate_plugins') ){
			return;
		}

		// Additional check
		check_admin_referer( 'bulk-plugins' );

		// Delete user metas with `mark_user_as_spammer` meta_value
		global $wpdb;
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'mark_user_as_spammer' ), array ( '%s' ) );
	}
}
Mark_User_As_Spammer::run();

register_uninstall_hook( __FILE__, array( 'Mark_User_As_Spammer', 'on_uninstall') );
?>