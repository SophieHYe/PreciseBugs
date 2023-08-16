<?php
/**
 * woo-popup
 *
 * @package   WooPopupAdmin
 * @author    Guillaume Kanoufi <guillaume@lostwebdesigns.com>
 * @license   GPL-2.0+
 * @link      http://lostwebdesigns.com
 * @copyright 2014 woocommerce, popup, woopopup
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * administrative side of the WordPress site.
 *
 * If you're interested in introducing public-facing
 * functionality, then refer to `class-woo-popup.php`
 *
 * @package WooPopupAdmin
 * @author  Guillaume Kanoufi <guillaume@lostwebdesigns.com>
 */
class WooPopupAdmin {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {


		$plugin = WooPopup::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();
		$this->options_slug = $plugin->get_plugin_options_slug();
		$this->options_data = $plugin->get_plugin_options_data();

		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add the options page and menu item.
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_slug . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		// Add the options update action
		add_action('admin_init', array($this, 'options_update'));

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
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), WooPopup::VERSION );
			wp_enqueue_style('jquery-ui-css', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css');
		}

	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), WooPopup::VERSION );
		}

	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		/*
		 * Add a settings page for this plugin to the Settings menu.
		 *
		 * NOTE:  Alternative menu locations are available via WordPress administration menu functions.
		 *
		 *        Administration Menus: http://codex.wordpress.org/Administration_Menus
		 *
		 */
		$this->plugin_screen_hook_suffix = add_menu_page(
			__( 'Woo Pop Up', $this->plugin_slug ),
			__( 'Woo Pop Up Settings', $this->plugin_slug ),
			'manage_options',
			$this->options_slug,
			array( $this, 'display_plugin_admin_page' )
		);

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		include_once( 'views/admin.php' );
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {


		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'admin.php?page=' . $this->options_slug ) . '">' . __( 'Settings', $this->plugin_slug ) . '</a>'
			),
			$links
		);

	}

	/**
	 * 	Save the plugin options
	 *
	 *
	 * @since    1.0.0
	 */
	public function options_update() {
		register_setting( $this->options_slug, $this->options_slug, array($this, 'validate') );
	}

	public function validate($input) {
	    $valid = array();
	    $valid['popup_content'] = wp_kses_post($input['popup_content']);
	    $valid['popup_page'] = sanitize_text_field($input['popup_page']);
	    $valid['popup_class'] = sanitize_text_field($input['popup_class']);
	    $valid['start_date'] = sanitize_text_field($input['start_date']);
	    $valid['end_date'] = sanitize_text_field($input['end_date']);
	    $valid['popup_timezone'] = sanitize_text_field($input['popup_timezone']);

	    if(isset($input['popup_permanent'])){
	    	  $valid['popup_permanent'] = sanitize_text_field($input['popup_permanent']);
	    }else{
	    	 $valid['popup_permanent'] = '0';
	    }


	    if (strlen($valid['popup_content']) == 0) {
	        add_settings_error(
	                'popup_content',                     // Setting title
	                'popup_content_texterror',            // Error ID
	                'Please enter a text to show on the pop up',     // Error message
	                'error'                         // Type of message
	        );

	        // Set it to the default value
	        $valid['popup_content'] = $this->data['popup_content'];
	    }

	    if (strlen($valid['popup_page']) == 0) {
	        add_settings_error(
	                'popup_page',
	                'popup_page_texterror',
	                'Please choose a page to display the pop up to',
	                'error'
	        );

	        $valid['popup_page'] = $this->data['popup_page'];
	    }
	    if (strlen($valid['popup_class']) == 0) {
	        add_settings_error(
	                'popup_class',
	                'popup_class_texterror',
	                'Please choose a class to display the pop up to',
	                'error'
	        );

	        $valid['popup_class'] = $this->data['popup_class'];
	    }


	    if (strlen($valid['start_date']) == 0) {
	        add_settings_error(
	                'start_date',
	                'start_date_texterror',
	                'Please enter a beginning date',
	                'error'
	        );

	        $valid['start_date'] = $this->data['start_date'];
	    }

	    if (strlen($valid['end_date']) == 0) {
	        add_settings_error(
	                'end_date',
	                'end_date_texterror',
	                'Please enter a beginning date',
	                'error'
	        );

	        $valid['end_date'] = $this->data['end_date'];
	    }

	    return $valid;
	}

}
