<?php

// start customizable functions, can be overriden by adding a rsvpmaker-custom.php file to the plugins directory (one level up from rsvpmaker directory)

if ( ! function_exists( 'my_events_menu' ) ) {

	function my_events_menu() {

		global $rsvp_options;

		if ( function_exists( 'do_blocks' ) ) {

			return;
		}

		add_meta_box( 'EventDatesBox', __( 'Event Options', 'rsvpmaker' ), 'draw_eventdates', 'rsvpmaker', 'normal', 'high' );

		if ( isset( $rsvp_options['additional_editors'] ) && $rsvp_options['additional_editors'] ) {

			add_meta_box( 'ExtraEditorsBox', __( 'Additional Editors', 'rsvpmaker' ), 'additional_editors', 'rsvpmaker', 'normal', 'high' );
		}

	}
}

if ( ! function_exists( 'draw_eventdates' ) ) {

	function draw_eventdates() {

		global $post;

		$post_id = ( isset( $_GET['post_id'] ) ) ? (int) $_GET['post_id'] : 0;
		$post    = ( $post_id ) ? get_post( $post_id ) : null;

		global $wpdb;

		global $rsvp_options;

		global $custom_fields;

		if ( isset( $_GET['debug'] ) ) {
			echo '<pre>';
			print_r( $post );
			echo '</pre>';
		}

		if ( isset( $_GET['clone'] ) ) {
			$id            = (int) $_GET['clone'];
			$custom_fields = get_rsvpmaker_custom( $id );
		} elseif ( isset( $post->ID ) ) {

			$custom_fields = get_rsvpmaker_custom( $post->ID );
		}

		if ( isset( $custom_fields['_rsvpmaker_special'][0] ) ) {

			$rsvpmaker_special = $custom_fields['_rsvpmaker_special'][0];

			if ( $rsvpmaker_special == 'Landing Page' ) {

				?>

<p>This is a landing page for an RSVPMaker webinar.</p>

<p><input type="radio" name="_require_webinar_passcode" value="<?php echo esc_attr( $custom_fields['_webinar_passcode'][0] ); ?>" 
																		  <?php
																			if ( isset( $custom_fields['_require_webinar_passcode'][0] ) && $custom_fields['_require_webinar_passcode'][0] ) {
																				echo 'checked="checked"';}
																			?>
	 > Passcode required to view webinar</p>

<p><input type="radio" name="_require_webinar_passcode" value="0" 
				<?php
				if ( ! isset( $custom_fields['_require_webinar_passcode'][0] ) || ! $custom_fields['_require_webinar_passcode'][0] ) {
					echo 'checked="checked"';}
				?>
	> No passcode required</p>

				<?php

			} else {
				do_action( 'rsvpmaker_special_metabox', $rsvpmaker_special );
			}

			return 'special';

		} elseif ( ( isset( $post->ID ) && rsvpmaker_is_template( $post->ID ) ) || isset( $_GET['new_template'] ) ) {

			?>

<p><em><strong><?php esc_html_e( 'Event Template', 'rsvpmaker' ); ?>:</strong> <?php esc_html_e( 'This form is for entering generic / boilerplate information, not specific details for an event on a specific date. Groups that meet on a monthly basis can post their standard meeting schedule, location, and contact details to make entering the individual events easier. You can also post multiple future meetings using the generic template and update those event listings as needed when the event date grows closer.', 'rsvpmaker' ); ?></em></p>

			<?php

			$template = get_template_sked( $post_id );

			template_schedule( $template );

			 rsvp_time_options( $post->ID );

			return;

		}

		if ( isset( $custom_fields['_meet_recur'][0] ) ) {

			$t = (int) $custom_fields['_meet_recur'][0];

			if ( $post_id ) {

				printf(
					'<p><a href="%s">%s</a> | <a href="%s">%s</a> | <a href="%s">%s</a> | <a href="%s">%s</a></p>',
					admin_url( 'post.php?action=edit&post=' . $t ),
					__( 'Edit Template Content', 'rsvpmaker' ),
					admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_details&post_id=' . $t ),
					__( 'Edit Template Options', 'rsvpmaker' ),
					admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&t=' . $t ),
					__( 'See Related Events', 'rsvpmaker' ),
					admin_url(
						'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&apply_target=' . intval( $post->ID ) . '&apply_current=' . $t . '#applytemplate

'
					),
					__( 'Switch Template', 'rsvpmaker' )
				);
			}
		} elseif ( isset( $post->ID ) ) {

			printf(
				'<p><a href="%s">%s</a></p>',
				admin_url(
					'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&apply_target=' . intval( $post->ID ) . '#applytemplate

'
				),
				__( 'Apply Template', 'rsvpmaker' )
			);
		}
		
		if(isset($post->ID))
		$event = get_rsvpmaker_event( $post->ID );
		$start = 0;

		if ( !empty($event) ) {
			$t = intval($event->ts_start);
			$end = intval($event->ts_end);
			echo "\n<div class=\"event_dates\"> \n";

			if ( $rsvp_options['long_date'] ) {
				echo utf8_encode( rsvpmaker_date( $rsvp_options['long_date'], $t ) );
			}

			$dur = $event->display_type;

			if ( ( $dur != 'allday' ) && ! strpos( $dur, '|' ) ) {

				echo rsvpmaker_date( ' ' . $rsvp_options['time_format'], $t );

			} elseif ( ( $dur == 'set' ) && $row['end_time'] ) {

				echo ' to ' . rsvpmaker_date( $rsvp_options['time_format'], $end );
			}
			rsvpmaker_date_option_event( $t, $end, $dur );

			echo "</div>\n";
			return;
		} else {

			echo '<p><em>' . __( 'You can enter dates and times in either text format or the numeric/database format.', 'rsvpmaker' ) . '</em> </p>';

			$t = time();

		}

		if ( isset( $_GET['t'] ) ) {

			$t = (int) $_GET['t'];

			$sked = get_template_sked( $t );

			$times = rsvpmaker_get_projected( $sked );

			foreach ( $times as $ts ) {

				if ( $ts > time() ) {

					break;
				}
			}

			rsvpmaker_date_option( $ts, 0, rsvpmaker_date( 'Y-m-d H:i:s', $ts ), $sked );

			$start = 1;

		} elseif ( $start == 0 ) {

			$start = 1;

			$date = ( isset( $_GET['add_date'] ) ) ? sanitize_text_field($_GET['add_date']) : 'today ' . $rsvp_options['defaulthour'] . ':' . $rsvp_options['defaultmin'] . ':00';

			rsvpmaker_date_option( $date, 0, rsvpmaker_date( 'Y-m-d H:i:s', $t ) );

		}

		if ( ! isset( $_GET['t'] ) ) { // if this is based on a template, use the template defaults

			rsvp_time_options( $post_id );
		}

		if ( isset( $_GET['debug'] ) ) {

			echo '<pre>';

			// print_r($custom_fields);

			echo '</pre>';

		}

	}
} // end draw event dates


if ( ! function_exists( 'template_schedule' ) ) {

	function template_schedule( $template ) {

		if ( ! is_array( $template ) ) {

			$template = unserialize( $template );
		}

		global $post;

		if ( ! isset( $post->ID ) ) {

			$post = (object) array( 'ID' => 0 );
		}

		global $wpdb;

		global $rsvp_options;

		// backward compatability

		if ( isset( $template['week'] ) && is_array( $template['week'] ) ) {

			$weeks = $template['week'];

			$dows = $template['dayofweek'];

		} else {

			$weeks = array();

			$dows = array();

			$weeks[0] = ( isset( $template['week'] ) ) ? $template['week'] : 0;

			$dows[0] = ( isset( $template['dayofweek'] ) ) ? $template['dayofweek'] : 0;

		}

		// default values

		if ( ! isset( $template['hour'] ) ) {

			$template['hour'] = 19;

			$template['minutes'] = '00';

		}

		$end_time = ( empty( $template['end_time'] ) ) ? '' : $template['end_time'];

		if ( $post->ID ) {

			printf( '<p><a href="%s">%s</a></p>', admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&t=' . intval( $post->ID ) ), esc_html( __( 'View/add/update events based on this template', 'rsvpmaker' ) ) );
		}

		global $wpdb;

		$dayarray  = array( __( 'Sunday', 'rsvpmaker' ), __( 'Monday', 'rsvpmaker' ), __( 'Tuesday', 'rsvpmaker' ), __( 'Wednesday', 'rsvpmaker' ), __( 'Thursday', 'rsvpmaker' ), __( 'Friday', 'rsvpmaker' ), __( 'Saturday', 'rsvpmaker' ) );
		$weekarray = array( __( 'Varies', 'rsvpmaker' ), __( 'First', 'rsvpmaker' ), __( 'Second', 'rsvpmaker' ), __( 'Third', 'rsvpmaker' ), __( 'Fourth', 'rsvpmaker' ), __( 'Last', 'rsvpmaker' ), __( 'Every', 'rsvpmaker' ) );

		echo '<p>' . esc_html( __( 'Regular Schedule', 'rsvpmaker' ) ) . ':</p><table id="skedtable"><tr><td>';

		if ( $weeks[0] == 0 ) {
			$weeks = array(); // clear out any other values
			$dows  = array();
		}

		if ( is_array( $weekarray ) ) {

			foreach ( $weekarray as $index => $label ) {

				$class = ( $index > 0 ) ? ' class="regular_sked" ' : '';

				$checked = ( in_array( $index, $weeks ) || ( ( $index == 0 ) && empty( $weeks ) ) ) ? ' checked="checked" ' : '';

				printf( '<div><input type="checkbox" name="sked[week][]" value="%d" id="wkcheck%d" %s %s /> %s<div>', $index, $index, $checked, $class, esc_html( $label ) );

			}
		}

		echo '</td><td id="daycolumn">';

		if ( is_array( $dayarray ) ) {

			foreach ( $dayarray as $index => $label ) {

				$checked = ( is_array( $dows ) && ( in_array( $index, $dows ) ) ) ? ' checked="checked" ' : '';

				printf( '<div><input type="checkbox" name="sked[dayofweek][]" value="%d" id="daycheck%d" %s class="days" /> %s<div>', $index, $index, $checked, esc_html( $label ) );

			}
		}

		echo '</td><tr></table><div id="daymsg"></div>';

		?>

<script>

jQuery(function () {

	jQuery('#wkcheck0').on('click', function () {

		if(this.checked){

		jQuery('#wkcheck1').prop('checked', false);

		jQuery('#wkcheck2').prop('checked', false);

		jQuery('#wkcheck3').prop('checked', false);

		jQuery('#wkcheck4').prop('checked', false);

		jQuery('#wkcheck5').prop('checked', false);

		jQuery('#wkcheck6').prop('checked', false);

		jQuery('#daycheck0').prop('checked', false);

		jQuery('#daycheck1').prop('checked', false);

		jQuery('#daycheck2').prop('checked', false);

		jQuery('#daycheck3').prop('checked', false);

		jQuery('#daycheck4').prop('checked', false);

		jQuery('#daycheck5').prop('checked', false);

		jQuery('#daycheck6').prop('checked', false);

		jQuery('#daycolumn').css('border', 'none');	

		jQuery('#daymsg').html('');

		}

	});

	jQuery('#wkcheck6').on('click', function () {

		if(this.checked){

		jQuery('#wkcheck0').prop('checked', false);

		jQuery('#wkcheck1').prop('checked', false);

		jQuery('#wkcheck2').prop('checked', false);

		jQuery('#wkcheck3').prop('checked', false);

		jQuery('#wkcheck4').prop('checked', false);

		jQuery('#wkcheck5').prop('checked', false);

		}

	});

	jQuery('.regular_sked').on('click', function () {

		if(this.checked){

		jQuery('#wkcheck0').prop('checked', false);

		if(!jQuery('#daycheck0').prop('checked') && !jQuery('#daycheck1').prop('checked') && !jQuery('#daycheck2').prop('checked') && !jQuery('#daycheck3').prop('checked') && !jQuery('#daycheck4').prop('checked') && !jQuery('#daycheck5').prop('checked') && !jQuery('#daycheck6').prop('checked'))

			{

				jQuery('#daycolumn').css('border', 'thin solid red');	

				jQuery('#skedtable td').css('padding', '5px');

				jQuery('#daymsg').html('<em><?php esc_html_e( 'choose one or more days of the week', 'rsvpmaker' ); ?></em>');

			}

		}

	});

	jQuery('.days').on('click', function () {

		if(this.checked){

		jQuery('#daycolumn').css('border', 'none');	

		jQuery('#daymsg').html('');

		}

	});



});

</script>



<p><?php esc_html_e( 'Stop date (optional)', 'rsvpmaker' ); ?>: <input type="text" name="sked[stop]" value="<?php
				if ( isset( $template['stop'] ) ) {
					echo esc_attr($template['stop']);}
				?>" placeholder="<?php
		esc_html_e( 'example', 'rsvpmaker' );
		echo ': ' . date( 'Y' ) . '-12-31';
		?>" /> <em>(<?php esc_html_e( 'format', 'rsvpmaker' ); ?>: "YYYY-mm-dd" or "+6 month" or "+1 year")</em></p>
		<?php
		$auto = ( ( isset( $_GET['new_template'] ) && ! empty( $rsvp_options['autorenew'] ) ) || get_post_meta( $post->ID, 'rsvpautorenew', true ) );
		?>
<p><input type="checkbox" name="rsvpautorenew" id="rsvpautorenew" 
		<?php
		if ( $auto ) {
			echo 'checked="checked"';}
		?>
 /> <?php esc_html_e( 'Automatically add dates according to this schedule', 'rsvpmaker' ); ?></em></p>

		<?php

		$h = $template['hour'];

		$minutes = $template['minutes'];

		$duration = isset( $template['duration'] ) ? $template['duration'] : '';

		$displayminutes = $displayhour = '';

		?>
<div><label>Time</label> <input type="time" id="sql-time" name="sked[time]" value="<?php echo esc_attr( $h . ':' . $minutes ); ?>" > </div>
<div id="template-time-error"></div>
		<?php

		rsvpmaker_duration_select( 'sked[duration]', $template, $h . ':' . $minutes, 0 );

	}
} // end template schedule


function rsvpmaker_sanitize_array_vars($array) {
	if(!is_array($array))
		return false;
	foreach($array as $index => $var) {
		if(is_array($var))
			$var = array_map('sanitize_text_field',$var);
		else
			$var = sanitize_text_field($var);
		$array[$index] = $var;
	}
	return $array;
}

function save_rsvp_template_meta( $post_id ) {

	if ( ! isset( $_POST['sked'] )  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {

		return;
	}

	// we only care about saving template data

	global $wpdb;

	global $post;

	global $current_user;

	if ( $parent_id = wp_is_post_revision( $post_id ) ) {

		$post_id = $parent_id;

	}

	$sked = array_map('rsvpmaker_sanitize_array_vars',$_POST['sked']);

	if ( $sked['time'] ) {
			$p               = explode( ':', $sked['time'] );
			$sked['hour']    = $p[0];
			$sked['minutes'] = $p[1];
	}

	if ( empty( $sked['dayofweek'] ) ) {
		$sked['dayofweek'][0] = 9;
	}

	$sked['duration'] = $sked['end_time_type'] = sanitize_text_field( $_POST['end_time_type'] );
	$sked['end']      = $sked['rsvp_sql_end'] = sanitize_text_field( $_POST['rsvp_sql_end'] );

	new_template_schedule( $post_id, $sked );

	if ( isset( $_POST['rsvpautorenew'] )  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {
		update_post_meta( $post_id, 'rsvpautorenew', 1 );
	} else {
		delete_post_meta( $post_id, 'rsvpautorenew' );
	}
}



if ( ! function_exists( 'rsvpmaker_roles' ) ) {

	function rsvpmaker_roles() {

		// by default, capabilities for events are the same as for blog posts

		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {

			$wp_roles = new WP_Roles();
		}

		// subscribers should not be able to edit
		$wp_roles->remove_cap( 'subscriber', 'edit_rsvpmakers' );

		// if roles persist from previous session, return
		if ( ! empty( $wp_roles->roles['administrator']['capabilities']['edit_rsvpmakers'] ) ) {
			return;
		}

		if ( is_array( $wp_roles->roles ) ) {

			foreach ( $wp_roles->roles as $role => $rolearray ) {

				foreach ( $rolearray['capabilities'] as $cap => $flag ) {

					if ( strpos( $cap, 'post' ) ) {
						$fbcap = str_replace( 'post', 'rsvpmaker', $cap );
						$wp_roles->add_cap( $role, $fbcap );
					}
				}
			}
		}

	}
}



function get_confirmation_options( $post_id = 0, $documents = array() ) {

	global $post;

	if ( isset( $post->ID ) ) {

		$post_id = $post->ID;
	}

	$output = '';

	$confirm = rsvp_get_confirm( $post_id, true );

	$output = sprintf( '<h3 id="confirmation">%s</h3>', __( 'Confirmation Message', 'rsvpmaker' ) );

	$output .= $confirm->post_content;

	foreach ( $documents as $d ) {

		$id = $d['id'];

		if ( ( $id == 'edit_confirm' ) || ( $id == 'customize_confirmation' ) ) {

			$output .= sprintf( '<p><a href="%s">Edit: %s</a></p>', esc_attr( $d['href'] ), esc_html( $d['title'] ) );
		}
	}

	if ( ( empty( $_GET['page'] ) || $_GET['page'] != 'rsvp_reminders' ) ) {
		$output              .= sprintf( '<p><a href="%s" target="_blank">Create / Edit Reminders</a></p>', admin_url( 'edit.php?post_type=rsvpmaker&page=rsvp_reminders&message_type=confirmation&post_id=' . $post_id ) );
		$payment_confirmation = (int) get_post_meta( $post_id, 'payment_confirmation_message', true );
		if ( empty( $payment_confirmation ) || empty( get_post( $payment_confirmation ) ) ) {
				$add_payment_conf = admin_url( 'edit.php?title=Payment%20Confirmation&rsvpcz=payment_confirmation_message&post_id=' . $post_id );
				$output          .= sprintf( '<p><a href="%s">%s</a></p>', $add_payment_conf, __( 'Add Payment Confirmation Message' ) );
		} else {
			$output .= sprintf( '<p><a href="%s">%s</a></p>', admin_url( 'post.php?post=' . $payment_confirmation . '&action=edit' ), __( 'Edit Payment Confirmation Message' ) );
		}
	}

	$templates = get_rsvpmaker_email_template();

	$chosen = (int) get_post_meta( $post_id, 'rsvp_tx_template', true );

	if ( ! $chosen ) {

		$chosen = (int) get_option( 'rsvpmaker_tx_template' );
	}

	$choose_template = '';

	if ( $templates ) {

		foreach ( $templates as $index => $template ) {

			if ( $index == 0 ) {

				continue;
			}

			$s = ( $index == $chosen ) ? ' selected="selected" ' : '';

			$choose_template .= sprintf( '<option value="%d" %s>%s</option>', $index, $s, $template['slug'] );

		}
	}

	$output .= sprintf( '<p>%s: <select name="rsvp_tx_template">%s</select></p><input type="hidden" name="rsvp_tx_post_id" value="%d">', __( 'Confirmation Email Template' ), $choose_template, $post_id );

	$output = '<div style="max-width: 800px">' . $output . '</div>';

	return $output;

}



if ( ! function_exists( 'GetRSVPAdminForm' ) ) {

	function GetRSVPAdminForm( $post_id ) {
		global $custom_fields;

		global $post;

		global $rsvp_options;

		$rsvp_on = ( isset( $custom_fields['_rsvp_on'][0] ) && ( ! $custom_fields['_rsvp_on'][0] ) ) ? 0 : 1;

		$include_event = $custom_fields['_rsvp_confirmation_include_event'][0];

		$after_payment = $custom_fields['_rsvp_confirmation_include_event'][0];

		$login_required = $custom_fields['_rsvp_login_required'][0];

		$rsvp_to = $custom_fields['_rsvp_to'][0];

		$rsvp_instructions = $custom_fields['_rsvp_instructions'][0];

		$rsvp_form = $custom_fields['_rsvp_form'][0];

		$rsvp_max = $custom_fields['_rsvp_max'][0];

		$rsvp_count = $custom_fields['_rsvp_count'][0]; // else $rsvp_count = 1;

		$rsvp_show_attendees = $custom_fields['_rsvp_show_attendees'][0];

		$rsvp_captcha = $custom_fields['_rsvp_captcha'][0];

		$rsvp_count_party = $custom_fields['_rsvp_count_party'][0];

		$rsvp_yesno = $custom_fields['_rsvp_yesno'][0];

		if ( isset( $custom_fields['_rsvp_reminder'][0] ) && $custom_fields['_rsvp_reminder'][0] ) {

			$t = rsvpmaker_strtotime( $custom_fields['_rsvp_reminder'][0] );

			$remindyear = date( 'Y', $t );

			if ( $remindyear == 1970 ) {

				$remindyear = '';

			} else {

				$remindmonth = date( 'm', $t );

				$remindday = rsvpmaker_date( 'd', $t );

				$remindtime = rsvpmaker_date( 'H:i:s', $t );

			}
		}

		if ( isset( $custom_fields['_rsvp_deadline'][0] ) && $custom_fields['_rsvp_deadline'][0] ) {

			$t = (int) $custom_fields['_rsvp_deadline'][0];

			$deadyear = date( 'Y', $t );

			$deadmonth = rsvpmaker_date( 'm', $t );

			$deadday = rsvpmaker_date( 'd', $t );

			$deadtime = rsvpmaker_date( 'H:i:00', $t );

		}

		if ( isset( $custom_fields['_rsvp_start'][0] ) && $custom_fields['_rsvp_start'][0] ) {

			$t = (int) $custom_fields['_rsvp_start'][0];

			$startyear = date( 'Y', $t );

			$startmonth = rsvpmaker_date( 'm', $t );

			$startday = rsvpmaker_date( 'd', $t );

			$starttime = rsvpmaker_date( 'H:00:00', $t );

		}

		?>

		<?php

		echo '</p>';

		if ( empty( $deadtime ) ) {
			$deadtime = '23:59:59';
		}

		if ( empty( $starttime ) ) {
			$starttime = '00:00:00';
		}

		if ( empty( $remindtime ) ) {
			$remindtime = '00:00:00';
		}

		?>

<p>

		<?php esc_html_e( 'Collect RSVPs', 'rsvpmaker' ); ?>

  <input type="radio" name="setrsvp[on]" id="setrsvpon" value="1" 
		<?php
		if ( $rsvp_on ) {
			echo 'checked="checked" ';}
		?>
		 />

		<?php esc_html_e( 'YES', 'rsvpmaker' ); ?> <input type="radio" name="setrsvp[on]" id="setrsvpon" value="0" 
				  <?php
					if ( ! $rsvp_on ) {
						echo 'checked="checked" ';}
					?>
			 />

		<?php esc_html_e( 'NO', 'rsvpmaker' ); ?> </p>

<div id="rsvpdetails">

  <input type="checkbox" name="setrsvp[login_required]" id="setrsvp[login_required]" value="1" 
		<?php
		if ( $login_required ) {
			echo 'checked="checked" ';}
		?>
		 />

		<?php echo __( 'Login required', 'rsvpmaker' ); ?> <?php
		if ( ! $rsvp_on ) {
			echo ' <strong style="color: red;">' . __( 'Check to activate', 'rsvpmaker' ) . '</strong> ';}
		?>

  <input type="checkbox" name="setrsvp[yesno]" id="setrsvp[yesno]" value="1" 
		<?php
		if ( $rsvp_yesno ) {
			echo 'checked="checked" ';}
		?>
		 />

		<?php echo __( 'Show Yes/No Radio Buttons', 'rsvpmaker' ); ?> 

<br />  <input type="radio" name="setrsvp[show_attendees]" id="setrsvp[show_attendees]" value="1" 
		<?php
		if ( $rsvp_show_attendees == 1 ) {
			echo 'checked="checked" ';}
		?>
	 />

		<?php echo __( ' Display attendee names and content of note field publicly', 'rsvpmaker' ); ?>

 <input type="radio" name="setrsvp[show_attendees]" id="setrsvp[show_attendees]" value="2" 
		<?php
		if ( $rsvp_show_attendees == 2 ) {
			echo 'checked="checked" ';}
		?>
		 />

		<?php echo __( ' Display attendees for logged in users', 'rsvpmaker' ); ?>

 <input type="radio" name="setrsvp[show_attendees]" id="setrsvp[show_attendees]" value="0" 
		<?php
		if ( ! $rsvp_show_attendees ) {
			echo 'checked="checked" ';}
		?>
		 />

		<?php echo __( ' Do not display', 'rsvpmaker' ); ?>

		
<br />  <input type="checkbox" name="setrsvp[captcha]" id="setrsvp[captcha]" value="1" 
		<?php
		if ( $rsvp_captcha ) {
			echo 'checked="checked" ';}
		?>
	 />

		<?php echo __( ' Include CAPTCHA challenge', 'rsvpmaker' ); ?> <?php
		if ( ! $rsvp_captcha ) {
			echo ' <strong style="color: red;">' . __( 'Check to activate', 'rsvpmaker' ) . '</strong> ';}
		?>

</p>

<div id="rsvpoptions">

		<?php echo __( 'Email Address for Notifications', 'rsvpmaker' ); ?>: <input id="setrsvp[to]" name="setrsvp[to]" type="text" value="<?php echo esc_attr( $rsvp_to ); ?>"><br />

<br /><?php echo __( 'Instructions for User', 'rsvpmaker' ); ?>:<br />

<textarea id="setrsvp[instructions]" name="setrsvp[instructions]" cols="80" style="max-width: 95%;"><?php echo esc_attr( $rsvp_instructions ); ?></textarea>

<br />

  <input type="checkbox" name="setrsvp[rsvpmaker_send_confirmation_email]" id="rsvpmaker_send_confirmation_email" value="1" 
		<?php
		if ( ! isset( $custom_fields['_rsvp_rsvpmaker_send_confirmation_email'][0] ) || $custom_fields['_rsvp_rsvpmaker_send_confirmation_email'][0] ) {
			echo ' checked="checked" ';}
		?>
	 > <?php esc_html_e( 'Send confirmation emails', 'rsvpmaker' ); ?>

  <input type="checkbox" name="setrsvp[confirmation_after_payment]" id="rsvpmaker_send_confirmation_email" value="1" 
		<?php
		if ( ! isset( $custom_fields['_rsvp_confirmation_after_payment'][0] ) || $custom_fields['_rsvp_confirmation_after_payment'][0] ) {
			echo ' checked="checked" ';}
		?>
	 > <?php esc_html_e( 'Send confirmation AFTER payment', 'rsvpmaker' ); ?>

  <input type="checkbox" name="setrsvp[confirmation_include_event]" id="rsvp_confirmation_include_event"  value="1" 
		<?php
		if ( $include_event ) {
			echo ' checked="checked" ';}
		?>
	 > <?php esc_html_e( 'Include event listing with confirmation and reminders', 'rsvpmaker' ); ?>

		<?php
		echo get_confirmation_options( $post->ID, get_related_documents() );

		if ( empty( $custom_fields['_webinar_landing_page_id'][0] ) || isset( $_GET['youtube'] ) ) {

			echo '<br /><strong>' . __( 'Webinar Setup', 'rsvpmaker' ) . '</strong><br />YouTube Live: <input type="text" name="youtube_live" /> <input type="checkbox" name="webinar_other" value="1" /> ' . __( 'Other webinar', 'rsvpmaker' ) . ' <input type="checkbox" name="youtube_require_passcode" value="1" /> ' . __( 'Require passcode to view', 'rsvpmaker' ) . '<br /><em>' . __( 'If your event is a webinar, entering a YouTube Live url or checking &quot;Other webinar&quot; will create a landing page, plus suggested cofirmation and reminder messages to get you started. For YouTube Live, RSVPMaker adds the codes for the video player and chat.', 'rsvpmaker' ) . '.</em>';
		}

		?>

<br /><br /><strong><?php echo __( 'Special Options', 'rsvpmaker' ); ?></strong>
<br /><em><?php esc_html_e( 'By default, RSVP registration opens when the event is published and ends at the start time of the event. The options below allow you to specify when registration shold open and set an earlier deadline for RSVPs to close (or keep RSVPs open after the start of the event).', 'rsvpmaker' ); ?></em>

<table>

		<?php

		if ( rsvpmaker_is_template() ) {

			$deadlinedaysbefore = '';

			for ( $i = 0; $i < 31; $i++ ) {

				$s = ( isset( $custom_fields['_rsvp_deadline_daysbefore'] ) && ( $custom_fields['_rsvp_deadline_daysbefore'][0] == $i ) ) ? ' selected="selected" ' : '';

				$deadlinedaysbefore .= sprintf( '<option %s value="%d">%d %s</option>', $s, $i, $i, __( 'days before', 'rsvpmaker' ) );

			}

			$regdays = '';

			for ( $i = 0; $i < 181; $i++ ) {

				$s = ( isset( $custom_fields['_rsvp_reg_daysbefore'] ) && ( $custom_fields['_rsvp_reg_daysbefore'][0] == $i ) ) ? ' selected="selected" ' : '';

				$regdays .= sprintf( '<option %s value="%d">%d %s</option>', $s, $i, $i, __( 'days before', 'rsvpmaker' ) );

			}

			$deadlinehours    = '';
			$currentdeadhours = ( empty( $custom_fields['_rsvp_deadline_hours'][0] ) ) ? 0 : (int) $custom_fields['_rsvp_deadline_hours'][0];
			for ( $i = -12; $i < 0; $i++ ) {
				$dhour          = abs( $i );
				$s              = ( $currentdeadhours == $i ) ? ' selected="selected" ' : '';
				$deadlinehours .= sprintf( '<option %s value="%d">%d %s</option>', $s, $i, $i, __( 'hours after', 'rsvpmaker' ) );
			}

			for ( $i = 0; $i < 24; $i++ ) {
				$s              = ( $currentdeadhours == $i ) ? ' selected="selected" ' : '';
				$deadlinehours .= sprintf( '<option %s value="%d">%d %s</option>', $s, $i, $i, __( 'hours before', 'rsvpmaker' ) );
			}

			$reghours = '';

			for ( $i = 0; $i < 24; $i++ ) {

				$s = ( isset( $custom_fields['_rsvp_reg_hours'] ) && ( $custom_fields['_rsvp_reg_hours'][0] == $i ) ) ? ' selected="selected" ' : '';

				$reghours .= sprintf( '<option %s value="%d">%d %s</option>', $s, $i, $i, __( 'hours before', 'rsvpmaker' ) );

			}

			printf( '<tr><td>%s</td><td> <select name="setrsvp[deadline_daysbefore]">%s</select> <select name="setrsvp[deadline_hours]">%s</select>  </td></tr>', __( 'Deadline (optional)', 'rsvpmaker' ), $deadlinedaysbefore, $deadlinehours );

			printf( '<tr><td>%s</td><td> <select name="setrsvp[reg_daysbefore]">%s</select> <select name="setrsvp[reg_hours]">%s</select></td></tr>', __( 'Registration Starts (optional)', 'rsvpmaker' ), $regdays, $reghours );

		} else {

			?>

<tr><td><?php echo __( 'Deadline (optional)', 'rsvpmaker' ) . '</td><td> ' . __( 'Month', 'rsvpmaker' ); ?>: <input type="text" name="deadmonth" id="deadmonth" value="<?php 
if ( isset( $deadmonth ) ) {echo esc_attr($deadmonth);}?>" size="2" /> <?php echo __( 'Day', 'rsvpmaker' ); ?>: 
<input type="text" name="deadday" id="deadday" value="<?php
if ( isset( $deadday ) ) {
	echo esc_attr($deadday);}
?>" size="2" /> <?php echo __( 'Year', 'rsvpmaker' ); ?>: 

<input type="text" name="deadyear" id="deadyear" value="<?php
			if ( isset( $deadyear ) ) {
				echo esc_attr($deadyear);}
			?>" size="4" /> 
<?php rsvptimes( $deadtime, 'deadtime' ); ?> </td></tr>

<tr><td><?php echo __( 'Registration Starts (optional)', 'rsvpmaker' ) . '</td><td>' . __( 'Month', 'rsvpmaker' ); ?>: 
<input type="text" name="startmonth" id="startmonth" value="<?php
					if ( isset( $startmonth ) ) {
						echo esc_attr($startmonth);}
					?>" size="2" /> <?php echo __( 'Day', 'rsvpmaker' ); ?>: 
<input type="text" name="startday" id="startday" value="<?php
if ( isset( $startday ) ) {
	echo esc_attr($startday);}
?>" size="2" /> <?php echo __( 'Year', 'rsvpmaker' ); ?>: 
<input type="text" name="startyear" id="startyear" value="<?php
			if ( isset( $startyear ) ) {
				echo esc_attr($startyear);}
			?>" size="4" /> <?php rsvptimes( $starttime, 'starttime' ); ?></td></tr>

			<?php

		}//end not template

		if ( ! empty( $remindday ) ) { // only show if this was previously set

			?>

<tr><td><?php echo __( 'Reminder (optional)', 'rsvpmaker' ) . '</td><td>' . __( 'Month', 'rsvpmaker' ); ?>: 
<input type="text" name="remindmonth" id="remindmonth" value="<?php
					if ( isset( $remindmonth ) ) {
						echo esc_attr( $remindmonth );}
					?>" size="2" /> <?php echo __( 'Day', 'rsvpmaker' ); ?>: 
<input type="text" name="remindday" id="remindday" value="<?php
			if ( isset( $remindday ) ) {
				echo esc_attr( $remindday );}
			?>" size="2" /> <?php echo __( 'Year', 'rsvpmaker' ); ?>: 

<input type="text" name="remindyear" id="remindyear" value="<?php
if ( isset( $remindyear ) ) {
	echo esc_attr( $remindyear );}
?>" size="4" /> <?php rsvptimes( $remindtime, 'remindtime' ); ?></td></tr>

<?php

		}

		?>
</table>

<br /><?php echo __( 'Show RSVP Count', 'rsvpmaker' ); ?> 
<input type="checkbox" name="setrsvp[count]" id="setrsvp[count]" value="1" <?php
					if ( isset( $rsvp_count ) && $rsvp_count ) {
						echo ' checked="checked" ';}
					?>
	 /> 

<br /><?php echo __( 'Maximum participants', 'rsvpmaker' ); ?> 
<input type="text" name="setrsvp[max]" id="setrsvp[max]" value="<?php
					if ( isset( $rsvp_max ) ) {
						echo esc_attr( $rsvp_max );}
					?>" size="4" /> (<?php echo __( '0 for none specified', 'rsvpmaker' ); ?>)

<br /><?php echo __( 'Time Slots', 'rsvpmaker' ); ?>:

<select name="setrsvp[timeslots]" id="setrsvp[timeslots]">

<option value="0">None</option>

<option value="0:30" 
		<?php
		if ( isset( $custom_fields['_rsvp_timeslots'][0] ) && ( $custom_fields['_rsvp_timeslots'][0] == '0:30' ) ) {
			echo ' selected = "selected" ';}
		?>
	 >30 minutes</option>

		<?php

		$tslots = (int) $custom_fields['_rsvp_timeslots'][0];

		for ( $i = 1; $i < 13; $i++ ) {

			$selected = ( $i == $tslots ) ? ' selected = "selected" ' : '';

			echo '<option value="' . $i . '" ' . $selected . ">$i-hour slots</option>";

		};
		?>

</select>

<br /><em><?php echo __( 'Used for volunteer shift signups. Duration must also be set.', 'rsvpmaker' ); ?></em>

		<?php

		if ( is_numeric( $rsvp_form ) ) {

			if ( ! empty( $_POST['reset_form'] )  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {
				$rsvp_form = (int) $_POST['reset_form'];
				update_post_meta( $post->ID, '_rsvp_form', $rsvp_form );
			}

			$fpost = get_post( $rsvp_form );

			$edit = admin_url( 'post.php?action=edit&post=' . $fpost->ID . '&back=' . $post_id );

			$customize = admin_url( '?post_id=' . $post->ID . '&customize_form=' . $fpost->ID );

			echo '<h3 id="rsvpform-section">' . __( 'RSVP Form', 'rsvpmaker' ) . '</h3>';

			$guest = ( strpos( $fpost->post_content, 'rsvpmaker-guests' ) ) ? 'Yes' : 'No';

			$note = ( strpos( $fpost->post_content, 'name="note"' ) || strpos( $fpost->post_content, 'formnote' ) ) ? 'Yes' : 'No';

			preg_match_all( '/\[([A-Za-z0_9_]+)/', $fpost->post_content, $matches );

			if ( ! empty( $matches ) ) {

				foreach ( $matches[1] as $match ) {

					$fields[ $match ] = $match;
				}
			}

			preg_match_all( '/"slug":"([^"]+)/', $fpost->post_content, $matches );

			if ( ! empty( $matches ) ) {

				foreach ( $matches[1] as $match ) {

					$fields[ $match ] = $match;
				}
			}

			$merged_fields = ( empty( $fields ) ) ? '' : implode( ',', $fields );

			printf( '<div>Fields: %s<br />Guests: %s<br />Note field: %s</div>', $merged_fields, $guest, $note );

			if ( current_user_can( 'edit_post', $fpost->ID ) ) {

				if ( $fpost->post_parent == 0 ) {

					printf( '<div id="editconfirmation"><a href="%s" target="_blank">Edit</a> (default from Settings)</div><div><a href="%s" target="_blank">Customize</a></div>', $edit, $customize );

				} elseif ( $fpost->post_parent != $post->ID ) {

					printf( '<div id="editconfirmation"><a href="%s" target="_blank">Edit</a> (inherited from Template)</div><div><a href="%s" target="_blank">Customize</a></div>', $edit, $customize );

				} else {

					printf( '<div id="editconfirmation"><a href="%s" target="_blank">Edit</a></div>', $edit );

					printf( '<div><input type="checkbox" name="reset_form" value="%d" /> Reset to default form', intval( $rsvp_options['rsvp_form'] ) );

				}
			} else {
				printf( '<div><a href="%s" target="_blank">Customize</a></div>', $customize );
			}
		} else {

			?>

<br /><?php echo __( 'RSVP Form', 'rsvpmaker' ); ?> (<a href="#" id="enlarge">Enlarge</a>):<br />

<textarea id="rsvpform" name="setrsvp[form]" cols="120" rows="5" style="max-width: 95%;">
			<?php
			if ( isset( $rsvp_form ) ) {
				echo htmlentities( $rsvp_form );}
			?>
	</textarea>

			<?php rsvp_form_setup_form( $rsvp_form ); ?>

<div>

 <button id="create-form">Generate form</button>

</div>

			<?php

		}

		?>

<h3>Payment Gateway</h3>

		<?php

		$gateway_options = get_rsvpmaker_payment_options();

		$gateway = get_rsvpmaker_payment_gateway();

		$o = '';

		if ( is_array( $gateway_options ) ) {

			foreach ( $gateway_options as $gateway_option ) {

				$s = ( $gateway == $gateway_option ) ? ' selected="selected" ' : '';

				$o .= sprintf( '<option %s value="%s">%s</option>', $s, $gateway_option, $gateway_option );

			}
		}

		printf( '<p>Gateway: <select name="payment_gateway">%s</select></p>', $o );

		if ( ( class_exists( 'Stripe_Checkout_Functions' ) || ( ! empty( $rsvp_options['rsvpmaker_stripe_sk'] ) ) ) && empty( $rsvp_options['stripe'] ) && ! empty( $rsvp_options['paypal_config'] ) ) {

			$s = ( ! empty( $custom_fields['_rsvp_stripe'][0] ) ) ? 'checked="checked"' : '';

			echo '<p><input type="checkbox" name="setrsvp[stripe]" value="1" ' . $s . ' /> ' . __( 'use RSVPMakerStripe instead of PayPal', 'rsvpmaker' ) . '</p>';

		}

		?>

<p><strong><?php echo __( 'Pricing', 'rsvpmaker' ); ?></strong></p>

<p><?php echo __( 'You can set a different price for members vs. non-members, adults vs. children, etc.', 'rsvpmaker' ); ?></p>

<p><input type="radio" name="setrsvp[count_party]" value="1" 
		<?php
		if ( $rsvp_count_party ) {
			echo ' checked="checked" ';}
		?>
	 > Multiply price times size of party

<br /><input type="radio" name="setrsvp[count_party]" value="0" 
		<?php
		if ( ! $rsvp_count_party ) {
			echo ' checked="checked" ';}
		?>
	 > Let user specify number of admissions per category

</p>

		<?php
		echo '<p>' . __( 'Optionally, you can add a time limit on specific prices, if for example you are offering "early bird" pricing on registration, after which the price goes up. Enter a full date and time. Example:', 'rsvpmaker' ) . ' ' . date( 'Y-m-d' ) . '  23:59:00 or ' . date( 'F j, Y' ) . ' 11:59 pm ' . __( 'for midnight tonight', 'rsvpmaker' );

		if ( $rsvp_count_party ) {
			printf( '<p>%s</p>', __( 'You can also specify fields that should not be displayed depending on price selections. Example: <em>The meal options at a conference should be disabled for attendees who choose "workshop only" pricing, or the dinner options should be disabled for those who select the lunch only.</em>', 'rsvptoast' ) );
		}

		$hide = array();

		if ( isset( $custom_fields['_hiddenrsvpfields'][0] ) ) {
			$hide = unserialize( $custom_fields['_hiddenrsvpfields'][0] );
		}

		if ( isset( $custom_fields['_per'][0] ) ) {
			$per = unserialize( $custom_fields['_per'][0] );
		}

		if ( empty( $per['unit'][0] ) ) {
			$per            = array();
			$per['unit'][0] = __( 'Tickets', 'rsvpmaker' );
		}

		$defaultfields = array( 'first', 'last', 'email', 'phone', 'phone_type' );

		preg_match_all( '/(textfield|selectfield|radio|checkbox)="([^"]+)"/', $rsvp_form, $matches );

		$newfields = array_diff( $matches[2], $defaultfields );

		echo '<div id="priceper">';

		$start = 1;

		foreach ( $per['unit'] as $i => $value ) {

			$start = $i + 1;

			?>

<div class="priceblock" id="block_<?php echo esc_attr($i); ?>">

<div class="pricelabel"><?php esc_html_e( 'Units', 'rsvpmaker' ); ?>:</div><div class="pricevalue"><input name="unit[<?php
									if ( isset( $i ) ) {
										echo esc_attr($i);}
									?>]" value="<?php
			if ( isset( $per['unit'][ $i ] ) ) {
				echo esc_attr( $per['unit'][ $i ] );}
			?>" /></div>

<div class="pricelabel">@ <?php esc_html_e( 'Price', 'rsvpmaker' ); ?>:</div><div class="pricevalue"><input name="price[<?php
if ( isset( $i ) ) {
	echo esc_attr($i);}
?>]" value="<?php
			if ( isset( $per['price'][ $i ] ) ) {
				echo esc_attr( $per['price'][ $i ] );}
			?>" /> 
			<?php
			if ( isset( $rsvp_options['paypal_currency'] ) ) {
				echo esc_html($rsvp_options['paypal_currency']);}
			?>
	</div>

<div class="pricelabel"><?php esc_html_e( 'Deadline (optional)', 'rsvpmaker' ); ?>:</div><div class="pricevalue"><input name="price_deadline[<?php
									if ( isset( $i ) ) {
										echo esc_attr($i);}
									?>]" value="<?php
			if ( isset( $per['price_deadline'][ $i ] ) ) {
				echo rsvpmaker_date( 'Y-m-d H:i:s', (int) $per['price_deadline'][ $i ] );}
			?>" placeholder="<?php echo date( 'Y-m-d 23:59:00' ); ?>" /></div>

	<div class="pricelabel"><?php esc_html_e( 'Multiple Admissions', 'rsvpmaker' ); ?>:</div><div class="pricevalue">
	<input name="price_multiple[<?php
										if ( isset( $i ) ) {
											echo esc_attr($i);}
										?>]" value="<?php
			if ( isset( $per['price_multiple'][ $i ] ) ) {
				echo (int) $per['price_multiple'][ $i ];
			} else {
				echo 1;
			}
			?>" /><br /><em><?php echo __( 'Example: If the price is for a table of 8, enter "8"', 'rsvpmaker' ); ?></em></div>

			<?php

			if ( $rsvp_count_party && ! empty( $newfields ) ) {

				foreach ( $newfields as $field ) {

					if ( isset( $hide[ $i ] ) && is_array( $hide[ $i ] ) && in_array( $field, $hide[ $i ] ) ) {

							$showcheck = '';

							$hidecheck = ' checked="checked" ';

					} else {

							$showcheck = ' checked="checked" ';

							$hidecheck = '';

					}

						printf( '<div class="pricelabel">%s:</div><div class="pricevalue"><input type="radio" name="showhide[%d][%s]" value="0" %s /> Show <input type="radio" name="showhide[%d][%s]" value="1" %s /> Hide</div>', $field, $i, $field, $showcheck, $i, $field, $hidecheck );

				}
			}

			?>

</div>

			<?php

		}

		$pad = ( $start < 3 ) ? 5 : 1;

		for ( $i = $start; $i < ( $start + $pad ); $i++ ) {

			$starterblanks = $i + 1;

			?>

<div class="priceblock" id="block_<?php echo esc_attr($i); ?>">

<div class="pricelabel"><?php esc_html_e( 'Units', 'rsvpmaker' ); ?>:</div><div class="pricevalue"><input name="unit[<?php
									if ( isset( $i ) ) {
										echo esc_attr($i);}
									?>]" value="<?php
			if ( isset( $per['unit'][ $i ] ) ) {
				echo esc_attr( $per['unit'][ $i ] );}
			?>" /></div>

<div class="pricelabel">@ <?php esc_html_e( 'Price', 'rsvpmaker' ); ?>:</div><div class="pricevalue">
<input name="price[<?php
									if ( isset( $i ) ) {
										echo esc_attr($i);}
									?>]" value="
			<?php
			if ( isset( $per['price'][ $i ] ) ) {
				echo esc_attr( $per['price'][ $i ] );}
			?>
	" /> 
			<?php
			if ( isset( $rsvp_options['paypal_currency'] ) ) {
				echo esc_html($rsvp_options['paypal_currency']);}
			?>
	</div>

<div class="pricelabel"><?php esc_html_e( 'Deadline (optional)', 'rsvpmaker' ); ?>:</div><div class="pricevalue"><input name="price_deadline[<?php
									if ( isset( $i ) ) {
										echo esc_attr($i);}
									?>]" value="<?php
			if ( isset( $per['price_deadline'][ $i ] ) ) {
				echo rsvpmaker_date( 'Y-m-d H:i:s', (int) $per['price_deadline'][ $i ] );}
			?>" placeholder="<?php echo date( 'Y-m-d 23:59:00' ); ?>" /></div>

	<div class="pricelabel"><?php esc_html_e( 'Multiple Admissions', 'rsvpmaker' ); ?>:</div><div class="pricevalue">
	<input name="price_multiple[<?php
										if ( isset( $i ) ) {
											echo esc_attr($i);}
										?>]" value="<?php
			if ( isset( $per['price_multiple'][ $i ] ) ) {
				echo (int) $per['price_multiple'][ $i ];
			} else {
				echo 1;
			}
			?>" /><br /><em><?php echo __( 'Example: If the price is for a table of 8, enter "8"', 'rsvpmaker' ); ?></em>	

	</div>

			<?php

			if ( $rsvp_count_party && ! empty( $newfields ) ) {

				foreach ( $newfields as $field ) {

						printf( '<div class="pricelabel">%s: </div> <div class="pricevalue"> <input type="radio" name="showhide[%d][%s]" value="0" checked="checked" /> Show <input type="radio" name="showhide[%d][%s]" value="1" /> Hide</div>', $field, $i, $field, $i, $field );

				}
			}

			?>

