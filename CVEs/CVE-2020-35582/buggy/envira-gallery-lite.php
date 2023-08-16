<?php
/**
 * Plugin Name: Envira Gallery Lite
 * Plugin URI:  http://enviragallery.com
 * Description: Envira Gallery is the best responsive WordPress gallery plugin. This is the Lite version.
 * Author:      Envira Gallery Team
 * Author URI:  http://enviragallery.com
 * Version:     1.8.3.2
 * Text Domain: envira-gallery-lite
 *
 * Envira Gallery is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Envira Gallery is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Envira Gallery. If not, see <http://www.gnu.org/licenses/>.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * @since 1.0.0
 *
 * @package Envira_Gallery
 * @author  Envira Gallery Team
 */
class Envira_Gallery_Lite {

	/**
	 * Holds the class object.
	 *
	 * @since 1.0.0
	 *
	 * @var object
	 */
	public static $instance;

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $version = '1.8.3.2';

	/**
	 * The name of the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $plugin_name = 'Envira Gallery Lite';

	/**
	 * Unique plugin slug identifier.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $plugin_slug = 'envira-gallery-lite';

	/**
	 * Plugin file.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $file = __FILE__;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Fire a hook before the class is setup.
		do_action( 'envira_gallery_pre_init' );

		// Load the plugin textdomain.
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		// Load the plugin.
		add_action( 'init', array( $this, 'init' ), 0 );

		if ( ! defined( 'ENVIRA_VERSION' ) ) {

			define( 'ENVIRA_VERSION', $this->version );

		}

		if ( ! defined( 'ENVIRA_SLUG' ) ) {

			define( 'ENVIRA_SLUG', $this->plugin_slug );

		}

		if ( ! defined( 'ENVIRA_FILE' ) ) {

			define( 'ENVIRA_FILE', $this->file );

		}

		if ( ! defined( 'ENVIRA_DIR' ) ) {

			define( 'ENVIRA_DIR', plugin_dir_path( __FILE__ ) );

		}

		if ( ! defined( 'ENVIRA_URL' ) ) {

			define( 'ENVIRA_URL', plugin_dir_url( __FILE__ ) );

		}

	}

	/**
	 * Loads the plugin textdomain for translation.
	 *
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain( 'envira-gallery-lite' );

	}

	/**
	 * Loads the plugin into WordPress.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Don't load Lite if Envira Gallery is already active.
		// Envira Gallery will display a notice telling the user to deactivate Lite.
        if ( class_exists( 'Envira_Gallery' ) ) {
            return;
        }

        // Run hook once Envira has been initialized.
        // This hook is deliberately different from the Pro version, to prevent the entire site breaking
		// if a user activates Lite with Pro Addons.
        do_action( 'envira_gallery_lite_init' );

		// Load global components.
		$this->require_global();

        // Load admin only components.
        if ( is_admin() ) {
            $this->require_admin();
        }

		// Add hook for when Envira has loaded.
		// This hook is deliberately different from the Pro version, to prevent the entire site breaking
		// if a user activates Lite with Pro Addons.
		do_action( 'envira_gallery_lite_loaded' );

	}

	/**
	 * Loads all admin related files into scope.
	 *
	 * @since 1.0.0
	 */
	public function require_admin() {

		require plugin_dir_path( __FILE__ ) . 'includes/admin/addons.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/common.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/editor.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/media.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/media-view.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/metaboxes.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/notice.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/posttype.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/table.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/review.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/gutenberg.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/subscribe.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/promotion.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/welcome.php';

	}

	/**
	 * Loads a partial view for the Administration screen
	 *
	 * @since 1.5.0
	 *
	 * @param      string    $template      PHP file at includes/admin/partials, excluding file extension
	 * @param      array     $data          Any data to pass to the view
	 * @return     void
	 */
	public function load_admin_partial( $template, $data = array() ){

		$dir = trailingslashit( plugin_dir_path( __FILE__ ) . 'includes/admin/partials' );

		if ( file_exists( $dir . $template . '.php' ) ) {
			require_once(  $dir . $template . '.php' );
			return true;
		}

		return false;

	}

