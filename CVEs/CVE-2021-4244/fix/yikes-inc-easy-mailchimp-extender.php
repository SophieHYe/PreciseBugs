<?php
/**
 * Plugin Name: Easy Forms for Mailchimp
 * Plugin URI:  https://yikesplugins.com/plugin/easy-forms-for-mailchimp/
 * Description: The ultimate Mailchimp WordPress plugin. Easily build <strong>unlimited forms for your Mailchimp lists</strong>, add them to your site and track subscriber activity. To get started, go to the settings page and enter your <a href="https://yikesplugins.com/support/knowledge-base/finding-your-mailchimp-api-key/" target="_blank">Mailchimp API key</a>.
 * Version:     6.8.6
 * Author:      YIKES, Inc.
 * Author URI:  https://www.yikesplugins.com/
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: yikes-inc-easy-mailchimp-extender
 *
 * Easy Forms for Mailchimp is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Easy Forms for Mailchimp is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Easy Forms for Mailchimp. If not, see <http://www.gnu.org/licenses/>.
 *
 * We at YIKES, Inc. embrace the open source philosophy on a daily basis. We donate company time back to the WordPress project,
 * and constantly strive to improve the WordPress project and community as a whole.
 *
 * "'Free software' is a matter of liberty, not price. To understand the concept, you should think of 'free' as in 'free speech,' not as in 'free beer'."
 * - Richard Stallman
 */


// If accessed directly, abort
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 *  Define version constant
 *
 *  @since 6.1.3
 */
if ( ! defined( 'YIKES_MC_VERSION' ) ) {
	define( 'YIKES_MC_VERSION', '6.8.6' );
}

/**
 *  Define path constant to our plugin directory.
 *
 *  @since 6.0.0
 */