</div>

			<?php

		}

		echo '</div>';

		?>

<p><a id="add_blanks" href="#">+ <?php esc_html_e( '', 'rsvpmaker' ); ?>More Prices</a></p>

	

	<h3><?php esc_html_e( 'Coupon Codes', 'rsvpmaker' ); ?></h3>

	<p><?php esc_html_e( 'Optional: Set one or more codes for a discounted fee or a percent off the total.', 'rsvpmaker' ); ?></p>

		<?php

		$coupon_codes = get_post_meta( $post->ID, '_rsvp_coupon_code' );

		if ( empty( $coupon_codes ) ) {

			$coupon_codes = array();

			$coupon_methods = array( 'amount' );

			$coupon_discounts = array();

		} else {

			$coupon_methods = get_post_meta( $post->ID, '_rsvp_coupon_method' );

			$coupon_methods[] = 'amount'; // gives us one blank row

			$coupon_discounts = get_post_meta( $post->ID, '_rsvp_coupon_discount' );

		}

		foreach ( $coupon_methods as $index => $coupon_method ) {

			$coupon_code = ( isset( $coupon_codes[ $index ] ) ) ? $coupon_codes[ $index ] : '';

			$coupon_discount = ( isset( $coupon_discounts[ $index ] ) ) ? $coupon_discounts[ $index ] : '';

			?>

	<p><?php esc_html_e( 'Coupon Code', 'rsvpmaker' ); ?> <input type="text" name="coupon_code[]" value="<?php echo esc_attr( $coupon_code ); ?>" /> <?php esc_html_e( 'Method', 'rsvpmaker' ); ?>: <select name="coupon_method[]"><option value="amount" 
				 <?php
					if ( $coupon_method == 'amount' ) {
						echo 'selected="selected"';}
					?>
		 >Discounted Fee</option><option value="percent" 
			<?php
			if ( $coupon_method == 'percent' ) {
					echo 'selected="selected"';}
			?>
		 >Percent Off</option></select> <?php esc_html_e( 'Discount', 'rsvpmaker' ); ?>: <input type="text" name="coupon_discount[]" value="<?php echo esc_attr( $coupon_discount ); ?>" /> <br /></p>

			<?php

		}

		?>

	<div id="morecodes"></div>

<p><a id="add_codes" href="#">+ <?php esc_html_e( 'More Codes', 'rsvpmaker' ); ?></a></p>

<script type="text/javascript">	

jQuery(document).ready(function($) {

var blankcount = <?php echo esc_attr( $starterblanks ); ?>;

var lastblank = blankcount - 1;

var blank = $('#block_' + lastblank).html();

$('#add_blanks').click(function(event){

	event.preventDefault();

var newblank = '<' + 'div class="priceblock" id="blank_'+blankcount+'">' +

	blank.replace(/\[[0-9]+\]/g,'['+blankcount+']') +

	'<' + '/div>';

blankcount++;

$('#priceper').append(newblank);

});



$('#add_codes').click(function(event){

	event.preventDefault();

var newblank = '<p><?php esc_html_e( 'Coupon Code', 'rsvpmaker' ); ?> <input type="text" name="coupon_code[]" value="" /> Method: <select name="coupon_method[]"><option value="amount" selected="selected" >Discounted Fee</option><option value="percent"  >Percent Off</option></select> Discount: <input type="text" name="coupon_discount[]" value="" /> </p>';

$('#morecodes').append(newblank);

});





});

</script>

		<?php

		if ( isset( $_GET['debug'] ) ) {

			$defaultfields = array( 'first', 'last', 'email', 'phone', 'phone_type' );

			preg_match_all( '/(textfield|selectfield|radio|checkbox)="([^"]+)"/', $rsvp_form, $matches );

			$newfields = array_diff( $matches[2], $defaultfields );

		}

		if ( isset( $_GET['showmeta'] ) ) {

			echo '<pre>';

			print_r( $custom_fields );

			echo '</pre>';

		}

		?>

</div><!-- end rsvpdetails -->

</div>

		<?php

	}
} // end rsvp admin ui

function ajax_rsvp_email_lookup( $email, $event ) {

	$p = get_permalink( $event );

	if ( ! is_email( $email ) ) {

		return;
	}

	global $wpdb;

	$wpdb->show_errors();

	$sql = $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'rsvpmaker WHERE email LIKE %s AND event=%d', $email, $event );

	$results = $wpdb->get_results( $sql );

	if ( $results ) {

		$out = '<div class="previous_rsvp_prompt">' . __( 'Did you RSVP previously?', 'rsvpmaker' ) . '</div>';

		foreach ( $results as $row ) {

			$out .= 'RSVP ';

			$out .= ( $row->yesno ) ? __( 'YES', 'rsvpmaker' ) : __( 'NO', 'rsvpmaker' );

			$out .= esc_html( ' ' . $row->first . ' ' . $row->last );

			$sql = $wpdb->prepare( 'SELECT count(*) FROM ' . $wpdb->prefix . 'rsvpmaker WHERE master_rsvp=%d', intval( $row->id ) );

			$guests = $wpdb->get_var( $sql );

			if ( $guests ) {

				$out .= ' + ' . esc_html( $guests ) . ' ' . __( 'guests', 'rsvpmaker' );
			}

			return sprintf(
				'<div><a href="%s">%s</a> %s</div>',
				add_query_arg(
					array(
						'e'      => $row->email,
						'update' => $row->id,
					),
					$p
				),
				__( 'Update', 'rsvpmaker' ),
				$out
			);

		}
	} else {
		return;
	}

}



function rsvp_form_setup_form( $rsvp_form ) {

	$hidden = ( strpos( $rsvp_form, 'hidden="email"' ) );

	$email_list_ok = ( strpos( $rsvp_form, 'checkbox="email_list_ok"' ) );

	preg_match( '/textfield="([^"]+)"/', $rsvp_form, $match );

	$emailfirst = ( $match[1] == 'email' ) ? ' checked="checked" ' : '';

	?>

<div id="rsvp-dialog-form" title="Form setup">

  <p><?php esc_html_e( 'First Name, Last Name, Email (required)', 'rsvpmaker' ); ?> Display options: <select id="name_email_hidden" name="name_email_hidden">

	  <option value="email_first" 
	  <?php
		if ( $emailfirst ) {
			echo 'selected="selected"';}
		?>
			 ><?php esc_html_e( 'email, then name', 'rsvpmaker' ); ?></option>

	  <option value="name_first" 
	  <?php
		if ( ! $emailfirst && ! $hidden ) {
			echo 'selected="selected"';}
		?>
			 ><?php esc_html_e( 'name, then email', 'rsvpmaker' ); ?></option>

	  <option value="hidden" 
	  <?php
		if ( $hidden ) {
			echo 'selected="selected"';}
		?>
			 ><?php esc_html_e( 'hidden (use with login required)', 'rsvpmaker' ); ?></option>

	  </select>

<br /><?php esc_html_e( 'For radio buttons or select fields, use the format Label:option 1, option 2', 'rsvpmaker' ); ?> (<em><?php esc_html_e( 'Meal:Steak,Chicken,Vegitarian', 'rsvpmaker' ); ?></em>)</p> 

	<fieldset>

	<?php

	preg_match_all( '/(\[.+\])/', $rsvp_form, $matches );

	preg_match( '/max_party="(\d+")/', $rsvp_form, $maxparty );

	$codes = implode( $matches[1] );

	$codes .= '[rsvpfield textfield=""][rsvpfield textfield=""][rsvpfield textfield=""]';

	echo do_shortcode( $codes );

	global $extrafield;

	printf( '<input type="hidden" id="extrafields" value="%s" />', $extrafield );

	$mp = ( empty( $maxparty[1] ) ) ? '' : $maxparty[1] - 1;

	?>

<p><input type="checkbox" name="guests" id="guests" value="1" 
	<?php
	if ( strpos( $rsvp_form, 'rsvpguests' ) ) {
		echo 'checked="checked"';}
	?>
	 /> <?php esc_html_e( 'Include guest form', 'rsvpmaker' ); ?> - <?php esc_html_e( 'up to', 'rsvpmaker' ); ?> <input type="text" name="maxguests" id="maxguests" value="<?php echo esc_attr($mp); ?>" size="2" /> <?php esc_html_e( ' guests (enter # or leave blank for no limit)', 'rsvpmaker' ); ?><br /> <input type="checkbox" name="note" id="note" value="1" 
	<?php
	if ( strpos( $rsvp_form, 'rsvpnote' ) ) {
		echo 'checked="checked"';}
	?>
	> <?php esc_html_e( 'Include notes field', 'rsvpmaker' ); ?> <input type="checkbox" name="emailcheckbox" id="emailcheckbox" value="1" 
	<?php
	if ( $email_list_ok ) {
		echo 'checked="checked"';}
	?>
	 > <?php esc_html_e( 'Include "Add me to email list" checkbox', 'rsvpmaker' ); ?></p>

<p><input type="checkbox" name="guests" id="guests" value="1" 
	<?php
	if ( strpos( $rsvp_form, 'rsvpguests' ) ) {
		echo 'checked="checked"';}
	?>
	 /> <?php esc_html_e( 'Include guest form', 'rsvpmaker' ); ?> - <?php esc_html_e( 'up to', 'rsvpmaker' ); ?> <input type="text" name="maxguests" id="maxguests" value="<?php echo esc_attr($mp); ?>" size="2" /> <?php esc_html_e( ' guests (enter # or leave blank for no limit)', 'rsvpmaker' ); ?><br /> <input type="checkbox" name="note" id="note" value="1" 
	<?php
	if ( strpos( $rsvp_form, 'rsvpnote' ) ) {
		echo 'checked="checked"';}
	?>
	> <?php esc_html_e( 'Include notes field', 'rsvpmaker' ); ?> <input type="checkbox" name="emailcheckbox" id="emailcheckbox" value="1" 
	<?php
	if ( $email_list_ok ) {
		echo 'checked="checked"';}
	?>
	 > <?php esc_html_e( 'Include "Add me to email list" checkbox', 'rsvpmaker' ); ?></p>

	  <!-- Allow form submission with keyboard without duplicating the dialog button -->

	  <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">

	</fieldset>

</div> 

	<?php

}



if ( ! function_exists( 'capture_email' ) ) {

	function capture_email( $rsvp ) {

		// placeholder function, may be overriden to sign person up for email list

		// or use this action, triggered by email_list_ok parameter in form

		if ( isset( $rsvp['email_list_ok'] ) && $rsvp['email_list_ok'] ) {

			do_action( 'rsvpmaker_email_list_okay', $rsvp );
		}

	}
} // end capture email



