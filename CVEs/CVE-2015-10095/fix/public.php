<?php
/**
 * Represents the view for the public-facing component of the plugin.
 *
 * This typically includes any information, if any, that is rendered to the
 * frontend of the theme when the plugin is activated.
 *
 * @package   WooPopup
 * @author    Guillaume Kanoufi <guillaume@lostwebdesigns.com>
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2014 woocommerce, popup, woopopup
 */
?>

<!-- This file is used to markup the public facing aspect of the plugin. -->
<?php
	$woo_popup_vars = get_option($this->options_slug);
	// $popup_content = do_shortcode($woo_popup_vars['popupcontent']);

	// $formatted_popup = wpautop(stripslashes($popup_content));
	$formatted_popup = apply_filters( 'the_content', $woo_popup_vars['popup_content'] );
	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
		$class = 'woocommerce-'.$woo_popup_vars['popup_class'];
	}else{
		$class = 'woopopup-'.$woo_popup_vars['popup_class'];
	}
	$woo_popup_vars['popup_content'] = '<div id="woopopup" style="display:none;" aria-hidden="true"><div class="'.$class.'">'.$formatted_popup.'</div></div>';
	wp_localize_script( $this->plugin_slug . '-plugin-script', 'woo_popup', $woo_popup_vars );