	/**
	 * Loads all global files into scope.
	 *
	 * @since 1.0.0
	 */
	public function require_global() {

		require plugin_dir_path( __FILE__ ) . 'includes/global/common.php';
		require plugin_dir_path( __FILE__ ) . 'includes/global/posttype.php';
		require plugin_dir_path( __FILE__ ) . 'includes/global/shortcode.php';
		require plugin_dir_path( __FILE__ ) . 'includes/global/rest.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/ajax.php';

	}

	/**
	 * Returns a gallery based on ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id     The gallery ID used to retrieve a gallery.
	 * @return array|bool Array of gallery data or false if none found.
	 */
	public function get_gallery( $id ) {

		// Attempt to return the transient first, otherwise generate the new query to retrieve the data.
		if ( false === ( $gallery = get_transient( '_eg_cache_' . $id ) ) ) {
			$gallery = $this->_get_gallery( $id );
			if ( $gallery ) {
				$expiration = Envira_Gallery_Common::get_instance()->get_transient_expiration_time();
				set_transient( '_eg_cache_' . $id, $gallery, $expiration );
			}
		}

		// Return the gallery data.
		return $gallery;

	}

	/**
	 * Internal method that returns a gallery based on ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id     The gallery ID used to retrieve a gallery.
	 * @return array|bool Array of gallery data or false if none found.
	 */
	public function _get_gallery( $id ) {

		$meta = get_post_meta( $id, '_eg_gallery_data', true );

		/**
		* Version 1.2.1+: Check if $meta has a value - if not, we may be using a Post ID but the gallery
		* has moved into the Envira CPT
		*/
		if ( empty( $meta ) ) {
			$gallery_id = get_post_meta( $id, '_eg_gallery_id', true );
			$meta = get_post_meta( $gallery_id, '_eg_gallery_data', true );
		}

		return $meta;

	}

	/**
	 * Returns the number of images in a gallery.
	 *
	 * @since 1.2.1
	 *
	 * @param int $id The gallery ID used to retrieve a gallery.
	 * @return int    The number of images in the gallery.
	 */
	public function get_gallery_image_count( $id ) {

		$gallery = $this->get_gallery( $id );
	    return isset( $gallery['gallery'] ) ? count( $gallery['gallery'] ) : 0;

	}

	/**
	 * Returns a gallery based on slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The gallery slug used to retrieve a gallery.
	 * @return array|bool  Array of gallery data or false if none found.
	 */
	public function get_gallery_by_slug( $slug ) {

		// Attempt to return the transient first, otherwise generate the new query to retrieve the data.
		if ( false === ( $gallery = get_transient( '_eg_cache_' . $slug ) ) ) {
			$gallery = $this->_get_gallery_by_slug( $slug );
			if ( $gallery ) {
				$expiration = Envira_Gallery_Common::get_instance()->get_transient_expiration_time();
				set_transient( '_eg_cache_' . $slug, $gallery, $expiration );
			}
		}

		// Return the gallery data.
		return $gallery;

	}

	/**
	 * Internal method that returns a gallery based on slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The gallery slug used to retrieve a gallery.
	 * @return array|bool  Array of gallery data or false if none found.
	 */
	public function _get_gallery_by_slug( $slug ) {

		// Get Envira CPT by slug.
		$galleries = new WP_Query( array(
			'post_type'    => 'envira',
			'name'              => $slug,
			'fields'        => 'ids',
			'posts_per_page' => 1,
		) );
		if ( $galleries->posts ) {
			return get_post_meta( $galleries->posts[0], '_eg_gallery_data', true );
		}

		// If nothing found, get Envira CPT by _eg_gallery_old_slug.
		// This covers Galleries migrated from Pages/Posts --> Envira CPTs.
		$galleries = new WP_Query( array(
			'post_type'     => 'envira',
			'no_found_rows' => true,
			'cache_results' => false,
			'fields'        => 'ids',
			'meta_query'    => array(
				array(
					'key'     => '_eg_gallery_old_slug',
					'value'   => $slug,
				),
			),
			'posts_per_page' => 1,
		) );
		if ( $galleries->posts ) {
			return get_post_meta( $galleries->posts[0], '_eg_gallery_data', true );
		}

		// No galleries found.
		return false;

	}