if ( ! function_exists( 'save_replay_rsvp' ) ) {

	function save_replay_rsvp() {

		global $wpdb;

		global $rsvp_options;

		global $rsvp_id;

		if ( isset( $_POST['replay_rsvp'] ) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {

			if ( get_magic_quotes_gpc() ) {

				$_POST = array_map( 'stripslashes_deep', $_POST );
			}

			$req_uri = trim( sanitize_text_field($_POST['replay_rsvp']) );

			$req_uri .= ( strpos( $req_uri, '?' ) ) ? '&' : '?';

			// sanitize input

			foreach ( $_POST['profile'] as $name => $value ) {

				$rsvp[ $name ] = sanitize_text_field( $value );
			}

			if ( isset( $_POST['note'] ) ) {

				$note = sanitize_text_field( $_POST['note'] );

			} else {
				$note = '';
			}

			$answer = 'YES';

			$event = ( ! empty( $_POST['event'] ) ) ? (int) $_POST['event'] : 0;

			if ( ! $event ) {

				die( 'Event ID not set' );
			}

			// page hasn't loaded yet, so retrieve post variables based on event

			$post = get_post( $event );

			// get rsvp_to

			$custom_fields = get_post_custom( $post->ID );

			$rsvp_to = $custom_fields['_rsvp_to'][0];

			$rsvp_confirm = rsvp_get_confirm( $post->ID );

			// if permalinks are not turned on, we need to append to query string not add our own ?

			if ( ! is_admin() && isset( $custom_fields['_rsvp_captcha'][0] ) && $custom_fields['_rsvp_captcha'][0] ) {

				if ( ! isset( $_SESSION['captcha_key'] ) ) {

					session_start();
				}

				if ( $_SESSION['captcha_key'] != md5( $_POST['captcha'] ) ) {

					header( 'Location: ' . $req_uri . '&err=' . urlencode( 'security code not entered correctly! Please try again.' ) );

					exit();

				}
			}

			if ( ! is_admin() && ! empty( $rsvp_options['rsvp_recaptcha_site_key'] ) && ! empty( $rsvp_options['rsvp_recaptcha_secret'] ) ) {

				if ( ! rsvpmaker_recaptcha_check( $rsvp_options['rsvp_recaptcha_site_key'], $rsvp_options['rsvp_recaptcha_secret'] ) ) {

					header( 'Location: ' . $req_uri . '&err=' . urlencode( 'failed recaptcha test' ) );

					exit();

				}
			}

			if ( isset( $_POST['required'] ) || empty( $rsvp['email'] ) ) {

				$required = explode( ',', $_POST['required'] );

				if ( ! in_array( 'email', $required ) ) {

					$required[] = 'email';
				}

				$missing = '';

				foreach ( $required as $r ) {
					$r = sanitize_text_field($r);
					if ( empty( $rsvp[ $r ] ) ) {

						$missing .= $r . ' ';
					}
				}

				if ( $missing != '' ) {

					header( 'Location: ' . $req_uri . '&err=' . urlencode( 'missing required fields: ' . esc_attr( $missing ) ) );

					exit();

				}
			}

			if ( preg_match_all( '/http/', $_POST['note'], $matches ) > 2 ) {

				header( 'Location: ' . $req_uri . '&err=Invalid input' );

				exit();

			}

			if ( preg_match( '|//|', implode( ' ', $rsvp ) ) ) {

				header( 'Location: ' . $req_uri . '&err=Invalid input' );

				exit();

			}

			if ( isset( $rsvp['email'] ) ) {

				// assuming the form includes email, test to make sure it's a valid one

				if ( ! apply_filters( 'rsvmpmaker_spam_check', $rsvp['email'] ) ) {

					header( 'Location: ' . $req_uri . '&err=' . urlencode( 'Invalid input.' ) );

					exit();

				}

				if ( ! filter_var( $rsvp['email'], FILTER_VALIDATE_EMAIL ) ) {

					header( 'Location: ' . $req_uri . '&err=' . urlencode( 'Invalid email.' ) );

					exit();

				}
			}

			if ( isset( $_POST['onfile'] ) ) {

				$sql = $wpdb->prepare( 'SELECT details FROM ' . $wpdb->prefix . "rsvpmaker WHERE event='$event' AND email LIKE %s AND first LIKE %s AND last LIKE %s  ORDER BY id DESC", $rsvp['email'], $rsvp['first'], $rsvp['last'] );

				$details = $wpdb->get_var( $sql );

				if ( $details ) {

					$contact = unserialize( $details );

				} else {
					$contact = rsvpmaker_profile_lookup( $rsvp['email'] );
				}

				if ( $contact ) {

					foreach ( $contact as $name => $value ) {

						if ( ! isset( $rsvp[ $name ] ) ) {

							$rsvp[ $name ] = $value;
						}
					}
				}
			}

			global $current_user; // if logged in

			$future = is_rsvpmaker_future( $event, 1 ); // if start time in the future (or within one hour)

			$yesno = ( $future ) ? 1 : 2;// 2 for replay

			$rsvp_sql = $wpdb->prepare( ' SET first=%s, last=%s, email=%s, yesno=%d, event=%d, note=%s, details=%s, participants=%d, user_id=%d ', $rsvp['first'], $rsvp['last'], $rsvp['email'], $yesno, $event, $note, serialize( $rsvp ), 1, $current_user->ID );

			capture_email( $rsvp );

			$rsvp_id = ( isset( $_POST['rsvp_id'] ) ) ? (int) $_POST['rsvp_id'] : 0;

			if ( $rsvp_id ) {

				$rsvp_sql = 'UPDATE ' . $wpdb->prefix . 'rsvpmaker ' . $rsvp_sql . " WHERE id=$rsvp_id";

				$wpdb->show_errors();

				$wpdb->query( $rsvp_sql );

			} else {

				$rsvp_sql = 'INSERT INTO ' . $wpdb->prefix . 'rsvpmaker ' . $rsvp_sql;

				$wpdb->show_errors();

				$wpdb->query( $rsvp_sql );

				$rsvp_id = $wpdb->insert_id;

				$sql = 'SELECT date FROM ' . $wpdb->prefix . "rsvpmaker_event WHERE event=$event ";

				if ( empty( $wpdb->get_var( $sql ) ) ) {
					$sql = $wpdb->prepare( 'INSERT INTO  ' . $wpdb->prefix . 'rsvpmaker_event SET event=%d, post_title=%s, date=%s', $event, $post->post_title, get_rsvp_date( $event ) );
					$wpdb->query( $sql );
				}
			}

			setcookie( 'rsvp_for_' . $event, $rsvp_id, time() + ( 60 * 60 * 24 * 90 ), '/', sanitize_text_field($_SERVER['SERVER_NAME']) );

			if ( $future ) {

				$cleanmessage = '';

				foreach ( $rsvp as $name => $value ) {

					$label = get_post_meta( $event, 'rsvpform' . $name, true );

					if ( $label ) {

						$name = $label;
					}

					$cleanmessage .= $name . ': ' . $value . "\n";// labels from form

				}

				$subject = __( 'You registered for ', 'rsvpmaker' ) . ' ' . esc_html( $post->post_title );

				if ( ! empty( $_POST['note'] ) ) {

					$cleanmessage .= 'Note: ' . sanitize_textarea_field( stripslashes( $_POST['note'] ) );
				}

				rsvp_notifications( $rsvp, $rsvp_to, $subject, $cleanmessage, $rsvp_confirm );

			} else {

				// cron for follow up messages

				$sql = "SELECT * 

FROM  `$wpdb->postmeta` 

WHERE meta_key REGEXP '_rsvp_reminder_msg_[0-9]{1,2}'

AND  `post_id` = " . $event;

				$results = $wpdb->get_results( $sql );

				// $msg = var_export($results,true);

				if ( $results ) {

					foreach ( $results as $row ) {

						$parts = explode( '_msg_', $row->meta_key );

						$hours = $parts[1];

						rsvpmaker_replay_cron( $event, $rsvp_id, $hours );

					}
				}
			}

			$landing_id = (int) $_POST['landing_id'];

			$passcode = get_post_meta( $landing_id, '_webinar_passcode', true );

			$landing_permalink = $req_uri . '&webinar=' . $passcode . '&e=' . $rsvp['email'];

			header( 'Location: ' . $landing_permalink );

			exit();

		}

	}
} // end save replay rsvp



if ( ! function_exists( 'save_rsvp' ) ) {

	function save_rsvp() {

		global $wpdb;

		global $rsvp_options;

		global $post;

		global $rsvp_id;

		global $rsvpdata;

		$rsvp_id = ( isset( $_POST['rsvp_id'] ) ) ? (int) $_POST['rsvp_id'] : 0;

		$cleanmessage = '';

		if ( isset( $_POST['withdraw'] ) ) {

			foreach ( $_POST['withdraw'] as $withdraw_id ) {

				$wpdb->query( 'UPDATE ' . $wpdb->prefix . "rsvpmaker SET yesno=0 WHERE id=".intval($withdraw_id) );

			}
		}

		if ( isset( $_POST['yesno'] ) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {

			$_POST = stripslashes_deep( $_POST );

			// sanitize input

			foreach ( $_POST['profile'] as $name => $value ) {

				$rsvp[ $name ] = sanitize_text_field( $value );
			}

			if ( isset( $_POST['note'] ) ) {

				$note = sanitize_text_field( $_POST['note'] );

			} else {
				$note = '';
			}

			$yesno = (int) $_POST['yesno'];

			$answer = ( $yesno ) ? __( 'YES', 'rsvpmaker' ) : __( 'NO', 'rsvpmaker' );

			$event = ( ! empty( $_POST['event'] ) ) ? (int) $_POST['event'] : 0;

			if ( ! $event ) {

				die( 'Event ID not set' );
			}
			if(!get_post_meta($event,'_rsvp_on'))
				return;

			// page hasn't loaded yet, so retrieve post variables based on event

			$post = get_post( $event );

			// get rsvp_to

			$custom_fields = get_post_custom( $post->ID );

			$rsvp_to = $custom_fields['_rsvp_to'][0];

			$rsvp_confirm = rsvp_get_confirm( $post->ID );

			$rsvp_max = $custom_fields['_rsvp_max'][0];

			$count = $wpdb->get_var( 'SELECT count(*) FROM ' . $wpdb->prefix . "rsvpmaker WHERE event=$event AND yesno=1" );

			// if permalinks are not turned on, we need to append to query string not add our own ?

			$guest_sql = array();

			$guest_text = array();

			if ( is_admin() ) {

				$req_uri = admin_url( 'edit.php?page=rsvp&post_type=rsvpmaker&event=' . $event );

			} else {

				$req_uri = site_url( '?post_type=rsvpmaker&p=' . $event . '&e=' . $rsvp['email'] );

			}

			if ( ! is_admin() && isset( $custom_fields['_rsvp_captcha'][0] ) && $custom_fields['_rsvp_captcha'][0] ) {

				if ( ! isset( $_SESSION['captcha_key'] ) ) {

					session_start();
				}

				if ( $_SESSION['captcha_key'] != md5( $_POST['captcha'] ) ) {

					header( 'Location: ' . $req_uri . '&err=' . urlencode( 'security code not entered correctly! Please try again.' ) );

					exit();

				}
			}

			if ( ! is_admin() && ! empty( $rsvp_options['rsvp_recaptcha_site_key'] ) && ! empty( $rsvp_options['rsvp_recaptcha_secret'] ) ) {

				if ( ! rsvpmaker_recaptcha_check( $rsvp_options['rsvp_recaptcha_site_key'], $rsvp_options['rsvp_recaptcha_secret'] ) ) {

					header( 'Location: ' . $req_uri . '&err=' . urlencode( 'failed recaptcha test' ) );

					exit();

				}
			}

			if ( isset( $_POST['required'] ) || empty( $rsvp['email'] ) ) {

				$required = explode( ',', $_POST['required'] );

				if ( ! in_array( 'email', $required ) ) {

					$required[] = 'email';
				}

				$missing = '';

				foreach ( $required as $r ) {
					$r = sanitize_text_field($r);
					if ( empty( $rsvp[ $r ] ) ) {
						$missing .= $r . ' ';
					}
				}

				if ( $missing != '' ) {

					header( 'Location: ' . $req_uri . '&err=' . urlencode( 'missing required fields: ' . $missing ) );

					exit();

				}
			}

			if ( ! isset( $rsvp['first'] ) ) {

				$rsvp['first'] = '';
			}

			if ( ! isset( $rsvp['last'] ) ) {

				$rsvp['last'] = '';
			}

			if ( isset( $_POST['note'] ) && preg_match_all( '/http/', $_POST['note'], $matches ) > 2 ) {

				header( 'Location: ' . $req_uri . '&err=Invalid input' );

				exit();

			}

			if ( preg_match( '|//|', implode( ' ', $rsvp ) ) ) {

				header( 'Location: ' . $req_uri . '&err=Invalid input' );

				exit();

			}

			if ( isset( $rsvp['email'] ) ) {

				// assuming the form includes email, test to make sure it's a valid one

				if ( ! apply_filters( 'rsvmpmaker_spam_check', $rsvp['email'] ) ) {

					header( 'Location: ' . $req_uri . '&err=' . urlencode( 'Invalid input.' ) );

					exit();

				}

				if ( ! filter_var( $rsvp['email'], FILTER_VALIDATE_EMAIL ) ) {

					header( 'Location: ' . $req_uri . '&err=' . urlencode( 'Invalid email.' ) );

					exit();

				}
			}

			if ( empty( $rsvp_id ) ) {

				$duplicate_check = $wpdb->get_var( 'SELECT id FROM ' . $wpdb->prefix . "rsvpmaker WHERE email='" . $rsvp['email'] . "' AND first='" . $rsvp['first'] . "' AND last='" . $rsvp['last'] . "' AND event=$post->ID " );

				if ( $duplicate_check ) {

					rsvpmaker_debug_log( $rsvp, 'duplicate check' );

					$rsvp_id = $duplicate_check;

				}
			}

			if ( $rsvp_id ) {

				$sql = 'SELECT details FROM ' . $wpdb->prefix . "rsvpmaker WHERE email !='' AND id=" . $rsvp_id;

				$details = $wpdb->get_var( $sql );

				if ( $details ) {

					$contact = unserialize( $details );

					if ( is_array( $contact ) ) {

						foreach ( $contact as $name => $value ) {

							if ( ! isset( $rsvp[ $name ] ) ) {

								$rsvp[ $name ] = $value;
							}
						}
					}
				} else {
					$rsvp_id = null;
				}
			}

			$rsvp['payingfor'] = '';

			if ( isset( $_POST['payingfor'] ) && is_array( $_POST['payingfor'] ) ) {

				$rsvp['total'] = 0;

				$participants = 0;

				foreach ( $_POST['payingfor'] as $index => $value ) {

					$value = (int) $value;

					$unit = sanitize_text_field( $_POST['unit'][ $index ] );

					$price = (float) $_POST['price'][ $index ];

					$price = rsvpmaker_check_coupon_code( $price );

					$cost = $value * $price;

					$rsvp['payingfor'] .= '<div class="payingfor">' . "$value $unit @ " . number_format( $price, 2, $rsvp_options['currency_decimal'], $rsvp_options['currency_thousands'] ) . ' ' . $rsvp_options['paypal_currency'] . '</div>';

					$rsvp['total'] += $cost;

					$participants += $value;

				}
			}

			if ( isset( $_POST['timeslot'] ) && is_array( $_POST['timeslot'] ) ) {

				$participants = $rsvp['participants'] = (int) $_POST['participants'];

				$rsvp['timeslots'] = ''; // ignore anything retrieved from prev rsvps

				foreach ( $_POST['timeslot'] as $slot ) {

					if ( ! empty( $rsvp['timeslots'] ) ) {

						$rsvp['timeslots'] .= ', ';
					}

					$rsvp['timeslots'] .= rsvpmaker_date( 'g:i A', sanitize_text_field($slot) );

				}
			}

			if ( ! isset( $participants ) && $yesno ) {

				// if they didn't specify # of participants (paid tickets or volunteers), count the host plus guests

				$participants = 1;

				if ( ! empty( $_POST['guest']['first'] ) ) {

					foreach ( $_POST['guest']['first'] as $first ) {

						if ( $first ) {

							$participants++;
						}
					}
				}

				if ( isset( $_POST['guestdelete'] ) ) {

					$participants -= sizeof( $_POST['guestdelete'] );
				}
			}

			if ( ! $yesno ) {

				$participants = 0; // if they said no, they don't count
			}

			if ( $participants && isset( $_POST['guest_count_price'] ) ) {

				$cleanmessage .= '<div>' . __( 'Participants', 'rsvpmaker' ) . ": $participants</div>\n";

				$index = (int) $_POST['guest_count_price'];

				$per = unserialize( $custom_fields['_per'][0] );

				$price = $per['price'][ $index ];

				$unit = $per['unit'][ $index ];

				$multiple = (int) ( isset( $per['price_multiple'][ $index ] ) ) ? $per['price_multiple'][ $index ] : 1;

				if ( $multiple == 1 ) { // coupon codes not applied to multiple admission "table" pricing

					$price = rsvpmaker_check_coupon_code( $price );
				}

				if ( $multiple > 1 ) {

					$rsvp['total'] = $price;

					if ( $participants > $multiple ) {

						$multiple_warning = '<div style="color:red;">' . "Warning: party of $participants exceeds table size" . '</div>';

					} else {

						$padguests = $multiple - $participants;

						$participants = $multiple;

					}
				} else {
					$rsvp['total'] = $price * $participants;
				}

				$rsvp['payingfor'] .= "$participants $unit @ " . number_format( $price, 2, $rsvp_options['currency_decimal'], $rsvp_options['currency_thousands'] );

				$rsvp['pricechoice'] = $index;

			}

			global $current_user; // if logged in

			global $rsvpmaker_coupon_message;

			if ( ! empty( $rsvpmaker_coupon_message ) ) {

				$rsvp['coupon'] = $rsvpmaker_coupon_message;
			}

			$rsvp_sql = $wpdb->prepare( ' SET first=%s, last=%s, email=%s, yesno=%d, event=%d, note=%s, details=%s, participants=%d, user_id=%d ', $rsvp['first'], $rsvp['last'], $rsvp['email'], $yesno, $event, $note, serialize( $rsvp ), $participants, $current_user->ID );

			capture_email( $rsvp );

			if ( $rsvp_id ) {

				$rsvp_sql = 'UPDATE ' . $wpdb->prefix . 'rsvpmaker ' . $rsvp_sql . " WHERE id=$rsvp_id";

				$wpdb->show_errors();

				$wpdb->query( $rsvp_sql );

			} else {

				$count++;

				if ( $rsvp_max && ( $count > $rsvp_max ) ) {

					$cleanmessage .= '<div style="color:red;">' . __( 'Max RSVP count limit reached, entry not added for:', 'rsvpmaker' ) . "\n" . $rsvp['first'] . ' ' . $rsvp['last'] . '</div>';

					$rsvp_id = 0;

				} else {

					$rsvp_sql = 'INSERT INTO ' . $wpdb->prefix . 'rsvpmaker ' . $rsvp_sql;

					$wpdb->show_errors();

					$wpdb->query( $rsvp_sql );

					$rsvp_id = $wpdb->insert_id;

					$sql = 'SELECT date FROM ' . $wpdb->prefix . "rsvpmaker_event WHERE event=$event ";

					if ( empty( $wpdb->get_var( $sql ) ) ) {

						$sql = $wpdb->prepare( 'INSERT INTO  ' . $wpdb->prefix . 'rsvpmaker_event SET event=%d, post_title=%s, date=%s', $event, $post->post_title, get_rsvp_date( $event ) );
						//rsvpmaker_debug_log( $sql, 'save_rsvp rsvpmaker_event' );
						$wpdb->query( $sql );

					}
				}
			}

			if ( ! empty( $rsvp_options['send_payment_reminders'] ) && isset( $price ) && ( $price > 0 ) ) {

				rsvpmaker_payment_reminder_cron( $rsvp_id );
			}

			setcookie( 'rsvp_for_' . $post->ID, $rsvp_id, time() + 60 * 60 * 24 * 90, '/', sanitize_text_field($_SERVER['SERVER_NAME']) );

			setcookie( 'rsvpmaker', $rsvp_id, time() + 60 * 60 * 24 * 90, '/', sanitize_text_field($_SERVER['SERVER_NAME']) );

			if ( isset( $_POST['timeslot'] ) ) {

				$participants = (int) $_POST['participants'];

				// clear previous response, if any

				$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "rsvp_volunteer_time WHERE rsvp=$rsvp_id" );

				foreach ( $_POST['timeslot'] as $slot ) {

					$slot = (int) $slot;

					$sql = $wpdb->prepare( 'INSERT INTO ' . $wpdb->prefix . 'rsvp_volunteer_time SET time=%d, event=%d, rsvp=%d, participants=%d', $slot, $post->ID, $rsvp_id, $participants );

					$wpdb->query( $sql );

				}
			}

			// get start date

			$rows = get_rsvp_dates( $event );

			$row = $rows[0];

			$date = rsvpdate_shortcode( array( 'format' => '%b %e' ) );

			foreach ( $rsvp as $name => $value ) {

				$label = get_post_meta( $post->ID, 'rsvpform' . $name, true );

				if ( $label ) {

					$name = $label;
				}

				if ( ! empty( $value ) ) {

					$cleanmessage .= $name . ': ' . $value . "\n";// labels from rsvp form
				}
			}

			$guestof = (empty($rsvp['first'])) ? '' : $rsvp['first'] . ' ';
			$guestof .=  (empty($rsvp['last'])) ? '' : $rsvp['last'];

			if ( isset( $_POST['guest']['first'] ) ) {

				foreach ( $_POST['guest']['first'] as $index => $first ) {

					$last = ( empty( $_POST['guest']['last']) || !empty($_POST['guest']['last'][ $index ]) ) ? '' : sanitize_text_field($_POST['guest']['last'][ $index ]);
					if ( ! empty( $first ) ) {
						$guest_sql[ $index ] = $wpdb->prepare( ' SET event=%d, yesno=%d, `master_rsvp`=%d, `guestof`=%s, `first` = %s, `last` = %s', $event, $yesno, $rsvp_id, $guestof, $first, $last );
						$guest_text[ $index ] = sprintf( "Guest: %s %s\n", $first, $last );
						$guest_list[ $index ] = sprintf( '%s %s', $first, $last );
						$lastguest = $index;
					}
				}
			}

			if ( ! empty( $padguests ) ) {

				for ( $i = 0; $i < $padguests; $i++ ) {

					$index = $i + 100;

					$tbd = $i + 1;

					$guest_sql[ $index ] = $wpdb->prepare( ' SET event=%d, yesno=%d, `master_rsvp`=%d, `guestof`=%s, `first` = %s, `last` = %s', $event, $yesno, $rsvp_id, $guestof, 'Placeholder', 'Guest TBD ' . $tbd );

					$guest_text[ $index ] = sprintf( "Guest: %s %s\n", 'Placeholder', 'Guest TBD ' . $tbd );

					$guest_list[ $index ] = sprintf( '%s %s', 'Placeholder', 'Guest TBD ' . $tbd );

					$newrow[ $index ]['first'] = 'Placeholder';

					$newrow[ $index ]['last'] = 'Guest TBD ' . $tbd;

				}
			}

			if ( sizeof( $guest_sql ) ) {
				foreach ( $_POST['guest'] as $field => $column ) {
					foreach ( $column as $index => $value ) {
						if ( empty( $guest_text[ $index ] ) ) {
							$guest_text[ $index ] = '';
						}
						if ( isset( $guest_sql[ $index ] ) ) {
							$newrow[ $index ][ $field ] = $value;
							if ( ( $field != 'first' ) && ( $field != 'last' ) && ( $field != 'id' ) ) {
								$guest_text[ $index ] .= sprintf( "%s: %s\n", $field, $value );
								$guestlast = (empty($_POST['guest']['last'][ $index ])) ? '' : sanitize_text_field($_POST['guest']['last'][ $index ]);
								$guest_list[ $index ] = sprintf( '%s %s', $first, $guestlast );
							}
						}
					}
				}
			}

			if ( sizeof( $guest_sql ) ) {

				foreach ( $guest_sql as $index => $sql ) {

					$sql .= $wpdb->prepare( ', `details`=%s ', serialize( $newrow[ $index ] ) );

					$id = ( isset( $_POST['guest']['id'][ $index ] ) ) ? (int) $_POST['guest']['id'][ $index ] : 0;

					if ( isset( $_POST['guestdelete'][ $id ] ) ) {

						$gd = (int) $_POST['guestdelete'][ $id ];

						$sql = 'DELETE FROM ' . $wpdb->prefix . 'rsvpmaker WHERE id=' . $gd;

						$guest_text[ $index ] = __( 'Deleted:', 'rsvpmaker' ) . "\n" . $guest_text[ $index ];

						$guest_list[ $index ] = __( 'Deleted:', 'rsvpmaker' ) . ' ' . $guest_list[ $index ];

						$wpdb->query( $sql );

					} elseif ( $id ) {

						$sql = 'UPDATE ' . $wpdb->prefix . 'rsvpmaker ' . $sql . ' WHERE id=' . $id;

						$wpdb->query( $sql );

					} else {

						$count++;

						if ( $rsvp_max && ( $count > $rsvp_max ) ) {

							$guest_text[ $index ] = '<div style="color:red;">' . __( 'Max RSVP count limit reached, entry not added for:', 'rsvpmaker' ) . "\n" . $guest_text[ $index ] . '</div>';

							$guest_list[ $index ] = '<div style="color:red;">' . __( 'Max RSVP count limit reached, entry not added for:', 'rsvpmaker' ) . "\n" . $guest_text[ $index ] . '</div>';

						} else {

							$sql = 'INSERT INTO ' . $wpdb->prefix . 'rsvpmaker ' . $sql;

							$wpdb->query( $sql );

						}
					}
				}
			}

			if ( ! empty( $guest_list ) ) {

				$cleanmessage .= __( 'Guests', 'rsvpmaker' ) . ': ' . implode( ', ', $guest_list );
			}

			if ( ! empty( $multiple_warning ) ) {

				$cleanmessage .= $multiple_warning;
			}

			if ( ! is_admin() ) {

				if ( ! empty( $_POST['note'] ) ) {

					$cleanmessage .= 'Note: ' . stripslashes( $_POST['note'] );
				}

				$include_event = get_post_meta( $post->ID, '_rsvp_confirmation_include_event', true );

				if ( $include_event ) {

					$embed = event_to_embed( $post->ID, $post, 'confirmation' );

					$cleanmessage .= "\n\n" . $embed['content'];

				}

				$rsvpdata['rsvpdetails'] = $cleanmessage;

				$rsvpdata['rsvpmessage'] = $rsvp_confirm; // confirmation message from editor

				// $rsvpdata["rsvptitle"] = $post->post_title;

				$rsvpdata['rsvpyesno'] = $answer;
				$rsvpdata['yesno'] = $yesno;
				$rsvpdata['rsvpdate'] = $date;

				$rsvp_options['rsvplink'] = get_rsvp_link( $post->ID );

				$rsvpdata['rsvpupdate'] = preg_replace( '/#rsvpnow">[^<]+/', '#rsvpnow">' . $rsvp_options['update_rsvp'], str_replace( '*|EMAIL|*', $rsvp['email'] . '&update=' . $rsvp_id, $rsvp_options['rsvplink'] ) );
				
				rsvp_notifications_via_template( $rsvp, $rsvp_to, $rsvpdata );

				// rsvp_notifications ($rsvp,$rsvp_to,$subject,$cleanmessage,$rsvp_confirm);

			}

			do_action( 'rsvp_recorded', $rsvp );

			header( 'Location: ' . $req_uri . '&rsvp=' . $rsvp_id . '#rsvpmaker_top' );

			exit();

		}

	}
} // end save rsvp



if ( ! function_exists( 'rsvp_notifications' ) ) {

	function rsvp_notifications( $rsvp, $rsvp_to, $subject, $message, $rsvp_confirm = '' ) {

		include_once 'rsvpmaker-ical.php';

		global $post;

		$message = wpautop( $message );

		$mail['html'] = $rsvp_confirm . "\n\n" . $message;

		global $rsvp_options;

		$mail['to'] = $rsvp_to;

		$mail['from'] = $rsvp['email'];

		$mail['fromname'] = $rsvp['first'] . ' ' . $rsvp['last'];

		$mail['subject'] = $subject;

		rsvpmaker_tx_email( $post, $mail );

		if ( isset( $post->ID ) ) { // not for replay

			$mail['ical'] = rsvpmaker_to_ical_email( $post->ID, $rsvp_to, $rsvp['email'] );
		}

		$mail['to'] = $rsvp['email'];

		$mail['from'] = $rsvp_to;

		$mail['fromname'] = get_bloginfo( 'name' );

		$mail['subject'] = 'Confirming ' . $subject;

		rsvpmaker_tx_email( $post, $mail );

	}
} // end rsvp notifications


if ( ! function_exists( 'admin_payment' ) ) {

	function admin_payment( $rsvp_id, $charge ) {

		global $wpdb;

		global $current_user;

		$event = (int) $_GET['event'];

		$paid = $charge;

		$paid_amounts = get_post_meta( $event, '_paid_' . $rsvp_id );

		if ( is_array( $paid_amounts ) ) {

			foreach ( $paid_amounts as $payment ) {

				$paid += $payment;
			}
		}

		$wpdb->query( 'UPDATE ' . $wpdb->prefix . "rsvpmaker SET amountpaid='$paid' WHERE id=$rsvp_id " );

		add_post_meta( $event, '_paid_' . $rsvp_id, $charge );

		delete_post_meta( $event, '_open_invoice_' . $rsvp_id );

		delete_post_meta( $event, '_invoice_' . $rsvp_id );

		$row = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . "rsvpmaker WHERE id=$rsvp_id ", ARRAY_A );

		$message = sprintf( '<p>%s ' . __( 'payment for', 'rsvpmaker' ) . ' %s %s ' . __( ' manually recorded by', 'rsvpmaker' ) . ' %s<br />' . __( 'Post ID', 'rsvpmaker' ) . ': %s<br />' . __( 'Time', 'rsvpmaker' ) . ': %s</p>', $charge, esc_attr( $row['first'] ), esc_attr( $row['last'] ), $current_user->display_name, $event, date( 'r' ) );

		add_post_meta( $event, '_paypal_log', $message );

		echo wp_kses_post($message);

	}
} // end admin payment

