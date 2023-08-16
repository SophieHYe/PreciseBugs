<?php
/**
 * Plugin Name: Icons For Features
 * Plugin URI: http://www.woothemes.com/products/icons-for-features/
 * Description: Hey there! Do you want to display awesome icons for each of your features? Look no further, I'm here to help!
 * Version: 1.0.1
 * Author: WooThemes
 * Author URI: http://woothemes.com/
 * Requires at least: 3.8.1
 * Tested up to: 4.1.1
 *
 * Text Domain: icons-for-features
 * Domain Path: /languages/
 *
 * @package Icons_For_Features
 * @category Core
 * @author Matty
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Returns the main instance of Icons_For_Features to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object Icons_For_Features
 */
function Icons_For_Features() {
	return Icons_For_Features::instance();
} // End Icons_For_Features()

Icons_For_Features();

/**
 * Main Icons_For_Features Class
 *
 * @class Icons_For_Features
 * @version	1.0.0
 * @since 1.0.0
 * @package	Kudos
 * @author Matty
 */
final class Icons_For_Features {
	/**
	 * Icons_For_Features The single instance of Icons_For_Features.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $token;

	/**
	 * The version number.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $version;

	/**
	 * An instance of the Icons_For_Features_Admin class.
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $admin;

	/**
	 * The name of the hook on which we will be working our magic.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $hook;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct () {
		$this->token 			= 'icons-for-features';
		$this->plugin_url 		= plugin_dir_url( __FILE__ );
		$this->plugin_path 		= plugin_dir_path( __FILE__ );
		$this->version 			= '1.0.0';

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		/* Conditionally load the admin. */
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'register_styles' ) );

			require_once( 'classes/class-icons-for-features-admin.php' );
			$this->admin = new Icons_For_Features_Admin();
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'register_styles' ) );
			// An unfortunate caveat, as we need to wait for the entire page to load, in order to determine whether or not there have been Features called.
			add_action( 'wp_footer', array( $this, 'maybe_enqueue_styles' ) );
			add_filter( 'woothemes_features_item_template', array( $this, 'add_feature_icon_placeholder' ), 10, 2 );
			add_filter( 'woothemes_features_template', array( $this, 'override_feature_icon_placeholder' ), 10, 2 );
			add_filter( 'woothemes_features_html', array( $this, 'maybe_remove_override_filter' ) );
		}
	} // End __construct()

	/**
	 * Main Icons_For_Features Instance
	 *
	 * Ensures only one instance of Icons_For_Features is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see Icons_For_Features()
	 * @return Main Icons_For_Features instance
	 */
	public static function instance () {
		if ( is_null( self::$_instance ) )
			self::$_instance = new self();
		return self::$_instance;
	} // End instance()

	/**
	 * Load the localisation file.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'icons-for-features', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	} // End load_plugin_textdomain()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), '1.0.0' );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), '1.0.0' );
	} // End __wakeup()

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
	} // End install()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		// Log the version number.
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number()

	/**
	 * Force has_post_thumbnail() to return false, to "skip over" images where there is an icon for the feature.
	 * This caters for older versions of the Features by WooThemes plugin, where there are a few useful filters that are missing.
	 * @access public
	 * @since  1.0.0
	 * @param  boolean $response  Force this to be false, somehow.
	 * @param  int $object_id The current object ID.
	 * @param  string $meta_key  The specified meta key to retrieve.
	 * @param  boolean $single    Whether this is a singular instance key or not.
	 * @return boolean            Always return a boolean.
	 */
	public function override_has_post_thumbnail ( $response, $object_id, $meta_key, $single ) {
		if ( '_thumbnail_id' != $meta_key ) return $response;
		if ( '' != get_post_meta( intval( $object_id ), '_icon', true ) ) $response = false;
		return $response;
	} // End override_has_post_thumbnail()

	/**
	 * Remove the filter used to force has_post_thumbnail() to return false.
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function maybe_remove_override_filter ( $html ) {
		remove_filter( 'get_post_metadata', array( $this, 'override_has_post_thumbnail' ), 10, 4 );
		return $html;
	} // End maybe_remove_override_filter()

	/**
	 * Add an %%ICON%% placeholder to the feature template, replacing %%IMAGE%%, if it exists.
	 * If no %%IMAGE%% tag is present, the administrator doesn't want an image to display, so don't display an icon (respect their wishes).
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function add_feature_icon_placeholder ( $tpl, $args ) {
		add_filter( 'get_post_metadata', array( $this, 'override_has_post_thumbnail' ), 10, 4 );

		$tpl = str_replace( '%%IMAGE%%', '%%ICON%%%%IMAGE%%', $tpl );
		return $tpl;
	} // End add_feature_icon_placeholder()

	/**
	 * Override the %%ICON%% template tag, if an icon is available. If not, remove the template tag.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function override_feature_icon_placeholder ( $html, $post ) {
		$icon = $this->get_the_icon_html( get_the_ID() );
		$html = str_replace( '%%ICON%%', $icon, $html );
		return $html;
	} // End override_feature_icon_placeholder()

	/**
	 * Register the CSS files to be loaded for this plugin.
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function register_styles () {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_register_style( $this->token . '-icons', esc_url( $this->plugin_url . 'assets/lib/font-awesome/css/font-awesome' . $suffix . '.css' ), array(), '4.0.3', 'all' );
		wp_register_style( $this->token . '-icons-loader', esc_url( $this->plugin_url . 'assets/css/style.css' ), array( $this->token . '-icons' ), $this->version, 'all' );
		wp_register_style( $this->token . '-icons-admin', esc_url( $this->plugin_url . 'assets/css/admin.css' ), array( $this->token . '-icons' ), $this->version, 'all' );
	} // End register_styles()

	/**
	 * Conditionally load the CSS files for this plugin.
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function maybe_enqueue_styles () {
		if ( ( is_singular() && 'feature' == get_post_type() ) || is_post_type_archive( 'feature' ) || did_action( 'woothemes_features_before' ) ) {
			wp_enqueue_style( $this->token . '-icons-loader' );
		}
	} // End maybe_enqueue_styles()

	/**
	 * Get the HTML for the stored icon for a given feature.
	 * @access public
	 * @since  1.0.0
	 * @param  int $post_id The feature for which to retrieve the icon.
	 * @return string       Formatted icon HTML.
	 */
	public function get_the_icon_html ( $post_id = null ) {
		if ( is_null( $post_id ) ) $post_id = get_the_ID();
		$response = '';
		$icon = get_post_meta( intval( $post_id ), '_icon', true );
		if ( '' != $icon && in_array( $icon, $this->get_supported_icon_list() ) ) {
			$response = '<div class="icon-preview fa ' . esc_attr( $icon ) . '"></div>' . "\n";
		}
		return (string)apply_filters( 'icons_for_features_get_the_icon_html', $response );
	} // End get_the_icon_html()

	/**
	 * Transform a given icon key into a human-readable label.
	 * @access public
	 * @since  1.0.0
	 * @param  string $key Given icon key.
	 * @return string      Formatted icon label.
	 */
	public function get_icon_label ( $key ) {
		$label = $key;
		$label = str_replace( 'fa-', '', $label );
		$label = str_replace( '-', ' ', $label );

		if ( ' o' == substr( $label, -2 ) ) {
			$label = substr( $label, 0, ( strlen( $label ) -2 ) );
		}

		$label = ucwords( $label );
		return $label;
	} // End get_icon_label()

	/**
	 * Returns a filterable list of supported icon keys.
	 * @access public
	 * @since  1.0.0
	 * @return array Supported icon keys.
	 */
	public function get_supported_icon_list () {
		return (array)apply_filters( 'icons_for_features_supported_icons', array(
			'fa-glass',
			'fa-music',
			'fa-search',
			'fa-envelope-o',
			'fa-heart',
			'fa-star',
			'fa-star-o',
			'fa-user',
			'fa-film',
			'fa-th-large',
			'fa-th',
			'fa-th-list',
			'fa-check',
			'fa-times',
			'fa-search-plus',
			'fa-search-minus',
			'fa-power-off',
			'fa-signal',
			'fa-cog',
			'fa-trash-o',
			'fa-home',
			'fa-file-o',
			'fa-clock-o',
			'fa-road',
			'fa-download',
			'fa-arrow-circle-o-down',
			'fa-arrow-circle-o-up',
			'fa-inbox',
			'fa-play-circle-o',
			'fa-repeat',
			'fa-refresh',
			'fa-list-alt',
			'fa-lock',
			'fa-flag',
			'fa-headphones',
			'fa-volume-off',
			'fa-volume-down',
			'fa-volume-up',
			'fa-qrcode',
			'fa-barcode',
			'fa-tag',
			'fa-tags',
			'fa-book',
			'fa-bookmark',
			'fa-print',
			'fa-camera',
			'fa-font',
			'fa-bold',
			'fa-italic',
			'fa-text-height',
			'fa-text-width',
			'fa-align-left',
			'fa-align-center',
			'fa-align-right',
			'fa-align-justify',
			'fa-list',
			'fa-outdent',
			'fa-indent',
			'fa-video-camera',
			'fa-picture-o',
			'fa-pencil',
			'fa-map-marker',
			'fa-adjust',
			'fa-tint',
			'fa-pencil-square-o',
			'fa-share-square-o',
			'fa-check-square-o',
			'fa-arrows',
			'fa-step-backward',
			'fa-fast-backward',
			'fa-backward',
			'fa-play',
			'fa-pause',
			'fa-stop',
			'fa-forward',
			'fa-fast-forward',
			'fa-step-forward',
			'fa-eject',
			'fa-chevron-left',
			'fa-chevron-right',
			'fa-plus-circle',
			'fa-minus-circle',
			'fa-times-circle',
			'fa-check-circle',
			'fa-question-circle',
			'fa-info-circle',
			'fa-crosshairs',
			'fa-times-circle-o',
			'fa-check-circle-o',
			'fa-ban',
			'fa-arrow-left',
			'fa-arrow-right',
			'fa-arrow-up',
			'fa-arrow-down',
			'fa-share',
			'fa-expand',
			'fa-compress',
			'fa-plus',
			'fa-minus',
			'fa-asterisk',
			'fa-exclamation-circle',
			'fa-gift',
			'fa-leaf',
			'fa-fire',
			'fa-eye',
			'fa-eye-slash',
			'fa-exclamation-triangle',
			'fa-plane',
			'fa-calendar',
			'fa-random',
			'fa-comment',
			'fa-magnet',
			'fa-chevron-up',
			'fa-chevron-down',
			'fa-retweet',
			'fa-shopping-cart',
			'fa-folder',
			'fa-folder-open',
			'fa-arrows-v',
			'fa-arrows-h',
			'fa-bar-chart-o',
			'fa-twitter-square',
			'fa-facebook-square',
			'fa-camera-retro',
			'fa-key',
			'fa-cogs',
			'fa-comments',
			'fa-thumbs-o-up',
			'fa-thumbs-o-down',
			'fa-star-half',
			'fa-heart-o',
			'fa-sign-out',
			'fa-linkedin-square',
			'fa-thumb-tack',
			'fa-external-link',
			'fa-sign-in',
			'fa-trophy',
			'fa-github-square',
			'fa-upload',
			'fa-lemon-o',
			'fa-phone',
			'fa-square-o',
			'fa-bookmark-o',
			'fa-phone-square',
			'fa-twitter',
			'fa-facebook',
			'fa-github',
			'fa-unlock',
			'fa-credit-card',
			'fa-rss',
			'fa-hdd-o',
			'fa-bullhorn',
			'fa-bell',
			'fa-certificate',
			'fa-hand-o-right',
			'fa-hand-o-left',
			'fa-hand-o-up',
			'fa-hand-o-down',
			'fa-arrow-circle-left',
			'fa-arrow-circle-right',
			'fa-arrow-circle-up',
			'fa-arrow-circle-down',
			'fa-globe',
			'fa-wrench',
			'fa-tasks',
			'fa-filter',
			'fa-briefcase',
			'fa-arrows-alt',
			'fa-users',
			'fa-link',
			'fa-cloud',
			'fa-flask',
			'fa-scissors',
			'fa-files-o',
			'fa-paperclip',
			'fa-floppy-o',
			'fa-square',
			'fa-bars',
			'fa-list-ul',
			'fa-list-ol',
			'fa-strikethrough',
			'fa-underline',
			'fa-table',
			'fa-magic',
			'fa-truck',
			'fa-pinterest',
			'fa-pinterest-square',
			'fa-google-plus-square',
			'fa-google-plus',
			'fa-money',
			'fa-caret-down',
			'fa-caret-up',
			'fa-caret-left',
			'fa-caret-right',
			'fa-columns',
			'fa-sort',
			'fa-sort-asc',
			'fa-sort-desc',
			'fa-envelope',
			'fa-linkedin',
			'fa-undo',
			'fa-gavel',
			'fa-tachometer',
			'fa-comment-o',
			'fa-comments-o',
			'fa-bolt',
			'fa-sitemap',
			'fa-umbrella',
			'fa-clipboard',
			'fa-lightbulb-o',
			'fa-exchange',
			'fa-cloud-download',
			'fa-cloud-upload',
			'fa-user-md',
			'fa-stethoscope',
			'fa-suitcase',
			'fa-bell-o',
			'fa-coffee',
			'fa-cutlery',
			'fa-file-text-o',
			'fa-building-o',
			'fa-hospital-o',
			'fa-ambulance',
			'fa-medkit',
			'fa-fighter-jet',
			'fa-beer',
			'fa-h-square',
			'fa-plus-square',
			'fa-angle-double-left',
			'fa-angle-double-right',
			'fa-angle-double-up',
			'fa-angle-double-down',
			'fa-angle-left',
			'fa-angle-right',
			'fa-angle-up',
			'fa-angle-down',
			'fa-desktop',
			'fa-laptop',
			'fa-tablet',
			'fa-mobile',
			'fa-circle-o',
			'fa-quote-left',
			'fa-quote-right',
			'fa-spinner',
			'fa-circle',
			'fa-reply',
			'fa-github-alt',
			'fa-folder-o',
			'fa-folder-open-o',
			'fa-smile-o',
			'fa-frown-o',
			'fa-meh-o',
			'fa-gamepad',
			'fa-keyboard-o',
			'fa-flag-o',
			'fa-flag-checkered',
			'fa-terminal',
			'fa-code',
			'fa-reply-all',
			'fa-mail-reply-all',
			'fa-star-half-o',
			'fa-location-arrow',
			'fa-crop',
			'fa-code-fork',
			'fa-chain-broken',
			'fa-question',
			'fa-info',
			'fa-exclamation',
			'fa-superscript',
			'fa-subscript',
			'fa-eraser',
			'fa-puzzle-piece',
			'fa-microphone',
			'fa-microphone-slash',
			'fa-shield',
			'fa-calendar-o',
			'fa-fire-extinguisher',
			'fa-rocket',
			'fa-maxcdn',
			'fa-chevron-circle-left',
			'fa-chevron-circle-right',
			'fa-chevron-circle-up',
			'fa-chevron-circle-down',
			'fa-html5',
			'fa-css3',
			'fa-anchor',
			'fa-unlock-alt',
			'fa-bullseye',
			'fa-ellipsis-h',
			'fa-ellipsis-v',
			'fa-rss-square',
			'fa-play-circle',
			'fa-ticket',
			'fa-minus-square',
			'fa-minus-square-o',
			'fa-level-up',
			'fa-level-down',
			'fa-check-square',
			'fa-pencil-square',
			'fa-external-link-square',
			'fa-share-square',
			'fa-compass',
			'fa-caret-square-o-down',
			'fa-caret-square-o-up',
			'fa-caret-square-o-right',
			'fa-eur',
			'fa-gbp',
			'fa-usd',
			'fa-inr',
			'fa-jpy',
			'fa-rub',
			'fa-krw',
			'fa-btc',
			'fa-file',
			'fa-file-text',
			'fa-sort-alpha-asc',
			'fa-sort-alpha-desc',
			'fa-sort-amount-asc',
			'fa-sort-amount-desc',
			'fa-sort-numeric-asc',
			'fa-sort-numeric-desc',
			'fa-thumbs-up',
			'fa-thumbs-down',
			'fa-youtube-square',
			'fa-youtube',
			'fa-xing',
			'fa-xing-square',
			'fa-youtube-play',
			'fa-dropbox',
			'fa-stack-overflow',
			'fa-instagram',
			'fa-flickr',
			'fa-adn',
			'fa-bitbucket',
			'fa-bitbucket-square',
			'fa-tumblr',
			'fa-tumblr-square',
			'fa-long-arrow-down',
			'fa-long-arrow-up',
			'fa-long-arrow-left',
			'fa-long-arrow-right',
			'fa-apple',
			'fa-windows',
			'fa-android',
			'fa-linux',
			'fa-dribbble',
			'fa-skype',
			'fa-foursquare',
			'fa-trello',
			'fa-female',
			'fa-male',
			'fa-gittip',
			'fa-sun-o',
			'fa-moon-o',
			'fa-archive',
			'fa-bug',
			'fa-vk',
			'fa-weibo',
			'fa-renren',
			'fa-pagelines',
			'fa-stack-exchange',
			'fa-arrow-circle-o-right',
			'fa-arrow-circle-o-left',
			'fa-caret-square-o-left',
			'fa-dot-circle-o',
			'fa-wheelchair',
			'fa-vimeo-square',
			'fa-try',
			'fa-plus-square-o'
			) );
	} // End get_supported_icon_list()
} // End Class
?>