if ( ! defined( 'YIKES_MC_PATH' ) ) {
	define( 'YIKES_MC_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 *  Define URL constant to our plugin directory.
 *
 *  @since 6.0.0
 */
if ( ! defined( 'YIKES_MC_URL' ) ) {
	define( 'YIKES_MC_URL', plugin_dir_url( __FILE__ ) );
}

// Include our autoloader
require_once dirname( __FILE__ ) . '/class-loader.php';

/**
 *  activate_yikes_inc_easy_mailchimp_extender();
 *  Fires during activation.
 *
 *  This action is documented in includes/class-yikes-inc-easy-mailchimp-extender-activator.php
 *  and carries out some important tasks such as creating our custom database table if it doesn't
 *  already exist, and defining default options.
 *
 *  @since 6.0.0
 *  @return void
 */
register_activation_hook( __FILE__, 'activate_yikes_inc_easy_mailchimp_extender' );
function activate_yikes_inc_easy_mailchimp_extender( $network_wide ) {
	Yikes_Inc_Easy_Mailchimp_Extender_Activator::activate( $network_wide );
}

/**
 *  uninstall_yikes_inc_easy_mailchimp_extender();
 *  The code that runs during uninstall.
 *
 *  This action is documented in includes/class-yikes-inc-easy-mailchimp-extender-uninstall.php
 *  and carries out the deletion of Mailchimp transients, plugin options and Mailchimp form tables.
 *
 * @since 6.0.0
 *  @return void
 */
register_deactivation_hook( __FILE__, 'deactivate_yikes_inc_easy_mailchimp_extender' );
function deactivate_yikes_inc_easy_mailchimp_extender() {
	// delete the activation re-driect option
	update_option( 'yikes_mailchimp_activation_redirect', 'true' );
}

/**
 *  uninstall_yikes_inc_easy_mailchimp_extender();
 *  The code that runs during uninstall.
 *
 *  This action is documented in includes/class-yikes-inc-easy-mailchimp-extender-uninstall.php
 *  and carries out the deletion of Mailchimp transients, plugin options and Mailchimp form tables.
 *
 * @since 6.0.0
 * @return void
 */
register_uninstall_hook( __FILE__, 'uninstall_yikes_inc_easy_mailchimp_extender' );
function uninstall_yikes_inc_easy_mailchimp_extender() {
	Yikes_Inc_Easy_Mailchimp_Extender_Uninstaller::uninstall();
}

/**
 *  Multi-site blog creation
 *
 *  If a new blog is created on a mutli-site network
 *  we should run our activation hook to create the necessary form table
 *
 *  @since 6.0.0
 *  @return void
 */
add_action( 'wpmu_new_blog', 'yikes_easy_mailchimp_new_network_site', 10, 6 );
function yikes_easy_mailchimp_new_network_site( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
	if ( is_plugin_active_for_network( 'yikes-inc-easy-mailchimp-extender/yikes-inc-easy-mailchimp-extender.php' ) ) {
		switch_to_blog( $blog_id );
		Yikes_Inc_Easy_Mailchimp_Extender_Activator::activate( false );
		restore_current_blog();
	}
}

/**
 * Retrieve the forms interface that we should be using.
 *
 * By default this will use the new Options interface, but this can be
 * overridden by a constant, YIKES_MC_CUSTOM_DB.
 *
 * @author Jeremy Pry
 * @return Yikes_Inc_Easy_Mailchimp_Extender_Form_Interface
 */
function yikes_easy_mailchimp_extender_get_form_interface() {
	static $interface = null;

	if ( null === $interface ) {
		if ( yikes_inc_easy_mailchimp_extender_use_custom_db() ) {
			global $wpdb;
			$interface = new Yikes_Inc_Easy_Mailchimp_Extender_Forms( $wpdb );
		} else {
			$interface = new Yikes_Inc_Easy_Mailchimp_Extender_Option_Forms();
		}
	}

	return $interface;
}

/**
 * Determine whether we should use the custom database table.
 *
 * @author Jeremy Pry
 * @return bool Whether to use the custom database table.
 */
function yikes_inc_easy_mailchimp_extender_use_custom_db() {
	/**
	 * Filter whether we should use the custom database table instead of the Options API
	 *
	 * @param bool $use_custom_db True to use the custom database table, false to use the Options API.
	 */
	return (bool) apply_filters( 'yikes_easy_mailchimp_extender_use_custom_db', defined( 'YIKES_EMCE_CUSTOM_DB' ) && YIKES_EMCE_CUSTOM_DB );
}

/**
 *  Begins execution of the plugin.
 *
 *  @since 6.0.0
 *  @return Yikes_Inc_Easy_Mailchimp_Extender
 */
function yikes_inc_easy_mailchimp_extender() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new Yikes_Inc_Easy_Mailchimp_Extender( yikes_easy_mailchimp_extender_get_form_interface() );
		$plugin->run();
	}

	return $plugin;
}
yikes_inc_easy_mailchimp_extender()->run();

/**
 * Helper function to return our API key
 * Support the use of a PHP constant
 *
 * @return string Mailchimp API key from the PHP constant, or the options
 * @security strip away tags and patch security
 * @since 6.2.2
 */
function yikes_get_mc_api_key() {
	if ( defined( 'YIKES_MC_API_KEY' ) ) {
		return trim( strip_tags( YIKES_MC_API_KEY ) );
	}

	return trim( strip_tags( get_option( 'yikes-mc-api-key', '' ) ) );
}

/**
 * Get the API Manager instance.
 *
 * @author Jeremy Pry
 * @return Yikes_Inc_Easy_Mailchimp_API_Manager
 */
function yikes_get_mc_api_manager() {
	static $manager = null;

	if ( null === $manager ) {
		$manager = new Yikes_Inc_Easy_Mailchimp_API_Manager( yikes_get_mc_api_key() );
	}

	return $manager;
}

add_action( 'plugins_loaded', 'yikes_mailchimp_plugin_textdomain' );
function yikes_mailchimp_plugin_textdomain() {
	load_plugin_textdomain( 'yikes-inc-easy-mailchimp-extender', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
}

/*
*	Enjoy this wonderfully powerful (and free) plugin.
*	~<|:D
*/