function rsvpmaker_localdate() {

	if ( empty( $_REQUEST['action'] ) || $_REQUEST['action'] != 'rsvpmaker_localstring' ) {

		return;
	}
	if(!wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')))
		return;

	$output = '';

	global $rsvp_options;

	if ( ! empty( $_REQUEST['localstring'] ) ) {

		preg_match( '/(.+:00 ).+\(([^)]+)/', sanitize_text_field($_REQUEST['localstring']), $matches );

		$tf = str_replace( 'T', '', $rsvp_options['time_format'] );

		$t = rsvpmaker_strtotime( $matches[1] );

		$output = rsvpmaker_date( $rsvp_options['long_date'], $t ) . ' ' . rsvpmaker_date( $tf, $t ) . ' ' . $matches[2];

	}

	echo wp_kses_post($output);

	wp_die();

}



if ( ! function_exists( 'basic_form' ) ) {

	function basic_form( $form = '' ) {

		global $rsvp_options;

		global $post;

		if ( empty( $form ) ) {

			$form = get_post_meta( $post->ID, '_rsvp_form', true );
		}

		if ( empty( $form ) ) {

			$form = $rsvp_options['rsvp_form'];
		}

		if ( is_numeric( $form ) ) {

			$fpost = get_post( $form );

			echo do_blocks( $fpost->post_content );

		} else {
			echo do_shortcode( $form );
		}

	}
}



// global variable for content

$confirmed_content = '';



if ( ! function_exists( 'event_content' ) ) {

	function event_content( $content, $formonly = false, $form = '' ) {

		if ( is_admin() ) { // || !in_the_loop()

			return $content;
		}

		global $wpdb, $post, $rsvp_options, $profile, $master_rsvp, $showbutton, $blanks_allowed, $email_context, $confirmed_content;
		// If the post is not an event, leave it alone

		if ( $post->post_type != 'rsvpmaker' ) {
			return $content;
		}

		$rsvpconfirm = $rsvp_confirm = '';

		$display = array();

		if ( post_password_required( $post ) ) {

			return $content;

		}

		global $custom_fields; // make this globally accessible

		$custom_fields = get_rsvpmaker_custom( $post->ID );

		$content = apply_filters( 'rsvpmaker_event_content_top', $content, $custom_fields );

		// if requiring passcode, check code (unless RSVP cookie is set)

		if ( isset( $custom_fields['_require_webinar_passcode'][0] ) && $custom_fields['_require_webinar_passcode'][0] && ! isset( $_COOKIE[ 'rsvp_for_' . $post->ID ] ) ) {

			$event_id = $custom_fields['_require_webinar_passcode'][0];

			if ( ! isset( $_GET['webinar'] ) ) {

				return rsvpmaker_replay_form( $custom_fields['_webinar_event_id'][0] );
			}

			$code = sanitize_text_field($_GET['webinar']);

			$required = $custom_fields['_require_webinar_passcode'][0];

			if ( $required != trim( $code ) ) {

				return rsvpmaker_replay_form( $custom_fields['_webinar_event_id'][0] );
			}
		}

		$permalink = site_url( '?post_type=rsvpmaker&p=' . intval( $post->ID ) );

		if ( isset( $custom_fields['_rsvp_on'][0] ) ) {

			$rsvp_on = $custom_fields['_rsvp_on'][0];
		}

		if ( isset( $custom_fields['_rsvp_login_required'][0] ) ) {

			$login_required = $custom_fields['_rsvp_login_required'][0];
		}

		if ( isset( $custom_fields['_rsvp_to'][0] ) ) {

			$rsvp_to = $custom_fields['_rsvp_to'][0];
		}

		if ( isset( $custom_fields['_rsvp_max'][0] ) ) {

			$rsvp_max = $custom_fields['_rsvp_max'][0];
		}

		$rsvp_count = ( isset( $custom_fields['_rsvp_count'][0] ) && $custom_fields['_rsvp_count'][0] ) ? 1 : 0;

		$rsvp_show_attendees = ( isset( $custom_fields['_rsvp_show_attendees'][0] ) && $custom_fields['_rsvp_show_attendees'][0] ) ? $custom_fields['_rsvp_show_attendees'][0] : 0;

		if ( isset( $custom_fields['_rsvp_deadline'][0] ) && $custom_fields['_rsvp_deadline'][0] ) {

			$deadline = (int) $custom_fields['_rsvp_deadline'][0];
		}

		if ( isset( $custom_fields['_rsvp_start'][0] ) && $custom_fields['_rsvp_start'][0] ) {

			$rsvpstart = (int) $custom_fields['_rsvp_start'][0];
		}

		$rsvp_instructions = ( isset( $custom_fields['_rsvp_instructions'][0] ) ) ? $custom_fields['_rsvp_instructions'][0] : null;

		$rsvp_yesno = ( isset( $custom_fields['_rsvp_yesno'][0] ) ) ? $custom_fields['_rsvp_yesno'][0] : 1;

		$replay = ( isset( $custom_fields['_replay'][0] ) ) ? $custom_fields['_replay'][0] : null;

		$first = ( isset( $_GET['first'] ) ) ? sanitize_text_field($_GET['first']) : null;

		$last = ( isset( $_GET['last'] ) ) ? sanitize_text_field($_GET['last']) : null;

		$rsvprow = null;

		$e = get_rsvp_email();

		$rsvp_id = get_rsvp_id( $e );

		$profile = rsvpmaker_profile_lookup( $e );

		if ( $rsvp_id && $e ) {

			$sql = 'SELECT * FROM ' . $wpdb->prefix . "rsvpmaker WHERE id=$rsvp_id and email='$e'";

			$rsvprow = $wpdb->get_row( $sql, ARRAY_A );

		}

		if ( $profile ) {

			$first = $profile['first'];

			$last = $profile['last'];

		}

		if ( isset( $_GET['rsvp'] ) ) {

			$rsvp_confirm = rsvp_get_confirm( $post->ID );

			$rsvp_confirm .= "\n\n" . wpautop( get_post_meta( $post->ID, '_rsvp_' . $e, true ) );

			$rsvpconfirm = '<h3>' . esc_html( __( 'RSVP Recorded', 'rsvpmaker' ) ) . '</h3>	

' . $rsvp_confirm;

		} elseif ( isset( $_COOKIE[ 'rsvp_for_' . $post->ID ] ) && ! $email_context ) {

			$rsvp_confirm = rsvp_get_confirm( $post->ID );

			if ( $rsvprow ) {

				$permalink .= ( strpos( $permalink, '?' ) ) ? '&' : '?';

				$rsvpconfirm = '

<h4>' . $rsvp_options['update_rsvp'] . '?</h4>	

<p><a href="' . esc_url_raw( $permalink . 'update=' . $rsvp_id . '&e=' . $rsvprow['email'] ) . '#rsvpnow">' . __( 'Yes', 'rsvpmaker' ) . '</a>, ' . __( 'I want to update this record for ', 'rsvpmaker' ) . esc_html( $rsvprow['first'] . ' ' . $rsvprow['last'] ) . '</p>

';

			}
		}

		if ( ( ( $e && isset( $_GET['rsvp'] ) ) || ( is_user_logged_in() && ! $email_context ) ) ) {

			if ( $rsvprow && is_single() ) {

				$master_rsvp = $rsvprow['id'];

				$rsvpwithdraw = sprintf( '<div><input type="checkbox" checked="checked" name="withdraw[]" value="%d"> %s %s</div>', esc_attr( $rsvprow['id'] ), esc_html( $rsvprow['first'] ), esc_html( $rsvprow['last'] ) );

				$answer = ( $rsvprow['yesno'] ) ? __( 'Yes', 'rsvpmaker' ) : __( 'No', 'rsvpmaker' );

				$rsvpconfirm .= '<div class="rsvpdetails"><p>' . __( 'Your RSVP', 'rsvpmaker' ) . ": $answer</p>\n";

				$profile = $details = rsvp_row_to_profile( $rsvprow );

				if ( isset( $details['total'] ) && $details['total'] ) {

					$invoice_id = (int) get_post_meta( $post->ID, '_open_invoice_' . $rsvp_id, true );

					$paid = 0;

					$paid_amounts = get_post_meta( $post->ID, '_paid_' . $rsvp_id );

					if ( is_array( $paid_amounts ) ) {

						foreach ( $paid_amounts as $payment ) {

							$paid += $payment;
						}
					}

					$charge = $details['total'] - $paid;

					$price_display = ( $charge == $details['total'] ) ? $details['total'] : $details['total'] . ' - ' . $paid . ' = ' . $charge;

					if ( $invoice_id ) {

						update_post_meta( $post->ID, '_invoice_' . $rsvp_id, $charge );

					} else {

						$invoice_id = 'rsvp' . add_post_meta( $post->ID, '_invoice_' . $rsvp_id, $charge );

						add_post_meta( $post->ID, '_open_invoice_' . $rsvp_id, $invoice_id );

					}

					$rsvpconfirm .= '<p><strong>' . __( 'Pay for ', 'rsvpmaker' ) . ' ' . esc_html( $details['payingfor'] ) . ' = ' . number_format( $details['total'], 2, $rsvp_options['currency_decimal'], $rsvp_options['currency_thousands'] . ' ' . $rsvp_options['paypal_currency'] ) . '</strong></p>';

					if ( $charge != $details['total'] ) {

						$rsvpconfirm .= '<p><strong>' . __( 'Previously Paid', 'rsvpmaker' ) . ' ' . number_format( $paid, 2, $rsvp_options['currency_decimal'], $rsvp_options['currency_thousands'] ) . ' ' . $rsvp_options['paypal_currency'] . '</strong></p>';

						$rsvpconfirm .= '<p><strong>' . __( 'Balance Owed', 'rsvpmaker' ) . ' ' . number_format( $charge, 2, $rsvp_options['currency_decimal'], $rsvp_options['currency_thousands'] ) . ' ' . $rsvp_options['paypal_currency'] . '</strong></p>';

					}

					if ( $charge > 0 ) {

						$gateway = get_rsvpmaker_payment_gateway();

						if ( $gateway == 'Stripe' ) {

							$rsvprow['amount'] = $charge;

							$rsvprow['rsvp_id'] = $rsvp_id;

							$rsvpconfirm .= rsvpmaker_to_stripe( $rsvprow );

						} 
						elseif ( $gateway == 'Both Stripe and PayPal' ) {

							$rsvprow['amount'] = $charge;
							$rsvprow['rsvp_id'] = $rsvp_id;
							$rsvpconfirm .= rsvpmaker_to_stripe( $rsvprow );
							$rsvpconfirm .= '<p>'. __('Credit card processing by Stripe','rsvpmaker').'</p>';
							$rsvpconfirm .= '<p>'. __('Or pay with PayPal','rsvpmaker').'</p>';
							$rsvpconfirm .= rsvpmaker_paypal_button( $charge, $rsvp_options['paypal_currency'], $post->post_title, array('rsvp'=>$rsvp_id,'event' => $post->ID) );
						} 
						elseif ( $gateway == 'Stripe via WP Simple Pay' ) {

							$rsvpconfirm .= '<p>' . do_shortcode( '[stripe amount="' . ( $charge * 100 ) . '" description="' . htmlentities( $post->post_title ) . ' ' . esc_html( $details['payingfor'] ) . '" ]' ) . '</p>';

						} elseif ( $gateway == 'Cash or Custom' ) {

							ob_start();

							do_action( 'rsvpmaker_cash_or_custom', $charge, $invoice_id, $rsvp_id, $details, $profile, $post );

							$rsvpconfirm .= ob_get_clean();

						} elseif ( $gateway == 'PayPal REST API' ) {

							$rsvpconfirm .= rsvpmaker_paypal_button( $charge, $rsvp_options['paypal_currency'], $post->post_title, array('rsvp'=>$rsvp_id,'event' => $post->ID) );

						} 
					
					}
				}

				if ( ! isset( $_GET['rsvp'] ) ) {

					$guestsql = 'SELECT * FROM ' . $wpdb->prefix . 'rsvpmaker WHERE master_rsvp=' . $rsvprow['id'];

					if ( $results = $wpdb->get_results( $guestsql, ARRAY_A ) ) {

						$rsvpconfirm .= '<p>' . __( 'Guests', 'rsvpmaker' ) . ':</p>';

						foreach ( $results as $row ) {

							$rsvpconfirm .= $row['first'] . ' ' . $row['last'] . '<br />';

							$rsvpwithdraw .= sprintf( '<div><input type="checkbox" checked="checked" name="withdraw[]" value="%d"> %s %s</div>', $row['id'], esc_html( $row['first'] ), esc_html( $row['last'] ) );

						}
					}
				}

				$rsvpconfirm .= "</p></div>\n";

			}
		} elseif ( $e && isset( $_GET['update'] ) ) {

			$sql = 'SELECT * FROM ' . $wpdb->prefix . 'rsvpmaker WHERE ' . $wpdb->prepare( 'event=%d AND email=%s AND id=%d', $post->ID, $e, intval($_GET['update']) );

			$rsvprow = $wpdb->get_row( $sql, ARRAY_A );

			if ( $rsvprow ) {

				$master_rsvp = $rsvprow['id'];

				$answer = ( $rsvprow['yesno'] ) ? __( 'Yes', 'rsvpmaker' ) : __( 'No', 'rsvpmaker' );

				$profile = $details = rsvp_row_to_profile( $rsvprow );

			}
		}

		$dateblock = ( strpos( $post->post_content, 'rsvpdateblock]' ) ) ? '' : rsvpmaker_format_event_dates( $post->ID );
		$event     = get_rsvpmaker_event( $post->ID );
		if ( $event ) {
			$dur       = $event->display_type;
			$last_time = (int) $event->ts_end;
			$firstrow  = $event->date;
		} else {
			$dur = $last_time = $firstrow = '';
		}

		if ( ! empty( $rsvpconfirm ) ) {

			$rsvpconfirm = '<div id="rsvpconfirm">' . $rsvpconfirm . '</div>';
		}

		// $content = '<div>'.$content;

		if ( ! $formonly && ! empty( $dateblock ) ) {

			$content = $dateblock . $content;
		}

		if ( ! empty( $rsvpconfirm ) ) {

			$content = $rsvpconfirm . $content;
		}

		if ( isset( $_GET['rsvp'] ) ) {

			// don't repeat form

			$link = get_permalink();

			$args = array(
				'e'      => $_GET['e'],
				'update' => $_GET['rsvp'],
			);

			$link = add_query_arg( $args, $link );

			$content .= sprintf( '<p><a href="%s#rsvpnow">%s</a>', esc_url_raw( $link ), esc_html( $rsvp_options['update_rsvp'] ) );

			$confirmed_content[ $post->ID ] = $content;

			return $content;

		}

		$showbutton = apply_filters( 'rsvpmaker_showbutton', $showbutton );

		if ( isset( $rsvp_on ) && $rsvp_on ) {

			// check for responses so far

			$sql = 'SELECT first,last,note FROM ' . $wpdb->prefix . "rsvpmaker WHERE event=$post->ID AND yesno=1 ORDER BY id DESC";

			$attendees = $wpdb->get_results( $sql );

			$total = sizeof( $attendees ); // (int) $wpdb->get_var($sql);

			if ( isset( $rsvp_max ) && $rsvp_max ) {

				$blanks_allowed = ( $total + 1 ) - $rsvp_max;

				if ( $total >= $rsvp_max ) {

					$too_many = true;
				}

				$blanks_allowed = $rsvp_max - ( $total );

				if ( ! isset( $answer ) ) {

					$blanks_allowed--;
				}
			} else {
				$blanks_allowed = 1000;
			}

			if ( $rsvp_count ) {

				$content .= '<div class="signed_up_ajax" id="signed_up_' . esc_attr( $post->ID ) . '" post="' . esc_attr( $post->ID ) . '"></div>';

			}

			$now = time();

			$rsvplink = get_rsvp_link( $post->ID, true );
			if ( !is_rsvpmaker_deadline_future( $post->ID ) ) {
				$content .= '<p class="rsvp_status">' . __( 'RSVP deadline is past', 'rsvpmaker' ) . '</p>';
			} 
			elseif ( isset( $rsvpstart ) && ( $now < $rsvpstart ) ) {

				$content .= '<p class="rsvp_status">' . esc_html( __( 'RSVPs accepted starting: ', 'rsvpmaker' ) . utf8_encode( rsvpmaker_date( $rsvp_options['long_date'] ), $rsvpstart ) ) . '</p>';

			} elseif ( isset( $too_many ) ) {

				$content .= '<p class="rsvp_status">' . esc_html( __( 'RSVPs are closed', 'rsvpmaker' ) ) . '</p>';
				if ( isset( $rsvpwithdraw ) ) {

					$content .= sprintf( '<h3>%s</h3><form method="post" action="%s">%s<p><button>%s</button></p>%s</form>', esc_html( __( 'To cancel, check the attendee names to be removed', 'rsvpmaker' ) ), esc_url_raw( $rsvplink ), esc_url_raw( $rsvpwithdraw ), __( 'Cancel RSVP', 'rsvpmaker' ), rsvpmaker_nonce('return') );

				}
			}
			elseif ( ( $rsvp_on && is_admin() && isset( $_GET['page'] ) && ( $_GET['page'] != 'rsvp' ) ) || ( $rsvp_on && is_email_context() ) || ( $rsvp_on && isset( $_GET['load'] ) ) ) { // when loaded into editor

				$content .= sprintf( $rsvp_options['rsvplink'], $rsvplink );

			} elseif ( $rsvp_on && $login_required && ! is_user_logged_in() ) { // show button, coded to require login

				$content .= sprintf( $rsvp_options['rsvplink'], $rsvplink );

			} elseif ( $rsvp_on && ! is_admin() && ! $formonly && ( ! is_single() || $showbutton ) ) { // show button

				$content .= sprintf( $rsvp_options['rsvplink'], $rsvplink );

			} elseif ( $rsvp_on && ( is_single() || is_admin() || $formonly ) ) {

				ob_start();
				echo '<div id="rsvpsection">';
				?>

<form id="rsvpform" action="<?php echo esc_url_raw( $permalink ); ?>" method="post">



<h3 id="rsvpnow"><?php echo esc_html( $rsvp_options['rsvp_form_title'] ); ?></h3> 

				<?php

				if ( get_post_meta( $post->ID, '_rsvp_form_show_date', true ) ) {
					echo rsvpmaker_format_event_dates( $post->ID );
				}

				if ( $rsvp_instructions ) {
					echo '<p>' . wp_kses_post( nl2br( $rsvp_instructions ) ) . '</p>';
				}

				if ( $rsvp_show_attendees ) {

					  echo '<p class="rsvp_status">' . __( 'Names of attendees will be displayed publicly, along with the contents of the notes field.', 'rsvpmaker' ) . '</p>';

					if ( $rsvp_show_attendees == 2 ) {

						echo ' (' . __( 'only for logged in users', 'rsvpmaker' ) . ')';
					}

					echo '</p>';

				}

				if ( $rsvp_yesno ) {
					echo '<p>' . __( 'Your Answer', 'rsvpmaker' );
					?>
					: <input name="yesno" class="radio_buttons" type="radio" value="1" 
					<?php
					if ( ! isset( $rsvprow ) || $rsvprow['yesno'] ) {
						echo 'checked="checked"';
					}
					?>
 /> <?php echo __( 'Yes', 'rsvpmaker' ); ?> <input name="yesno" type="radio"  class="radio_buttons" value="0" 
					<?php
					if ( isset( $rsvprow['yesno'] ) && ( $rsvprow['yesno'] == 0 ) ) {
						echo 'checked="checked"';
					}
					?>
 /> 
					<?php
					echo __( 'No', 'rsvpmaker' ) . '</p>';
				} else {
					echo '<input name="yesno" type="hidden" value="1" />';
				}

				rsvphoney_ui();

				if ( $dur && ( $slotlength = ! empty( $custom_fields['_rsvp_timeslots'][0] ) ) ) {

					?>

<div><?php echo __( 'Number of Participants', 'rsvpmaker' ); ?>: <select name="participants">

	<option value="1">1</option>

	<option value="2">2</option>

	<option value="3">3</option>

	<option value="4">4</option>

	<option value="5">5</option>

	<option value="6">6</option>

	<option value="7">7</option>

	<option value="8">8</option>

	<option value="9">9</option>

	<option value="10">10</option>

  </select></div>



<div><?php echo __( 'Choose timeslots', 'rsvpmaker' ); ?></div>

					<?php

					$t = rsvpmaker_strtotime( $firstrow['datetime'] );

					$dur = $firstrow['duration'];

					if ( strpos( $dur, ':' ) ) {

						$dur = rsvpmaker_strtotime( $dur );
					}

					$day = rsvpmaker_date( 'j', $t );

					$month = date( 'n', $t );

					$year = date( 'Y', $t );

					$hour = rsvpmaker_date( 'G', $t );

					$minutes = rsvpmaker_date( 'i', $t );

					$slotlength = explode( ':', $slotlength );

					$min_add = $slotlength[0] * 60;

					$min_add = ( empty( $slotlength[1] ) ) ? $min_add : ( $min_add + $slotlength[1] );

					for ( $i = 0; ( $slot = rsvpmaker_mktime( $hour, $minutes + ( $i * $min_add ), 0, $month, $day, $year ) ) < $dur; $i++ ) {

						$sql = 'SELECT SUM(participants) FROM ' . $wpdb->prefix . "rsvp_volunteer_time WHERE time=$slot AND event = $post->ID";

						$signups = ( $signups = $wpdb->get_var( $sql ) ) ? $signups : 0;

						echo '<div><input type="checkbox" name="timeslot[]" value="' . $slot . '" /> ' . rsvpmaker_date( ' ' . $rsvp_options['time_format'], $slot ) . " $signups participants signed up</div>";

					}
				}

				if ( isset( $custom_fields['_per'][0] ) && $custom_fields['_per'][0] ) {

					$pf = '';

					$options = '';

					$per = unserialize( $custom_fields['_per'][0] );

					foreach ( $per['unit'] as $index => $value ) {

						if ( ( $index == 0 ) && empty( $per['price'][ $index ] ) ) { // no price = $0 where no other price is specified

							continue;
						}

						if ( empty( $per['price'][ $index ] ) && ( $per['price'][ $index ] != 0 ) ) {

							continue;
						}

						$price = (float) $per['price'][ $index ];

						$deadstring = '';

						if ( ! empty( $per['price_deadline'][ $index ] ) ) {

							$deadline = (int) $per['price_deadline'][ $index ];

							if ( time() > $deadline ) {

								continue;

							} else {
								$deadstring = ' (' . __( 'until', 'rsvpmaker' ) . ' ' . rsvpmaker_date( $rsvp_options['short_date'] . ' ' . $rsvp_options['time_format'], $deadline ) . ')';
							}
						}

						$display[ $index ] = $value . ' @ ' . ( ( $rsvp_options['paypal_currency'] == 'USD' ) ? '$' : $rsvp_options['paypal_currency'] ) . ' ' . number_format( $price, 2, $rsvp_options['currency_decimal'], $rsvp_options['currency_thousands'] ) . $deadstring;

					}

					if ( isset( $custom_fields['_rsvp_count_party'][0] ) && $custom_fields['_rsvp_count_party'][0] ) {

						$number_prices = sizeof( $display );

						if ( $number_prices ) {

							if ( $number_prices == 1 ) { // don't show options, just one choice

								foreach ( $display as $index => $value ) {
									printf( '<h3 id="guest_count_pricing"><input type="hidden" name="guest_count_price" value="%s">%s</h3>', $index, esc_html( $value ) );
								}
							} else {

								foreach ( $display as $index => $value ) {

									$s = ( isset( $profile['pricechoice'] ) && ( $index == $profile['pricechoice'] ) ) ? ' selected="selected" ' : '';

									$options .= sprintf( '<option value="%d" %s>%s</option>', $index, $s, esc_html( $value ) );

								}

								printf( '<div id="guest_count_pricing">' . __( 'Options', 'rsvpmaker' ) . ': <select name="guest_count_price"  id="guest_count_price">%s</select></div>', $options );

							}
						}
					} else {

						if ( sizeof( $display ) ) {

							foreach ( $display as $index => $value ) {

								if ( empty( $per['price'][ $index ] ) && ( $per['price'][ $index ] != 0 ) ) {

									continue;
								}

								$price = (float) $per['price'][ $index ];

								$unit = $per['unit'][ $index ];

								$pf .= '<div class="paying_for_tickets"><select name="payingfor[' . $index . ']" class="tickets"><option value="0">0</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option></select><input type="hidden" name="unit[' . $index . ']" value="' . esc_attr( $unit ) . '" />' . esc_html( $value ) . '<input type="hidden" name="price[' . $index . ']" value="' . esc_attr( $price ) . '" /></div>' . "\n";

							}
						}

						if ( ! empty( $pf ) ) {

							echo '<h3>' . __( 'Paying For', 'rsvpmaker' ) . '</h3><p>' . $pf . "</p>\n";
						}
					}

					// coupon code

					if ( ! empty( $custom_fields['_rsvp_coupon_code'][0] ) ) {

						printf( '<p>Coupon Code: <input type="text" name="coupon_code" size="10" /><br /><em>If you have a coupon code, enter it above</em>.</p>' );
					}
				}

				basic_form( $form );

				if ( isset( $custom_fields['_rsvp_captcha'][0] ) && $custom_fields['_rsvp_captcha'][0] ) {

					?>

<p>          <img src="<?php echo plugins_url( '/captcha/captcha_ttf.php', __FILE__ ); ?>" alt="CAPTCHA image">

<br />

					<?php esc_html_e( 'Type the hidden security message', 'rsvpmaker' ); ?>:<br />                    

<input maxlength="10" size="10" name="captcha" type="text" autocomplete="off" />

</p>

					<?php

					do_action( 'rsvpmaker_after_captcha' );

				}
				global $rsvp_required_field;
				$rsvp_required_field['email'] = 'email';// at a minimum			

				rsvpmaker_recaptcha_output();
				if ( isset( $rsvp_options['privacy_confirmation'] ) && ( $rsvp_options['privacy_confirmation'] == '1' ) ) {

					echo '<p><input type="checkbox" name="profile[privacy_consent]" id="privacy_consent" value="1" /> ' . wp_kses_post( $rsvp_options['privacy_confirmation_message'] ) . '</p>';

					if ( ! in_array( 'privacy_consent', $rsvp_required_field ) ) {

						$rsvp_required_field[] = 'privacy_consent';
					}
				}

				if ( isset( $rsvp_required_field ) ) {

					echo '<div id="jqerror"></div><input type="hidden" name="required" id="required" value="' . esc_attr( implode( ',', $rsvp_required_field ) ) . '" />';
				}

				?>

		<p> 

		  <input type="submit" id="rsvpsubmit" name="Submit" value="<?php esc_html_e( 'Submit', 'rsvpmaker' ); ?>" /> 

		</p> 

<input type="hidden" name="rsvp_id" id="rsvp_id" value="
				<?php
				if ( isset( $profile['id'] ) ) {
					echo esc_attr( $profile['id'] );}
				?>
" /><input type="hidden" name="event" id="event" value="<?php echo esc_attr( $post->ID ); ?>" /><?php rsvpmaker_nonce(); ?>

</form>	

</div>

				<?php
				$content .= ob_get_clean();
			}

			if ( isset( $_GET['err'] ) ) {

				$error = sanitize_text_field($_GET['err']);

				$content = '<div id="rsvpconfirm" >

<h3 class="rsvperror">' . __( 'Error', 'rsvpmaker' ) . '<br />' . esc_attr( $error ) . '</h3>

<p>' . __( 'Please correct your submission.', 'rsvpmaker' ) . '</p>

</div>

' . $content;

			}

			if ( ( ( $rsvp_show_attendees == 1 ) || ( ( $rsvp_show_attendees == 2 ) && is_user_logged_in() ) ) && $total && ! isset( $_GET['load'] ) && ! isset( $_POST['profile'] ) ) {

				// use api

				$content .= '<p><button class="rsvpmaker_show_attendees" post_id="' . esc_attr( $post->ID ) . '" >' . __( 'Show Attendees', 'rsvpmaker' ) . '</button></p>

<div id="attendees-' . esc_attr( $post->ID ) . '"></div>';

			}
		} // end if($rsvp_on)

		$terms = get_the_term_list( $post->ID, 'rsvpmaker-type', '', ', ', ' ' );

		if ( $terms && is_string( $terms ) ) {

			$content .= '<p class="rsvpmeta">' . __( 'Event Types', 'rsvpmaker' ) . ': ' . $terms . '</p>';
		}

		$content = apply_filters( 'rsvpmaker_event_content_bottom', $content, $custom_fields );

		return $content;

	}
} // end event content

function rsvp_report_shortcode( $atts ) {

	if ( ! isset( $atts['public'] ) || ( $atts['public'] == '0' ) ) {

		if ( ! is_user_logged_in() ) {

			return sprintf( /* translators: login link */    __( 'You must <a href="%s">login</a> to view this.', 'rsvpmaker' ), login_redirect( $_SERVER['REQUEST_URI'] ) );
		}
	}

	global $post;

	$permalink = get_permalink( $post->ID );

	$permalink .= ( strpos( $permalink, '?' ) ) ? '&rsvp_print=1&' . rsvpmaker_nonce('query') : '?rsvp_print=1&' . rsvpmaker_nonce('query');

	ob_start();

	rsvp_report();

	$report = ob_get_clean();

	return str_replace( admin_url( 'edit.php?post_type=rsvpmaker&page=rsvp' ), $permalink, $report );

}



if ( ! function_exists( 'rsvp_report' ) ) {

	function rsvp_report() {

		global $wpdb, $post, $rsvp_options;
		$daily_count = [];

		$wpdb->show_errors();

		if ( isset( $_GET['event'] ) ) {

			$post = get_post( (int) $_GET['event'] );
		}

		if ( isset( $_POST['move_rsvp'] ) && isset( $_POST['move_to'] ) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key'))  ) {

			if ( empty( $_POST['move_rsvp'] ) ) {

				printf( '<div class="notice notice-error"><p>%s</p></option>', __( 'No RSVP entry selected', 'rsvpmaker' ) );

			} else {

				$move_rsvp = (int) $_POST['move_rsvp'];
				$move_to   = (int) $_POST['move_to'];
				$sql       = 'UPDATE ' . $wpdb->prefix . "rsvpmaker SET event=$move_to WHERE id=$move_rsvp ";
				$wpdb->query( $sql );
				$sql = 'UPDATE ' . $wpdb->prefix . "rsvpmaker SET event=$move_to WHERE master_id=$move_rsvp ";
				$wpdb->query( $sql );
			}
		}

		$guest_check = '';

		$wpdb->show_errors();

		?>

<div class="wrap"> 

	<div id="icon-edit" class="icon32"><br /></div>

<h2>
		<?php
		$param = ( empty( $_GET['limit'] ) ) ? '' : sanitize_text_field($_GET['limit']) . ' ' . sanitize_text_field($_GET['detail']);

		if ( sizeof( $_GET ) > 2 ) {
			printf( '<a href="%s">%s</a> - %s %s', admin_url( 'edit.php?post_type=rsvpmaker&page=rsvp' ), __( 'RSVP Report', 'rsvpmaker' ), __( 'Details', 'rsvpmaker' ), $param );
		} else {
			esc_html_e( 'RSVP Report', 'rsvpmaker' );
		}
		?>
		</h2> 

		<?php

		if ( ! empty( $_GET['fields'] ) ) {

			rsvp_report_table();

			echo '</div>';

			return;

		}

		if ( isset( $_POST['deletenow'] ) && current_user_can( 'edit_others_posts' ) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key'))  ) {

			if (  ! wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {

				die( 'failed security check' );
			}

			foreach ( $_POST['deletenow'] as $d ) {

				$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . "rsvpmaker where id=".intval($d) );
			}
		}

		if ( isset( $_GET['delete'] ) && current_user_can( 'edit_others_posts' ) ) {

			$delete = intval($_GET['delete']);

			$row = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . "rsvpmaker WHERE id=$delete" );

			$guests = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . "rsvpmaker WHERE master_rsvp=$delete" );

			if ( is_array( $guests ) ) {

				foreach ( $guests as $guest ) {

					$guestcheck .= sprintf( '<input type="checkbox" name="deletenow[]" value="%s" checked="checked" /> Delete guest: %s %s<br />', $guest->id, esc_html( $guest->first ), esc_html( $guest->last ) );
				}
			}

			echo sprintf(
				'<form action="%s" method="post">

<h2 style="color: red;">' . __( 'Confirm Delete for', 'rsvpmaker' ) . ' %s %s</h2>

<input type="hidden" name="deletenow[]" value="%s"  />

%s

%s

<input type="submit" style="color: red;" value="' . __( 'Delete Now', 'rsvpmaker' ) . '"  />

</form>

',
				admin_url() . 'edit.php?post_type=rsvpmaker&page=rsvp',
				esc_html( $row->first ),
				esc_html( $row->last ),
				esc_attr( $delete ),
				esc_attr( $guestcheck ),
				rsvpmaker_nonce('return')
			);

		}

		if ( isset( $_GET['event'] ) ) {

			$eventid   = (int) $_GET['event'];
			$permalink = get_permalink( $eventid );

			$event_row = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . "rsvpmaker_event WHERE event=$eventid" );

			$date = $event_row->date;

			$t = rsvpmaker_strtotime( $date );

			$title = '<a target="_blank" href="' . $permalink . '">' . esc_html( $event_row->post_title ) . ' ' . rsvpmaker_date( $rsvp_options['long_date'], $t ) . '</a>';

			echo '<h2>' . __( 'RSVPs for', 'rsvpmaker' ) . ' ' . $title . "</h2>\n";

			if ( ! isset( $_GET['rsvp_print'] ) ) {

				echo '<div style="float: right; margin-left: 15px; margin-bottom: 15px;"><a href="edit.php?post_type=rsvpmaker&page=rsvp">' . __( 'Show Events List', 'rsvpmaker' ) . '</a> |

<a href="edit.php?post_type=rsvpmaker&page=rsvp&event=' . $eventid . '&rsvp_order=alpha">Alpha Order</a> <a href="edit.php?post_type=rsvpmaker&page=rsvp&event=' . $eventid . '&rsvp_order=timestamp">Most Recent First</a> | <a href="edit.php?post_type=rsvpmaker&page=rsvp&event=' . $eventid . '&rsvp_order=alpha">Alpha Order</a>

		</div>';

		//printf('<p>%d registered</p>',sizeof($rsvps));

				echo '<p><a href="' . sanitize_text_field($_SERVER['REQUEST_URI']) . '&print_rsvp_report=1&rsvp_print=1&' . rsvpmaker_nonce('query') . '" target="_blank" >Format for printing</a></p>';

				echo '<p><a href="edit.php?post_type=rsvpmaker&page=rsvp&event=' . $eventid . '&paypal_log=1">Show PayPal Log</a></p>';

				if ( isset( $phpexcel_enabled ) ) {

					echo '<p><a href="#excel">Download to Excel</a></p>';
				}
			}

			if ( ! empty( $_GET['paypal_log'] ) ) {

				$log = get_post_meta( $eventid, '_paypal_log' );

				if ( $log ) {

					echo '<div style="border: thin solid red; padding: 5px;"><strong>PayPal</strong><br />';

					echo implode( '', $log );

					echo '</div>';

				}
			}

			if ( ! empty( $_POST['paymentAmount'] )  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {

				$rsvp_id = (int) $_POST['rsvp_id'];

				$paid = (float) $_POST['paymentAmount'];

				admin_payment( $rsvp_id, $paid );

			}

			if ( ! empty( $_POST['markpaid'] )  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {

				foreach ( $_POST['markpaid'] as $value ) {
					$value = sanitize_text_field($value);
					$parts = explode( ':', $value );
					admin_payment( $parts[0], $parts[1] );
				}
			}

			if ( isset( $_GET['rsvp'] ) ) {

				$sql = 'SELECT * FROM ' . $wpdb->prefix . 'rsvpmaker WHERE ' . $wpdb->prepare( 'id=%d', intval($_GET['rsvp']) );

				$rsvprow = $wpdb->get_row( $sql, ARRAY_A );

				if ( $rsvprow ) {

					$master_rsvp = $rsvprow['id'];

					$answer = ( $rsvprow['yesno'] ) ? __( 'Yes', 'rsvpmaker' ) : __( 'No', 'rsvpmaker' );

					if ( empty( $rsvpconfirm ) ) {

						$rsvpconfirm = '';
					}

					$rsvpconfirm .= '<div style="border: medium solid #555; padding: 10px;"><p>' . esc_html( $rsvprow['first'] . ' ' . $rsvprow['last'] ) . ": $answer</p>\n";

					$profile = $details = rsvp_row_to_profile( $rsvprow );

					if ( isset( $details['total'] ) && $details['total'] ) {

						$rsvp_id = (int) $_GET['rsvp'];

						$invoice_id = (int) get_post_meta( $eventid, '_open_invoice_' . $rsvp_id, true );

						$paid = $rsvprow['amountpaid'];

						$charge = $details['total'] - $paid;

						$price_display = ( $charge == $details['total'] ) ? $details['total'] : $details['total'] . ' - ' . $paid . ' = ' . $charge;

						if ( $invoice_id ) {

							update_post_meta( $eventid, '_invoice_' . $rsvp_id, $charge );

						} else {

							$invoice_id = 'rsvp' . add_post_meta( $eventid, '_invoice_' . $rsvp_id, $charge );

							add_post_meta( $eventid, '_open_invoice_' . $rsvp_id, $invoice_id );

						}

						$rsvpconfirm .= '<p><strong>' . esc_html( __( 'Record Payment', 'rsvpmaker' ) . ' ' . esc_html( $details['payingfor'] ) . ' = ' . number_format( $details['total'], 2, $rsvp_options['currency_decimal'], $rsvp_options['currency_thousands'] ) . ' ' . $rsvp_options['paypal_currency'] ) . '</strong></p>';

						if ( $charge != $details['total'] ) {

							$rsvpconfirm .= '<p><strong>' . esc_html( __( 'Previously Paid', 'rsvpmaker' ) . ' ' . number_format( $paid, 2, $rsvp_options['currency_decimal'], $rsvp_options['currency_thousands'] ) . ' ' . $rsvp_options['paypal_currency'] ) . '</strong></p>';
						}

						if ( $charge > 0 ) {

							$rsvpconfirm .= '<form method="post" name="donationform" id="donationform" action="' . admin_url( 'edit.php?page=rsvp&post_type=rsvpmaker&event=' . $eventid ) . '">

<p>' . __( 'Amount', 'rsvpmaker' ) . ': ' . $charge . '<input name="markpaid[]" type="hidden" id="markpaid_' . $rsvp_id . '"  value="' . $rsvp_id . ':' . $charge . '"> ' . $rsvp_options['paypal_currency'] . '</p><input name="rsvp_id" type="hidden" id="rsvp_id" value="' . $rsvp_id . '" ><input type="submit" name="Submit" value="' . __( 'Mark Paid', 'rsvpmaker' ) . '"></p>
'.rsvpmaker_nonce('return').'
</form>';

						}
					}

					$rsvpconfirm .= '</div>';

					echo $rsvpconfirm;

				}
			}

			if ( isset( $_GET['edit_rsvp'] ) && current_user_can( 'edit_rsvpmakers' ) ) {

				admin_edit_rsvp( $_GET['edit_rsvp'], $eventid );
			}

			$rsvp_order = ( isset( $_GET['rsvp_order'] ) && ( $_GET['rsvp_order'] == 'alpha' ) ) ? ' ORDER BY yesno DESC, last, first' : ' ORDER BY yesno DESC, timestamp DESC';

			$sql = 'SELECT * FROM ' . $wpdb->prefix . "rsvpmaker WHERE event=$eventid $rsvp_order";

			$wpdb->show_errors();

			$results = $wpdb->get_results( $sql, ARRAY_A );

			format_rsvp_details( $results );

		} elseif ( isset( $_GET['detail'] ) ) {

			if ( ! isset( $_GET['rsvp_print'] ) ) {

				echo '<p><a href="' . admin_url( 'edit.php?post_type=rsvpmaker&page=rsvp' ) . '">' . __( 'Show Events List', 'rsvpmaker' ) . '</a> | <a href="' . sanitize_text_field($_SERVER['REQUEST_URI']) . '&print_rsvp_report=1&rsvp_print=1&' . rsvpmaker_nonce('query') . '" target="_blank" >' . __( 'Format for printing', 'rsvpmaker' ) . '</a></p>';
			}

			$limit = (int) $_GET['limit'];

			if ( $_GET['detail'] == 'future' ) {

				$future = get_future_events( '', $limit );

			} else {
				$future = get_past_events( '', $limit );
			}

			$all_emails = array();

			if ( is_array( $future ) ) {

				foreach ( $future as $f ) {

					$sql = 'SELECT * FROM ' . $wpdb->prefix . 'rsvpmaker WHERE event=' . $f->ID . ' ORDER BY yesno DESC, timestamp DESC';

					$wpdb->show_errors();

					$rsvps = $wpdb->get_results( $sql, ARRAY_A );

					if ( ! empty( $rsvps ) ) {

						printf( '<h1>RSVPs for <a target="_blank" href="%s">%s %s</a></h1>', get_permalink( $f->ID ), esc_html( $f->post_title ), esc_html( $f->date ) );

						$emails = format_rsvp_details( $rsvps );

						if ( ! empty( $emails ) ) {

							$all_emails = array_merge( $all_emails, $emails );
						}
					}
				}
			}

			if ( ! empty( $all_emails ) ) {

				$attendees = implode( ', ', $all_emails );

				$label = __( 'Email Attendees (all)', 'rsvpmaker' );

				printf( '<p><a href="mailto:%s">%s: %s</a>', esc_attr( $attendees ), esc_html( $label ), esc_html( $attendees ) );

			}
		} else { // show events list

			$eventlist = '';

			$sql = 'SELECT * FROM ' . $wpdb->prefix . 'rsvpmaker_event ';

			if ( ! isset( $_GET['show'] ) ) {

				$sql2 = $sql . ' WHERE date < CURDATE( ) ORDER BY date DESC LIMIT 0,10';

				$sql .= ' WHERE date > CURDATE( ) ORDER BY date';

				$eventlist .= '<p>' . __( 'Showing future and recent events', 'rsvpmaker' ) . ' (<a href="' . sanitize_text_field($_SERVER['REQUEST_URI']) . '&show=all">show all</a>)<p>';

				?>

<form action="edit.php" method="get">
<?php rsvpmaker_nonce(); ?>
				<?php esc_html_e( 'Show details for', 'rsvpmaker' ); ?>

<input type="hidden" name="page" value="rsvp">

<input type="hidden" name="post_type" value="rsvpmaker">

<select name="limit">

<option value="5">5</option>

<option value="10">10</option>

<option value="25">25</option>

<option value="50">50</option>

<option value="100">100</option>

</select>

<select name="detail">

<option value="past">past</option>

<option value="future">future</option>

</select> events 

<button><?php esc_html_e( 'Show', 'rsvpmaker' ); ?></button>

</form>

				<?php

			} else {

				$eventlist .= '<p>' . esc_html( __( 'Showing past events (for which RSVPs were collected) as well as upcoming events.', 'rsvpmaker' ) ) . '<p>';

				$sql .= ' ORDER BY date DESC';

			}

			$wpdb->show_errors();

			$results = $wpdb->get_results( $sql );

			if ( $results ) {

				foreach ( $results as $row ) {

					if ( empty( $events[ $row->event ] ) ) {

						$events[ $row->event ] = $row->post_title;
					}

					$t = rsvpmaker_strtotime( $row->date );

					$events[ $row->event ] .= ' ' . rsvpmaker_date( $rsvp_options['long_date'], $t );

				}
			}

			if ( ! empty( $sql2 ) ) {

				$results = $wpdb->get_results( $sql2 );

				if ( $results ) {

					foreach ( $results as $row ) {

						if ( empty( $events[ $row->event ] ) ) {

							$events[ $row->event ] = $row->post_title;
						}

						$t = rsvpmaker_strtotime( $row->date );

						$events[ $row->event ] .= ' ' . rsvpmaker_date( $rsvp_options['long_date'], $t );

					}
				}
			}

			if ( ! empty( $events ) ) {

				foreach ( $events as $post_id => $event ) {

					$eventlist .= "<h3>$event</h3>";

					$sql = 'SELECT count(*) FROM ' . $wpdb->prefix . 'rsvpmaker WHERE yesno=1 AND event=' . intval( $post_id );

					if ( $rsvpcount = $wpdb->get_var( $sql ) ) {

						$eventlist .= '<p><a href="' . admin_url() . 'edit.php?post_type=rsvpmaker&page=rsvp&event=' . intval( $post_id ) . '">' . __( 'RSVP', 'rsvpmaker' ) . ' ' . __( 'Yes', 'rsvpmaker' ) . ': ' . $rsvpcount . '</a></p>';
					}
				}
			}

			if ( $eventlist && ! isset( $_GET['rsvp_print'] ) ) {
				echo '<h2>' . esc_html( __( 'Events', 'rsvpmaker' ) ) . "</h2>\n" . $eventlist;
			}
		}

	}
} // end rsvp report



