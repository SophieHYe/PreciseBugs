<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Class RP4WP_Settings
 *
 * @todo Make class for each input type with own sanitize method.
 */
class RP4WP_Settings {

	const PREFIX = 'rp4wp_';

	const PAGE = 'rp4wp';

	private $sections;
	private $defaults;

	/**
	 * Constructor
	 */
	public function __construct() {

		// CSS default
		$css_default_lines   = array();
		$css_default_lines[] = '.rp4wp-related-posts ul{width:100%;padding:0;margin:0;float:left;}';
		$css_default_lines[] = '.rp4wp-related-posts ul>li{list-style:none;padding:0;margin:0;padding-bottom:20px;clear:both;}';
		$css_default_lines[] = '.rp4wp-related-posts ul>li>p{margin:0;padding:0;}';
		$css_default_lines[] = '.rp4wp-related-post-image{width:35%;padding-right:25px;-moz-box-sizing:border-box;-webkit-box-sizing:border-box;box-sizing:border-box;float:left;}';

		if ( is_rtl() ) {
			$css_default_lines   = array();
			$css_default_lines[] = '.rp4wp-related-posts ul{width:100%;padding:0;margin:0;float:right;}';
			$css_default_lines[] = '.rp4wp-related-posts ul>li{list-style:none;padding:0;margin:0;padding-bottom:20px;float:right;}';
			$css_default_lines[] = '.rp4wp-related-posts ul>li>p{margin:0;padding:0;}';
			$css_default_lines[] = '.rp4wp-related-post-image{width:35%;padding-left:25px;-moz-box-sizing:border-box;-webkit-box-sizing:border-box;box-sizing:border-box;float:right;}';
		}

		// The fields
		$this->sections = apply_filters( 'rp4wp_settings_sections', array(
			'general' => array(
				'id'          => 'general',
				'label'       => __( 'General', 'related-posts-for-wp' ),
				'description' => __( 'The following options affect the general behaviour of the plugin.', 'related-posts-for-wp' ),
				'fields'      => array(
					'automatic_linking'             => array(
						'id'          => 'automatic_linking',
						'label'       => __( 'Enable', 'related-posts-for-wp' ),
						'description' => __( 'Checking this will enable automatically linking posts to new posts', 'related-posts-for-wp' ),
						'type'        => 'checkbox',
						'default'     => 1,
					),
					'automatic_linking_post_amount' => array(
						'id'          => 'automatic_linking_post_amount',
						'label'       => __( 'Amount of Posts', 'related-posts-for-wp' ),
						'description' => __( 'The amount of automatically linked post', 'related-posts-for-wp' ),
						'type'        => 'text',
						'default'     => '3',
					),
					'heading_text'                  => array(
						'id'          => 'heading_text',
						'label'       => __( 'Heading text', 'related-posts-for-wp' ),
						'description' => __( 'The text that is displayed above the related posts. To disable, leave field empty.', 'related-posts-for-wp' ),
						'type'        => 'text',
						'default'     => __( 'Related Posts', 'related-posts-for-wp' ),
					),
					'excerpt_length'                => array(
						'id'          => 'excerpt_length',
						'label'       => __( 'Excerpt length', 'related-posts-for-wp' ),
						'description' => __( 'The amount of words to be displayed below the title on website. To disable, set value to 0.', 'related-posts-for-wp' ),
						'type'        => 'text',
						'default'     => '15',
					)
				)
			),
			'styling' => array(
				'id'          => 'styling',
				'label'       => __( 'Styling', 'related-posts-for-wp' ),
				'description' => __( 'The following options affect how related posts are displayed on the frontend.', 'related-posts-for-wp' ),
				'fields'      => array(
					'display_image' => array(
						'id'          => 'display_image',
						'label'       => __( 'Display Image', 'related-posts-for-wp' ),
						'description' => __( 'Checking this will enable displaying featured images of related posts.', 'related-posts-for-wp' ),
						'type'        => 'checkbox',
						'default'     => 0,
					),
					'css'           => array(
						'id'          => 'css',
						'label'       => __( 'CSS', 'related-posts-for-wp' ),
						'description' => __( 'Warning! This is an advanced feature! An error here will break frontend display. To disable, leave field empty.', 'related-posts-for-wp' ),
						'type'        => 'textarea',
						'default'     => implode( PHP_EOL, $css_default_lines ),
					)
				)
			),
			'misc'    => array(
				'id'          => 'misc',
				'label'       => __( 'Misc', 'related-posts-for-wp' ),
				'description' => __( "A shelter for options that just don't fit in anywhere else.", 'related-posts-for-wp' ),
				'fields'      => array(
					'restart_wizard_button' => array(
						'id'          => 'restart_wizard_button',
						'label'       => __( 'Rebuild posts linkage?', 'related-posts-for-wp' ),
						'description' => __( "Click this button if you want to restart the wizard. Please note that this will delete all current related post links, also those you've manually added. Of course, we will never delete your actual posts.", 'related-posts-for-wp' ),
						'type'        => 'button_link',
						'href'        => admin_url( '?page=rp4wp_install&reinstall=1&rp4wp_nonce=' . wp_create_nonce( RP4WP_Constants::NONCE_INSTALL ) ),
						'default'     => __( 'Rebuild', 'related-posts-for-wp' ),
					),
					'clean_on_uninstall'    => array(
						'id'          => 'clean_on_uninstall',
						'label'       => __( 'Remove Data on Uninstall?', 'related-posts-for-wp' ),
						'description' => __( 'Check this box if you would like to completely remove all of its data when the plugin is deleted.', 'related-posts-for-wp' ),
						'type'        => 'checkbox',
						'default'     => 0,
					),
					'show_love'             => array(
						'id'          => 'show_love',
						'label'       => __( 'Show love?', 'related-posts-for-wp' ),
						'description' => __( "Display a 'Powered by' line under your related posts. <strong>BEWARE! Only for the real fans.</strong>", 'related-posts-for-wp' ),
						'type'        => 'checkbox',
						'default'     => 0,
					),
				)
			),
		) );

		// Set defaults
		foreach ( $this->sections as $section ) {
			foreach ( $section['fields'] as $field ) {
				$this->defaults[$field['id']] = $field['default'];
			}
		}

		// Setup settings
		add_action( 'admin_init', array( $this, 'setup' ) );

	}

