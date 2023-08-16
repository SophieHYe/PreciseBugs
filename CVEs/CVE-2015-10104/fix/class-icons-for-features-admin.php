<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

/**
 * Icons_For_Features_Admin Class
 *
 * All functionality pertaining to the icons for features administration interface.
 *
 * @package WordPress
 * @subpackage Icons_For_Features
 * @category Plugin
 * @author Matty
 * @since 1.0.0
 */
class Icons_For_Features_Admin {
	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $token;

	/**
	 * Constructor function.
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function __construct () {
		$this->token = 'icons-for-features';

		add_action( 'admin_menu', array( $this, 'meta_box_setup' ), 20 );
		add_action( 'save_post', array( $this, 'meta_box_save' ) );

		// Register necessary scripts and styles, to enable others to enqueue them at will as well.
		add_action( 'admin_print_styles', array( $this, 'maybe_load_styles' ) );
		add_action( 'admin_print_scripts', array( $this, 'maybe_load_scripts' ) );

		add_filter( 'manage_edit-feature_columns', array( $this, 'register_custom_column_headings' ), 20, 1 );
		add_action( 'manage_posts_custom_column', array( $this, 'register_custom_columns' ), 20, 2 );

		// Display an admin notice, if the Features by WooThemes plugin it's present or is present yet not activated.
		add_action( 'network_admin_notices', array( $this, 'maybe_display_activation_notice' ) );
		add_action( 'admin_notices', array( $this, 'maybe_display_activation_notice' ) );

		// Process the 'Dismiss' link, if valid.
		add_action( 'admin_init', array( $this, 'maybe_process_dismiss_link' ) );
	} // End __construct()

	/**
	 * If the nonce is valid and the action is "icons-for-features-dismiss", process the dismissal.
	 * @access  public
	 * @since   1.2.1
	 * @return  void
	 */
	public function maybe_process_dismiss_link () {
		if ( isset( $_GET['action'] ) && ( 'icons-for-features-dismiss' == $_GET['action'] ) && isset( $_GET['nonce'] ) && check_admin_referer( 'icons-for-features-dismiss', 'nonce' ) ) {
			update_option( 'icons_for_features_dismiss_activation_notice', true );

			$redirect_url = remove_query_arg( 'action', remove_query_arg( 'nonce', $_SERVER['REQUEST_URI'] ) );

			wp_safe_redirect( esc_url( $redirect_url ) );
			exit;
		}
	} // End maybe_process_dismiss_link()