if ( ! function_exists( 'format_rsvp_details' ) ) {

	function format_rsvp_details( $results, $editor_options = true ) {

		global $rsvp_options, $wpdb;
		$update      = false;
		
		$missing = $owed_list = '';

		$members = $nonmembers = 0;

		if ( $results ) {

			$fields = array( 'yesno', 'first', 'last', 'email', 'guestof', 'amountpaid' );
		}

		printf('<p>%d registered</p>',sizeof($results));

		foreach ( $results as $index => $row ) {

			$row['yesno'] = ( $row['yesno'] ) ? 'YES' : 'NO';

			if ( $row['yesno'] ) {

				$emails[ $row['email'] ] = $row['email'];
			}

			if ( get_user_by( 'email', $row['email'] ) ) {

				$members++;

			} else {
				$nonmembers++;
			}

			echo '<h3>' . esc_html( $row['yesno'] . ' ' . $row['first'] . ' ' . $row['last'] . ' ' . $row['email'] );

			if ( $row['guestof'] ) {

				echo esc_html( ' (' . __( 'guest of', 'rsvpmaker' ) . ' ' . $row['guestof'] . ')' );
			}

			echo '</h3>';

			if ( $row['master_rsvp'] ) {

				if ( isset( $guestcount[ $row['master_rsvp'] ] ) ) {

					$guestcount[ $row['master_rsvp'] ]++;

				} else {
					$guestcount[ $row['master_rsvp'] ] = 1;
				}
			} else {
				$master_row[ $row['id'] ] = $row['first'] . ' ' . $row['last'];
			}

			if ( $row['details'] ) {
				$details = unserialize( $row['details'] );
			}

			if ( isset( $details['total'] ) ) {
				echo '<div style="font-weight: bold;">' . __( 'Total', 'rsvpmaker' ) . ': ' . esc_html( $details['total'] ) . '</div>';
			}

			if ( ! empty( $details['payingfor'] ) ) {
				echo '<div style="font-weight: bold;">' . __( 'Paying For', 'rsvpmaker' ) . ': ' . esc_html( $details['payingfor'] ) . '</div>';
			}

			if ( $row['amountpaid'] > 0 ) {
				echo '<div style="color: #006400;font-weight: bold;">' . __( 'Paid', 'rsvpmaker' ) . ': ' . esc_html( $row['amountpaid'] ) . '</div>';
			}

			if ( isset( $details['total'] ) ) {
				$owed = $details['total'] - $row['amountpaid'];
				if ( $owed ) {
					echo '<div style="color: red;font-weight: bold;">' . __( 'Owed', 'rsvpmaker' ) . ': ' . $owed . '</div>';

					if ( $owed > 0 ) {

						$owed_list .= sprintf( '<p><input type="checkbox" name="markpaid[]" value="%s:%s">%s %s %s %s</p>', esc_attr( $row['id'] ), esc_html( $owed ), esc_html( $row['first'] ), esc_html( $row['last'] ), esc_html( $owed ), __( 'Owed', 'rsvpmaker' ) );
					}
				}
			}

			echo '<p>';

			if ( $row['details'] ) {

				$details    = unserialize( $row['details'] );
				$newdetails = array();
				if ( is_array( $details ) ) {
					foreach ( $details as $name => $value ) {

						if ( $value ) {
							if ( strpos( $name, ' ' ) ) {
								$update = true;
							}
							$label = get_post_meta( $row['event'], 'rsvpform' . $name, true );
							if ( $label ) {
								$name = $label;
							}
							echo esc_attr($name) . ': ' . esc_attr( $value ) . '<br />';

							$field                = preg_replace( '/[^a-z0-9_]/', '_', strtolower( $name ) );
							$newdetails[ $field ] = $value;
							if ( ! in_array( $name, $fields ) ) {
								$fields[] = $name;
							}
						}
					}
				}
			}

			if ( $row['note'] ) {

				echo 'note: ' . nl2br( esc_attr( $row['note'] ) ) . '<br />';
			}

			$t = rsvpmaker_strtotime( $row['timestamp'] );

			echo 'posted: ' . rsvpmaker_date( $rsvp_options['short_date'], $t );
			
			if(isset($daily_count[rsvpmaker_date( 'Y-m-d', $t )]))
				$daily_count[rsvpmaker_date( 'Y-m-d', $t )]++;
			else
				$daily_count[rsvpmaker_date( 'Y-m-d', $t )] = 1;

			echo '</p>';

			if ( $update ) {
				$sql = $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'rsvpmaker SET details=%s WHERE id=%d', serialize( $newdetails ), $row['id'] );
				$wpdb->query( $sql );
			}

			if ( ! isset( $_GET['rsvp_print'] ) && current_user_can( 'edit_others_posts' ) && $editor_options ) {

				echo sprintf( '<p><a href="%s&delete=%d">Delete record for: %s %s</a></p>', admin_url() . 'edit.php?post_type=rsvpmaker&page=rsvp', $row['id'], esc_attr( $row['first'] ), esc_attr( $row['last'] ) );
			}

			$userrsvps[] = $row['user_id'];

		}

		if ( ! empty( $rsvp_options['missing_members'] ) ) {

			$blogusers = get_users( 'blog_id=1&orderby=nicename' );

			foreach ( $blogusers as $user ) {

				if ( in_array( $user->ID, $userrsvps ) ) {

					continue;
				}

				$userdata = get_userdata( $user->ID );

				$missing .= "<p>$userdata->display_name $userdata->user_email</p>\n";

			}
		}

		if ( ! empty( $missing ) ) {

			echo '<hr /><h3>' . __( 'Members Who Have Not Responded', 'rsvpmaker' ) . '</h3>' . esc_html( $missing );

		}

		if ( ! empty( $emails ) ) {

			$emails = apply_filters( 'rsvp_yes_emails', $emails );
		}

		if ( isset( $emails ) && is_array( $emails ) ) {

			$emails = array_filter( $emails ); // removes empty elements

			$attendees = implode( ', ', $emails );

			$label = __( 'Email Attendees', 'rsvpmaker' );

			printf( '<p><a href="mailto:%s">%s: %s</a>', esc_attr( $attendees ), esc_html( $label ), esc_html( $attendees ) );

		}

		if ( $members && $nonmembers ) {

			printf( '<p>Responses from %d members with user accounts and %d nonmembers.</p>', $members, $nonmembers );
		}

		if(!empty($daily_count))
		{
			echo '<h3>RSVPs Per Day</h3>';
			foreach($daily_count as $day => $count) {
				printf('<p>%s: %s</p>',$day, $count);
			}
		}

		if ( empty( $_GET['event'] ) ) {
			return;
		}

		global $phpexcel_enabled; // set if excel extension is active

		if ( isset( $fields ) ) {

			if ( $fields && ! isset( $_GET['rsvp_print'] ) && ! isset( $_GET['limit'] ) ) {

				$fields[] = 'note';

				$fields[] = 'timestamp';

				foreach ( $fields as $field ) {

					// no duplicates, please

					$i = preg_replace( '/[^a-z0-9]/', '_', strtolower( $field ) );

					if ( $i == 'first_name' ) {

						$i = 'first';
					}

					if ( $i == 'last_name' ) {

						$i = 'last';
					}

					$newfields[ $i ] = $i;

				};
				?>

<div id="excel" name="excel" style="padding: 10px; border: thin dotted #333; width: 300px;margin-top: 30px;">

<h3><?php esc_html_e( 'Data Table / Spreadsheet', 'rsvpmaker' ); ?></h3>

<form method="get" action="edit.php" target="_blank">

				<?php

				foreach ( $_GET as $name => $value ) {

					echo sprintf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $name ), esc_attr( $value ) );
				}

				foreach ( $newfields as $i => $field ) {

					echo '<input type="checkbox" name="fields[]" value="' . $i . '" checked="checked" /> ' . $field . "<br />\n";
				}

				rsvpmaker_nonce();

				?>

<p><button name="print_rsvp_report" value="1" ><?php esc_html_e( 'Print Report', 'rsvpmaker' ); ?></button> <button name="rsvp_csv" value="1" ><?php esc_html_e( 'Download CSV', 'rsvpmaker' ); ?></button></p>

				<?php

				if ( isset( $phpexcel_enabled ) ) {

					$rsvpexcel = wp_create_nonce( 'rsvpexcel' );

					printf( '<p><button name="rsvpexcel" value="%s" />%s</button></p>', $rsvpexcel, __( 'Download to Excel', 'rsvpmaker' ) );

				} else {

					echo '<br />';

					esc_html_e( 'Additional RSVPMaker Excel plugin required for download to Excel function.', 'rsvpmaker' );

					echo '<a href="https://wordpress.org/plugins/rsvpmaker-excel/">https://wordpress.org/plugins/rsvpmaker-excel/</a>';

				}

				?>

</form>

</div>

				<?php

			}
		}

		$options = $name = '';

		if ( is_admin() && ! isset( $_GET['rsvp_print'] ) ) {

			if ( ! empty( $master_row ) ) {

				foreach ( $master_row as $id => $name ) {

					if ( isset( $guestcount[ $id ] ) ) {

						$name .= sprintf( ' + %d guests', $guestcount[ $id ] );
					}

					$options .= sprintf( '<option value="%d">%s</option>', $id, esc_attr( $name ) );

				}
			}

			?>

<h3><?php esc_html_e( 'Edit Entries', 'rsvpmaker' ); ?></h3>

<form action="edit.php" method="get">
<?php rsvpmaker_nonce(); ?>
<select name="edit_rsvp"><option value="0">Add New</option><?php echo $options; ?></select>

<input type="hidden" name="page" value="rsvp">

<input type="hidden" name="post_type" value="rsvpmaker">

<input type="hidden" name="event" value="<?php echo intval($_GET['event']); ?>">

<button><?php esc_html_e( 'Edit', 'rsvpmaker' ); ?></button>

</form>



<h3><?php esc_html_e( 'Move Between Events', 'rsvpmaker' ); ?></h3>

<p><?php esc_html_e( 'Transfers the individual who registered and any guests registered as part of the same party to another event. Payment status is also transferred.' ); ?></p>

<form action="<?php admin_url( 'edit.php?page=rsvp&post_type=rsvpmaker&event=' . sanitize_text_field($_GET['event']) ); ?>" method="post">
<?php rsvpmaker_nonce(); ?>
<p><select name="move_rsvp"><option value=""><?php esc_html_e( 'Pick Entry', 'rsvpmaker' ); ?></option><?php echo $options; ?></select>

to <select name="move_to">

			<?php

			$future = get_future_events( '', 50 );

			if ( $future ) {

				foreach ( $future as $event ) {

					if ( $event->ID != $_GET['event'] ) {

						printf( '<option value="%d">%s - %s</option>', $event->ID, esc_html( $event->post_title ), esc_html( $event->date ) );
					}
				}
			}

			?>

</select> </p>

<button><?php esc_html_e( 'Move', 'rsvpmaker' ); ?></button>

</form>



			<?php

			if ( ! empty( $owed_list ) ) {

				printf( '<h3>Record Payments</h3><form action="%s" method="post">%s', admin_url( 'edit.php?page=rsvp&post_type=rsvpmaker&event=' . intval( $_GET['event'] ) ), rsvpmaker_nonce('return') );

				echo $owed_list;

				?>

<button><?php esc_html_e( 'Mark Paid', 'rsvpmaker' ); ?></button>

</form>

				<?php

			} // end is admin
		}

		if ( ! empty( $emails ) ) {

			return $emails;
		}

	}
} // end format_rsvp_details



function admin_edit_rsvp( $id, $event ) {

	global $wpdb;

	global $profile;

	global $master_rsvp;

	global $post;

	if ( $id == 0 ) {

		$profile = array( 'yesno' => 1 );

	} else {

		$row = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . 'rsvpmaker WHERE id=' . $id, ARRAY_A );

		$profile = rsvp_row_to_profile( $row );

	}

	$master_rsvp = $id;

	$custom_fields = get_rsvpmaker_custom( $event );

	global $rsvp_options;

	$form = $custom_fields['_rsvp_form'][0];

	printf( '<form action="%s" method="post">', admin_url( 'edit.php?page=rsvp&post_type=rsvpmaker&event=' . $event ) );
	rsvpmaker_nonce();
	echo '<p>';
	?>
	<input name="yesno" type="radio" value="1" <?php echo ( $profile['yesno'] ) ? 'checked="checked"' : ''; ?> /> <?php echo __( 'Yes', 'rsvpmaker' ); ?> <input name="yesno" type="radio" value="0" <?php echo ( ! $profile['yesno'] ) ? 'checked="checked"' : ''; ?> /> 
														<?php
														echo __( 'No', 'rsvpmaker' ) . '</p>';

														$results = get_rsvp_dates( $event );

														if ( $results ) {

															$start = 2;

															$firstrow = null;

															$dateblock = '';

															global $last_time;

															foreach ( $results as $row ) {

																$timeblock = '<span class="time">';

																if ( ! $firstrow ) {

																	$firstrow = $row;
																}

																$last_time = $t = rsvpmaker_strtotime( $row['datetime'] );

																$dateblock .= '<div itemprop="startDate" datetime="' . date( 'c', $t ) . '">';

																$dateblock .= utf8_encode( rsvpmaker_date( $rsvp_options['long_date'], $t ) );

																$dur = $row['duration'];

																$timeblock .= rsvpmaker_date( ' ' . $rsvp_options['time_format'], $t );

																// dchange

																if ( $dur == 'set' ) {

																	$dur = rsvpmaker_strtotime( $row['end_time'] );
																}

																if ( is_numeric( $dur ) ) {

																	$timeblock .= ' <span class="end_time">' . __( 'to', 'rsvpmaker' ) . ' ' . rsvpmaker_date( $rsvp_options['time_format'], $dur ) . '</span>';
																}

																if ( ( $dur != 'allday' ) && ! strpos( $dur, '|' ) ) {

																	$dateblock .= $timeblock . '<span>';
																}

																$dateblock .= "</div>\n";

															}
														}

														echo '<div class="dateblock">' . $dateblock . "\n</div>\n";

														if ( $dur && ( $slotlength = $custom_fields['_rsvp_timeslots'][0] ) ) {

															?>

<div><?php echo __( 'Number of Participants', 'rsvpmaker' ); ?>: <select name="participants">

	<option value="1">1</option>

	<option value="2">2</option>

	<option value="3">3</option>

	<option value="4">4</option>

	<option value="5">5</option>

	<option value="6">6</option>

	<option value="7">7</option>

	<option value="8">8</option>

	<option value="9">9</option>

	<option value="10">10</option>

  </select></div>



<div><?php echo __( 'Choose timeslots', 'rsvpmaker' ); ?></div>

															<?php

															$t = rsvpmaker_strtotime( $firstrow['datetime'] );

															$dur = $firstrow['duration'];

															if ( strpos( $dur, ':' ) ) {

																$dur = rsvpmaker_strtotime( $dur );
															}

															$day = rsvpmaker_date( 'j', $t );

															$month = rsvpmaker_date( 'n', $t );

															$year = date( 'Y', $t );

															$hour = rsvpmaker_date( 'G', $t );

															$minutes = rsvpmaker_date( 'i', $t );

															$slotlength = explode( ':', $slotlength );

															$min_add = $slotlength[0] * 60;

															$min_add = $min_add + $slotlength[1];

															for ( $i = 0; ( $slot = rsvpmaker_mktime( $hour, $minutes + ( $i * $min_add ), 0, $month, $day, $year ) ) < $dur; $i++ ) {

																$sql = 'SELECT SUM(participants) FROM ' . $wpdb->prefix . "rsvp_volunteer_time WHERE time=$slot AND event = $post->ID";

																$signups = ( $signups = $wpdb->get_var( $sql ) ) ? $signups : 0;

																echo '<div><input type="checkbox" name="timeslot[]" value="' . $slot . '" /> ' . rsvpmaker_date( ' ' . $rsvp_options['time_format'], $slot ) . " $signups participants signed up</div>";

															}
														}

														if ( isset( $custom_fields['_per'][0] ) && $custom_fields['_per'][0] ) {

															$pf = '';

															$options = '';

															$per = unserialize( $custom_fields['_per'][0] );

															if ( isset( $custom_fields['_rsvp_count_party'][0] ) && $custom_fields['_rsvp_count_party'][0] ) {

																foreach ( $per['unit'] as $index => $value ) {

																	$price = (float) $per['price'][ $index ];

																	if ( ! $price ) {

																		break;
																	}

																	$display[] = $value . ' @ ' . ( ( $rsvp_options['paypal_currency'] == 'USD' ) ? '$' : $rsvp_options['paypal_currency'] ) . ' ' . number_format( $price, 2, $rsvp_options['currency_decimal'], $rsvp_options['currency_thousands'] );

																}

																$number_prices = ( empty( $display ) ) ? 0 : sizeof( $display );

																if ( $number_prices ) {

																	if ( $number_prices == 1 ) { // don't show options, just one choice

																		printf( '<h3 id="guest_count_pricing"><input type="hidden" name="guest_count_price" value="%s">%s ' . __( 'per person', 'rsvpmaker' ) . '</h3>', 0, esc_html( $display[0] ) );

																	} else {

																		foreach ( $display as $index => $value ) {

																			$s = ( $index == $profile['pricechoice'] ) ? ' selected="selected" ' : '';

																			$options .= sprintf( '<option value="%d" %s>%s</option>', $index, $s, esc_html( $value ) );

																		}

																			printf( '<div id="guest_count_pricing">' . __( 'Options', 'rsvpmaker' ) . ': <select name="guest_count_price">%s</select></div>', $options );

																	}
																}
															} else {

																foreach ( $per['unit'] as $index => $value ) {

																	$price = (float) $per['price'][ $index ];

																	if ( ! $price ) {

																		break;
																	}

																	$pf .= '<div><select name="payingfor[' . $index . ']" class="tickets"><option value="0">0</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option></select><input type="hidden" name="unit[' . $index . ']" value="' . $value . '" />' . $value . ' @ <input type="hidden" name="price[' . $index . ']" value="' . $price . '" />' . ( ( $rsvp_options['paypal_currency'] == 'USD' ) ? '$' : $rsvp_options['paypal_currency'] ) . ' ' . number_format( $price, 2, $rsvp_options['currency_decimal'], $rsvp_options['currency_thousands'] ) . '</div>' . "\n";

																}

																if ( ! empty( $pf ) ) {

																	echo '<h3>' . __( 'Paying For', 'rsvpmaker' ) . '</h3><p>' . $pf . "</p>\n";
																}
															}
														}

														if ( is_numeric( $form ) ) {

															$fpost = get_post( $form );

															$form = $fpost->post_content;

															if ( function_exists( 'do_blocks' ) ) {

																$form = do_blocks( $form );
															}
														}

														echo do_blocks( do_shortcode( $form ) );

														printf( '<input type="hidden" name="rsvp_id" id="rsvp_id" value="%d" /><input type="hidden" id="event" name="event" value="%d" />%s<p><button>Submit</button></p></form>', $id, $event, rsvpmaker_nonce('query') );

														echo '<p>' . __( 'Tip: If you do not have an email address for someone you registered offline, you can use the format firstnamelastname@example.com (example.com is an Internet domain reserved for examples and testing). You will get an error message if you try to leave it blank' ) . '</p>';

														echo rsvp_form_jquery();

}

if ( ! function_exists( 'rsvp_print' ) ) {

	function rsvp_print() {

		if ( isset( $_GET['rsvp_print'] ) && isset( $_GET['page'] ) && is_admin() ) {

			if(!wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
				die("Security error");
			if ( 'word' == $_GET['rsvp_print'] ) {
				global $post;
				$fname = (empty($post->post_name)) ? time() : $post->post_name;
				$fname = apply_filters('rsvp_print_to_word',$fname);
				header( 'Content-Type: application/msword' );
				header( 'Content-disposition: attachment; filename=' . $fname . '.doc' );
			}
				
			$slug = sanitize_text_field($_GET['page']);

			$hookname = get_plugin_page_hookname( $slug, '' );

			echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">

<head>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<title>' . get_admin_page_title() . '</title>

</head>



<body>

';

			do_action( $hookname );

			echo '</body></html>';

			exit();

		}

	}//end rsvp_print()
}// if exists



if ( ! function_exists( 'rsvp_csv' ) ) {

	function rsvp_csv() {

		if ( ! isset( $_GET['rsvp_csv'] ) ) {

			return;
		}

		if ( ! wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key'))) { 
			die( 'Security error' );
		}

		global $wpdb;
		$fields  = array_map('sanitize_text_field',$_GET['fields']);
		$eventid = (int) $_GET['event'];
		$post    = get_post( $eventid );

		header( 'Content-Type: text/csv' );

		header( 'Content-Disposition: attachment;filename="' . $post->post_name . '-' . date( 'Y-m-d-H-i' ) . '.csv"' );

		header( 'Cache-Control: max-age=0' );

		$out = fopen( 'php://output', 'w' );

		fputcsv( $out, $fields );

		$sql = 'SELECT * FROM ' . $wpdb->prefix . "rsvpmaker WHERE event=$eventid ORDER BY yesno DESC, last, first";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$rows = sizeof( $results );

		$phonecells = $phonecol . '1:' . $phonecol . ( $rows + 1 );

		if ( is_array( $results ) ) {

			foreach ( $results as $row ) {

				$index++;

				$row['yesno'] = ( $row['yesno'] ) ? 'YES' : 'NO';

				if ( $row['details'] ) {

					$details = unserialize( $row['details'] );

					$row = array_merge( $row, $details );

				}

				$newrow = array();

				if ( is_array( $fields ) ) {

					foreach ( $fields as $column => $name ) {

						if ( isset( $row[ $name ] ) ) {

							$newrow[] = $row[ $name ];

						} else {
							$newrow[] = '';
						}
					}
				}

				fputcsv( $out, $newrow );

			}
		}

		fclose( $out );

		exit();

	}
} // end rsvp_csv



function rsvp_report_table() {

	?>

<style>

table#rsvptable {

	border-collapse: collapse;

}

table#rsvptable td, table#rsvptable td {

border: thin solid #555;

padding: 3px;

text-align: left;

}

