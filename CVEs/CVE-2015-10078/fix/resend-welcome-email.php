<?php
/*
Plugin Name: Resend Welcome Email
Plugin URI: http://www.twitter.com/atwellpub
Description: Adds option to regenerate and resend password to user. Adds this option to user profile edit screen. 
Version: 1.0.1
Author: adbox
Author URI: http://www.twitter.com/atwellpub
*
*/



if ( !class_exists( 'Resend_Welcome_Email' )) {

	class Resend_Welcome_Email {
		
		/**
		*  initiates class
		*/
		public function __construct() {

			global $wpdb;
			
			if (!current_user_can('edit_user')) {
				return;
			}
			
			/* Define constants */
			self::define_constants();
			
			/* Define hooks and filters */
			self::load_hooks();
			
			/* adds admin listeners for processing actions */			
			self::add_admin_listeners();
			
		}
		

		
		/**
		*  Defines constants
		*/
		public static function define_constants() {
			define('RESEND_WELCOME_EMAIL_CURRENT_VERSION', '1.0.1' ); 
			define('RESEND_WELCOME_EMAIL_LABEL' , 'Resend Welcome Email' ); 
			define('RESEND_WELCOME_EMAIL_SLUG' , plugin_basename( dirname(__FILE__) ) ); 
			define('RESEND_WELCOME_EMAIL_FILE' ,  __FILE__ ); 
			define('RESEND_WELCOME_EMAIL_URLPATH', plugins_url( ' ', __FILE__ ) ); 
			define('RESEND_WELCOME_EMAIL_PATH', WP_PLUGIN_DIR.'/'.plugin_basename( dirname(__FILE__) ).'/' ); 
		}
		
		/**
		*  Loads hooks and filters selectively
		*/
		public static function load_hooks() {
				
			//add_filter( 'user_row_actions',  array( __CLASS__ , 'filter_user_row_actions' ), 10, 2 );
			add_filter( 'personal_options',  array( __CLASS__ , 'personal_options' ), 10, 2 );
			
		}
		
		
		/**
		 *  Discovers which tests to run and runs them
		 */
		public static function filter_user_row_actions(  array $actions, WP_User $user ) {
			
			if ( ! $link = self::send_welcome_email_url( $user ) ) {
				return $actions;
			}

			$actions['send_welcome_email'] = '<a href="' . $link . '">' . __( 'Resend Welcome Email', 'resend-welcome-email' ) . '</a>';

			return $actions;
		}
		
		public static function personal_options(  WP_User $user ) {
			if ( ! $link = self::send_welcome_email_url( $user ) ) {
				return $actions;
			}
			
			?>
			<tr>
				<th scope="row"><?php _e( 'Reset Password and Send Welcome Email',  'user-switching' ); ?></th>
				<td><a href="<?php echo $link; ?>"><?php _e( 'Reset Password and Send Welcome Email', 'user-switching' ); ?></a></td>
			</tr>
			<?php
		}
		
		/**
		 *  Listens for email send commands and fires them
		 */
		public static function add_admin_listeners() {
			if (!isset($_GET['action']) && $_GET['action'] != 'resend_welcome_email' ) {
				return;
			}
		
			/* Resend welcome email */
			self::resend_welcome_email();	
			
			/* Register success notice */
			add_action( 'admin_notices', array( __CLASS__ , 'define_notice') );
			
		}
		
		/** 
		 *  Register admin notice that email has been sent
		 */
		public static function define_notice() {
			?>
			<div class="updated">
				<p><?php _e( 'Welcome email sent!' , 'resend-welcome-email'); ?></p>
			</div>
			<?php			
		}
		
		/**
		 * Helper function. Returns the switch to or switch back URL for a given user.
		 *
		 * @param  WP_User $user The user to be switched to.
		 * @return string|bool The required URL, or false if there's no old user or the user doesn't have the required capability.
		 */
		public static function send_welcome_email_url( WP_User $user ) {

			return esc_url(wp_nonce_url( add_query_arg( array(
				'action'  => 'resend_welcome_email',
				'user_id' => $user->ID
			), '') , "send_welcome_email_{$user->ID}" ));

		}
		
		/**
		 * Resends the welcome email
		 *
		 * @param  int  $user_id      The ID of the user to re-send welcome email to
		 * @return bool|WP_User WP_User object on success, false on failure.
		 */
		public static function resend_welcome_email( ) {
			$user_id = $_GET['user_id'];
			
			if ( !$user = get_userdata( $user_id ) ) {
				return false;
			}
			
			// Generate a password
			$password = substr(md5(uniqid(microtime())), 0, 7);
			$user_info = get_userdata($user_id);

			wp_update_user(array('ID' => $user_id, 'user_pass' => $password));
	
			wp_new_user_notification($user_id, $password);

		}
		
		
	}

	/** 
	*  Load Resend_Welcome_Email class in init
	*/
	function Load_Resend_Welcome_Email() {
		$Resend_Welcome_Email = new Resend_Welcome_Email();
	}
	add_action( 'admin_init' , 'Load_Resend_Welcome_Email' , 99 );
}