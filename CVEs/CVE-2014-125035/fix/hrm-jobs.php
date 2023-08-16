<?php
/**
 * Plugin Name: Job Postings
 * Plugin URI: http://hatrackmedia.com
 * Description: A plugin for creating and displaying job opportunities.
 * Author: Bobby Bryant
 * Author URI: http://hatrackmedia.com
 * Version: 0.0.1
 * License: GPLv2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue PLugin Styles and scripts
 */

function hrm_jobs_enqueue_scripts() {

$screen = get_current_screen();

if ( is_object($screen) && 'job' == $screen->post_type ) {

	wp_enqueue_style( 'jobs-admin', plugins_url( '/css/jobs-admin.css', __FILE__) );

	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_script( 'reorder-js', plugins_url( '/js/reorder.js', __FILE__), array('jquery'), '', true );
	wp_enqueue_script( 'jquery-ui-datepicker' );
  	wp_enqueue_script( 'field-date-js', plugins_url('js/Field_Date.js', __FILE__), array('jquery-core', 'jquery-ui-core', 'jquery-ui-datepicker'), '', true );
	wp_enqueue_style( 'jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css' );
  }
}
add_action( 'admin_enqueue_scripts', 'hrm_jobs_enqueue_scripts' );

/**
 * Require project specific files
 */
require_once 'jobs-cpt.php';
require_once 'jobs-fields.php';
require_once 'render-admin.php';

/**
 * Create Sorting Admin Page
 */
function hrm_jobs_add_menu_page() {
	add_submenu_page(
		'edit.php?post_type=job',
		'Reorder Jobs',
		'Reorder Jobs',
		'edit_pages',
		'reorder_jobs',
		'hrm_jobs_render_admin'
	);
}

/**
 * Create Jobs Shortcode
 */

function hrm_jobs_list_shortcode ( $atts, $content = null ) {
	$args = array(
		'post_type' => 'job',
		'orderby' => 'menu_order',
		'order' => 'ASC',
		'post_per_page' => 100, /* add a reasonable max # rows */
		'no_found_rows' => true, /* don't generate a count as part of query */
	);

	$jobs = new WP_Query( $args );
	?>

	<?php   if ( $jobs->have_posts() ) : ?>

			<table id="job-list">
				<tr>
					<th>Job Title</th>
					<th>Location</th>
					<th></th>
				</tr>
				<?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>

					<?php $jobUrl = get_permalink(); ?>

				<tr>
					<td id="<?php the_id(); ?>"><?php the_title(); ?></td>
					<td><?php the_terms( $post->ID, 'location') ?></td>
					<td><a href="<?php echo esc_url( $jobUrl ) ?>">Learn More</a></td>
				</tr>

				<?php endwhile; ?>
			</table>

			<?php else: ?>
			<p><?php _e( 'You have no Jobs to display.', 'hrm_jobs' ); ?></p>
			<?php endif; ?>
		</div>

	<?php

}

add_shortcode ( 'hrm_job_list', 'hrm_jobs_list_shortcode');