</style>

	<?php

	global $wpdb;

	$fields = array_map('sanitize_text_field',$_GET['fields']);

	$eventid = (int) $_GET['event'];

	$sql = 'SELECT post_title FROM ' . $wpdb->posts . " WHERE ID = $eventid";

	$title = $wpdb->get_var( $sql );

	echo "<h2>$title</h2>\n<table id=\"rsvptable\"><tr>\n";

	// Create new PHPExcel object

	if ( is_array( $fields ) ) {

		foreach ( $fields as $column => $name ) {

			echo "<th>$name</th>";

		}
	}

	echo '</tr>';

	$sql = 'SELECT * FROM ' . $wpdb->prefix . "rsvpmaker WHERE event=$eventid ORDER BY yesno DESC, last, first";

	$results = $wpdb->get_results( $sql, ARRAY_A );

	$rows = sizeof( $results );

	$phonecells = $phonecol . '1:' . $phonecol . ( $rows + 1 );

	if ( is_array( $results ) ) {

		foreach ( $results as $row ) {

			$index++;

			$row['yesno'] = ( $row['yesno'] ) ? 'YES' : 'NO';

			if ( $row['details'] ) {

				$details = unserialize( $row['details'] );

				$row = array_merge( $row, $details );

			}

			echo '<tr>';

			if ( is_array( $fields ) ) {

				foreach ( $fields as $column => $name ) {

					if ( isset( $row[ $name ] ) ) {

						printf( '<td>%s</td>', esc_html( $row[ $name ] ) );

					} else {
						echo '<td></td>';
					}
				}
			}

			echo '</tr>';

		}
	}

		echo '</table>';

}



if ( ! function_exists( 'get_spreadsheet_data' ) ) {

	function get_spreadsheet_data( $eventid ) {

		global $wpdb;

		$sql = 'SELECT yesno,first,last,email, details, note, guestof FROM ' . $wpdb->prefix . "rsvpmaker WHERE event=$eventid ORDER BY yesno DESC, last, first";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $results as $index => $row ) {

			$srow['answer'] = ( $row['yesno'] ) ? 'YES' : 'NO';

			$srow['name'] = $row['first'] . ' ' . $row['last'];

			$details = unserialize( $srow['details'] );

			$srow['address'] = $details['address'] . ' ' . $details['city'] . ' ' . $details['state'] . ' ' . $details['zip'];

			$srow['employment'] = $details['occupation'] . ' ' . $details['company'];

			$srow['email'] = $row['email'];

			$srow['guestof'] = $row['guestof'];

			$srow['note'] = $row['note'];

			$spreadsheet[] = $srow;

		}

		return $spreadsheet;

	}
} // end get spreadsheet data



if ( ! function_exists( 'widgetlink' ) ) {

	function widgetlink( $evdates, $plink, $evtitle ) {

		return sprintf( '<a href="%s">%s</a> %s', esc_attr( $plink ), esc_html( $evtitle ), esc_html( $evdates ) );

	}
} // end widgetlink


if ( ! function_exists( 'rsvpmaker_profile_lookup' ) ) {

	function rsvpmaker_profile_lookup( $email = '' ) {

		global $wpdb;

		$profile = array();

		if ( isset( $_GET['blank'] ) ) {

			return null;
		}

		if ( ! empty( $email ) ) {

			$sql = 'SELECT details FROM ' . $wpdb->prefix . 'rsvpmaker WHERE email LIKE "' . $email . '" ORDER BY id DESC';

			$details = $wpdb->get_var( $sql );

			if ( ! empty( $details ) ) {

				$details = unserialize( $details );

				$profile['email'] = $details['email'];

				$profile['first'] = $details['first'];

				$profile['last'] = $details['last'];

				foreach ( $details as $name => $value ) {

					if ( strpos( $name, 'phone' ) !== false ) {

						$profile[ $name ] = $value;
					}
				}
			}
		} else {

			// if members are registered and logged in, retrieve basic info for profile

			if ( is_user_logged_in() ) {

				global $current_user;

				$profile['email'] = $current_user->user_email;

				$profile['first'] = $current_user->first_name;

				$profile['last'] = $current_user->last_name;

			}
		}

		return $profile;

	}
}



if ( ! function_exists( 'ajax_guest_lookup' ) ) {

	function ajax_guest_lookup() {

		$event = (int) $_GET['ajax_guest_lookup'];

		global $wpdb;

		$sql = 'SELECT first,last,note FROM ' . $wpdb->prefix . "rsvpmaker WHERE event=$event AND yesno=1 ORDER BY id DESC";

		$attendees = $wpdb->get_results( $sql );

		echo '<div class="attendee_list">';

		if ( is_array( $attendees ) ) {

			foreach ( $attendees as $row ) {

				;
				?>

<h3 class="attendee"><?php echo esc_html( $row->first ); ?> <?php echo esc_html( $row->last ); ?></h3>

				<?php

				if ( $row->note ) {
				}

				echo wpautop( $row->note );

			}
		}

		echo '</div>';

		exit();

	}
}



function rsvp_reminder_activation() {

	if ( isset( $_GET['autorenew'] ) ) {

		rsvpautorenew_test();
	}

	if ( ! wp_next_scheduled( 'rsvp_daily_reminder_event' ) ) {

		$hour = 12 - get_option( 'gmt_offset' );

		$t = rsvpmaker_mktime( $hour, 0, 0, date( 'n' ), date( 'j' ), date( 'Y' ) );

		wp_schedule_event( current_time( 'timestamp' ), 'daily', 'rsvp_daily_reminder_event' );

	}

	$active = get_option( 'rsvpmaker_discussion_active' );

	// if stalled, restart email queue process

	if ( $active && ! wp_next_scheduled( 'rsvpmaker_relay_init_hook' ) ) {

		wp_schedule_event( time(), 'doubleminute', 'rsvpmaker_relay_init_hook' );
	}

}



if ( ! function_exists( 'rsvp_daily_reminder' ) ) {

	function rsvp_daily_reminder() {

		rsvpautorenew_test(); // also check for templates that autorenew
		cleanup_rsvpmaker_child_documents(); // delete form and confirmation messages
		rsvpmaker_reminders_nudge(); // make sure events with reminders set are in cron
		rsvpmaker_consistency_check();

		global $wpdb;

		global $rsvp_options;

		$today = rsvpmaker_date( 'Y-m-d' );

		$sql = "SELECT * FROM `$wpdb->postmeta` WHERE `meta_key` LIKE '_rsvp_reminder' AND `meta_value`='$today'";

		if ( $reminders = $wpdb->get_results( $sql ) ) {

			foreach ( $reminders as $reminder ) {

				$post_id = $reminder->post_id;

				$q = "p=$post_id&post_type=rsvpmaker";

				echo "Post $post_id is scheduled for a reminder $q<br />";

				global $post;

				query_posts( $q );

				global $wp_query;

				// treat as single, display rsvp button, not form

				$wp_query->is_single = false;

				the_post();

				if ( $post->post_title ) {

					$event_title = $post->post_title;

					ob_start();

					echo '<h1>';

					the_title();

					echo "</h1>\n<div>\n";

					the_content();

					echo "\n</div>\n";

					$event = ob_get_clean();

					$rsvpto = get_post_meta( $post_id, '_rsvp_to', true );

					$sql = 'SELECT * FROM ' . $wpdb->prefix . "rsvpmaker WHERE event=$post_id AND yesno=1";

					$rsvps = $wpdb->get_results( $sql, ARRAY_A );

					if ( $rsvps ) {

						foreach ( $rsvps as $row ) {

							$notify = $row['email'];

							$row['yesno'] = ( $row['yesno'] ) ? 'YES' : 'NO';

							$notification = '<p>' . __( 'This is an automated reminder that we have you on the RSVP list for the event shown below. If your plans have changed, you can update your response by clicking on the RSVP button again.', 'rsvpmaker' ) . '</p>';

							$notification .= '<h3>' . esc_html( $row['yesno'] . ' ' . $row['first'] . ' ' . $row['last'] . ' ' . $row['email'] );

							if ( $row['guestof'] ) {

								$notification .= esc_html( ' (' . __( 'guest of', 'rsvpmaker' ) . ' ' . $row['guestof'] . ')' );
							}

							$notification .= "</h3>\n";

							$notification .= '<p>';

							if ( $row['details'] ) {

								$details = unserialize( $row['details'] );

								if ( is_array( $details ) ) {

									foreach ( $details as $name => $value ) {

										if ( $value ) {

											$notification .= esc_html( "$name: $value" ) . '<br />';

										}
									}
								}
							}

							if ( $row['note'] ) {

								$notification .= 'note: ' . wp_kses_post( nl2br( $row['note'] ) ) . '<br />';
							}

							$t = rsvpmaker_strtotime( $row['timestamp'] );

							$notification .= 'posted: ' . rsvpmaker_date( $rsvp_options['short_date'], $t );

							$notification .= '</p>';

							$notification .= "<h3>Event Details</h3>\n" . str_replace( '*|EMAIL|*', $notify, $event );

							$notification = wp_kses_post( $notification );

							echo "Notification for $notify<br />$notification";

							$subject = '=?UTF-8?B?' . base64_encode( __( 'Event Reminder for', 'rsvpmaker' ) . ' ' . $event_title ) . '?=';

							if ( isset( $rsvp_options['smtp'] ) && ! empty( $rsvp_options['smtp'] ) ) {

								$mail['subject'] = __( 'Event Reminder for', 'rsvpmaker' ) . ' ' . $event_title;

								$mail['html'] = $notification;

								$mail['to'] = $notify;

								$mail['from'] = $rsvp_to;

								$mail['fromname'] = get_bloginfo( 'name' );

								rsvpmailer( $mail );

							} else {

								$subject = '=?UTF-8?B?' . base64_encode( __( 'Event Reminder for', 'rsvpmaker' ) . ' ' . $event_title ) . '?=';

								mail( $notify, $subject, $notification, "From: $rsvpto\nContent-Type: text/html; charset=UTF-8" );

							}
						}
					}
				}
			}
		} else {
			echo 'none found';
		}

	}
}// end



if ( ! function_exists( 'rsvpguests' ) ) {

	function rsvpguests( $atts ) {

		if ( is_admin() || wp_is_json_request() ) {

			return;
		}

		$label = ( isset( $atts['label'] ) ) ? $atts['label'] : __( 'Guest', 'rsvpmaker' );

		$addmore = ( isset( $atts['addmore'] ) ) ? $atts['addmore'] : __( 'Add more guests', 'rsvpmaker' );

		global $guestextra;

		global $wpdb;

		global $blanks_allowed;

		global $master_rsvp;

		$wpdb->show_errors();

		$output = '';

		$count = 1; // reserve 0 for host

		$max_party = ( isset( $atts['max_party'] ) ) ? (int) $atts['max_party'] : 0;

		if ( isset( $master_rsvp ) && $master_rsvp ) {

			$guestsql = 'SELECT * FROM ' . $wpdb->prefix . 'rsvpmaker WHERE master_rsvp=' . $master_rsvp . ' ORDER BY id';

			if ( $results = $wpdb->get_results( $guestsql, ARRAY_A ) ) {

				foreach ( $results as $row ) {

					$guestprofile = rsvp_row_to_profile( $row );

					$output .= sprintf( '<div class="guest_blank"><p><strong>%s %d</strong></p>', esc_html( $label ), $count ) . "\n";

					$output .= guestfield( array( 'textfield' => 'first' ), $guestprofile, $count );

					$output .= guestfield( array( 'textfield' => 'last' ), $guestprofile, $count );

					if ( is_array( $guestextra ) ) {

						foreach ( $guestextra as $atts ) {

							$output .= guestfield( $atts, $guestprofile, $count );
						}
					}

					$output .= sprintf( '<div><input type="checkbox" name="guestdelete[%s]" value="%s" /> ' . __( 'Delete Guest', 'rsvpmaker' ) . ' %d</div><input type="hidden" name="guest[id][%s]" value="%s">', $row['id'], $row['id'], $count, $count, $row['id'] );

					$output .= '</div>' . "\n";

					$count++;

				}
			}
		}

		$max_guests = $blanks_allowed + $count;

		if ( $max_party ) {

			$max_guests = ( $max_party > $max_guests ) ? $max_guests : $max_party; // use the lower limit
		}

		// now the blank field

		if ( $blanks_allowed < 1 ) {

			return $output . '<p><em>' . __( 'No room for additional guests', 'rsvpmaker' ) . '</em><p>'; // if event is full, no additional guests

		} elseif ( $count > $max_guests ) {

			return $output . '<p><em>' . __( 'No room for additional guests', 'rsvpmaker' ) . '</em><p>'; // limit by # of guests per person

		} elseif ( $max_guests && ( $count >= $max_guests ) ) {

			return $output . '<p><em>' . __( 'No room for additional guests (max per party)', 'rsvpmaker' ) . '</em><p>'; // limit by # of guests per person
		}

			$output .= '<input type="hidden" id="max_guests" value="' . $max_guests . '" />';

			$output .= '<div class="guest_blank" id="first_blank"><p><strong>' . $label . ' ###</strong></p>' . "\n";

			$output .= guestfield( array( 'textfield' => 'first' ), array(), '' );

			$output .= guestfield( array( 'textfield' => 'last' ), array(), '' );

		if ( is_array( $guestextra ) ) {

			foreach ( $guestextra as $atts ) {

				$output .= guestfield( $atts, array(), '' );
			}
		}

			$output .= '</div>' . "\n";

		$output = '<div id="guest_section" tabindex="-1">' . "\n" . $output . '</div>' . '<!-- end of guest section-->';

		if ( $max_guests > ( $count + 1 ) ) {

			$output .= '<p><a href="#guest_section" id="add_guests" name="add_guests">(+) ' . $addmore . "</a><!-- end of guest section--></p>\n";
		}

		$output .= '<script type="text/javascript"> var guestcount =' . $count . '; </script>';

		return $output;

	}
}



if ( ! function_exists( 'rsvpprofiletable' ) ) {

	function rsvpprofiletable( $atts, $content = null ) {

		global $profile;

		if ( ! isset( $atts['show_if_empty'] ) || ! ( isset( $profile[ $atts['show_if_empty'] ] ) && $profile[ $atts['show_if_empty'] ] ) ) {

			return do_shortcode( $content );

		} else {

			$p = get_post_permalink();

			$p .= ( strpos( $p, '?' ) ) ? '&blank=1' : '?blank=1';

			return '

<p id="profiledetails">' . __( 'Profile details on file. To update profile, or RSVP for someone else', 'rsvpmaker' ) . ' <a href="' . $p . '">' . __( 'fetch a blank form', 'rsvpmaker' ) . '</a></p>

<input type="hidden" name="onfile" value="1" />';

		}

	}
}



if ( ! function_exists( 'rsvpfield' ) ) {

	function rsvpfield( $atts ) {

		global $profile;

		global $rsvp_required_field;

		global $guestextra;

		global $current_user;

		// synonyms

		if ( isset( $atts['text'] ) && ! isset( $atts['textfield'] ) ) {
			$atts['textfield'] = $atts['text'];
		}

		if ( isset( $atts['select'] ) && ! isset( $atts['selectfield'] ) ) {
			$atts['selectfield'] = $atts['select'];
		}

		if ( is_admin() && ! isset( $_REQUEST['edit_rsvp'] ) ) {

			$output = '';

			$guestfield = ( isset( $atts['guestfield'] ) ) ? (int) $atts['guestfield'] : 0;

			$guestoptions = array( __( 'main form', 'rsvpmaker' ), __( 'main+guest', 'rsvpmaker' ), __( 'guest form only', 'rsvpmaker' ) );

			$goptions = '';

			foreach ( $guestoptions as $index => $option ) {

				$s = ( $index == $guestfield ) ? ' selected="selected" ' : '';

				$goptions .= '<option value="' . $index . '" ' . $s . '>' . $option . '</option>';

			}

			$private = ( isset( $atts['private'] ) && $atts['private'] ) ? ' checked="checked" ' : '';

			if ( isset( $atts['textfield'] ) ) {

				$field = $atts['textfield'];

				if ( ( $field == 'email' ) || ( $field == 'first' ) || ( $field == 'last' ) ) {

					return;
				}

				if ( strpos( $field, 'hone' ) && empty( $atts['private'] ) ) {

					$private = ' checked="checked" ';
				}

				$label = ucfirst( str_replace( '_', ' ', $field ) );

				global $extrafield;

				$extrafield++;

				$output = '<select name="type' . $extrafield . '" id="type' . $extrafield . '"><option value="text" selected="selected">text</option><option value="hidden">hidden</option><option value="radio">radio</option><option value="select">select</option><option value="checkbox">checkbox</option></select> ' . __( 'Show', 'rsvpmaker' ) . ': <select id="guest' . $extrafield . '" name="guest' . $extrafield . '">' . $goptions . '</select>

<input type="checkbox" id="private' . $extrafield . '" name="private' . $extrafield . '" value="1" ' . $private . ' /> ' . __( 'private', 'rsvpmaker' ) . '

<br /><input type="text" name="extra' . $extrafield . '" id="extra' . $extrafield . '" value="' . esc_attr( $label ) . '"  class="text ui-widget-content ui-corner-all" />';

			}

			if ( isset( $atts['hidden'] ) ) {

				$field = $atts['hidden'];

				if ( ( $field == 'email' ) || ( $field == 'email' ) || ( $field == 'email' ) ) {

					return;
				}

				$label = ucfirst( str_replace( '_', ' ', $field ) );

				global $extrafield;

				$extrafield++;

				$output = '<select id="type' . $extrafield . '"><option value="text">text</option><option value="hidden" selected="selected">hidden</option><option value="radio">radio</option><option value="select">select</option><option value="checkbox">checkbox</option></select><input type="hidden" id="guest' . $extrafield . '" />

<input type="hidden" id="private' . $extrafield . '" name="private' . $extrafield . '" /> 

<br /><input type="text" id="extra' . $extrafield . '" value="' . esc_attr( $label ) . '"  class="text ui-widget-content ui-corner-all" />';

			}

			if ( isset( $atts['radio'] ) ) {

				$field = $atts['radio'];

				if ( ( $field == 'email' ) || ( $field == 'email' ) || ( $field == 'email' ) ) {

					return;
				}

				$label = ucfirst( str_replace( '_', ' ', $field ) );

				global $extrafield;

				$extrafield++;

				$output = '<select id="type' . $extrafield . '"><option value="text">text</option><option value="hidden">hidden</option><option value="radio"  selected="selected">radio</option><option value="select">select</option><option value="checkbox">checkbox</option></select> ' . __( 'Show', 'rsvpmaker' ) . ': <select id="guest' . $extrafield . '" name="guest' . $extrafield . '">' . $goptions . '</select>

<input type="checkbox" id="private' . $extrafield . '" name="private' . $extrafield . '" value="1" ' . $private . ' /> ' . __( 'private', 'rsvpmaker' ) . '

<br /><input type="text" id="extra' . $extrafield . '" value="' . esc_attr( $label ) . ':' . esc_attr( $atts['options'] ) . '"  class="text ui-widget-content ui-corner-all" />';

			}

			if ( isset( $atts['selectfield'] ) ) {

				$field = $atts['selectfield'];

				if ( ( $field == 'email' ) || ( $field == 'email' ) || ( $field == 'email' ) ) {

					return;
				}

				if ( strpos( $field, 'hone' ) && empty( $atts['private'] ) ) {

					$private = ' checked="checked" ';
				}

				$label = ucfirst( str_replace( '_', ' ', $field ) );

				global $extrafield;

				$extrafield++;

				$output = '<select id="type' . $extrafield . '"><option value="text">text</option><option value="hidden">hidden</option><option value="radio">radio</option><option value="select" selected="selected">select</option><option value="checkbox">checkbox</option></select> 

' . __( 'Show', 'rsvpmaker' ) . ': <select id="guest' . $extrafield . '" name="guest' . $extrafield . '">' . $goptions . '</select> <input type="checkbox" id="private' . $extrafield . '" name="private' . $extrafield . '" value="1" ' . $private . ' /> ' . __( 'private', 'rsvpmaker' ) . '		

<br /><input type="text" id="extra' . $extrafield . '" value="' . esc_attr( $label . ':' . $atts['options'] ) . '"  class="text ui-widget-content ui-corner-all" />';

			}

			return $output;

		}

		// front end behavior

		if ( isset( $atts['textfield'] ) ) {

			$field = str_replace( ' ', '_', $atts['textfield'] );// preg_replace('/[^a-zA-Z0-9_]/','_',$atts["textfield"]);

			$meta = ( is_user_logged_in() ) ? get_user_meta( $current_user->ID, $field, true ) : '';

			$profile[ $field ] = ( isset( $profile[ $field ] ) ) ? $profile[ $field ] : $meta;

			if ( ! is_admin() && ! empty( $profile[ $field ] ) && isset( $atts['private'] ) && $atts['private'] ) {

				$output = '<span  class="onfile ' . $field . '" >' . __( 'private data on file', 'rsvpmaker' ) . '</span>';

			} else {

				$size = ( isset( $atts['size'] ) ) ? ' size="' . $atts['size'] . '" ' : '';

				$data = ( isset( $profile[ $field ] ) ) ? ' value="' . $profile[ $field ] . '" ' : '';

				$output = '<input  class="' . $field . '" type="text" name="profile[' . $field . ']" id="' . $field . '" ' . $size . $data . ' />';

			}
		}

		if ( isset( $atts['hidden'] ) ) {

			$field = $atts['hidden'];

			$meta = ( is_user_logged_in() ) ? get_user_meta( $current_user->ID, $field, true ) : '';

			$profile[ $field ] = ( isset( $profile[ $field ] ) ) ? $profile[ $field ] : $meta;

			$size = ( isset( $atts['size'] ) ) ? ' size="' . $atts['size'] . '" ' : '';

			$data = ( isset( $profile[ $field ] ) ) ? ' value="' . $profile[ $field ] . '" ' : '';

			$output = '<input  class="' . $field . '" type="hidden" name="profile[' . $field . ']" id="' . $field . '" ' . $size . $data . ' />';

		} elseif ( isset( $atts['selectfield'] ) ) {

			$field = $atts['selectfield'];

			$meta = ( is_user_logged_in() ) ? get_user_meta( $current_user->ID, $field, true ) : '';

			$profile[ $field ] = ( isset( $profile[ $field ] ) ) ? $profile[ $field ] : $meta;

			if ( ! is_admin() && ! empty( $profile[ $field ] ) && isset( $atts['private'] ) && $atts['private'] ) {

				return '<span  class="onfile ' . $field . '" >' . __( 'private data on file', 'rsvpmaker' ) . '</span>';
			}

			$selected = ( isset( $atts['selected'] ) ) ? trim( $atts['selected'] ) : '';

			if ( ! empty( $profile[ $field ] ) ) {

				$selected = $profile[ $field ];
			}

			$output = '<span  class="' . $field . '"><select class="' . $field . '" name="profile[' . $field . ']" id="' . $field . '" >' . "\n";

			if ( isset( $atts['options'] ) ) {

				$o = explode( ',', $atts['options'] );

				foreach ( $o as $i ) {

					$i = trim( $i );

					$s = ( $selected == $i ) ? ' selected="selected" ' : '';

					$output .= '<option value="' . $i . '" ' . $s . '>' . $i . '</option>' . "\n";

				}
			}

			$output .= '</select></span>' . "\n";

		} elseif ( isset( $atts['checkbox'] ) ) {

			$field = $atts['checkbox'];

			$value = $atts['value'];

			$ischecked = ( isset( $atts['checked'] ) ) ? ' checked="checked" ' : '';

			$meta = ( is_user_logged_in() ) ? get_user_meta( $current_user->ID, $field, true ) : '';

			$profile[ $field ] = ( isset( $profile[ $field ] ) ) ? $profile[ $field ] : $meta;

			if ( ! empty( $profile[ $field ] ) && isset( $atts['private'] ) && $atts['private'] ) {

				return '<span  class="onfile ' . $field . '" >' . __( 'private data on file', 'rsvpmaker' ) . '</span>';
			}

			if ( isset( $profile[ $field ] ) ) {

				$ischecked = ' checked="checked" ';
			}

			$output = '<input class="' . $field . '" type="checkbox" name="profile[' . $field . ']" id="' . $field . '" value="' . $value . '" ' . $ischecked . '/>';

		} elseif ( isset( $atts['radio'] ) ) {

			$field = $atts['radio'];

			$meta = ( is_user_logged_in() ) ? get_user_meta( $current_user->ID, $field, true ) : '';

			$profile[ $field ] = ( isset( $profile[ $field ] ) ) ? $profile[ $field ] : $meta;

			if ( ! empty( $profile[ $field ] ) && isset( $atts['private'] ) && $atts['private'] ) {

				return '<span  class="onfile ' . $field . '" >' . __( 'private data on file', 'rsvpmaker' ) . '</span>';
			}

			$sep = ( isset( $atts['sep'] ) ) ? $atts['sep'] : ' ';

			$checked = ( isset( $atts['checked'] ) ) ? trim( $atts['checked'] ) : '';

			if ( isset( $profile[ $field ] ) ) {

				$checked = $profile[ $field ];
			}

			if ( isset( $atts['options'] ) ) {

				$o = explode( ',', $atts['options'] );

				$radio = array();

				foreach ( $o as $i ) {

					$i = trim( $i );

					$ischecked = ( $checked == $i ) ? ' checked="checked" ' : '';

					$radio[] = '<span  class="' . $field . '"><input class="' . $field . '" type="radio" name="profile[' . $field . ']" id="' . $field . $i . '" class="' . $field . '"  value="' . $i . '"  ' . $ischecked . '/> ' . $i . '</span> ';

				}
			}

			$output = implode( $sep, $radio );

		}

		if ( isset( $atts['required'] ) || isset( $atts['require'] ) ) {

			$output = '<span class="required">' . $output . '</span>';

			$rsvp_required_field[ $field ] = $field;

		}

		if ( isset( $atts['demo'] ) ) {

			$demo = "<div>Shortcode:</div>\n<p><strong>[</strong>rsvpfield";

			foreach ( $atts as $name => $value ) {

				if ( $name == 'demo' ) {

					continue;
				}

				$demo .= ' ' . $name . '="' . $value . '"';

			}

			$demo .= "<strong>]</strong></p>\n";

			$demo .= "<div>HTML:</div>\n<pre>" . htmlentities( $output ) . "</pre>\n";

			$demo .= "<div>Profile:</div>\n<pre>" . var_export( $profile, true ) . "</pre>\n";

			$demo .= "<div>Display:</div>\n<p>";

			$output = $demo . $output . '</p>';

		}

		if ( isset( $atts['guestfield'] ) && $atts['guestfield'] ) {

			$guestextra[ $field ] = $atts;

			if ( $atts['guestfield'] == 2 ) {

				return; // guest only don't display on main form
			}
		}

		if ( $field == 'email' ) {

			$output .= '<div id="rsvp_email_lookup"></div>';
		}

		return $output;

	}
}



if ( ! function_exists( 'guestfield' ) ) {

	function guestfield( $atts, $profile, $count ) {

		global $fieldcount;

		if ( ! $fieldcount ) {

			$fieldcount = 1;
		}

		// synonyms

		if ( isset( $atts['text'] ) && ! isset( $atts['textfield'] ) ) {
			$atts['textfield'] = $atts['text'];
		}

		if ( isset( $atts['select'] ) && ! isset( $atts['selectfield'] ) ) {
			$atts['selectfield'] = $atts['select'];
		}

		if ( isset( $atts['textfield'] ) ) {

			$field = $atts['textfield'];

			$label = ( isset( $atts['label'] ) ) ? $atts['label'] : ucfirst( str_replace( '_', ' ', $field ) );

			$firstlabel = __( 'First', 'rsvpmaker' );

			$lastlabel = __( 'Last', 'rsvpmaker' );

			if ( ( $label == 'First' ) && ( $label != $firstlabel ) ) {

				$label = str_replace( 'First', $firstlabel, $label );
			}

			if ( ( $label == 'Last' ) && ( $label != $lastlabel ) ) {

				$label = str_replace( 'Last', $lastlabel, $label );
			}

			$size = ( isset( $atts['size'] ) ) ? ' size="' . $atts['size'] . '" ' : '';

			$data = ( isset( $profile[ $field ] ) ) ? ' value="' . $profile[ $field ] . '" ' : '';

			$output = '<div class="' . $field . '"><label>' . $label . ':</label> <input type="text" name="guest[' . $field . '][' . $count . ']" id="' . $field . $fieldcount++ . '" ' . $size . $data . '  class="' . $field . '" /></div>';

		} elseif ( isset( $atts['selectfield'] ) ) {

			$field = $atts['selectfield'];

			$label = ( isset( $atts['label'] ) ) ? $atts['label'] : ucfirst( str_replace( '_', ' ', $field ) );

			$selected = ( isset( $atts['selected'] ) ) ? trim( $atts['selected'] ) : '';

			if ( isset( $profile[ $field ] ) ) {

				$selected = $profile[ $field ];
			}

			$output = '<div class="' . $field . '"><label>' . $label . ':</label> <select  class="' . $field . '" name="guest[' . $field . '][' . $count . ']" id="' . $field . $fieldcount++ . '" >' . "\n";

			if ( isset( $atts['options'] ) ) {

				$o = explode( ',', $atts['options'] );

				foreach ( $o as $i ) {

					$i = trim( $i );

					$s = ( $selected == $i ) ? ' selected="selected" ' : '';

					$output .= '<option value="' . $i . '" ' . $s . '>' . $i . '</option>' . "\n";

				}
			}

			$output .= '</select></div>' . "\n";

		} elseif ( isset( $atts['radio'] ) ) {

			$field = $atts['radio'];

			$label = ( isset( $atts['label'] ) ) ? $atts['label'] : ucfirst( str_replace( '_', ' ', $field ) );

			$sep = ( isset( $atts['sep'] ) ) ? $atts['sep'] : ' ';

			$checked = ( isset( $atts['checked'] ) ) ? trim( $atts['checked'] ) : '';

			if ( isset( $profile[ $field ] ) ) {

				$checked = $profile[ $field ];
			}

			if ( isset( $atts['options'] ) ) {

				$o = explode( ',', $atts['options'] );

				foreach ( $o as $i ) {

					$i = trim( $i );

					$ischecked = ( $checked == $i ) ? ' checked="checked" ' : '';

					$radio[] = '<input  class="' . $field . '" type="radio" name="guest[' . $field . '][' . $count . ']" id="' . $field . $i . $fieldcount++ . '" class="' . $field . '"  value="' . $i . '"  ' . $ischecked . '/> ' . $i . ' ';

				}
			}

			$output = '<div  class="' . $field . '"><label>' . $label . ':</label> ' . implode( $sep, $radio ) . '</div>';

		}

		return $output;

	}
}



if ( ! function_exists( 'rsvpnote' ) ) {

	function rsvpnote() {

		global $rsvp_row;

		return ( isset( $rsvp_row->note ) ) ? $rsvp_row->note : '';

	}
}



