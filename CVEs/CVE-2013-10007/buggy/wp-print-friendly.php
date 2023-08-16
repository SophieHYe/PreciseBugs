<?php
/*
Plugin Name: WP Print Friendly
Plugin URI: http://www.thinkoomph.com/plugins-modules/wp-print-friendly/
Description: Extends WordPress' template system to support printer-friendly templates. Works with permalink structures to support nice URLs.
Author: Erick Hitter & Oomph, Inc.
Version: 0.5.2
Author URI: http://www.thinkoomph.com/

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class wp_print_friendly {
	var $query_var = 'print';

	var $ns = 'wp_print_friendly';

	var $settings_key = 'wpf';
	var $settings_defaults = array(
		'auto' => false,
		'placement' => 'below',
		'post_types' => array( 'post', 'page' ),
		'print_text' => 'Print this entry',
		'print_text_page' => 'Print this page',
		'css_class' => 'print_link',
		'link_target' => 'same',
		'endnotes' => true,
		'endnotes_label' => 'Endnotes:'
	);

	var $notice_key = 'wpf_admin_notice_dismissed';

	/**
	 * Register deactivation hook and filter.
	 *
	 * @uses register_deactivation_hook, add_filter
	 * @return null
	 */
	public function __construct() {
		register_deactivation_hook( __FILE__, array( $this, 'deactivation_hook' ) );
		add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ) );
	}

	/**
	 * Clean up after plugin deactivation.
	 *
	 * @uses flush_rewrite_rules, delete_option
	 * @action register_deactivation_hook
	 * @return null
	 */
	public function deactivation_hook() {
		flush_rewrite_rules();

		delete_option( $this->settings_key );
		delete_option( $this->notice_key );
	}

	/**
	 * Register actions and filters.
	 *
	 * @uses add_action, add_filter, get_option
	 * @action plugins_loaded
	 * @return null
	 */
	public function action_plugins_loaded() {
		add_action( 'init', array( $this, 'action_init' ), 20 );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		add_filter( 'request', array( $this, 'filter_request' ) );
		add_action( 'pre_get_posts', array( $this, 'action_pre_get_posts' ) );
		add_filter( 'template_include', array( $this, 'filter_template_include' ) );
		add_filter( 'redirect_canonical', array( $this, 'filter_redirect_canonical' ) );
		add_filter( 'body_class', array( $this, 'filter_body_class' ) );
		add_filter( 'the_content', array( $this, 'filter_the_content' ), 0 );
		add_filter( 'the_content', array( $this, 'filter_the_content_auto' ) );
		add_filter( 'the_content', array( $this, 'filter_the_content_late' ), 99 );

		if ( ! get_option( $this->notice_key ) )
			add_action( 'admin_notices', array( $this, 'action_admin_notices_activation' ) );
	}

	/**
	 * Add print endpoint and rewrite rules for term taxonomy archives
	 *
	 * @uses add_rewrite_endpoint, $wp_rewrite, get_taxonomies, add_rewrite_rule, trailingslashit
	 * @action init
	 * @return null
	 */
	public function action_init() {
		add_rewrite_endpoint( $this->query_var, EP_ALL );

		global $wp_rewrite;

		//Taxonomies, since they aren't covered by add_rewrite_endpoint
		if ( $wp_rewrite->permalink_structure ) {
			$taxonomies = get_taxonomies( array(), 'objects' );
			foreach ( $taxonomies as $taxonomy => $args ) {
				if ( $args->rewrite == false || 'post_format' == $taxonomy )
					continue;

				$taxonomy_slug = '';
				if ( $args->rewrite[ 'with_front' ] && $wp_rewrite->front != '/' ) $taxonomy_slug .= $wp_rewrite->front;
				$taxonomy_slug .= $args->rewrite[ 'slug' ];

				$query_var = $args->query_var ? $args->query_var : 'taxonomy=' . $taxonomy . '&term';

				add_rewrite_rule( $taxonomy_slug . '/(.+)/' . $this->query_var . '(/([0-9]*))?/?$', $wp_rewrite->index . '?' . $query_var . '=$matches[1]&' . $this->query_var . '=$matches[3]', 'top' );
			}
		}

		//Extra rules needed if verbose page rules are requested
		if ( $wp_rewrite->use_verbose_page_rules ) {
			//Build regex
			$regex = substr( str_replace( $wp_rewrite->rewritecode, $wp_rewrite->rewritereplace, $wp_rewrite->permalink_structure ), 1 );
			$regex = trailingslashit( $regex );
			$regex .= $this->query_var . '(/([0-9]*))?/?$';

			//Build corresponding query string
			$query = substr( str_replace( $wp_rewrite->rewritecode, $wp_rewrite->queryreplace, $wp_rewrite->permalink_structure ), 1 );
			$query = explode( '/', $query );
			$query = array_filter( $query );

			$i = 1;
			foreach ( $query as $key => $qv ) {
				$query[ $key ] .= '$matches[' . $i . ']';
				$i++;
			}

			$query[] = $this->query_var . '=$matches[' . ( $i + 1 ) . ']';

			$query = implode( '&', $query );

			//Add rule
			add_rewrite_rule( $regex, $wp_rewrite->index . '?' . $query, 'top' );
		}
	}

	/**
	 * Register plugin option and disable rewrite rule flush warning.
	 *
	 * @uses register_setting, update_option
	 * @action admin_init
	 * @return null
	 */
	public function action_admin_init() {
		register_setting( $this->settings_key, $this->settings_key, array( $this, 'admin_options_validate' ) );

		if ( isset( $_GET[ $this->notice_key ] ) )
			update_option( $this->notice_key, 1 );
	}

	/**
	 * Determine if print template is being requested.
	 *
	 * @global $wp_query
	 * @return bool
	 */
	public function is_print() {
		global $wp_query;
		return is_array( $wp_query->query ) && array_key_exists( $this->query_var, $wp_query->query );
	}

	/**
	 * Select appropriate template based on post type and available templates.
	 * Returns an array with name and path keys for available template or false if no template is found.
	 *
	 * @uses get_queried_object, is_home, is_front_page, locate_template
	 * @return array or false
	 */
	public function template_chooser() {
		//Get queried object to check post type
		$queried_object = get_queried_object();

		//Get plugin path
		$pluginpath = dirname( __FILE__ );

		if ( ( is_home() || is_front_page() ) && ( '' !== ( $path = locate_template( 'wpf-home.php', false ) ) ) ) {
			$template = array(
				'name' => 'wpf-home',
				'path' => $path
			);
		}
		elseif (
			is_object( $queried_object ) &&
			property_exists( $queried_object, 'taxonomy' ) &&
			property_exists( $queried_object, 'slug' ) &&
			( '' !== ( $path = locate_template( array( 'wpf-' . $queried_object->taxonomy . '-' . $queried_object->slug . '.php', 'wpf-' . $queried_object->taxonomy . '.php' ), false ) ) )
		)
			$template = array(
				'name' => 'wpf-' . $queried_object->taxonomy,
				'path' => $path
			);
		elseif (
			is_object( $queried_object ) &&
			property_exists( $queried_object, 'post_type' ) &&
			property_exists( $queried_object, 'post_name' ) &&
			( '' !== ( $path = locate_template( array( 'wpf-' . $queried_object->post_type . '-' . $queried_object->post_name . '.php', 'wpf-' . $queried_object->post_type . '.php' ), false ) ) )
		)
			$template = array(
				'name' => 'wpf-' . $queried_object->post_type,
				'path' => $path
			);
		elseif (
			is_object( $queried_object ) &&
			property_exists( $queried_object, 'post_name' ) &&
			( '' !== ( $path = locate_template( 'wpf-' . $queried_object->post_name . '.php', false ) ) )
		)
			$template = array(
				'name' => 'wpf-' . $queried_object->post_name,
				'path' => $path
			);
		elseif ( '' !== ( $path = locate_template( 'wpf.php', false ) ) )
			$template = array(
				'name' => 'wpf-default',
				'path' => $path
			);
		elseif ( file_exists( $pluginpath . '/default-template.php' ) )
			$template = array(
				'name' => 'wpf-plugin-default',
				'path' => $pluginpath . '/default-template.php'
			);

		return isset( $template ) ? $template : false;
	}

	/**
	 * Detect request for print stylesheet on the homepage and reset query variables.
	 *
	 * @param array $qv
	 * @filter request
	 * @return array
	 */
	public function filter_request( $qv ) {
		if ( array_key_exists( 'pagename', $qv ) && $qv[ 'pagename' ] == $this->query_var ) {
			$qv[ $this->query_var ] = '';
			unset( $qv[ 'page' ] );
			unset( $qv[ 'pagename' ] );
		}

		if ( array_key_exists( $this->query_var, $qv ) && is_numeric( $qv[ $this->query_var ] ) )
			$qv[ 'page' ] = intval( $qv[ $this->query_var ] );

		return $qv;
	}

	/**
	 * Filter query when request to print specific page is made.
	 *
	 * @param object $query
	 * @action pre_get_posts
	 * @return object
	 */
	public function action_pre_get_posts( $query ) {
		if ( array_key_exists( $this->query_var, $query->query_vars ) && ! empty( $query->query_vars[ $this->query_var ] ) ) {
			$qv = explode( '/', $query->query_vars[ $this->query_var ] );

			if ( array_key_exists( 1, $qv ) && is_numeric( $qv[ 1 ] ) )
				$query->query_vars[ 'page' ] = (int)$qv[ 1 ];
		}

		return $query;
	}

	/**
	 * Filter template include to return print template if requested.
	 *
	 * @param string $template
	 * @filter template_include
	 * @return string
	 */
	public function filter_template_include( $template ) {
		if ( $this->is_print() && ( $print_template = $this->template_chooser() ) )
			$template = $print_template[ 'path' ];

		return $template;
	}

	/**
	 * Prevent canonical redirect if print URL is requested.
	 *
	 * @param string $url
	 * @uses this::is_print
	 * @filter redirect_canonical
	 * @return string or false
	 */
	public function filter_redirect_canonical( $url ) {
		if ( $this->is_print() )
			$url = false;

		return $url;
	}

	/**
	 * Filter body classes to include references to print template.
	 *
	 * @param array $classes
	 * @filter body_class
	 * @return array
	 */
	public function filter_body_class( $classes ) {
		if ( $this->is_print() && ( $print_template = $this->template_chooser() ) ) {
			if ( $print_template[ 'name' ] == 'default' )
				$classes[] = 'wpf';
			else
				$classes[] = $print_template[ 'name' ];
		}

		return $classes;
	}

	/**
	 * Filter post content to support printing entire post on one page.
	 *
	 * @param string $content
	 * @uses get_query_var
	 * @filter the_content
	 * @return string
	 */
	public function filter_the_content( $content ) {
		if ( $this->is_print() ) {
			$print = get_query_var( $this->query_var );

			if ( $print == 'all' || $print == '/all' || empty( $print ) ) {
				global $post;

				$content = $post->post_content;
				$content = str_replace("\n<!--nextpage-->\n", "\n\n", $content);
				$content = str_replace("\n<!--nextpage-->", "\n", $content);
				$content = str_replace("<!--nextpage-->\n", "\n", $content);
				$content = str_replace("<!--nextpage-->", ' ', $content);
			}
		}

		return $content;
	}

	/**
	 * Filter the content if automatic inclusion is selected.
	 *
	 * @param string $content
	 * @uses $this::get_options, $post, $this::print_url, get_query_var, apply_filters
	 * @filter the_content
	 * @return string
	 */
	public function filter_the_content_auto( $content ) {
		$options = $this->get_options();

		global $post;

		if ( is_array( $options ) && array_key_exists( 'auto', $options ) && $options[ 'auto' ] == true && in_array( $post->post_type, $options[ 'post_types' ] ) && ! $this->is_print() ) {
			extract( $options );

			//Basic URL
			$print_url = $this->print_url();

			//Page URL, if necessary
			if ( ! empty( $print_text_page ) && strpos( $post->post_content, '<!--nextpage-->' ) !== false ) {
				$page = get_query_var( 'page' );
				$page = $page ? $page : 1;

				$print_url_page = $this->print_url( false, $page );
			}

			//Build link(s)
			$link = '<p class="wpf_wrapper"><a class="' . $css_class . '" href="' . $print_url . '"' . ( $link_target == 'new' ? ' target="_blank"' : '' ) . '>' . $print_text . '</a>';

			if ( isset( $print_url_page ) ) {
				$link .= ' | ';
				$link .= '<a class="' . $css_class . ' ' . $css_class . '_cur" href="' . $print_url_page . '"' . ( $link_target == 'new' ? ' target="_blank"' : '' ) . '>' . $print_text_page . '</a>';
			}

			$link .= '</p><!-- .wpf_wrapper -->';

			//Place link(s)
			if ( $placement == 'above' )
				$content = $link . $content;
			elseif ( $placement == 'below' )
				$content = $content . $link;
			elseif ( $placement == 'both' )
				$content = $link . $content . $link;
		}

		return $content;
	}

	/**
	 * Convert links to endnotes if desired.
	 *
	 * @param string $content
	 * @uses $this::is_print, $this::get_options
	 * @filter the_content
	 * @return string
	 */
	public function filter_the_content_late( $content ) {
		if ( $this->is_print() ) {
			global $post;

			$options = $this->get_options();

			//Endnotes
			if ( $options[ 'endnotes' ] ) {
				$links = array();
				$i = 1;

				//Build array of links
				preg_match_all( '#<a href=(["\'{1}])([^"\']+)(["\'{1}])([^>]*)>(.*?)</a>#i', $content, $matches );

				if (
					isset( $matches ) && is_array( $matches ) &&
					array_key_exists( 0, $matches ) && ! empty( $matches[ 0 ] ) &&
					array_key_exists( 2, $matches ) && ! empty( $matches[ 2 ] ) &&
					array_key_exists( 5, $matches ) && ! empty( $matches[ 5 ] )
				) {
					//Format matches for replacement in content
					$replacements = array();
					foreach ( $matches[ 0 ] as $key => $match ) {
						$replacements[ $match ] = array(
							'url' => $matches[ 2 ][ $key ],
							'title' => $matches[ 5 ][ $key ]
						);
					}

					//Replace links with endnote markers
					foreach ( $replacements as $match => $args ) {
						$content = str_replace( $match, $args[ 'title' ] . '[' . $i . ']', $content );
						$links[ $i ] = $args;
						$i++;
					}

					//Output endnotes
					$endnotes = '<div class="wpf-endnotes">';
					$endnotes .= '<strong>' . $options[ 'endnotes_label' ] . '</strong>';
					$endnotes .= '<ol>';
					foreach ( $links as $link ) {
						$endnotes .= '<li>';
						$endnotes .=  preg_replace( '#<img(.*)>#', '[Image]', $link[ 'title' ] ) . ': ' . esc_url( $link[ 'url' ] );
						$endnotes .= '</li>';
					}
					$endnotes .= '</ol></div><!-- .wpf-endnotes -->';

					$content .= $endnotes;
				}
			}
		}

		return $content;
	}

	/**
	 * Generate URL for post's printer-friendly format.
	 *
	 * @param int $post_id
	 * @param int $page
	 * @uses is_view_all, is_home, is_front_page, home_url, $post, get_permalink, is_category, get_category_link, is_tag, get_tag_link, is_date, get_query_var, get_day_link, get_month_link, get_year_link, is_tax, get_queried_object, get_term_link, $wp_rewrite, path_join, trailingslashit, add_query_arg
	 * @return string or bool
	 */
	public function print_url( $post_id = false, $page = false ) {
		if ( $page === true )
			return false;

		if ( function_exists( 'is_view_all' ) && is_view_all() )
			$page = false;

		$link = false;

		//Get link base specific to page type being viewed
		if ( is_singular() || in_the_loop() ) {
			if ( $post_id == false ) {
				global $post;
				$post_id = $post->ID;
			}

			if ( ! $post_id )
				return false;

			$link = get_permalink( $post_id );
		}
		elseif ( is_home() || is_front_page() )
			$link = home_url( '/' );
		elseif ( is_category() )
			$link = get_category_link( get_query_var( 'cat' ) );
		elseif ( is_tag() )
			$link = get_tag_link( get_query_var( 'tag_id' ) );
		/* DISABLED FOR NOW AS PRINTING OF DATE-BASED ARCHIVES DOESN'T WORK YET
		elseif ( is_date() ) {
			$year = get_query_var( 'year' );
			$monthnum = get_query_var( 'monthnum' );
			$day = get_query_var( 'day' );

			if ( $day )
				$link = get_day_link( $year, $monthnum, $day );
			elseif ( $monthnum )
				$link = get_month_link( $year, $monthnum );
			else
				$link = get_year_link( $year );
		}*/
		elseif ( is_tax() ) {
			$queried_object = get_queried_object();

			if ( is_object( $queried_object ) && property_exists( $queried_object, 'taxonomy' ) && property_exists( $queried_object, 'term_id' ) )
				$link = get_term_link( (int)$queried_object->term_id, $queried_object->taxonomy );
		}

		//If link base is set, build link
		if ( $link !== false ) {
			global $wp_rewrite;

			$page = intval( $page );

			if ( $wp_rewrite->using_permalinks() ) {
				$link = path_join( $link, $this->query_var );

				if ( $page )
					$link = path_join( $link, intval( $page ) );

				if ( $wp_rewrite->use_trailing_slashes )
					$link = trailingslashit( $link );
			}
			else {
				$link = add_query_arg( $this->query_var, is_numeric( $page ) ? intval( $page ) : 'all', $link );

				if ( $page )
					$link = add_query_arg( 'page', is_numeric( $page ) ? intval( $page ) : 'all', $link );
			}
		}

		return $link;
	}

	/**
	 * Add menu item for options page.
	 *
	 * @uses add_options_page
	 * @action admin_menu
	 * @return null
	 */
	public function action_admin_menu() {
		add_options_page( 'WP Print Friendly Options', 'WP Print Friendly', 'manage_options', $this->ns, array( $this, 'admin_options' ) );
	}

	/**
	 * Render options page.
	 *
	 * @uses settings_fields, $this::get_options, _e, checked, esc_attr
	 * @return html
	 */
	public function admin_options() {
	?>
		<div class="wrap">
			<h2>WP Print Friendly</h2>

			<form action="options.php" method="post">
				<?php
					settings_fields( $this->settings_key );
					$options = $this->get_options();

					$post_types = $this->post_types_array();
				?>

				<h3>Display Options</h3>

				<table class="form-table">
					<tr>
						<th scope="row"><?php _e( 'Automatically add print links based on settings below?', 'wp_print_friendly' ); ?></th>
						<td>
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[auto]" id="auto-true" value="1"<?php checked( $options[ 'auto' ], true, true ); ?> /> <label for="auto-true"><?php _e( 'Yes', 'wp_print_friendly' ); ?></label><br />
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[auto]" id="auto-false" value="0"<?php checked( $options[ 'auto' ], false, true ); ?> /> <label for="auto-false"><?php _e( 'No', 'wp_print_friendly' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Automatically place link:', 'wp_print_friendly' ); ?></th>
						<td>
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[placement]" id="placement-above" value="above"<?php checked( $options[ 'placement' ], 'above', true ); ?> /> <label for="placement-above"><?php _e( 'Above content', 'wp_print_friendly' ); ?></label><br />
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[placement]" id="placement-below" value="below"<?php checked( $options[ 'placement' ], 'below', true ); ?> /> <label for="placement-below"><?php _e( 'Below content', 'wp_print_friendly' ); ?></label><br />
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[placement]" id="placement-both" value="both"<?php checked( $options[ 'placement' ], 'both', true ); ?> /> <label for="placement-both"><?php _e( 'Above and below content', 'wp_print_friendly' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Display automatically on:', 'wp_print_friendly' ); ?></th>
						<td>
							<?php foreach ( $post_types as $post_type ): ?>
								<input type="checkbox" name="<?php echo esc_attr( $this->settings_key ); ?>[post_types][]" id="pt-<?php echo $post_type->name; ?>" value="<?php echo $post_type->name; ?>"<?php if ( in_array( $post_type->name, $options[ 'post_types' ] ) ) echo ' checked="checked"'; ?> /> <label for="pt-<?php echo $post_type->name; ?>"><?php echo $post_type->labels->name; ?></label><br />
							<?php endforeach; ?>
						</td>
					</tr>
				</table>

				<h3>Link Options</h3>

				<table class="form-table">
					<tr>
						<th scope="row"><?php _e( 'Text for link to print entire item:', 'wp_print_friendly' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( $this->settings_key ); ?>[print_text]" id="print_text" value="<?php echo esc_attr( $options[ 'print_text' ] ); ?>" style="width: 40%;" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Text for link to print current page:', 'wp_print_friendly' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( $this->settings_key ); ?>[print_text_page]" id="print_text_page" value="<?php echo esc_attr( $options[ 'print_text_page' ] ); ?>" style="width: 40%;" />

							<p class="description"><?php _e( 'If viewing a multipage post (set by using the &lt;!--nextpage--&gt; tag), the text above is used for a link to print just the current page.', 'wp_print_friendly' ); ?></p>
							<p class="description"><?php _e( '<strong>To hide this link,</strong> clear the field\'s contents.', 'wp_print_friendly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'CSS for print links:', 'wp_print_friendly' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( $this->settings_key ); ?>[css_class]" id="css_class" value="<?php echo esc_attr( $options[ 'css_class' ] ); ?>" style="width: 40%;" />

							<p class="description"><?php _e( 'For page-specific print links, a second class, created by appending <strong>_cur</strong> to the above text, is added to each link.', 'wp_print_friendly' ); ?></p>
							<p class="description"><?php _e( 'Be aware that Internet Explorer will only interpret the first two CSS classes, so if multiple classes are entered above, the page-specific class may not be available in IE.', 'wp_print_friendly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Open print-friendly views:', 'wp_print_friendly' ); ?></th>
						<td>
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[link_target]" id="target-same" value="same"<?php checked( $options[ 'link_target' ], 'same', true ); ?> /> <label for="target-same"><?php _e( 'In the same window', 'wp_print_friendly' ); ?></label><br />
							<input type="radio" name="<?php echo esc_attr( $this->settings_key ); ?>[link_target]" id="target-new" value="new"<?php checked( $options[ 'link_target' ], 'new', true ); ?> /> <label for="target-new"><?php _e( 'In a new window', 'wp_print_friendly' ); ?></label>
						</td>
					</tr>
				</table>

				<h3>Endnote Options</h3>

				<table class="form-table">
					<tr>
						<th scope="row"><?php _e( 'Include endnotes for links found in content?', 'wp_print_friendly' ); ?></th>
						<td>
							<input type="checkbox" name="<?php echo esc_attr( $this->settings_key ); ?>[endnotes]" id="endnotes" value="1"<?php checked( $options[ 'endnotes' ], true, true ); ?> /> <label for="endnotes"><?php _e( 'Yes', 'wp_print_friendly' ); ?></label>

							<p class="description"><?php _e( 'If enabled, content is automatically scanned for links and an endnote is added for each link found. This can be helpful for users if your content includes many links.', 'wp_print_friendly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="endnotes-label"><?php _e( 'Endnotes heading:', 'wp_print_friendly' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( $this->settings_key ); ?>[endnotes_label]" class="regular-text code" id="endnotes-label" value="<?php echo esc_attr( $options[ 'endnotes_label' ] ); ?>" />

							<p class="description"><?php _e( 'If endnotes are enabled, the text entered above will be output above the list of links.', 'wp_print_friendly' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" class="button-primary" value="Save Changes" />
				</p>
			</form>

		</div><!-- .wrap -->
	<?php
	}

	/**
	 * Validate options
	 *
	 * @param array $options
	 * @uses $this::get_options, $this::post_types_array, delete_option, sanitize_text_field
	 * @return array
	 */
	public function admin_options_validate( $options ) {
		$new_options = array(
			'endnotes' => false
		);

		if ( is_array( $options ) ) {
			foreach ( $options as $key => $value ) {
				switch( $key ) {
					case 'auto':
					case 'endnotes':
						$new_options[ $key ] = (bool)$value;
					break;

					case 'placement':
						$placements = array(
							'above',
							'below',
							'both'
						);

						$new_options[ $key ] = in_array( $value, $placements ) ? $value : 'below';
					break;

					case 'post_types':
						$post_types = $this->post_types_array();

						$new_options[ $key ] = array();

						if ( is_array( $value ) && is_array( $post_types ) ) {
							foreach ( $post_types as $post_type ) {
								if ( in_array( $post_type->name, $value ) )
									$new_options[ $key ][] = $post_type->name;
							}
						}
					break;

					case 'print_text':
					case 'print_text_page':
					case 'css_class':
					case 'endnotes_label':
						$value = sanitize_text_field( $value );

						if ( $key == 'print_text' && empty( $value ) )
							$value = 'Print this entry';

						$new_options[ $key ] = $value;
					break;

					case 'link_target':
						$new_options[ $key ] = $value == 'new' ? 'new' : 'same';
					break;

					default:
						continue;
					break;
				}
			}
		}

		return $new_options;
	}

	/**
	 * Return plugin options array parsed with default options.
	 *
	 * @uses wp_parse_args, get_option
	 * @return array
	 */
	public function get_options() {
		$options = get_option( $this->settings_key, $this->settings_defaults );

		if ( ! array_key_exists( 'post_types', $options ) )
			$options[ 'post_types' ] = array();

		return wp_parse_args( $options, $this->settings_defaults );
	}

	/**
	 * Build array of available post types, excluding all builtin ones except 'post' and 'page'.
	 *
	 * @uses get_post_types
	 * @return array
	 */
	public function post_types_array() {
		$post_types = array();
		foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
			if ( $post_type->_builtin == false || $post_type->name == 'post' || $post_type->name == 'page' )
				$post_types[] = $post_type;
		}

		return $post_types;
	}

	/**
	 * Display admin notice regarding rewrite rules flush.
	 *
	 * @uses get_option, _e, __, admin_url, add_query_arg
	 * @action admin_notices
	 * @return html or null
	 */
	public function action_admin_notices_activation() {
		if ( ! get_option( $this->notice_key ) ):
		?>

		<div id="wpf-rewrite-flush-warning" class="error fade">
			<p><strong><?php _e( 'WP Print Friendly', 'wp_print_friendly' ); ?></strong></p>

			<p><?php printf( __( 'You must refresh your site\'s permalinks before WP Print Friendly is fully activated. To do so, go to <a href="%s">Permalinks</a> and click the <strong><em>Save Changes</em></strong> button at the bottom of the screen.', 'wp_print_friendly' ), admin_url( 'options-permalink.php' ) ); ?></p>

			<p><?php printf( __( 'When finished, click <a href="%s">here</a> to hide this message.', 'wp_print_friendly' ), admin_url( add_query_arg( $this->notice_key, 1, 'index.php' ) ) ); ?></p>
		</div>

		<?php
		endif;
	}

	/**
	 * Render page numbers, such as "Page 1 of 5."
	 *
	 * @param int $post_id
	 * @param string $before
	 * @param string $separator
	 * @param string $after
	 * @uses $this::is_print, get_query_var, get_post_field
	 * @return string or false
	 */
	public function page_numbers( $post_id = false, $before = 'Page ', $separator = ' of ', $after = '' ) {
		if ( ! $this->is_print() )
			return false;

		//Don't display on views that include all pages of a post
		$print = get_query_var( $this->query_var );
		if ( $print == 'all' || $print == '/all' || empty( $print ) )
			return false;

		//Get post ID and post content, or return false it either fails validation
		$post_id = intval( $post_id );

		if ( ! $post_id ) {
			global $post;
			$post_id = $post->ID;
			$post_content = $post->post_content;
		}

		$post_id = intval( $post_id );

		if ( ! $post_id )
			return false;

		if ( ! isset( $post_content ) || empty( $post_content ) )
			$post_content = get_post_field( 'post_content', $post_id );

		if ( ! is_string( $post_content ) || empty( $post_content ) || strpos( $post_content, '<!--nextpage-->' ) === false )
			return false;

		//Get current page
		$page = get_query_var( $this->query_var );
		$page = $page ? $page : 1;

		//Get total number of pages, or return false if total cannot be determined
		$num_pages = substr_count( $post_content, '<!--nextpage-->' );

		if ( is_int( $num_pages ) && $num_pages > 0 )
			$num_pages = $num_pages + 1;
		else
			return false;

		//Having made it this far, return the specified string
		return $before . $page . $separator . $num_pages . $after;
	}
}
global $wpf;
$wpf = new wp_print_friendly;

/**
 * Shortcut to function for generating post's printer-friendly format URL
 *
 * @param int $post_id
 * @param int $page
 * @uses $wpf
 * @return string or bool
 */
function wpf_get_print_url( $post_id = false, $page = false ) {
	global $wpf;
	if ( ! is_a( $wpf, 'wp_print_friendly' ) )
		$wpf = new wp_print_friendly;

	return $wpf->print_url( intval( $post_id ), intval( $page ) );
}

/**
 * Output link to printer-friendly post format.
 *
 * @param string $link_text
 * @param string $class
 * @param int $post_id
 * @param bool $page_link
 * @param string $page_link_separator
 * @param string $page_link_text
 * @param string $link_target
 * @uses $post, wpf_get_print_url, esc_attr, esc_url, get_query_var
 * @return string or null
 */
function wpf_the_print_link( $page_link = false, $link_text = 'Print this post', $class = 'print_link', $page_link_separator = ' | ', $page_link_text = 'Print this page', $link_target = 'same' ) {
	global $post;
	$url = wpf_get_print_url( $post->ID );

	$page_link = (bool)$page_link;

	if ( function_exists( 'is_view_all' ) && is_view_all() )
		$page_link = false;

	if ( $url ) {
		$link = '<a ' . ( $class ? 'class="' . esc_attr( $class ) . '"' : '' ) . ' href="' . esc_url( $url ) . '"' . ( $link_target == 'new' ? ' target="_blank"' : '' ) . '>' . $link_text . '</a>';

		if ( $page_link && strpos( $post->post_content, '<!--nextpage-->' ) !== false ) {
			$page = get_query_var( 'page' );
			$page = $page ? $page : 1;
			$link .= $page_link_separator . '<a ' . ( $class ? 'class="' . esc_attr( $class ) . '_cur ' . esc_attr( $class ) . '"' : '' ) . ' href="' . esc_url( wpf_get_print_url( $post->ID, $page ) ) . '"' . ( $link_target == 'new' ? ' target="_blank"' : '' ) . '>' . $page_link_text . '</a>';
		}

		echo $link;
	}
}

/**
 * Display page numbers, such as "Page 1 of 5."
 *
 * @param int $post_id
 * @param string $before
 * @param string $separator
 * @param string $after
 * @uses $wpf
 * @return string or false
 */
function wpf_the_page_numbers( $post_id = false, $before = 'Page ', $separator = ' of ', $after = '' ) {
	global $wpf;
	if ( ! is_a( $wpf, 'wp_print_friendly' ) )
		$wpf = new wp_print_friendly;

	echo $wpf->page_numbers( intval( $post_id ), $before, $separator, $after );
}

if ( ! function_exists( 'is_print' ) ) {
	/**
	 * Conditional tag indicating if printer-friendly format was requested.
	 *
	 * @uses $wpf
	 * @return bool
	 */
	function is_print() {
		global $wpf;
		if ( ! is_a( $wpf, 'wp_print_friendly' ) )
			$wpf = new wp_print_friendly;

		return $wpf->is_print();
	}
}
?>