	/**
	 * Returns all galleries created on the site.
	 *
	 * @since 1.0.0
	 *
	 * @param bool           $skip_empty         Skip empty sliders.
	 * @param bool           $ignore_cache       Ignore Transient cache.
	 * @param string    $search_terms       Search for specified Galleries by Title
	 *
	 * @return array|bool                        Array of gallery data or false if none found.
	 */
	public function get_galleries( $skip_empty = true, $ignore_cache = false, $search_terms = '' ) {

		// Attempt to return the transient first, otherwise generate the new query to retrieve the data.
		if ( $ignore_cache || ! empty( $search_terms ) || false === ( $galleries = get_transient( '_eg_cache_all' ) ) ) {
			$galleries = $this->_get_galleries( $skip_empty, $search_terms );

			// Cache the results if we're not performing a search and we have some results
			if ( $galleries && empty( $search_terms ) ) {
				$expiration = Envira_Gallery_Common::get_instance()->get_transient_expiration_time();
				set_transient( '_eg_cache_all', $galleries, $expiration );
			}
		}

		// Return the gallery data.
		return $galleries;

	}

	/**
	 * Internal method that returns all galleries created on the site.
	 *
	 * @since 1.0.0
	 *
	 * @param bool           $skip_empty    Skip Empty Galleries.
	 * @param string    $search_terms  Search for specified Galleries by Title
	 * @return mixed                        Array of gallery data or false if none found.
	 */
	public function _get_galleries( $skip_empty = true, $search_terms = '' ) {

		// Build WP_Query arguments.
		$args = array(
			'post_type'     => 'envira',
			'post_status'   => 'publish',
			'posts_per_page'=> 99,
			'no_found_rows' => true,
			'fields'        => 'ids',
			'meta_query'    => array(
				array(
					'key'   => '_eg_gallery_data',
					'compare' => 'EXISTS',
				),
			),
		);

		// If search terms exist, add a search parameter to the arguments.
		if ( ! empty( $search_terms ) ) {
			$args['s'] = $search_terms;
		}

		// Run WP_Query.
		$galleries = new WP_Query( $args );
		if ( ! isset( $galleries->posts ) || empty( $galleries->posts ) ) {
			return false;
		}

		// Now loop through all the galleries found and only use galleries that have images in them.
		$ret = array();
		foreach ( $galleries->posts as $id ) {
			$data = get_post_meta( $id, '_eg_gallery_data', true );

			// Skip empty galleries.
			if ( $skip_empty && empty( $data['gallery'] ) ) {
				continue;
			}

			// Skip default/dynamic gallery types.
			$type = Envira_Gallery_Shortcode::get_instance()->get_config( 'type', $data );
			if ( 'defaults' === Envira_Gallery_Shortcode::get_instance()->get_config( 'type', $data ) || 'dynamic' === Envira_Gallery_Shortcode::get_instance()->get_config( 'type', $data ) ) {
				continue;
			}

			// Add gallery to array of galleries.
			$ret[] = $data;
		}

		// Return the gallery data.
		return $ret;

	}

	/**
	 * Returns the license key for Envira.
	 *
	 * As Lite doesn't need a license key, but includes/admin/addons.php
	 * expects something, we return an empty string.
	 *
	 * @since 1.5.0.3
	 *
	 * @return string Empty String
	 */
	public function get_license_key() {

		return '';

	}

	/**
	 * Returns the license key type for Envira.
	 *
	 * As Lite doesn't need a license key, but includes/admin/addons.php
	 * expects something, we return an empty string.
	 *
	 * @since 1.5.0.3
	 *
	 * @return string Empty String
	 */
	public function get_license_key_type() {

		return '';

	}