	/**
	 * Setup the settings
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public function setup() {
		if ( count( $this->sections ) > 0 ) {
			foreach ( $this->sections as $section ) {

				// Add the section
				add_settings_section(
					$section['id'],
					$section['label'],
					array( $this, 'section_intro' ),
					self::PAGE
				);

				// Check & Loop
				if ( count( $section['fields'] ) > 0 ) {
					foreach ( $section['fields'] as $field ) {

						// Add section
						add_settings_field(
							$field['id'],
							$field['label'],
							array( $this, 'do_field' ),
							self::PAGE,
							$section['id'],
							$field
						);

					}
				}

			}

			// Register section setting
			register_setting( self::PAGE, self::PAGE, array( $this, 'sanitize_option' ) );
		}
	}

	/**
	 * Method that is called when adding a section
	 *
	 * @param $section
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public function section_intro( $section ) {
		echo '<p>' . $this->sections[$section['id']]['description'] . '</p>' . PHP_EOL;
	}

	/**
	 * Method that outputs the correct field
	 *
	 * @param $field
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public function do_field( $field ) {

		// For now we just do a simple switch here, make this more OOP in future version
		switch ( $field['type'] ) {
			case 'checkbox':
				echo '<input type="checkbox" name="' . self::PAGE . '[' . $field['id'] . ']' . '" id="' . $field['id'] . '" value="1" ' . checked( 1, $this->get_option( $field['id'] ), false ) . ' />';
				break;
			case 'text':
				echo '<input type="text" name="' . self::PAGE . '[' . $field['id'] . ']' . '" id="' . $field['id'] . '" value="' . $this->get_option( $field['id'] ) . '" class="rp4wp-input-text" />';
				break;
			case 'textarea':
				echo '<textarea name="' . self::PAGE . '[' . $field['id'] . ']' . '" id="' . $field['id'] . '">' . $this->get_option( $field['id'] ) . '</textarea>';
				break;
			case 'button_link':
				echo '<a href="' . $field['href'] . '" class="button">' . $field['default'] . '</a>';
				break;
		}

		// Description
		if ( isset( $field['description'] ) && '' != $field['description'] ) {
			echo '<label class="rp4wp-description" for="' . $field['id'] . '">' . $field['description'] . '</label>';
		}

		// Check if this option is being filtered
		if ( has_filter( 'rp4wp_' . $field['id'] ) ) {
			echo '<small>This option is overwritten by a filter.</small>';
		}

		// End of line
		echo PHP_EOL;

	}

	/**
	 * Sanitize the option value
	 *
	 * @param array $post_data
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return array
	 */
	public function sanitize_option( $post_data ) {

		// Unset automatic_linking if not set in post
		if ( ! isset( $post_data['automatic_linking'] ) ) {
			$post_data['automatic_linking'] = 0;
		}

		// Unset display_image if not set in post
		if ( ! isset( $post_data['display_image'] ) ) {
			$post_data['display_image'] = 0;
		}

		// automatic_linking must be an integer
		$post_data['automatic_linking'] = intval( $post_data['automatic_linking'] );

		// automatic_linking_post_amount must be an integer
		$post_data['automatic_linking_post_amount'] = intval( $post_data['automatic_linking_post_amount'] );

		// Excerpt length must be an integer
		$post_data['excerpt_length'] = intval( $post_data['excerpt_length'] );

		return $post_data;

	}

	/**
	 * Get the plugin options
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return mixed|void
	 */
	public function get_options() {
		return apply_filters( 'rp4wp_options', wp_parse_args( get_option( self::PAGE, array() ), $this->defaults ) );
	}

	/**
	 * Return a single option
	 *
	 * @param $option
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed|bool
	 */
	public function get_option( $option ) {
		$options = $this->get_options();

		return apply_filters( 'rp4wp_' . $option, isset( $options[ $option ] ) ? $options[ $option ] : false );
	}

}