<?php
/**
 * The WordPress Plugin Boilerplate.
 *
 * A foundation off of which to build well-documented WordPress plugins that
 * also follow WordPress Coding Standards and PHP best practices.
 *
 * @package   WooPopup
 * @author    Guillaume Kanoufi <guillaume@lostwebdesigns.com>
 * @license   GPL-2.0+
 * @link      http://lostwebdesigns.com
 * @copyright 2014 woocommerce, popup, woopopup
 *
 * @wordpress-plugin
 * Plugin Name:       woo-popup
 * Plugin URI:        http://wordpress.org/plugins/woo-popup/
 * Description:       Display a pop up window on the page of your choice.
 * Version:           1.3.0
 * Author:            Guillaume Kanoufi
 * Author URI:        https://github.com/g-kanoufi
 * Text Domain:       woo-popup-locale
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/<owner>/<repo>
 * WordPress-Plugin-Boilerplate: v2.6.1
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/

require_once( plugin_dir_path( __FILE__ ) . 'public/class-woo-popup.php' );

/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 */
register_activation_hook( __FILE__, array( 'WooPopup', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WooPopup', 'deactivate' ) );


add_action( 'plugins_loaded', array( 'WooPopup', 'get_instance' ) );

/*----------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *----------------------------------------------------------------------------*/

if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {

	require_once( plugin_dir_path( __FILE__ ) . 'admin/class-woo-popup-admin.php' );
	add_action( 'plugins_loaded', array( 'WooPopupAdmin', 'get_instance' ) );

}