	/**
	 * Returns the license key error(s) for Envira.
	 *
	 * As Lite doesn't need a license key, but includes/admin/addons.php
	 * expects something, we return false
	 *
	 * @since 1.5.0.3
	 *
	 * @return bool false
	 */
	public function get_license_key_errors() {

		return false;
	}

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @return object The Envira_Gallery object.
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Envira_Gallery_Lite ) ) {
			self::$instance = new Envira_Gallery_Lite();
		}

		return self::$instance;

	}
}

register_activation_hook( __FILE__, 'envira_gallery_lite_activation_hook' );
/**
 * Fired when the plugin is activated.
 *
 * @since 1.0.0
 *
 * @global int $wp_version      The version of WordPress for this install.
 * @global object $wpdb         The WordPress database object.
 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false otherwise.
 */
function envira_gallery_lite_activation_hook( $network_wide ) {

	global $wp_version;
	if ( version_compare( $wp_version, '4.0', '<' ) && ! defined( 'ENVIRA_FORCE_ACTIVATION' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( sprintf( __( 'Sorry, but your version of WordPress does not meet Envira Gallery\'s required version of <strong>4.0</strong> to run properly. The plugin has been deactivated. <a href="%s">Click here to return to the Dashboard</a>.', 'envira-gallery-lite' ), get_admin_url() ) );
	}

	// Make sure Envira Pro plugin, if activated is deactivated
	deactivate_plugins( 'envira-gallery/envira-gallery.php' );

}

// Load the main plugin class.
$envira_gallery_lite = Envira_Gallery_Lite::get_instance();


if ( ! function_exists( 'envira_mobile_detect' ) ) {

	/**
	 * Holder for mobile detect.
	 *
	 * @access public
	 * @return void
	 */
	function envira_mobile_detect(){

		//Check for mobile detect class before loading it again //prevents conflicts with themes
		if ( ! class_exists( 'Mobile_Detect' ) ) {

			require_once trailingslashit( plugin_dir_path( __FILE__ ) ) . 'includes/global/Mobile_Detect.php';

		}

		return new Mobile_Detect;

	}

}

// Conditionally load the upgrade function.
if ( ! function_exists( 'envira_wp_upe_upgrade_completed' ) ) {

	/**
	 * This function runs when WordPress completes its upgrade process
	 * It iterates through each plugin updated to see if Envira is included
	 * @param $upgrader_object Array
	 * @param $options Array
	 */
	function envira_wp_upe_upgrade_completed( $upgrader_object, $options ) {
		// The path to our plugin's main file
		$our_plugin = plugin_basename( __FILE__ );
		// If an update has taken place and the updated type is plugins and the plugins element exists
		if( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
			// Iterate through the plugins being updated and check if ours is there
			foreach( $options['plugins'] as $plugin ) {
				if( $plugin == $our_plugin ) {
					// Set a transient to record that our plugin has just been updated
					set_transient( 'envira_lite_updated', 1, 60 ); // one minute
				}
			}
		}
	}
	add_action( 'upgrader_process_complete', 'envira_wp_upe_upgrade_completed', 10, 2 );

}

// Conditionally load the template tag.
if ( ! function_exists( 'envira_gallery' ) ) {
	/**
	 * Primary template tag for outputting Envira galleries in templates.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $id          The ID of the gallery to load.
	 * @param string $type        The type of field to query.
	 * @param array  $args        Associative array of args to be passed.
	 * @param bool   $return    Flag to echo or return the gallery HTML.
	 */
	function envira_gallery( $id, $type = 'id', $args = array(), $return = false ) {

		// If we have args, build them into a shortcode format.
		$args_string = '';
		if ( ! empty( $args ) ) {
			foreach ( (array) $args as $key => $value ) {
				$args_string .= ' ' . $key . '="' . $value . '"';
			}
		}

		// Build the shortcode.
		$shortcode = ! empty( $args_string ) ? '[envira-gallery ' . $type . '="' . $id . '"' . $args_string . ']' : '[envira-gallery ' . $type . '="' . $id . '"]';

		// Return or echo the shortcode output.
		if ( $return ) {
			return do_shortcode( $shortcode );
		} else {
			echo do_shortcode( $shortcode );
		}

	}
}