if ( ! function_exists( 'date_title' ) ) {

	function date_title( $title, $sep = '&raquo;', $seplocation = 'left' ) {

		global $post;

		global $wpdb;

		if ( empty( $post->post_type ) ) {

			return $title;
		}

		if ( $post->post_type == 'rsvpmaker' ) {

			// get first date associated with event

			$sql = 'SELECT meta_value FROM ' . $wpdb->postmeta . " WHERE meta_key='_rsvp_dates' AND post_id = $post->ID ORDER BY meta_value";

			$dt = $wpdb->get_var( $sql );

			$title .= rsvpmaker_date( 'F jS', rsvpmaker_strtotime( $dt ) );

			if ( $seplocation == 'right' ) {

				$title .= " $sep ";

			} else {
				$title = " $sep $title ";
			}
		}

		return $title;

	}
}

add_filter( 'wp_title', 'date_title', 1, 3 );

if ( ! function_exists( 'rsvpmaker_template_list' ) ) {

	function rsvpmaker_template_list() {

		global $rsvp_options, $wpdb, $current_user;

		?>

<div class="wrap"> 

	<div id="icon-edit" class="icon32"><br /></div>

<h2>
		<?php
		esc_html_e( 'Event Templates', 'rsvpmaker' );

		printf( ' <a href="%s"  class="add-new-h2">%s</a>', admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_setup&new_template=1' ), __( 'New Template', 'rsvpmaker' ) );

		?>
  </h2> 

		<?php

		if ( ! empty( $_POST['import_shared_template'] )  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key'))  ) {
			$url = sanitize_text_field( $_POST['import_shared_template'] );
			printf( '<p>Importing %s</p>', $url );
			$duplicate = $wpdb->get_var( "SELECT ID FROM $wpdb->posts JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id  WHERE meta_key='template_imported_from' AND meta_value='$url' " );
			$response  = wp_remote_get( $url );

			if ( empty( $duplicate ) && is_array( $response ) && ! is_wp_error( $response ) ) {
				$headers = $response['headers']; // array of http header lines
				$body    = $response['body']; // use the content
				$data    = json_decode( $body );
				// print_r($data);
				$newpost['post_type']    = 'rsvpmaker';
				$newpost['post_status']  = 'publish';
				$newpost['post_author']  = $current_user->ID;
				$newpost['post_title']   = $data->post_title;
				$newpost['post_content'] = $data->post_content;
				$id                      = wp_insert_post( $newpost );
				rsvpmaker_set_template_defaults( $id );
				update_post_meta( $id, 'template_imported_from', $url );
			}
		}

		if ( ! empty( $_POST['share_template'] )  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {
			update_post_meta( $_POST['share_template'], 'rsvpmaker_shared_template', true );
		}

		if ( ! empty( $_POST['override'] )  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {

			$override = (int) $_POST['override'];

			$overridden = (int) $_POST['overridden'];

			$opost = get_post( $override );

			$target = get_post( $overridden );

			$sk = get_template_sked( $overridden );

			if ( $sk ) {

				wp_update_post(
					array(
						'ID'         => $override,
						'post_title' => $opost->post_title . ' (backup)',
					)
				);
			}

			$newpost = array(
				'ID'           => $overridden,
				'post_title'   => $opost->post_title,
				'post_content' => $opost->post_content,
				'post_name'    => $target->post_name,
			);

			wp_update_post( $newpost );
			$sql = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key='_detached_from_template' WHERE meta_key='_meet_recur' AND post_id=%d", $overridden );
			$wpdb->query( $sql );
			update_post_meta( $overridden, '_meet_recur', $override );

			printf( '<div class="updated notice notice-success">Applied "%s" template: <a href="%s">View</a> | <a href="%s">Edit</a></div>', $opost->post_title, get_permalink( $overridden ), admin_url( 'post.php?action=edit&post=' . $overridden ) );

			if(isset($_POST['copymeta']))
			{
				foreach($_POST['copymeta'] as $key)
				{
					$key = sanitize_text_field($key);
					if(empty($keysql))
						$keysql = '';
					else
						$keysql .= ' OR ';
					$keysql .= "  meta_key LIKE '$key%' ";//match multiple reminder messages
				}

				$sql = "select * from $wpdb->postmeta WHERE post_id=" . $override ." AND ($keysql) ";
				echo $sql;
				$results = $wpdb->get_results( $sql );
	
				if ( is_array( $results ) ) {
	
					foreach ( $results as $row ) {	
							update_post_meta( $overridden, $row->meta_key, $row->meta_value );
							$copied[] = $row->meta_key;
					}
				}

			}


			if ( ! empty( $copied ) ) {

				printf( '<p>Settings copied: %s</p>', implode( ', ', $copied ) );
			}
		}

		if ( isset( $_GET['override_template'] ) || ( isset( $_GET['t'] ) && isset( $_GET['overconfirm'] ) ) ) {

			$t = ( isset( $_GET['override_template'] ) ) ? (int) $_GET['override_template'] : (int) $_GET['t'];

			$e = (int) $_GET['event'];

			$ts = get_rsvp_date( $e );

			if ( isset( $_GET['overconfirm'] ) ) {

				$event = get_post( $e );

				$newpost = array(
					'ID'           => $t,
					'post_title'   => $event->post_title,
					'post_content' => $event->post_content,
				);

				wp_update_post( $newpost );

				printf( '<h1>Template updated based on contents of event for %s</h1>', rsvpmaker_date( $rsvp_options['long_date'], rsvpmaker_strtotime( $ts ) ) );

				$sql = "select * from $wpdb->postmeta WHERE post_id=" . $e;

				$results = $wpdb->get_results( $sql );

				$docopy = array( '_add_timezone', '_convert_timezone', '_calendar_icons', 'tm_sidebar', 'sidebar_officers' );

				if ( is_array( $results ) ) {

					foreach ( $results as $row ) {

						if ( ( strpos( $row->meta_key, 'rsvp' ) && ( $row->meta_key != '_rsvp_dates' ) ) || ( in_array( $row->meta_key, $docopy ) ) ) {

							update_post_meta( $t, $row->meta_key, $row->meta_value );

							$copied[] = $row->meta_key;

						}
					}
				}

				if ( ! empty( $copied ) ) {

					printf( '<p>Settings copied: %s</p>', implode( ', ', $copied ) );
				}
			} else {

				printf( '<h1 style="color: red;">Update Template?</h1><p>Click &quot;Confirm&quot; to override template with the contents of your %s event<p><form method="get" action="%s"><input type="hidden" name="post_type" value="rsvpmaker" /><input type="hidden" name="page" value="rsvpmaker_template_list" /><input type="hidden" name="t" value="%d" /><input type="hidden" name="event" value="%d" /><input type="hidden" name="overconfirm" value="1" /><button>Confirm</button>%s</form> ', rsvpmaker_date( $rsvp_options['long_date'], rsvpmaker_strtotime( $ts ) ), admin_url( 'edit.php' ), $t, $e, rsvpmaker_nonce('return') );

			}
		}

		if ( isset( $_POST['event_to_template'] )  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {

			$e = (int) $_POST['event_to_template'];

			$ts = get_rsvp_date( $e );

			$tsexplode = preg_split( '/[\s:]/', $ts );

			$event = get_post( $e );

			$newpost = array(
				'post_title'   => $event->post_title,
				'post_content' => $event->post_content,
				'post_type'    => 'rsvpmaker',
				'post_author'  => $current_user->ID,
				'post_status'  => 'publish',
			);

			$t = wp_insert_post( $newpost );

			array(
				'week'      => array( 0 ),
				'dayofweek' => array( 0 ),
				'hour'      => $tsexplode[1],
				'minutes'   => $tsexplode[2],
			);

			new_template_schedule( $t, $template );

			printf( '<h1>Template updated based on contents of event for %s</h1>', rsvpmaker_date( $rsvp_options['long_date'], rsvpmaker_strtotime( $ts ) ) );

			$sql = "select * from $wpdb->postmeta WHERE post_id=" . $e;

			$results = $wpdb->get_results( $sql );

			$docopy = array( '_add_timezone', '_convert_timezone', '_calendar_icons', 'tm_sidebar', 'sidebar_officers' );

			if ( is_array( $results ) ) {

				foreach ( $results as $row ) {

					if ( ( strpos( $row->meta_key, 'rsvp' ) && ( $row->meta_key != '_rsvp_dates' ) ) || ( in_array( $row->meta_key, $docopy ) ) ) {

						update_post_meta( $t, $row->meta_key, $row->meta_value );

						$copied[] = $row->meta_key;

					}
				}
			}

			if ( ! empty( $copied ) ) {

				printf( '<p>Settings copied: %s</p>', implode( ', ', $copied ) );
			}
		}

		if ( empty( $_REQUEST['t'] ) ) {

			printf( '<h3>Add One or More Events Based on a Template</h3><form method="get" action="%s"><input type="hidden" name="post_type" value="rsvpmaker" />%s <select name="page"><option value="rsvpmaker_setup">%s</option><option value="rsvpmaker_template_list">%s</option></select><br /><br />%s %s<br >%s %s</form>', admin_url( 'edit.php' ), __( 'Add', 'rsvpmaker' ), __( 'One event', 'rsvpmaker' ), __( 'Multiple events', 'rsvpmaker' ), __( 'based on', 'rsvpmaker' ), rsvpmaker_templates_dropdown( 't' ), get_submit_button( 'Submit' ), rsvpmaker_nonce('return') );
		}

		do_action( 'rsvpmaker_template_list_top' );

		if ( isset( $_GET['t'] ) ) {

			$t = (int) $_GET['t'];

			rsvp_template_checkboxes( $t );

		}

		$dayarray = array( __( 'Sunday', 'rsvpmaker' ), __( 'Monday', 'rsvpmaker' ), __( 'Tuesday', 'rsvpmaker' ), __( 'Wednesday', 'rsvpmaker' ), __( 'Thursday', 'rsvpmaker' ), __( 'Friday', 'rsvpmaker' ), __( 'Saturday', 'rsvpmaker' ) );

		$weekarray = array( __( 'Varies', 'rsvpmaker' ), __( 'First', 'rsvpmaker' ), __( 'Second', 'rsvpmaker' ), __( 'Third', 'rsvpmaker' ), __( 'Fourth', 'rsvpmaker' ), __( 'Last', 'rsvpmaker' ), __( 'Every', 'rsvpmaker' ) );

		global $wpdb;

		$wpdb->show_errors();

		global $current_user;

		global $rsvp_options;

		$current_template = $event_options = $template_options = '';

		$template_override = '';

		if ( isset( $_GET['restore'] ) ) {

			echo '<div class="notice notice-info">';

			$r = (int) $_GET['restore'];

			$sked['week'] = array( 6 );

			$sked['dayofweek'] = array();

			$sked['hour'] = $rsvp_options['defaulthour'];

			$sked['minutes'] = $rsvp_options['defaultmin'];

			if ( $_GET['specimen'] ) {

				$date = get_rsvp_date( $_GET['specimen'] );

				if ( $date ) {

					$t = strtotime( $date );

					$sked['dayofweek'] = array( date( 'w', $t ) );

					$sked['hour'] = date( 'h', $t );

					$sked['minutes'] = date( 'i', $t );

				}
			}

			$sked['duration'] = '';

			$sked['stop'] = '';

			new_template_schedule( $r, $sked );

			echo '<p>Restoring template. Edit to fix schedule parameters.</p></div>';

		}

		$results = rsvpmaker_get_templates('',true);

		if ( $results ) {

			printf( '<h3>Templates</h3><table  class="wp-list-table widefat fixed posts" cellspacing="0"><thead><tr><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr></thead><tbody>', __( 'Title', 'rsvpmaker' ), __( 'Schedule', 'rsvpmaker' ), __( 'Projected Dates', 'rsvpmaker' ), __( 'Event', 'rsvpmaker' ) );

			foreach ( $results as $post ) {

				if($post->post_status == 'draft')
					$post->post_title .= ' (draft)';

				if ( isset( $_GET['apply_current'] ) && ( $post->ID == $_GET['apply_current'] ) ) {

					$current_template = '<option value="' . $post->ID . '">Current Template: ' . $post->post_title . '</option>';
				}

				$sked = get_template_sked( $post->ID );

				// backward compatability

				if ( is_array( $sked['week'] ) ) {

					$weeks = $sked['week'];

					$dows = ( empty( $sked['dayofweek'] ) ) ? 0 : $sked['dayofweek'];

				} else {

					$weeks = array();

					$dows = array();

					$weeks[0] = ( isset( $sked['week'] ) ) ? $sked['week'] : 0;

					$dows[0] = ( isset( $sked['dayofweek'] ) ) ? $sked['dayofweek'] : 0;

				}

				$dayarray = array( __( 'Sunday', 'rsvpmaker' ), __( 'Monday', 'rsvpmaker' ), __( 'Tuesday', 'rsvpmaker' ), __( 'Wednesday', 'rsvpmaker' ), __( 'Thursday', 'rsvpmaker' ), __( 'Friday', 'rsvpmaker' ), __( 'Saturday', 'rsvpmaker' ) );

				$weekarray = array( __( 'Varies', 'rsvpmaker' ), __( 'First', 'rsvpmaker' ), __( 'Second', 'rsvpmaker' ), __( 'Third', 'rsvpmaker' ), __( 'Fourth', 'rsvpmaker' ), __( 'Last', 'rsvpmaker' ), __( 'Every', 'rsvpmaker' ) );

				if ( empty( $sked['week'] ) || ( (int) $sked['week'][0] == 0 ) ) {

					$s = __( 'Schedule Varies', 'rsvpmaker' );

				} else {

					foreach ( $weeks as $week ) {

						if ( empty( $s ) ) {

							$s = '';

						} else {
							$s .= '/ ';
						}

						$s .= $weekarray[ (int) $week ] . ' ';

					}

					if ( ! empty( $dows ) && is_array( $dows ) ) {

						foreach ( $dows as $dow ) {

							if ( $dow > -1 ) {

								$s .= $dayarray[ (int) $dow ] . ' ';
							}
						}
					}

					if ( empty( $sked['hour'] ) || empty( $sked['minutes'] ) ) {

						$time = '';

					} else {

						$time = rsvpmaker_strtotime( $sked['hour'] . ':' . $sked['minutes'] );

						$s .= ' ' . rsvpmaker_date( $rsvp_options['time_format'], $time );

					}
				}

				if(get_post_meta( $post->ID, 'rsvpautorenew', true ) )
					$s .= '<br><strong>'.__('Set to automatically add dates','rsvpmaker').'</strong>';

				$eds = get_additional_editors( $post->ID );

				if ( ( $post->post_author == $current_user->ID ) || in_array( $current_user->ID, $eds ) || current_user_can( 'edit_post', $post->ID ) ) {

					$template_edit_url = admin_url( 'post.php?action=edit&post=' . intval( $post->ID ) );

					$title = sprintf( '<a href="%s">%s</a>', esc_attr( $template_edit_url ), esc_html( $post->post_title ) );

					if ( strpos( $post->post_content, '[toastmaster' ) && function_exists( 'agenda_setup_url' ) ) { // rsvpmaker for toastmasters

						$title .= sprintf( ' (<a href="%s">Toastmasters %s</a>)', agenda_setup_url( $post->ID ), __( 'Agenda Setup', 'rsvptoast' ) );
					}

					$template_options .= sprintf( '<option value="%d">%s</option>', $post->ID, esc_html( $post->post_title ) );

					$template_override .= sprintf( '<option value="%d">APPLY TO TEMPLATE: %s</option>', $post->ID, esc_html( $post->post_title ) );

					$template_recur_url = admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&t=' . intval( $post->ID ) );

					$schedoptions = sprintf( ' (<a href="%s">Options</a>)', admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_details&post_id=' ) . intval( $post->ID ) );

					printf( '<tr><td>%s</td><td>%s</td><td><a href="%s">' . __( 'Create/Update', 'rsvpmaker' ) . '</a></td><td>%s</td></tr>' . "\n", wp_kses_post( $title ) . wp_kses_post( $schedoptions ), $s, esc_attr( $template_recur_url ), next_or_recent( $post->ID ) );

				} else {

					$title = $post->post_title;

					printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>' . "\n", esc_html( $title ), $s, __( 'Not an editor', 'rsvpmaker' ), next_or_recent( $post->ID ) );

				}

				$s = '';

			}

			echo '</tbody></table>';

			printf( '<p><a href="%s">See all templates (including drafts)</a></p>', admin_url( 'edit.php?post_type=rsvpmaker&rsvpsort=templates' ) );

			if ( isset( $template_options ) ) {

				echo '<div id="applytemplate"></div><h3>Apply Template to Existing Event</h3>';

				$target_id = isset( $_GET['apply_target'] ) ? (int) $_GET['apply_target'] : 0;

				if ( $target_id ) {

					$event = get_rsvp_event( 'ID = ' . $target_id );

					if ( ! empty( $event ) ) {

						$event_options .= sprintf( '<option value="%d" selected="selected">%s %s</option>', $event->ID, esc_html( $event->post_title ), esc_html( $event->datetime ) );
					}
				}

				$current_template .= '<option value="0">Choose Template</option>';

				$sql = "SELECT *, $wpdb->posts.ID as postID, meta_value as datetime

FROM `" . $wpdb->postmeta . "`

JOIN $wpdb->posts ON " . $wpdb->postmeta . ".post_id = $wpdb->posts.ID AND meta_key='_rsvp_dates'

WHERE meta_value >= '" . get_sql_curdate() . "' AND $wpdb->posts.post_status = 'publish'

ORDER BY meta_value LIMIT 0,100";

				$results = $wpdb->get_results( $sql );

				if ( is_array( $results ) ) {

					foreach ( $results as $r ) {

						$event_options .= sprintf( '<option value="%d">%s %s</option>', $r->postID, esc_html( $r->post_title ), $r->datetime );

					}
				}

				$action = admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list' );
	
				printf( '<form method="post" action="%s"><p>Apply <select name="override">%s</select> to <select name="overridden">%s</select></p><p>Copy settings:</p>', $action, $current_template . $template_options, $event_options . $template_override );
				printf('<div><input type="checkbox" value="_add_timezone" name="copymeta[]">  %s</div>',__('Show Timezone','rsvpmaker'));
				printf('<div><input type="checkbox" value="_convert_timezone" name="copymeta[]">  %s</div>',__('Convert Timezone','rsvpmaker'));
				printf('<div><input type="checkbox" value="_calendar_icons" name="copymeta[]">  %s</div>',__('Show Calendar Icons','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_on" name="copymeta[]">  %s</div>',__('Collect RSVPs on/off','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_instructions" name="copymeta[]">  %s</div>',__('RSVP Instructions','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_to" name="copymeta[]">  %s</div>',__('Email for RSVP Notifications','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_rsvpmaker_send_confirmation_email" name="copymeta[]">  %s</div>',__('Send Confirmation Email on/off','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_confirm" name="copymeta[]">  %s</div>',__('Confirmation Email Message','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_confirmation_include_event" name="copymeta[]">  %s</div>',__('Include Event Details in Confirmation','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_confirmation_after_payment" name="copymeta[]">  %s</div>',__('Send Confirmation After Payment on/off','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_reminder_" name="copymeta[]">  %s</div>',__('Reminder Email Messages','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_timezone_string" name="copymeta[]">  %s</div>',__('Timezone','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_count" name="copymeta[]">  %s</div>',__('Show RSVP Count Publicly','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_show_attendees" name="copymeta[]">  %s</div>',__('Show Attendees Publicly','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_login_required" name="copymeta[]">  %s</div>',__('Login Required','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_max" name="copymeta[]">  %s</div>',__('Maximum Number of Attendees','rsvpmaker'));
				printf('<div><input type="checkbox" value="_per" name="copymeta[]">  %s</div>',__('Pricing','rsvpmaker'));
				printf('<div><input type="checkbox" value="_rsvp_count_party" name="copymeta[]">  %s</div>',__('Count Members of Party for Event Pricing','rsvpmaker'));
				submit_button();
				rsvpmaker_nonce();
				echo '</form>';

			}
		}

		$event_options = '';

		$sql = "SELECT *, $wpdb->posts.ID as postID, meta_value as datetime

FROM `" . $wpdb->postmeta . "`

JOIN $wpdb->posts ON " . $wpdb->postmeta . ".post_id = $wpdb->posts.ID AND meta_key='_rsvp_dates'

WHERE meta_value >= DATE_SUB('" . get_sql_curdate() . "',INTERVAL 3 MONTH) AND $wpdb->posts.post_status = 'publish'

ORDER BY meta_value LIMIT 0,100";

		$results = $wpdb->get_results( $sql );

		if ( is_array( $results ) ) {

			foreach ( $results as $r ) {

				$event_options .= sprintf( '<option value="%d">%s %s</option>', $r->postID, esc_html( $r->post_title ), $r->datetime );

			}
		}

		$action = admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list' );

		echo '<h3>Create Template Based on Existing Event</h3>';

		printf(
			'<form method="post" action="%s"><p>%s <select name="event_to_template">%s</select>

		</p>',
			$action,
			__( 'Copy', 'rsvpmaker' ),
			$event_options
		);

		submit_button( __( 'Copy Event', 'rsvpmaker' ) );
		rsvpmaker_nonce();
		echo '</form>';

		printf( '<h2>%s</h2><p>%s</p>', __( 'Shared Templates', 'rsvpmaker' ), __( 'RSVPMaker users can share the content of templates between websites', 'rsvpmaker' ) );

		$shared_templates = '';
		$results          = $wpdb->get_results( "SELECT * FROM $wpdb->posts JOIN $wpdb->postmeta ON $wpdb->posts.ID= $wpdb->postmeta.post_id WHERE meta_key='rsvpmaker_shared_template'" );
		if ( $results ) {
			echo '<h3>My Shared Templates</h3>';
			foreach ( $results as $row ) {
				printf( '<p>%s<br />%s</p>', $row->post_title, rest_url( 'rsvpmaker/v1/shared_template/' . intval( $row->ID ) ) );
			}
		}

		do_action( 'import_shared_template_prompt' );

		printf( '<h3>%s</h3><form action="%s" method="post"><input name="import_shared_template" />', __( 'Import Shared Template', 'rsvpmaker' ), admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list' ) );
		submit_button( __( 'Import', 'rsvpmaker' ) );
		rsvpmaker_nonce();
		echo '</form>';

		if ( $template_options ) {
			printf( '<h3>%s</h3><form action="%s" method="post"><select name="share_template">%s</select>', __( 'Share Template', 'rsvpmaker' ), admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list' ), $template_options );
			submit_button( __( 'Share Template', 'rsvpmaker' ) );
			rsvpmaker_nonce();
			echo '</form>';
		}

		$restore = '';

		$sql = "select count(*) as copies, meta_value as t FROM $wpdb->postmeta WHERE `meta_key` = '_meet_recur' group by meta_value";

		$results = $wpdb->get_results( $sql );

		foreach ( $results as $index => $row ) {

			if ( ! rsvpmaker_is_template( $row->t ) ) {

				$corrupted = get_post( $row->t );

				if ( $corrupted ) {

					$future = future_rsvpmakers_by_template( $row->t );

					$futurecount = ( $future ) ? sizeof( $future ) : 0;

					$specimen = ( $futurecount ) ? $future[0] : 0;

					$restore .= sprintf( '<p><a href="%s">Restore</a> - This template appears to have been corrupted: <strong>%s</strong> (%d) last modified: %s, used for %d future events.</p>', admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&restore=' . intval( $corrupted->ID ) . '&specimen=' . intval( $specimen ) ), esc_html( $corrupted->post_title ), esc_attr( $corrupted->ID ), esc_attr( $corrupted->post_modified ), esc_attr( $futurecount ) );

					// $restore .= var_export($future,true);

				}
			}
		}

		if ( ! empty( $restore ) ) {

			echo '<h3>Restore Templates</h3>' . $restore;
		}

		$templates = rsvpmaker_get_templates();
		foreach ( $templates as $template ) {
			$date = get_rsvp_date( $template->ID );
			if ( $date ) {
				printf( '<p>Template %d also shows date %s</p>', $template->ID, esc_html( $date ) );
			}
		}
		?>



</div>

		<?php

	}
}// end if pluggable



function rsvpmaker_week( $index = 0, $context = '' ) {

	if ( $context == 'rsvpmaker_strtotime' ) {

		$weekarray = array( 'Varies', 'First', 'Second', 'Third', 'Fourth', 'Last', 'Every' );

	} else {

		$weekarray = array( __( 'Varies', 'rsvpmaker' ), __( 'First', 'rsvpmaker' ), __( 'Second', 'rsvpmaker' ), __( 'Third', 'rsvpmaker' ), __( 'Fourth', 'rsvpmaker' ), __( 'Last', 'rsvpmaker' ), __( 'Every', 'rsvpmaker' ) );

	}

	return $weekarray[ $index ];

}



function rsvpmaker_day( $index = 0, $context = '' ) {

	if ( $context == 'rsvpmaker_strtotime' ) {

		$dayarray = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );

	} else {

		$dayarray = array( __( 'Sunday', 'rsvpmaker' ), __( 'Monday', 'rsvpmaker' ), __( 'Tuesday', 'rsvpmaker' ), __( 'Wednesday', 'rsvpmaker' ), __( 'Thursday', 'rsvpmaker' ), __( 'Friday', 'rsvpmaker' ), __( 'Saturday', 'rsvpmaker' ), '' );

	}

	return $dayarray[ $index ];

}



if ( ! function_exists( 'rsvp_template_checkboxes' ) ) {

	function rsvp_template_checkboxes( $t ) {

		global $wpdb;

		global $current_user,$rsvp_options;

		$nomeeting = $editlist = $add_one = $add_date_checkbox = $event_options = $updatelist = '';

		$template = get_template_sked( $t );

		$post = get_post( $t );

		$template_editor = false;

		if ( current_user_can( 'edit_others_rsvpmakers' ) ) {

			$template_editor = true;

		} else {

			$eds = get_post_meta( $t, '_additional_editors', false );

			$eds[] = $wpdb->get_var( "SELECT post_author FROM $wpdb->posts WHERE ID = $t" );

			$template_editor = in_array( $current_user->ID, $eds );

		}

		$template = get_template_sked( $t );

		$weeks = $template['week'];

		$dows = $template['dayofweek'];

		$hour = ( isset( $template['hour'] ) ) ? (int) $template['hour'] : 17;

		$minutes = isset( $template['minutes'] ) ? $template['minutes'] : '00';

		$terms = get_the_terms( $t, 'rsvpmaker-type' );

		if ( $terms && ! is_wp_error( $terms ) ) {

			$rsvptypes = array();

			foreach ( $terms as $term ) {

				$rsvptypes[] = $term->term_id;

			}
		}

		$cy = date( 'Y' );

		$cm = rsvpmaker_date( 'm' );

		$cd = rsvpmaker_date( 'j' );

		$schedule = '';

		if ( $weeks[0] == 0 ) {

			$schedule = __( 'Schedule Varies', 'rsvpmaker' );

		} else {

			foreach ( $weeks as $week ) {

				$schedule .= rsvpmaker_week( $week ) . ' ';
			}

			$schedule .= ' / ';

			if ( ! empty( $dows ) && is_array( $dows ) ) {

				foreach ( $dows as $dow ) {

					$schedule .= rsvpmaker_day( $dow ) . ' ';
				}
			}
		}

		if(!empty($template['stop']))
		{
			$schedule .= ' - stop date set for '.$template['stop'] .' (<a href="'.admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_details&post_id='.$t).'">Options</a>)';
		}

		printf( '<p id="template_ck">%s:</p><h2>%s</h2><h3>%s</h3><blockquote><a href="%s">%s</a></blockquote>', __( 'Template', 'rsvpmaker' ), esc_html( $post->post_title ), $schedule, admin_url( 'post.php?action=edit&post=' . $t ), __( 'Edit Template', 'rsvpmaker' ) );

		$hour = (int) $template['hour'];

		$minutes = $template['minutes'];

		$his = ( $hour < 10 ) ? '0' . $hour : $hour;

		$his .= ':' . $minutes . ':00';

		$cy              = date( 'Y' );
		$template_editor = false;

		if ( current_user_can( 'edit_others_rsvpmakers' ) ) {

			$template_editor = true;

		} else {

			$eds = get_post_meta( $t, '_additional_editors', false );

			$eds[] = $wpdb->get_var( "SELECT post_author FROM $wpdb->posts WHERE ID = $t" );

			$template_editor = in_array( $current_user->ID, $eds );

		}

		$cm = rsvpmaker_date( 'm' );

		$cd = rsvpmaker_date( 'j' );

		global $current_user;

		$sched_result = get_events_by_template( $t );

		$add_date_checkbox = $updatelist = $editlist = $nomeeting = '';

		if ( $sched_result ) {

			foreach ( $sched_result as $index => $sched ) {

				$thistime = rsvpmaker_strtotime( $sched->datetime );

				$fulldate = rsvpmaker_date( $rsvp_options['long_date'] . ' ' . $rsvp_options['time_format'], $thistime );

				$a = ( $index % 2 ) ? '' : 'alternate';

				$tparts = preg_split( '/\s+/', $sched->datetime );

				if ( $his != $tparts[1] ) {

					$newtime = str_replace( $tparts[1], $his, $sched->datetime );

					$timechange = sprintf( '<input type="hidden" name="timechange[%d]" value="%s" />', $sched->ID, $newtime );

					if ( empty( $timechangemessage ) ) {

						$timechangemessage = '<p>' . __( 'Start time for updated events will be changed to', 'rsvpmaker' ) . ' ' . rsvpmaker_date( $rsvp_options['time_format'], rsvpmaker_strtotime( $newtime ) );
						echo wp_kses_post($timechangemessage);
					}
				} else {
					$timechange = '';
				}

				$donotproject[] = rsvpmaker_date( 'Y-m-j', $thistime );

				$nomeeting .= sprintf( '<option value="%s">%s (%s)</option>', $sched->postID, date( 'F j, Y', $thistime ), __( 'Already Scheduled', 'rsvpmaker' ) );

				$cy = date( 'Y', $thistime ); // advance starting time

				$cm = rsvpmaker_date( 'm', $thistime );

				$cd = rsvpmaker_date( 'j', $thistime );

				if ( strpos( $sched->post_title, 'o Meeting:' ) ) {

					$sched->post_title = '<span style="color:red;">' . $sched->post_title . '</span>';
				}

				if ( current_user_can( 'delete_post', $sched->postID ) ) {

					$delete_text = __( 'Move to Trash' );

					$d = '<input type="checkbox" name="trash_template[]" value="' . $sched->postID . '" class="trash_template" /> <a class="submitdelete deletion" href="' . get_delete_post_link( $sched->postID ) . '">' . esc_html( $delete_text ) . '</a>';

				} else {
					$d = '-';
				}

				$ifdraft = ( $sched->post_status == 'draft' ) ? ' (draft) ' : '';

				$edit = ( ( $sched->post_author == $current_user->ID ) || $template_editor ) ? sprintf( '<a href="%s?post=%d&action=edit">' . __( 'Edit', 'rsvpmaker' ) . '</a>', admin_url( 'post.php' ), $sched->postID ) : '-';

				$schedoptions = sprintf( ' (<a href="%s">Options</a>)', admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_details&post_id=' ) . $sched->ID );

				$editlist .= sprintf( '<tr class="%s"><td><input type="checkbox" name="update_from_template[]" value="%s" class="update_from_template" /> %s </td><td>%s</td><td><input type="checkbox" name="detach_from_template[]" value="%d" /> </td><td>%s</td><td>%s</td><td><a href="%s">%s</a></td></tr>', $a, $sched->postID, $timechange, $edit, $sched->postID, $d, rsvpmaker_date( 'F d, Y', $thistime ), get_post_permalink( $sched->postID ), $sched->post_title . $ifdraft . $schedoptions );

				$template_update = get_post_meta( $sched->postID, '_updated_from_template', true );

				if ( ! empty( $template_update ) && ( $template_update != $sched->post_modified ) ) {
					$mod = ' <span style="color:red;">* ' . __( 'Modified independently of template. Update could overwrite customizations.', 'rsvpmaker' ) . '</span> ' . sprintf( '<input type="checkbox" name="detach_from_template[]" value="%d" /> ', $sched->postID ) . __( 'Detach', 'rsvpmaker' );
				} else {
					$mod = '';
				}

				// $sametime_events
				$mod        .= rsvpmaker_sametime( $sched->datetime, $sched->ID );
				$updatelist .= sprintf( '<p class="%s"><input type="checkbox" name="update_from_template[]" value="%s"  class="update_from_template" /><em>%s</em> %s <span class="updatedate">%s</span> %s %s</p>', $a, $sched->postID, __( 'Update', 'rsvpmaker' ), $sched->post_title . $ifdraft, $fulldate, $mod, $timechange );

			}
		}

		if ( ! empty( $updatelist ) ) {

			$updatelist = '<p>' . __( 'Already Scheduled' ) . "</p>\n" . '<fieldset>

<div><input type="checkbox" class="checkall"> ' . __( 'Check all', 'rsvpmaker' ) . '</div>' . "\n"

			. $updatelist . "\n</fieldset>\n";
		}

		// missing template variable

		// problem call

		// print_r($template);

		$projected = rsvpmaker_get_projected( $template );
		if(isset($_GET['debug']))
		print_r($projected);
		if ( $projected && is_array( $projected ) ) {
			foreach ( $projected as $i => $ts ) {
				ob_start();

				$today = rsvpmaker_date( 'd', $ts );

				$cm = rsvpmaker_date( 'n', $ts );

				$y = date( 'Y', $ts );

				$y0 = $y - 1;

				$y2 = $y + 1;

				if ( ( $ts < current_time( 'timestamp' ) ) && ! isset( $_GET['start'] ) ) {

					continue; // omit dates past
				}

				if ( isset( $donotproject ) && is_array( $donotproject ) && in_array( rsvpmaker_date( 'Y-m-j', $ts ), $donotproject ) ) {

					continue;
				}

				if ( empty( $nomeeting ) ) {
					$nomeeting = '';
				}

				$nomeeting .= sprintf( '<option value="%s">%s</option>', date( 'Y-m-d', $ts ), date( 'F j, Y', $ts ) );

				?>

<div style="font-family:Courier, monospace"><input name="recur_check[<?php echo esc_attr($i); ?>]" type="checkbox" class="update_from_template" value="1">

				<?php esc_html_e( 'Month', 'rsvpmaker' ); ?>: 

			  <select name="recur_month[<?php echo esc_attr($i); ?>]"> 

			  <option value="<?php echo esc_attr($cm); ?>"><?php echo esc_attr($cm); ?></option> 

			  <option value="1">1</option> 

			  <option value="2">2</option> 

			  <option value="3">3</option> 

			  <option value="4">4</option> 

			  <option value="5">5</option> 

			  <option value="6">6</option> 

			  <option value="7">7</option> 

			  <option value="8">8</option> 

			  <option value="9">9</option> 

			  <option value="10">10</option> 

			  <option value="11">11</option> 

			  <option value="12">12</option> 

			  </select> 

				<?php esc_html_e( 'Day', 'rsvpmaker' ); ?> 

			<select name="recur_day[<?php echo esc_attr($i); ?>]"> 

				<?php

				echo sprintf( '<option value="%s">%s</option>', $today, $today );

				?>

			  <option value="">Not Set</option>

			  <option value="1">1</option> 

			  <option value="2">2</option> 

			  <option value="3">3</option> 

			  <option value="4">4</option> 

			  <option value="5">5</option> 

			  <option value="6">6</option> 

			  <option value="7">7</option> 

			  <option value="8">8</option> 

			  <option value="9">9</option> 

			  <option value="10">10</option> 

			  <option value="11">11</option> 

			  <option value="12">12</option> 

			  <option value="13">13</option> 

			  <option value="14">14</option> 

			  <option value="15">15</option> 

			  <option value="16">16</option> 

			  <option value="17">17</option> 

			  <option value="18">18</option> 

			  <option value="19">19</option> 

			  <option value="20">20</option> 

			  <option value="21">21</option> 

			  <option value="22">22</option> 

			  <option value="23">23</option> 

			  <option value="24">24</option> 

			  <option value="25">25</option> 

			  <option value="26">26</option> 

			  <option value="27">27</option> 

			  <option value="28">28</option> 

			  <option value="29">29</option> 

			  <option value="30">30</option> 

			  <option value="31">31</option> 

			</select> 

				<?php esc_html_e( 'Year', 'rsvpmaker' ); ?>

			<select name="recur_year[<?php echo esc_attr($i); ?>]"> 

			<option value="<?php echo esc_attr($y0); ?>"><?php echo esc_attr($y0); ?></option> 

			  <option value="<?php echo esc_attr($y); ?>" selected="selected"><?php echo esc_attr($y); ?></option> 

			  <option value="<?php echo esc_attr($y2); ?>"><?php echo esc_attr($y2); ?></option> 

			</select>

			<input type="text" name="recur_title[<?php echo esc_attr($i); ?>]" value="<?php echo esc_html( $post->post_title ); ?>" >
				<?php echo rsvpmaker_sametime( rsvpmaker_date( 'Y-m-d H:i:s', $ts ) ); ?>
</div>

				<?php

				$add_date_checkbox .= ob_get_clean();

				if ( empty( $add_one ) ) {

					$add_one = str_replace( 'type="checkbox"', 'type="hidden"', $add_date_checkbox );
				}
			} // end for loop
		}

		$action = admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&t=' . $t );

		if ( empty( $updatelist ) ) {
			$updatelist = '';
		}

		if ( current_user_can( 'edit_rsvpmakers' ) ) {

			printf(
				'<div class="group_add_date"><br />

<form method="post" action="%s">

%s

<div><strong>' . __( 'Projected Dates', 'rsvpmaker' ) . ':</strong></div>

<fieldset>

<div><input type="checkbox" class="checkall"> ' . __( 'Check all', 'rsvpmaker' ) . '</div>

%s

</fieldset>

<br />' . __( 'New Post Status', 'rsvpmaker' ) . ': <input name="newstatus" type="radio" value="publish" checked="checked" /> publish <input name="newstatus" type="radio" value="draft" /> draft<br />

<br /><input type="submit" value="' . __( 'Add/Update From Template', 'rsvpmaker' ) . '" />

<input type="hidden" name="template" value="%s" />
%s
</form>

</div><br />',
				$action,
				$updatelist,
				$add_date_checkbox,
				$t,
				rsvpmaker_nonce('return')
			);
		}

		if ( isset( $_GET['trashed'] ) ) {

				$ids = (int) $_GET['ids'];

				$message = '<a href="' . esc_url( wp_nonce_url( "edit.php?post_type=rsvpmaker&doaction=undo&action=untrash&ids=$ids", 'bulk-posts' ) ) . '">' . __( 'Undo' ) . '</a>';

				echo '<div id="message" class="updated"><p>' . __( 'Moved to trash', 'rsvpmaker' ) . ' ' . $message . '</p></div>';

		}

		$projected = rsvpmaker_get_projected( $template );

		if ( $projected && is_array( $projected ) ) {
			foreach ( $projected as $i => $ts ) {

				$today = rsvpmaker_date( 'd', $ts );

				$cm = rsvpmaker_date( 'n', $ts );

				$y = date( 'Y', $ts );

				$y2 = $y + 1;

				ob_start();

				if ( ( $ts < current_time( 'timestamp' ) ) && ! isset( $_GET['start'] ) ) {

					continue; // omit dates past
				}

				if ( isset( $donotproject ) && is_array( $donotproject ) && in_array( date( 'Y-m-j', $ts ), $donotproject ) ) {

					continue;
				}

				$nomeeting .= sprintf( '<option value="%s">%s</option>', date( 'Y-m-d', $ts ), date( 'F j, Y', $ts ) );

				?>

<div style="font-family:Courier, monospace"><input name="recur_check[<?php echo esc_attr($i); ?>]" type="checkbox" value="1">

				<?php esc_html_e( 'Month', 'rsvpmaker' ); ?>: 

			  <select name="recur_month[<?php echo esc_attr($i); ?>]"> 

			  <option value="<?php echo esc_attr($cm); ?>"><?php echo esc_attr($cm); ?></option> 

			  <option value="1">1</option> 

			  <option value="2">2</option> 

			  <option value="3">3</option> 

			  <option value="4">4</option> 

			  <option value="5">5</option> 

			  <option value="6">6</option> 

			  <option value="7">7</option> 

			  <option value="8">8</option> 

			  <option value="9">9</option> 

			  <option value="10">10</option> 

			  <option value="11">11</option> 

			  <option value="12">12</option> 

			  </select> 

				<?php esc_html_e( 'Day', 'rsvpmaker' ); ?> 

			<select name="recur_day[<?php echo esc_attr($i); ?>]"> 

				<?php

				printf( '<option value="%s">%s</option>', $today, $today );

				?>

			  <option value=""><?php esc_html_e( 'Not Set', 'rsvpmaker' ); ?></option>

			  <option value="1">1</option> 

			  <option value="2">2</option> 

			  <option value="3">3</option> 

			  <option value="4">4</option> 

			  <option value="5">5</option> 

			  <option value="6">6</option> 

			  <option value="7">7</option> 

			  <option value="8">8</option> 

			  <option value="9">9</option> 

			  <option value="10">10</option> 

			  <option value="11">11</option> 

			  <option value="12">12</option> 

			  <option value="13">13</option> 

			  <option value="14">14</option> 

			  <option value="15">15</option> 

			  <option value="16">16</option> 

			  <option value="17">17</option> 

			  <option value="18">18</option> 

			  <option value="19">19</option> 

			  <option value="20">20</option> 

			  <option value="21">21</option> 

			  <option value="22">22</option> 

			  <option value="23">23</option> 

			  <option value="24">24</option> 

			  <option value="25">25</option> 

			  <option value="26">26</option> 

			  <option value="27">27</option> 

			  <option value="28">28</option> 

			  <option value="29">29</option> 

			  <option value="30">30</option> 

			  <option value="31">31</option> 

			</select> 

				<?php esc_html_e( 'Year', 'rsvpmaker' ); ?>

			<select name="recur_year[<?php echo esc_attr($i); ?>]"> 

			  <option value="<?php echo esc_attr($y); ?>"><?php echo esc_attr($y); ?></option> 

			  <option value="<?php echo esc_attr($y2); ?>"><?php echo esc_attr($y2); ?></option> 

			</select>

</div>



				<?php

				$add_date_checkbox .= ob_get_clean();

				if ( empty( $add_one ) ) {

					$add_one = str_replace( 'type="checkbox"', 'type="hidden"', $add_date_checkbox );
				}
			} // end for loop
		}

		$action = admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&t=' . $t );

		if ( $editlist ) {

			do_action( 'update_from_template_prompt' );

			echo '<strong>' . __( 'Already Scheduled', 'rsvpmaker' ) . ':</strong><br /><br /><form method="post" action="' . $action . '">

<fieldset>

<table  class="wp-list-table widefat fixed posts" cellspacing="0">

<thead>

<tr><th class="manage-column column-cb check-column" scope="col" ><input type="checkbox" class="checkall" title="Check all"></th><th>' . __( 'Edit' ) . '</th><th>' . __( 'Detach' ) . '</th><th><input type="checkbox" class="trashall" title="Trash all"> ' . __( 'Move to Trash' ) . '<th>' . __( 'Date' ) . '</th><th>' . __( 'Title' ) . '</th></tr>

</thead>

<tbody>

' . $editlist . '

</tbody></table>

</fieldset>

<input type="submit" value="' . __( 'Update Checked', 'rsvpmaker' ) .'" />'.rsvpmaker_nonce('return').'</form>' . '<p>' . __( 'Update function copies title and content of current template, replacing the existing content of checked posts.', 'rsvpmaker' ) . '</p>';

		}

		if ( current_user_can( 'edit_rsvpmakers' ) && ! empty( $add_one ) ) {

			do_action( 'add_from_template_prompt' );

			printf(
				'<div class="group_add_date"><br />

<form method="post" action="%s">

<strong>' . __( 'Add One', 'rsvpmaker' ) . ':</strong><br />

%s

<input type="hidden" name="rsvpmaker_add_one" value="1" />

<input type="hidden" name="template" value="%s" />

<br /><input type="submit" value="' . __( 'Add From Template', 'rsvpmaker' ) . '" />
%s
</form>

</div><br />',
				$action,
				$add_one,
				$t,
				rsvpmaker_nonce('return')
			);

		}

		if ( current_user_can( 'edit_rsvpmakers' ) ) {

			printf(
				'<div class="group_add_date"><br />

<form method="post" action="%s">

<strong>%s:</strong><br />

%s: <select name="nomeeting">%s</select>

<br />%s:<br /><textarea name="nomeeting_note" cols="60" %s></textarea>

<input type="hidden" name="template" value="%s" />

<br /><input type="submit" value="%s" />
%s
</form>

</div><br />

',
				$action,
				__( 'No Meeting', 'rsvpmaker' ),
				__( 'Regularly Scheduled Date', 'rsvpmaker' ),
				$nomeeting,
				__( 'Note (optional)', 'rsvpmaker' ),
				'style="max-width: 95%;"',
				$t,
				__( 'Submit', 'rsvpmaker' ),
				rsvpmaker_nonce('return')
			);
		}

		echo "<script>

jQuery(function () {

    jQuery('.checkall').on('click', function () {

	jQuery(this).closest('fieldset').find('.update_from_template:checkbox').prop('checked', this.checked);

    });

    jQuery('.trashall').on('click', function () {

	jQuery(this).closest('fieldset').find('.trash_template:checkbox').prop('checked', this.checked);

    });

});

</script>

";

	}
} // end function_exists



if ( ! function_exists( 'rsvpmaker_updated_messages' ) ) {

	function rsvpmaker_updated_messages( $messages ) {

		if ( empty( $messages ) ) {

			return;
		}

		global $post;

		$post_ID = $post->ID;

		if ( $post->post_type != 'rsvpmaker' ) {
			return; // only for RSVPMaker
		}

		$singular = __( 'Event', 'rsvpmaker' );

		$link = sprintf( ' <a href="%s">%s %s</a>', esc_url( get_post_permalink( $post_ID ) ), __( 'View', 'rsvpmaker' ), $singular );

		$sked = get_template_sked( $post_ID );

		if ( ! empty( $sked ) ) {

			$singular = __( 'Event Template', 'rsvpmaker' );

			$link = sprintf( ' -> <a class="update_from_template" href="%s">%s</a>', esc_url( admin_url( 'edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&t=' . $post_ID ) ), __( 'Create/update events from template', 'rsvpmaker' ) );

		}

		$messages['rsvpmaker'] = array(

			0  => '', // Unused. Messages start at index 1.

			1  => $singular . ' ' . __( 'updated', 'rsvpmaker' ) . $link,

			2  => __( 'Custom field updated.' ),

			3  => __( 'Custom field deleted.' ),

			4  => $singular . ' ' . __( 'updated', 'rsvpmaker' ) . $link,

			5  => isset( $_GET['revision'] ) ? sprintf( __( $singular . ' restored to revision from %s' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,

			6  => $singular . ' ' . __( 'published', 'rsvpmaker' ) . $link,

			7  => __( 'Page saved.' ),

			8  => sprintf( __( $singular . ' submitted. <a target="_blank" href="%s">Preview ' . strtolower( $singular ) . '</a>' ), esc_url( add_query_arg( 'preview', 'true', get_post_permalink( $post_ID ) ) ) ),

			9  => sprintf( __( $singular . ' scheduled for: <strong>%s</strong>. <a target="_blank" href="%s">Preview ' . strtolower( $singular ) . '</a>' ), date_i18n( __( 'M j, Y @ G:i' ), rsvpmaker_strtotime( $post->post_date ) ), esc_url( get_post_permalink( $post_ID ) ) ),

			10 => sprintf( __( $singular . ' draft updated. <a target="_blank" href="%s">Preview ' . strtolower( $singular ) . '</a>' ), esc_url( add_query_arg( 'preview', 'true', get_post_permalink( $post_ID ) ) ) ),

		);

		return $messages;

	}
} // end if function



if ( ! function_exists( 'rsvpmaker_template_admin_title' ) ) {

	function rsvpmaker_template_admin_title() {

		global $title;

		global $post;

		global $post_new_file;

		if ( ! isset( $post ) || ( $post->post_type != 'rsvpmaker' ) ) {

			return;
		}

		if ( ! empty( $_GET['new_template'] ) || get_template_sked( $post->ID ) ) {

			$title .= ' ' . __( 'Template', 'rsvpmaker' );

			if ( isset( $post_new_file ) ) {

				$post_new_file = 'post-new.php?post_type=rsvpmaker&new_template=1';
			}
		}

	}
}



if ( ! function_exists( 'next_or_recent' ) ) {

	function next_or_recent( $template_id ) {

		global $wpdb;

		global $rsvp_options;

		$event = '';

		$sql = "SELECT DISTINCT $wpdb->posts.ID as postID, a1.meta_value as datetime, a2.meta_value as template

	 FROM " . $wpdb->posts . '

	 JOIN ' . $wpdb->postmeta . ' a1 ON ' . $wpdb->posts . '.ID =a1.post_id 

	 JOIN ' . $wpdb->postmeta . ' a2 ON ' . $wpdb->posts . ".ID =a2.post_id 

	 WHERE a1.meta_key='_rsvp_dates' AND a1.meta_value > '" . get_sql_curdate() . "' AND a2.meta_key='_meet_recur' AND a2.meta_value=" . $template_id . " AND post_status='publish'

	 ORDER BY a1.meta_value LIMIT 0,1";

		if ( $row = $wpdb->get_row( $sql ) ) {

			$t = rsvpmaker_strtotime( $row->datetime );

			$neatdate = utf8_encode( rsvpmaker_date( $rsvp_options['long_date'], $t ) );

			$event = sprintf( '<a href="%s">%s: %s</a>', get_post_permalink( $row->postID ), __( 'Next Event', 'rsvpmaker' ), $neatdate );

		} else {

			$sql = "SELECT DISTINCT $wpdb->posts.ID as postID, a1.meta_value as datetime, a2.meta_value as template

	 FROM " . $wpdb->posts . '

	 LEFT JOIN ' . $wpdb->postmeta . ' a1 ON ' . $wpdb->posts . '.ID =a1.post_id

	 LEFT JOIN ' . $wpdb->postmeta . ' a2 ON ' . $wpdb->posts . ".ID =a2.post_id 

	 WHERE a1.meta_key='_rsvp_dates' AND a1.meta_value < '" . get_sql_curdate() . "' AND a2.meta_key='_meet_recur' AND a2.meta_value=" . $template_id . " AND post_status='publish'

	 ORDER BY a1.meta_value DESC LIMIT 0,1";

			if ( $row = $wpdb->get_row( $sql ) ) {

				$t = rsvpmaker_strtotime( $row->datetime );

				$neatdate = utf8_encode( rsvpmaker_date( $rsvp_options['long_date'], $t ) );

				$event = sprintf( '<a style="color:#333;" href="%s">%s: %s</a>', get_post_permalink( $row->postID ), __( 'Most Recent', 'rsvpmaker' ), $neatdate );

			}
		}

		return $event;

	}
} // end if funnction



if ( isset( $_GET['message'] ) ) {
	add_filter( 'post_updated_messages', 'rsvpmaker_updated_messages' );
}

if ( ! function_exists( 'additional_editors_setup' ) ) {

	function additional_editors_setup() {

		global $rsvp_options;

		if ( isset( $rsvp_options['additional_editors'] ) && $rsvp_options['additional_editors'] ) {

			add_action( 'save_post', 'save_additional_editor' );

			// add_filter( 'user_has_cap', 'rsvpmaker_cap_filter', 99, 3 );

			add_filter( 'map_meta_cap', 'rsvpmaker_map_meta_cap', 10, 4 );

		}

	}
}



if ( ! function_exists( 'rsvpmaker_cap_filter_test' ) ) {

	function rsvpmaker_cap_filter_test( $cap, $post_id ) {

		if ( strpos( $cap, 'rsvpmaker' ) ) {

			return true;

		} elseif ( $post = get_post( $post_id ) ) {

			if ( isset( $post->post_type ) && ( $post->post_type == 'rsvpmaker' ) ) {

				return true;

			} else {
				return false;
			}
		} else {
			return false;
		}

	}
}



if ( ! function_exists( 'get_additional_editors' ) ) {

	function get_additional_editors( $post_id ) {

		global $wpdb;

		$eds = array();

		$recurid = get_post_meta( $post_id, '_meet_recur', true );

		if ( $recurid ) {

			$eds = get_post_meta( $recurid, '_additional_editors', false );

		}

		$post_eds = get_post_meta( $post_id, '_additional_editors', false );

		if ( is_array( $post_eds ) ) {

			foreach ( $post_eds as $this_eds ) {

				if ( ! in_array( $this_eds, $eds ) ) {

					$eds[] = $this_eds;
				}
			}
		}

		return $eds;

	}
}// end if exists



if ( ! function_exists( 'save_additional_editor' ) ) {

	function save_additional_editor( $post_id ) {

		if ( ! empty( $_POST['additional_editor'] ) || ! empty( $_POST['remove_editor'] ) ) {

			if ( $parent_id = wp_is_post_revision( $post_id ) ) {

				$post_id = $parent_id;

			}
		}

		if ( ! empty( $_POST['additional_editor'] )  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {

			$ed = (int) $_POST['additional_editor'];

			if ( $ed ) {

				add_post_meta( $post_id, '_additional_editors', $ed, false );
			}
		}

		if ( ! empty( $_POST['remove_editor'] )  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {

			foreach ( $_POST['remove_editor'] as $remove ) {

				$remove = (int) $remove;

				if ( $remove ) {

					delete_post_meta( $post_id, '_additional_editors', $remove );
				}
			}
		}

	}
} // end function exists



if ( ! function_exists( 'rsvpmaker_editor_dropdown' ) ) {

	function rsvpmaker_editor_dropdown( $eds ) {

		global $wpdb;

		$options = '';

		$sql = "SELECT * FROM $wpdb->users ORDER BY user_login";

		$results = $wpdb->get_results( $sql );

		if ( is_array( $results ) ) {

			foreach ( $results as $row ) {

				if ( in_array( $row->ID, $eds ) ) {

					continue;
				}

				$member = get_userdata( $row->ID );

				$index = preg_replace( '/[^a-zA-Z]/', '', $member->last_name . $member->first_name . $row->user_login );

				$sortmember[ $index ] = $member;

			}
		}

		ksort( $sortmember );

		foreach ( $sortmember as $index => $member ) {

			if ( isset( $member->last_name ) && ! empty( $member->last_name ) ) {

				$label = $member->first_name . ' ' . $member->last_name;

			} else {
				$label = $index;
			}

			$label = esc_html( $label );

			if ( $member->ID == $assigned ) {

				$s = ' selected="selected" ';

			} else {
				$s = '';
			}

			$options .= sprintf( '<option %s value="%d">%s</option>', $s, $member->ID, esc_html( $label ) );

		}

		return $options;

	}
} // end function exists



if ( ! function_exists( 'additional_editors' ) ) {

	function additional_editors() {

		global $post;

		global $custom_fields;

		if ( $post->ID ) {

			$eds = get_post_meta( $post->ID, '_additional_editors', false );
		}

		if ( $eds ) {

			echo '<strong>' . __( 'Editors', 'rsvpmaker' ) . ':</strong><br />';

			foreach ( $eds as $user_id ) {

				$member = get_userdata( $user_id );

				if ( isset( $member->last_name ) && ! empty( $member->last_name ) ) {

					$label = $member->first_name . ' ' . $member->last_name;

				} else {
					$label = $member->user_login;
				}

				$label .= ' ' . $member->user_email;

				echo esc_html( $label ) . sprintf( ' <strong>( <input type="checkbox" name="remove_editor[]" value="%d"> %s)</strong><br />', $user_id, __( 'Remove', 'rsvpmaker' ) );

			}
		}

		?>

<p><?php esc_html_e( 'Add Editor', 'rsvpmaker' ); ?>: <select name="additional_editor" ><option value=""><?php esc_html_e( 'Select' ); ?></option><?php echo rsvpmaker_editor_dropdown( $eds ); ?></select></p>

		<?php

		if ( isset( $custom_fields['_meet_recur'][0] ) ) {
		}

		{

		echo '<strong>' . __( 'Template', 'rsvpmaker' ) . ' ' . __( 'Editors', 'rsvpmaker' ) . ':</strong><br />';

		$t = isset( $custom_fields['_meet_recur'][0] ) ? $custom_fields['_meet_recur'][0] : 0;

		$eds = get_post_meta( $t, '_additional_editors', false );

		if ( $eds ) {

			foreach ( $eds as $user_id ) {

				$member = get_userdata( $user_id );

				if ( isset( $member->last_name ) && ! empty( $member->last_name ) ) {

					$label = $member->first_name . ' ' . $member->last_name;

				} else {
					$label = $member->user_login;
				}

				echo esc_html( $label ) . '<br />';

			}
		} else {
			esc_html_e( 'None', 'rsvpmaker' );
		}

		printf( '<p><a href="%s">' . __( 'Edit Template', 'rsvpmaker' ) . '</a></p>', admin_url( 'post.php?action=edit&post=' . $t ) );

		}

		do_action( 'rsvpmaker_additional_editors' );

	}
} // function exists



if ( ! function_exists( 'rsvpmaker_dashboard_widget_function' ) ) {

	function rsvpmaker_dashboard_widget_function() {

		global $wpdb;

		global $rsvp_options;

		global $current_user;

		// $wpdb->show_errors();

		do_action( 'rsvpmaker_dashboard_action' );

		if ( isset( $rsvp_options['dashboard_message'] ) && ! empty( $rsvp_options['dashboard_message'] ) ) {

			echo '<div>' . wp_kses_post( $rsvp_options['dashboard_message'] ) . '</div>';
		}

		echo '<p><strong>' . __( 'My Events', 'rsvpmaker' ) . '</strong><br /></p>';

		$results = get_future_events( 'post_author=' . $current_user->ID );

		if ( $results ) {

			foreach ( $results as $index => $row ) {

				$draft = ( $row->post_status == 'draft' ) ? ' (draft)' : '';

				printf( '<p><a href="%s">(' . __( 'Edit', 'rsvpmaker' ) . ')</a> <a href="%s">%s %s%s</a></p>', admin_url( 'post.php?action=edit&post=' . $row->ID ), get_post_permalink( $row->ID ), esc_html( $row->post_title ), utf8_encode( rsvpmaker_date( $rsvp_options['long_date'], rsvpmaker_strtotime( $row->datetime ) ) ), $draft );

				if ( $index == 10 ) {

					printf( '<p><a href="%s">&gt; &gt; ' . __( 'More', 'rsvpmaker' ) . '</a></p>', admin_url( 'edit.php?post_type=rsvpmaker&rsvpsort=chronological&author=' . $current_user->ID ) );

					break;

				}
			}
		} else {

			'<p>' . __( 'None', 'rsvpmaker' ) . '</p>';

		}

		printf( '<p><a href="%s">' . __( 'Add Event', 'rsvpmaker' ) . '</a></p>', admin_url( 'post-new.php?post_type=rsvpmaker' ) );

		$sql = "SELECT $wpdb->posts.ID as editid FROM $wpdb->posts JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id 

WHERE $wpdb->posts.post_type = 'rsvpmaker' AND $wpdb->postmeta.meta_key = '_additional_editors' AND $wpdb->postmeta.meta_value = $current_user->ID";

		$wpdb->show_errors();

		$result = $wpdb->get_results( $sql );

		$r2 = rsvpmaker_get_templates( 'AND post_author=$current_user->ID' );// $wpdb->get_results($sql);

		if ( $result && $r2 ) {

			$result = array_merge( $r2, $result );

		} elseif ( $r2 ) {

			$result = $r2;
		}

		if ( $result ) {

			foreach ( $result as $row ) {

				rsvp_template_checkboxes( $row->editid );

			}
		}

	}
} // end function exists



function rsvpmaker_add_dashboard_widgets() {

	global $rsvp_options;

	wp_add_dashboard_widget( 'rsvpmaker_dashboard_widget', __( 'Events', 'rsvpmaker' ), 'rsvpmaker_dashboard_widget_function' );

	if ( empty( $rsvp_options['dashboard'] ) || ( $rsvp_options['dashboard'] != 'top' ) ) {

		return;
	}

	// Globalize the metaboxes array, this holds all the widgets for wp-admin

	global $wp_meta_boxes;

	// Get the regular dashboard widgets array

	// (which has our new widget already but at the end)

	$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];

	// Backup and delete our new dashbaord widget from the end of the array

	$rsvpmaker_widget_backup = array(
		'rsvpmaker_dashboard_widget' =>

				$normal_dashboard['rsvpmaker_dashboard_widget'],
	);

	unset( $normal_dashboard['rsvpmaker_dashboard_widget'] );

	// Merge the two arrays together so our widget is at the beginning

	$sorted_dashboard = array_merge( $rsvpmaker_widget_backup, $normal_dashboard );

	// Save the sorted array back into the original metaboxes

	$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;

}



// Hook into the 'wp_dashboard_setup' action to register our other functions



function rsvpmaker_check_coupon_code( $price ) {

	global $post;

	global $rsvpmaker_coupon_message;

	$coupon_message = '';

	$codes = get_post_meta( $post->ID, '_rsvp_coupon_code' ); // array of codes

	// printf('<p>Initial price %s %s</p>',$price,$code);

	if ( ! empty( $codes ) && ! empty( $_POST['coupon_code'] )  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {

		$user_code = trim( $_POST['coupon_code'] );

		if ( ( in_array( $user_code, $codes ) ) ) {

			$index = array_search( $user_code, $codes );

			$discounts = get_post_meta( $post->ID, '_rsvp_coupon_discount' );

			$methods = get_post_meta( $post->ID, '_rsvp_coupon_method' );

			$discount = (float) $discounts[ $index ];

			$method = $methods[ $index ];

			$rsvpmaker_coupon_message = 'Coupon code applied: ' . $user_code;

			if ( $method == 'percent' ) {

				$price = $price - ( $price * ( $discount / 100 ) );

			} else {

				$price = $discount;

			}
		} else {

			$rsvpmaker_coupon_message = 'Coupon code not recognized';

		}
	}

	return $price;

}

?>
