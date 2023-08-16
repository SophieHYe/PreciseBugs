<?php
/*
 * Plugin Name: Custom Content Width
 * Plugin URI: http://wordpress.org/plugins/custom-content-width/
 * Description: Adds a 'Custom Content Width' setting to the Settings > Media screen, to let users override their theme's content width.
 * Author: George Stephanis
 * Version: 1.0
 * Author URI: http://stephanis.info/
 */

class Stephanis_Custom_Content_Width {
	static $instance;

	function __construct() {
		self::$instance = $this;
		add_action( 'init', array( $this, 'override_content_width' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	function override_content_width() {
		global $content_width;

		if( ! empty( $content_width ) )
			$this->original_content_width = $content_width;

		if( $custom_content_width = get_option( 'custom_content_width' ) )
			$content_width = $custom_content_width;
	}

	function register_settings() {
		register_setting( 'media', 'custom_content_width', 'intval' );
		$label = __('Custom Content Width', 'custom_content_width' );
		add_settings_field( 'custom_content_width', "<label for='custom_content_width'>{$label}</label>" , array( $this, 'custom_content_width_cb' ) , 'media' );
	}

	function custom_content_width_cb() {
		$value = get_option( 'custom_content_width' );
		?>
		<input type="number" class="small-text" min="0" id="custom_content_width" name="custom_content_width" value="<?php echo $value ? $value : ''; ?>" />
		<label for="custom_content_width">px</label>
		<?php if( ! empty( $this->original_content_width ) ): ?>
			<?php if( $value ): ?>
				<small><a href="javascript:;" onclick="jQuery('#custom_content_width').val('');"><?php _e('clear custom value', 'custom_content_width'); ?></a></small>
			<?php endif; ?>
			<br /><em><?php printf( __('Your theme&rsquo;s default content width is %s pixels.', 'custom_content_width'), $this->original_content_width ); ?></em>
		<?php endif;
	}
}
new Stephanis_Custom_Content_Width;