	/**
	 * Display an admin notice, if the Features by WooThemes plugin is present and not activated, or not present.
	 * @access  public
	 * @since   1.2.1
	 * @return  void
	 */
	public function maybe_display_activation_notice () {
		if ( $this->_is_features_plugin_activated() ) return;
		if ( ! current_user_can( 'manage_options' ) ) return; // Don't show the message if the user isn't an administrator.
		if ( is_multisite() && ! is_super_admin() ) return; // Don't show the message if on a multisite and the user isn't a super user.
		if ( true == get_option( 'icons_for_features_dismiss_activation_notice', false ) ) return; // Don't show the message if the user dismissed it.

		$slug = 'features-by-woothemes';
		$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $slug ), 'install-plugin_' . $slug );
		$activate_url = 'plugins.php?action=activate&plugin=' . urlencode( 'features-by-woothemes/woothemes-features.php' ) . '&plugin_status=all&paged=1&s&_wpnonce=' . urlencode( wp_create_nonce( 'activate-plugin_features-by-woothemes/woothemes-features.php' ) );
		if ( true == $this->_is_features_plugin_installed() ) {
			$text = '<a href="' . esc_url( $activate_url ) . '">' . __( 'Activate the Features by WooThemes plugin', 'icons-for-features' ) . '</a>';
		} else {
			$text = '<a href="' . esc_url( $install_url ) . '">' . __( 'Install the Features by WooThemes plugin', 'icons-for-features' ) . '</a>';
		}
		$text = sprintf( __( '%sIcons for Features%s is almost ready. %s to get started.', 'icons-for-features' ), '<strong>', '</strong>', $text );

		$dismiss_url = add_query_arg( 'action', 'icons-for-features-dismiss', add_query_arg( 'nonce', wp_create_nonce( 'icons-for-features-dismiss' ) ) );
				echo '<div class="updated fade"><p class="alignleft">' . $text . '</p><p class="alignright"><a href="' . esc_url( $dismiss_url ) . '">' . __( 'Dismiss', 'icons-for-features' ) . '</a></p><div class="clear"></div></div>' . "\n";
	} // End maybe_display_activation_notice()

	/**
	 * Check if the Features by WooThemes plugin is activated.
	 * @access  protected
	 * @since   6.0.0
	 * @return  boolean
	 */
	protected function _is_features_plugin_activated () {
		$response = false;
		$active_plugins = apply_filters( 'active_plugins', get_option('active_plugins' ) );
		if ( 0 < count( $active_plugins ) && in_array( 'features-by-woothemes/woothemes-features.php', $active_plugins ) ) $response = true;
		return $response;
	} // End _is_features_plugin_activated()

	/**
	 * Check if the Features by WooThemes plugin is installed.
	 * @access  protected
	 * @since   6.0.0
	 * @return  boolean
	 */
	protected function _is_features_plugin_installed () {
		$response = false;
		$plugins = get_plugins();
		if ( 0 < count( $plugins ) && in_array( 'features-by-woothemes/woothemes-features.php', array_keys( $plugins ) ) ) $response = true;
		return $response;
	} // End _is_features_plugin_installed()

	/**
	 * Conditionally load the admin styles if we're viewing the "feature" post type.
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function maybe_load_styles () {
		if ( 'feature' == get_post_type() ) {
			wp_enqueue_style( $this->token . '-icons-admin' );
		}
	} // End maybe_load_styles()

	/**
	 * Conditionally load the admin scripts if we're viewing the "feature" post type.
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function maybe_load_scripts () {
		if ( 'feature' == get_post_type() ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_script( $this->token . '-icons-admin', esc_url( Icons_For_Features()->plugin_url . 'assets/js/admin-icon-toggle' . $suffix . '.js' ), array( 'jquery' ), Icons_For_Features()->version, true );
		}
	} // End maybe_load_scripts()

	/**
	 * Setup the meta box.
	 *
	 * @access public
	 * @since  1.1.0
	 * @return void
	 */
	public function meta_box_setup () {
		add_meta_box( 'feature-icon', __( 'Feature Icon', 'icons-for-features' ), array( $this, 'meta_box_content' ), 'feature', 'side' );
	} // End meta_box_setup()

	/**
	 * The contents of our meta box.
	 *
	 * @access public
	 * @since  1.1.0
	 * @return void
	 */
	public function meta_box_content () {
		global $post_id;
		$fields = get_post_custom( $post_id );
		$icons = Icons_For_Features()->get_supported_icon_list();

		if ( 0 >= count( $icons ) ) {
			_e( 'No icons are currently supported.', 'icons-for-features' );
			return;
		}

		$icon = 'fa-no-feature-icon';
		if ( isset( $fields['_icon'][0] ) ) {
			$icon = esc_attr( $fields['_icon'][0] );
		}

		$html = '<input type="hidden" name="woo_' . $this->token . '_noonce" id="woo_' . $this->token . '_noonce" value="' . wp_create_nonce( $this->token ) . '" />';

		$html .= '<div class="icon-preview fa ' . esc_attr( $icon ) . '"></div>';

		$html .= '<select name="icon" class="feature-icon-selector">' . "\n";
			$html .= '<option value="">' . __( 'No Icon', 'icons-for-features' ) . '</option>' . "\n";
		foreach ( $icons as $k => $v ) {
			$html .= '<option value="' . esc_attr( $v ) . '"' . selected( $icon, $v, false ) . '>' . esc_html( Icons_For_Features()->get_icon_label( $v ) ) . '</option>' . "\n";
		}
		$html .= '</select>' . "\n";

		// Make sure this variable is empty, to ensure we have an empty hidden field.
		if ( 'fa-no-featured-icon' == $icon ) $icon = '';

		$html .= '<input type="hidden" name="currently-selected-icon" class="currently-selected-icon" value="' . esc_attr( $icon ) . '" />' . "\n";

		$html .= '<p><small>' . __( '(When an icon is selected, it takes the place of the featured image.)', 'icons-for-features' ) . '</small></p>' . "\n";

		echo $html;
	} // End meta_box_content()

	/**
	 * Save meta box fields.
	 *
	 * @access public
	 * @since  1.1.0
	 * @param int $post_id
	 * @return void
	 */
	public function meta_box_save ( $post_id ) {
		global $post, $messages;

		// Verify
		if ( ( get_post_type() != 'feature' ) || ! wp_verify_nonce( $_POST['woo_' . $this->token . '_noonce'], $this->token ) ) {
			return $post_id;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		$fields = array( 'icon' );

		foreach ( $fields as $f ) {

			${$f} = strip_tags(trim($_POST[$f]));

			if ( get_post_meta( $post_id, '_' . $f ) == '' ) {
				add_post_meta( $post_id, '_' . $f, ${$f}, true );
			} elseif( ${$f} != get_post_meta( $post_id, '_' . $f, true ) ) {
				update_post_meta( $post_id, '_' . $f, ${$f} );
			} elseif ( ${$f} == '' ) {
				delete_post_meta( $post_id, '_' . $f, get_post_meta( $post_id, '_' . $f, true ) );
			}
		}
	} // End meta_box_save()

	/**
	 * Add custom columns for the "manage" screen of this post type.
	 *
	 * @access public
	 * @param string $column_name
	 * @param int $id
	 * @since  1.0.0
	 * @return void
	 */
	public function register_custom_columns ( $column_name, $id ) {
		if ( 'feature' != get_post_type() ) return;
		global $post;

		switch ( $column_name ) {

			case 'icon':
				$value = '';

				$value = Icons_For_Features()->get_the_icon_html( $id );

				echo $value;
			break;

			default:
			break;
		}
	} // End register_custom_columns()

	/**
	 * Add custom column headings for the "manage" screen of this post type.
	 *
	 * @access public
	 * @param array $defaults
	 * @since  1.0.0
	 * @return void
	 */
	public function register_custom_column_headings ( $defaults ) {
		if ( 'feature' != get_post_type() ) return;
		$new_columns = array( 'icon' => __( 'Icon', 'icons-for-features' ) );

		$last_item = '';

		if ( count( $defaults ) > 2 ) {
			$last_item = array_slice( $defaults, -1 );

			array_pop( $defaults );
		}
		$defaults = array_merge( $defaults, $new_columns );

		if ( $last_item != '' ) {
			foreach ( $last_item as $k => $v ) {
				$defaults[$k] = $v;
				break;
			}
		}

		return $defaults;
	} // End register_custom_column_headings()
} // End Class
?>