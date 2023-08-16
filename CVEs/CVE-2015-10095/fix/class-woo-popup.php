<?php
/**
 * woo-popup
 *
 * @package   WooPopup
 * @author    Guillaume Kanoufi <guillaume@lostwebdesigns.com>
 * @license   GPL-2.0+
 * @link      http://lostwebdesigns.com
 * @copyright 2014 woocommerce, popup, woopopup
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * public-facing side of the WordPress site.
 *
 * If you're interested in introducing administrative or dashboard
 * functionality, then refer to `class-woo-popup-admin.php`
 *
 * @package WooPopup
 * @author  Guillaume Kanoufi <guillaume@lostwebdesigns.com>
 */
class WooPopup {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.3.0';

	/**
	 * Unique identifier for your plugin.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'woo-popup';

	/**
	 * Unique identifier for your plugin options.
	 *
	 *
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	private $options_slug = 'woo-popup_options';


	/**
	 * Default Settings Values.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	private $options_data = array(
		'popup_content' => 'This will be your pop up content',
		'popup_page' => ' ',
		'popup_class' => 'notice',
		'popup_permanent' => '0', //Default set to 0 so date are apparant
		'start_date' => '2014-02-02',  //Set up an old date so if default the pop up won't be fired
		'end_date' => '2014-02-04',  //Set up an old date so if default the pop up won't be fired
		'popup_timezone' => 'Europe/Paris'
	);

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Load public-facing style sheet and JavaScript.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Pass the php variable through wp_localize_script
		add_action( 'wp_enqueue_scripts', array( $this, 'pass_php_vars' ) );

	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return the plugin options slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_options_slug() {
		return $this->options_slug;
	}


	/**
	 * Return the plugin options default data.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin options variable.
	 */
	public function get_plugin_options_data() {
		return $this->options_data;
	}


	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {
		update_option('woo-popup_options', array( 'popup_content' => 'This will be your pop up content', 'popup_page' => ' ', 'popup_class' => 'notice', 'popup_theme' => 'pp_default', 'popup_permanent' => '0', 'start_date' => date('Y-m-d', time()), 'end_date' => date('Y-m-d', time()), 'popup_timezone' => 'Europe/Paris' ));
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private static function single_deactivate() {
		delete_option('woo-popup_options');
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );

	}

	public function trigger_plugin(){
		global $post, $popup_theme;
		$options = get_option($this->options_slug);
		$tz = $options['popup_timezone'];
		date_default_timezone_set($tz);
		$page = $options['popup_page'];
		$today = date('Y-m-d', time());
		$permanent = $options['popup_permanent'];
		$start_date = $options['start_date'];
		$end_date = $options['end_date'];
		$popup_theme = $options['popup_theme'];
		$diff_start = strtotime($today) - strtotime($start_date);
		$diff_end = strtotime($end_date) - strtotime($today);
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins'  )  )  ) && is_shop()){
			$page_id = get_option( 'woocommerce_shop_page_id' );
		}else{
			$page_id = $post->ID;
		}

		if(($permanent == 1 || $diff_start >= 0 && $diff_end >= 0) && ($page == 'all' || $page_id == $page)){
			return true;
		}else{
			return false;
		}
	}

	public function activated_woocommerce(){

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			global $woocommerce;
			$pp_dir['base'] = $woocommerce->plugin_url() . '/assets/js/jquery.prettyPhoto.js';
			$pp_dir['style'] = $woocommerce->plugin_url() . '/assets/css/prettyPhoto.css';
			$pp_dir['ver'] = $woocommerce->version;
		}else{
			$pp_dir['base'] = plugins_url( '/assets/js/jquery.prettyPhoto.js', __FILE__ );
			$pp_dir['style'] = plugins_url( '/assets/css/prettyPhoto.css', __FILE__ );
			$pp_dir['ver'] = '';
		}
		return $pp_dir;
	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		if($this->trigger_plugin() == true){
			$pp_dir = $this->activated_woocommerce();
			wp_enqueue_style( 'prettyPhoto_css', $pp_dir['style']);
			wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css', __FILE__ ), array('prettyPhoto_css'), self::VERSION   );
		}
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		if($this->trigger_plugin() == true){
			global $woocommerce, $popup_theme;
			$pp_dir = $this->activated_woocommerce();
			wp_enqueue_script( 'prettyPhoto', $pp_dir['base'] , array( 'jquery' ), $pp_dir['ver'] );

			wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'assets/js/public.js', __FILE__ ), array( 'prettyPhoto' ), self::VERSION, true );


			// Passing Plugin Options to js
			$plugin_data = array(
			    'theme' =>  $popup_theme,
			);
			wp_localize_script( $this->plugin_slug . '-plugin-script', 'plugin_options_vars', $plugin_data );
		}
	}

	public function pass_php_vars() {
		include_once( 'views/public.php' );
	}

}
