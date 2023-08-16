<?php
/*
* Backend functions
*/
function rsvpmaker_date_slug($data) {
	if(!wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')))
		return $data;
	
	if(!empty($_POST["override"]))
		return $data; // don't do this for template override
	
	if($data["post_status"] != 'publish')
		return $data;

	if(isset($_POST["edit_month"][0]) )
		{
		$y = (int) $_POST["edit_year"][0];
		$m = (int) $_POST["edit_month"][0];
		if($m < 10)
			$m = '0'.$m;
		$d = (int) $_POST["edit_day"][0];
		if($d < 10)
			$d = '0'.$d;			
		$date = $y.'-'.$m.'-'.$d;
	
		if (empty($data['post_name']) || !strpos($data['post_name'],$date) ) {
			$data['post_name'] = sanitize_title($data['post_title']);
			$data['post_name'] .= '-' .$date;
			}
		}
	elseif(isset($_POST["event_month"][0]) )
		{
		$y = (int) $_POST["event_year"][0];
		$m = (int) $_POST["event_month"][0];
		if($m < 10)
			$m = '0'.$m;
		$d = (int) $_POST["event_day"][0];
		if($d < 10)
			$d = '0'.$d;			
		$date = $y.'-'.$m.'-'.$d;
	
		if (empty($data['post_name']) || !strpos($data['post_name'],$date) ) {
			$data['post_name'] = sanitize_title($data['post_title']);
			$data['post_name'] .= '-' .$date;
			}
		}
	
	return $data;
}

add_filter('wp_insert_post_data', 'rsvpmaker_date_slug', 10);

function rsvpmaker_unique_date_slug($slug, $post_ID = 0, $post_status = '', $post_type = '', $post_parent = 0, $original_slug='' )
	{
	global $wpdb;
	if($post_type != 'rsvpmaker')
		return $slug;
	if($post_status != 'publish')
		return $slug;
	if(!empty($_POST["override"]))
		return $slug; // don't do this for template override
	
	$post = get_post($post_ID);
	if(empty($post->post_type)) return $slug;
	$date = str_replace(' ', '_',str_replace(':00','',get_rsvp_date($post_ID)));
	$newslug = sanitize_text_field($post->post_title.'-' .$date);
	return $newslug;
	}

add_filter('wp_unique_post_slug','rsvpmaker_unique_date_slug',10);

function rsvpmaker_save_calendar_data($post_id) {
	if(!wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')))
		return;

	global $wpdb, $current_user;
	$end_array = array();
	
	if($parent_id = wp_is_post_revision($post_id))
		{
		$post_id = $parent_id;
		}
	if(rsvpmaker_is_template($post_id))
	{
		$args = array($post_id,$current_user->user_email);
		wp_clear_scheduled_hook( 'rsvpmaker_create_update_reminder', $args );
		wp_schedule_single_event( strtotime('+2 hours'), 'rsvpmaker_create_update_reminder', $args);
	}
	if(!empty($_POST['newrsvpdate'])) {
		update_post_meta($post_id,'_rsvp_dates',sanitize_text_field($_POST['newrsvpdate'].' '.$_POST['newrsvptime']));
	}
	if(!empty($_POST['rsvpendtime']))
		update_post_meta($post_id,'_endfirsttime',sanitize_text_field($_POST['rsvpendtime']));
	if(!empty($_POST['end_time_type']))
		update_post_meta($post_id,'_firsttime',sanitize_text_field($_POST['end_time_type']));
	if(isset($_POST["_require_webinar_passcode"]))
		{
		update_post_meta($post_id,'_require_webinar_passcode',sanitize_text_field($_POST["_require_webinar_passcode"]));
		}
	if(isset($_POST["event_month"]) )
		{
		foreach($_POST["event_year"] as $index => $year)
			{
			$year = (int) $year;
			if(isset($_POST["event_day"][$index]) && $_POST["event_day"][$index])
				{
				$cddate = format_cddate($year,(int) $_POST["event_month"][$index], (int) $_POST["event_day"][$index], (int) $_POST["event_hour"][$index], (int) $_POST["event_minutes"][$index]);
				$dpart = explode(':',$_POST["event_duration"][$index]);
				if( is_numeric($dpart[0]) )
					{
					$hour = intval($_POST["event_hour"][$index]) + intval($dpart[0]);
					$minutes = (isset($dpart[1]) ) ? intval($_POST["event_minutes"][$index]) + intval($dpart[1]) : sanitize_text_field($_POST["event_minutes"][$index]);
					$t = rsvpmaker_mktime( $hour, $minutes,0,intval($_POST["event_month"][$index]),intval($_POST["event_day"][$index]),$year);
					$duration = rsvpmaker_date('Y-m-d H:i:s',$t);
					}
				else
					$duration = sanitize_text_field($_POST["event_duration"][$index]); // empty or all day
				if($duration == 'set')
					$end_array[] = sanitize_text_field($_POST["hourevent_duration"][$index]).':'.sanitize_text_field($_POST["minevent_duration"][$index]);
				$dates_array[] = $cddate;
				$durations_array[] = $duration;
				}
			}
		}
	
	if(isset($_POST["edit_month"]))
		{
		delete_transient('rsvpmakerdates');//invalidate cached values
		foreach($_POST["edit_year"] as $index => $year)
			{
				$year = (int) $year;
				$cddate = format_cddate(intval($year),intval($_POST["edit_month"][$index]), intval($_POST["edit_day"][$index]), intval($_POST["edit_hour"][$index]), intval($_POST["edit_minutes"][$index]));
				if(strpos( $_POST["edit_duration"][$index],':' ))
					{
					$dpart = explode(':',sanitize_text_field($_POST["edit_duration"][$index]));
					if( is_numeric($dpart[0]) )
						{
						$hour = intval($_POST["edit_hour"][$index]) + intval($dpart[0]);
						$minutes = (isset($dpart[1]) ) ? intval($_POST["edit_minutes"][$index]) + intval($dpart[1]) : intval($_POST["edit_minutes"][$index]);
						//dchange
						$duration = rsvpmaker_date('Y-m-d H:i:s',rsvpmaker_mktime( $hour, $minutes,0,intval($_POST["edit_month"][$index]),intval($_POST["edit_day"][$index]),$year));
						}
					}
				elseif( is_numeric($_POST["edit_duration"][$index]) )
					{
					$d_duration = (int) $_POST["edit_duration"][$index];
					$minutes = (int) $_POST["edit_minutes"][$index];				
					$minutes = $minutes + (60*$d_duration);
					//dchange - can this be removed?
					$duration = rsvpmaker_date('Y-m-d H:i:s',rsvpmaker_mktime( sanitize_text_field($_POST["edit_hour"][$index]), $minutes,0,sanitize_text_field($_POST["edit_month"][$index]),sanitize_text_field($_POST["edit_day"][$index]),$year));
					}
				else
					$duration = sanitize_text_field($_POST["edit_duration"][$index]); // empty or all day			
				if(($duration == 'set') || strpos($duration,'|') )
					$end_array[] = sanitize_text_field($_POST["houredit_duration"][$index]).':'.sanitize_text_field($_POST["minedit_duration"][$index]);
				$dates_array[] = $cddate;
				$durations_array[] = $duration;
				}
		} // end edit month
	
		if(!empty($dates_array) )
			{
				update_post_meta($post_id, '_rsvp_dates', $dates_array[0]);
				if(!empty($durations_array[0]))
				update_post_meta($post_id, '_firsttime', $durations_array[0]);
				if(!empty($end_array[0]))
				update_post_meta($post_id, '_endfirsttime', $end_array[0]);
			}
	
		if(isset($_POST["delete_date"]))
			{
			foreach($_POST["delete_date"] as $delete_date)
				{
				delete_rsvpmaker_date($post_id,sanitize_text_field($delete_date));
				}
			}
		
		if(isset($_POST["setrsvp"]) )
		{ // if rsvp parameters were set, was RSVP box checked?
		if(isset($_POST["setrsvp"]["on"]))
			update_post_meta($post_id, '_rsvp_on', (int) $_POST["setrsvp"]["on"]);
		}
		
		if(isset($_POST['payment_gateway']))
			update_post_meta($post_id, 'payment_gateway', sanitize_text_field($_POST["payment_gateway"]));
	
		if(isset($_POST["sked"]["week"]))
			{
			save_rsvp_template_meta($post_id);
			}
		if(!isset($_POST["sked"]) && !isset($_POST["setrsvp"]))
			return;
		if(isset($_POST['add_timezone']) && $_POST['add_timezone'])
			update_post_meta($post_id,'_add_timezone',1);
		else
			update_post_meta($post_id,'_add_timezone',0);	
		if(isset($_POST['convert_timezone']) && $_POST['convert_timezone'])
			update_post_meta($post_id,'_convert_timezone',1);
		else
			update_post_meta($post_id,'_convert_timezone',0);	
	
		if(isset($_POST['calendar_icons']) && $_POST['calendar_icons'])
			update_post_meta($post_id,'_calendar_icons',1);
		else
			update_post_meta($post_id,'_calendar_icons',0);
}

add_action('rsvpmaker_create_update_reminder','rsvpmaker_create_update_reminder',10,3);
function rsvpmaker_create_update_reminder($t, $author_email = '') {
	$template = get_post($t);
	$output = '';
	$sched_result = get_events_by_template($t);
	if(empty($sched_result))
		return;//no events to update
	$nag = true;
	foreach($sched_result as $event) {
		$updated_from_template = get_post_meta($event->ID,"_updated_from_template",true);
		if($updated_from_template >= $template->post_modified)
			$nag = false; // at least one event has been updated
		$up = ($updated_from_template < $template->post_modified) ? 'not updated'." $updated_from_template < $template->post_modified " : 'updated';
		$output .= sprintf('%d %s ',$event->ID, $up);
	}
	if($nag) {
		$mail['html'] = sprintf('<p>You updated the <strong>%s</strong> template but not the events based on that template.</p>'."\n".'<p>To update the whole series, use <a href="%s">Create/Update<a></p>',$template->post_title,admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&t='.$t));
		$mail['to'] = get_option('admin_email');
		$mail['from'] = get_option('admin_email');
		$mail['subject'] = __('Event template not applied to existing events:','rsvpmaker').' '.$template->post_title;
		rsvpmailer($mail);
		if($author_email != $mail['to'])
		{
			$mail['to'] = $author_email;
			rsvpmailer($mail);
		}
	}
}

function rsvpmaker_date_option($datevar = NULL, $index = NULL, $jquery_date = NULL, $sked = NULL) {

global $rsvp_options;
$prefix = "event_";

if(is_int($datevar))
	{
	$t = $datevar;
	$datevar = array();
	}
elseif(is_array($datevar) )
{
	$datestring = $datevar["datetime"];
	//dchange - check this
	$duration = $datevar["duration"];
	if(strpos($duration,':'))
		{
		$datevar['end_time'] = rsvpmaker_date('H:i',rsvpmaker_strtotime($duration));
		$datevar['duration'] = 'set';
		}
	$prefix = "edit_";
	if(isset($datevar["id"]))
		$index = $datevar["id"];
}
else
{
	$datestring = $datevar;
	$datevar = array();
}
if(!empty($datestring))
	{
	$datestring = str_replace('Every','Next',$datestring);
	$t = rsvpmaker_strtotime($datestring);
	}

$endtime = (is_array($sked) && isset($sked['end'])) ? rsvpmaker_strtotime('2000-01-01 '. $sked['end']) : $t+HOUR_IN_SECONDS;

?>
<div id="<?php echo esc_attr($prefix); ?>date<?php echo esc_attr($index);?>" ><input type="hidden" id="defaulthour" value="<?php echo esc_attr($rsvp_options["defaulthour"]); ?>" /><input type="hidden" id="defaultmin" value="<?php echo esc_attr($rsvp_options["defaultmin"]); ?>" />
<p><label>Date</label> <input type="date" name="newrsvpdate" id="newrsvpdate" value="<?php echo date('Y-m-d',$t); ?>"> 
</p>
<p><label>Time</label> <input name="newrsvptime" type="time" class="newrsvptime" id="newrsvptime" value="<?php echo rsvpmaker_date('H:i:s',$t) ?>"><span id="endtimespan"> to <input name="rsvpendtime" type="time" class="rsvpendtime" id="rsvpendtime" value="<?php echo rsvpmaker_date('H:i:s',$endtime) ?>"> </span>
</p>

<?php
if(!empty($sked['duration']))
	$datevar['duration'] = $sked['duration'];
if(empty($datestring))
	$datestring ='';
rsvpmaker_duration_select ($prefix.'duration['.$index.']', $datevar, $datestring, $index );

?>
</div>
<?php

}

function rsvpmaker_date_option_event($t, $endtime, $type) {

	global $rsvp_options;
	$prefix = "edit_";
	
	?>
	<div id="<?php echo esc_attr($prefix); ?>date<?php echo esc_attr($index);?>" ><input type="hidden" id="defaulthour" value="<?php echo esc_attr($rsvp_options["defaulthour"]); ?>" /><input type="hidden" id="defaultmin" value="<?php echo esc_attr($rsvp_options["defaultmin"]); ?>" />
	<p><label>Date</label> <input type="date" name="newrsvpdate" id="newrsvpdate" value="<?php echo date('Y-m-d',$t); ?>"> 
	</p>
	<p><label>Time</label> <input name="newrsvptime" type="time" class="newrsvptime" id="newrsvptime" value="<?php echo rsvpmaker_date('H:i:s',$t) ?>"><span id="endtimespan"> to <input name="rsvpendtime" type="time" class="rsvpendtime" id="rsvpendtime" value="<?php echo rsvpmaker_date('H:i:s',$endtime) ?>"> </span>
	</p>
	
	<?php
	rsvpmaker_duration_select_2021 ($type);
	?>
	</div>
	<?php
}

function save_rsvp_meta($post_id, $new = false)
{
if( !wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	return;
$setrsvp = array_map('sanitize_text_field',$_POST["setrsvp"]);
if($new)
{
rsvpmaker_defaults_for_post($post_id); 
}
else
{
	$checkboxes = array("show_attendees","count","captcha","login_required",'confirmation_include_event','rsvpmaker_send_confirmation_email','yesno');
	foreach($checkboxes as $check)
		{
			if(!isset($setrsvp[$check]))
				$setrsvp[$check] = 0;
		}	
}

if(isset($_POST["deadyear"]) && isset($_POST["deadmonth"]) && isset($_POST["deadday"]))
	{
	if(empty(trim($_POST["deadday"])))
		$setrsvp["deadline"] = '';
	else
		$setrsvp["deadline"] = rsvpmaker_strtotime(sanitize_text_field($_POST["deadyear"]).'-'.sanitize_text_field($_POST["deadmonth"]).'-'.sanitize_text_field($_POST["deadday"]).' '.sanitize_text_field($_POST["deadtime"]));
	}

if(isset($_POST["startyear"]) && isset($_POST["startmonth"]) && isset($_POST["startday"]))
	{
	if(empty(trim($_POST["startday"])))
		$setrsvp["start"] = '';
	else
		$setrsvp["start"] = rsvpmaker_strtotime(sanitize_text_field($_POST["startyear"].'-'.$_POST["startmonth"].'-'.$_POST["startday"].' '.$_POST["starttime"]));
	}

foreach($setrsvp as $name => $value)
	{
	$field = '_rsvp_'.$name;
	$single = true;
	update_post_meta($post_id, $field, sanitize_text_field($value));
	}

if(isset($_POST["unit"]))
	{
				
	foreach($_POST["unit"] as $index => $value)
		{
		$value = sanitize_text_field($value);
		if(empty($value))
			continue;
		if( empty($_POST["price"][$index]) && ($_POST["price"][$index] != 0) )
			continue;
		$per["unit"][$index] = $value;
		$per["price"][$index] = sanitize_text_field($_POST["price"][$index]);
		if(isset($_POST["price_multiple"][$index]) && ($_POST["price_multiple"][$index] > 1))
			$per["price_multiple"][$index] = sanitize_text_field($_POST["price_multiple"][$index]);
		if(!empty($_POST["price_deadline"][$index]))
			{
			
			$per["price_deadline"][$index] = rsvpmaker_strtotime(sanitize_text_field($_POST["price_deadline"][$index]));
			
			}
		if(isset($_POST['showhide'][$index]))
			{
				foreach($_POST['showhide'][$index] as $showindex => $showhide)
					{
						if($showhide)
							$pricehide[$index][] = $showindex;
					}
			}
		}	
	
	if(!empty($pricehide))
		{
			update_post_meta($post_id, '_hiddenrsvpfields', $pricehide);
		}
	
	$value = $per;
	$field = "_per";
	
	$current = get_post_meta($post_id, $field, $single); 
	
	if($value && ($current == "") )
		add_post_meta($post_id, $field, $value, true);
	
	elseif($value != $current)
		update_post_meta($post_id, $field, $value);
	
	elseif($value == "")
		delete_post_meta($post_id, $field, $current);
	
	}
	if(!empty($_POST["youtube_live"]) || !empty($_POST["webinar_other"]))
		{
		$ylive = sanitize_text_field($_POST["youtube_live"]);
		unset($_POST);
		rsvpmaker_youtube_live($post_id, $ylive);
		}

		if(isset($_POST['coupon_code']))

		{
	
		delete_post_meta($post_id,'_rsvp_coupon_code');
	
		delete_post_meta($post_id,'_rsvp_coupon_discount');
	
		delete_post_meta($post_id,'_rsvp_coupon_method');
	
		foreach($_POST['coupon_code'] as $index => $value)
	
		{
	
			$value = sanitize_text_field($value);
	
			$discount = sanitize_text_field($_POST['coupon_discount'][$index]);
	
			if(!empty($value) && is_numeric($discount))
	
			{
	
				$method = sanitize_text_field($_POST['coupon_method'][$index]);
	
				add_post_meta($post_id,'_rsvp_coupon_code',$value);
	
				add_post_meta($post_id,'_rsvp_coupon_discount',$discount);
	
				add_post_meta($post_id,'_rsvp_coupon_method',$method);		
	
			}
		}
	
		}
}

function rsvpmaker_menu_security($label, $slug,$options) {
echo esc_html($label);
?>
 <select name="security_option[<?php echo esc_attr($slug); ?>]" id="<?php echo esc_attr($slug); ?>">
  <option value="manage_options" <?php if(isset($options[$slug]) && ($options[$slug] == 'manage_options')) echo ' selected="selected" ';?> ><?php esc_html_e('Administrator','rsvpmaker');?> (manage_options)</option>
  <option value="edit_others_rsvpmakers" <?php if(isset($options[$slug]) && ($options[$slug] == 'edit_others_rsvpmakers')) echo ' selected="selected" ';?>><?php esc_html_e('Editor','rsvpmaker');?> (edit_others_rsvpmakers)</option>
  <option value="publish_rsvpmakers" <?php if(isset($options[$slug]) && ($options[$slug] == 'publish_rsvpmakers')) echo ' selected="selected" ';?> ><?php esc_html_e('Author','rsvpmaker');?> (publish_rsvpmakers)</option>
  <option value="edit_rsvpmakers" <?php if(isset($options[$slug]) && ($options[$slug] == 'edit_rsvpmakers')) echo ' selected="selected" ';?> ><?php esc_html_e('Contributor','rsvpmaker');?> (edit_rsvpmakers)</option>
  </select><br />
<?php
}
  
  // Avoid name collisions.
  if (!class_exists('RSVPMAKER_Options'))
      : class RSVPMAKER_Options
      {
          // this variable will hold url to the plugin  
          var $plugin_url;
          
          // name for our options in the DB
          var $db_option = 'RSVPMAKER_Options';
          
          // Initialize the plugin
          function __construct()
          {
              $this->plugin_url = plugins_url('',__FILE__).'/';

              // add options Page
              add_action('admin_menu', array(&$this, 'admin_menu'));
              
          }
          
          // hook the options page
          function admin_menu()
          {
              add_options_page('RSVPMaker', 'RSVPMaker', 'manage_options', basename(__FILE__), array(&$this, 'handle_options'));
          }
          
          
          // handle plugin options
          function get_options()
          {
              global $rsvp_options;
              return $rsvp_options;
          }
          
          // Set up everything
          function install()
          {
              // set default options
              $this->get_options();
          }
          
          // handle the options page
          function handle_options()
          {
			if(!empty($_POST) && !isset($_POST['rsvpelist']) && !wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')))
				wp_die('nonce security error');
			if(!empty($_POST['rsvpmaker_save_form']) && !empty($_POST['form_id']))
			{
				$forms = rsvpmaker_get_forms();
				$forms[sanitize_text_field($_POST['rsvpmaker_save_form'])] = (int) $_POST['form_id'];
				update_option('rsvpmaker_forms',$forms);
			}

			$options = $this->get_options();
			  if(isset($_POST["payment_option"])) {
              $newoptions = array_map('sanitize_text_field',stripslashes_deep($_POST["payment_option"]));
				$newoptions["stripe"] = (isset($_POST['payment_gateway']) && ($_POST['payment_gateway'] == 'stripe')) ? 1 : 0;
				$newoptions["cash_or_custom"] = (isset($_POST['payment_gateway']) && ($_POST['payment_gateway'] == 'cash_or_custom')) ? 1 : 0;
				$nfparts = explode('|',$_POST["currency_format"]);
				$newoptions["currency_decimal"] = sanitize_text_field($nfparts[0]);
				$newoptions["currency_thousands"] = sanitize_text_field($nfparts[1]);

				foreach($newoptions as $name => $value)
				  $options[$name] = sanitize_text_field($value);
				  
                  update_option($this->db_option, $options);

				  if(isset($_POST['rsvpmaker_stripe_keys']))
				  {
					//don't overwrite keys that are not displayed
					$stripe_keys = array_map( 'sanitize_text_field', $_POST['rsvpmaker_stripe_keys']);

					if(!isset($stripe_keys['sk']) || !isset($stripe_keys['sandbox_pk']))
					{
						$prev = get_option('rsvpmaker_stripe_keys');
						if(!isset($stripe_keys['sk']))
							{
								$stripe_keys['sk'] = $prev['sk'];
								$stripe_keys['pk'] = $prev['pk'];
							}
						if(!isset($stripe_keys['sandbox_sk']))
						{
							$stripe_keys['sandbox_sk'] = $prev['sandbox_sk'];
							$stripe_keys['sandbox_pk'] = $prev['sandbox_pk'];
						}
					}
					update_option('rsvpmaker_stripe_keys',$stripe_keys);
				  }
				if(isset($_POST['rsvpmaker_paypal_rest_keys']))
				{
					$pkeys = array_map( 'sanitize_text_field', $_POST['rsvpmaker_paypal_rest_keys']);

					if(!isset($pkeys['client_id']) || !isset($keys['sandbox_client_id']))
					{
						$prev = get_option('rsvpmaker_paypal_rest_keys');
						if(!isset($pkeys['client_id']))
							{
								$pkeys['client_id'] = $prev['client_id'];
								$pkeys['client_secret'] = $prev['client_secret'];
							}
						if(!isset($pkeys['sandbox_client_id']))
						{
							$pkeys['sandbox_client_id'] = $prev['sandbox_client_id'];
							$pkeys['sandbox_client_secret'] = $prev['sandbox_client_secret'];
					}
					}
				update_option('rsvpmaker_paypal_rest_keys',$pkeys);
				}
				  
				  $paypal_rest_keys = get_option('rsvpmaker_paypal_rest_keys');

                  echo '<div class="updated fade"><p>'.__('Plugin settings saved - payments.','rsvpmaker').' <a href="'.admin_url('options-general.php?page=rsvpmaker-admin.php&payment_key_test=1').'">',__('Test Keys','rsvpmaker').'</a></p>'.default_gateway_check( get_rsvpmaker_payment_gateway () ).'</div>';
			  }	

			  if(isset($_POST["enotify_option"])) {
				  //print_r($_POST["enotify_option"]);
				$newoptions = array_map( 'sanitize_text_field', stripslashes_deep($_POST["enotify_option"] ) );
				foreach($newoptions as $name => $value)
				  $options[$name] = sanitize_text_field($value);

                  update_option($this->db_option, $options);
                  echo '<div class="updated fade"><p>'.__('Plugin settings saved - email server.','rsvpmaker').'</p></div>';
			  }	
			  
			  if(isset($_POST["security_option"])) {
				foreach($_POST["security_option"] as $index => $value) {
					$newoptions[sanitize_text_field($index)] = sanitize_text_field($value);
				}
				$newoptions["additional_editors"] = (isset($_POST["security_option"]["additional_editors"]) && $_POST["security_option"]["additional_editors"]) ? 1 : 0;
				foreach($newoptions as $name => $value)
				  $options[$name] = sanitize_text_field($value);
                update_option($this->db_option, $options);
                  echo '<div class="updated fade"><p>'.__('Plugin settings saved - security.','rsvpmaker').'</p></div>';
				  //print_r($newoptions);
			  }	

			  if (isset($_POST['submitted'])) {
              		
                  $newoptions = stripslashes_deep($_POST["option"]);
                  $newoptions["rsvp_on"] = (isset($_POST["option"]["rsvp_on"]) && $_POST["option"]["rsvp_on"]) ? 1 : 0;
                  $newoptions["confirmation_include_event"] = (isset($_POST["option"]["confirmation_include_event"]) && $_POST["option"]["confirmation_include_event"]) ? 1 : 0;
                  $newoptions['rsvpmaker_send_confirmation_email'] = (isset($_POST["option"]['rsvpmaker_send_confirmation_email']) && $_POST["option"]['rsvpmaker_send_confirmation_email']) ? 1 : 0;
                  $newoptions["login_required"] = (isset($_POST["option"]["login_required"]) && $_POST["option"]["login_required"]) ? 1 : 0;
                  $newoptions["rsvp_captcha"] = (isset($_POST["option"]["rsvp_captcha"]) && $_POST["option"]["rsvp_captcha"]) ? 1 : 0;
				  if(isset($_POST["option"]["rsvp_recaptcha_site_key"])) {
                  $newoptions["rsvp_recaptcha_site_key"] = sanitize_text_field($_POST["option"]["rsvp_recaptcha_site_key"]);
                  $newoptions["rsvp_recaptcha_secret"] = sanitize_text_field($_POST["option"]["rsvp_recaptcha_secret"]);		  
				  }
                  $newoptions["rsvp_yesno"] = (isset($_POST["option"]["rsvp_yesno"]) && $_POST["option"]["rsvp_yesno"]) ? 1 : 0;
                  $newoptions["calendar_icons"] = (isset($_POST["option"]["calendar_icons"]) && $_POST["option"]["calendar_icons"]) ? 1 : 0;
                  $newoptions["convert_timezone"] = (isset($_POST["option"]["convert_timezone"]) && $_POST["option"]["convert_timezone"]) ? 1 : 0;
                  $newoptions["social_title_date"] = (isset($_POST["option"]["social_title_date"]) && $_POST["option"]["social_title_date"]) ? 1 : 0;
                  $newoptions["rsvp_count"] = (isset($_POST["option"]["rsvp_count"]) && $_POST["option"]["rsvp_count"]) ? 1 : 0;
                  $newoptions["show_attendees"] = (isset($_POST["option"]["show_attendees"]) && $_POST["option"]["show_attendees"]) ? 1 : 0;
                  $newoptions["debug"] = (isset($_POST["option"]["debug"]) && $_POST["option"]["debug"]) ? 1 : 0;
				  
				  $newoptions["dbversion"] = $options["dbversion"]; // gets set by db upgrade routine
				  
				$newoptions["eventpage"] = sanitize_text_field($_POST["option"]["eventpage"]);
                  $newoptions["log_email"] = (isset($_POST["option"]["log_email"]) && $_POST["option"]["log_email"]) ? 1 : 0;

				foreach($newoptions as $name => $value) {
					if($name == 'rsvplink')
						$options[$name] = $value;
					else
						$options[$name] = sanitize_text_field($value);
				}
				  
                  update_option($this->db_option, $options);
                  
				  echo '<div class="updated fade"><p>Plugin settings saved.</p></div>';
				  if(isset($_POST['defaultoverride'])) {
					$future = get_future_events();
					$fcount = sizeof($future);
					$templates = rsvpmaker_get_templates();
					$tcount = sizeof($templates);
					$future = array_merge($future,$templates);
					foreach($future as $event) {
						foreach($_POST['defaultoverride'] as $slug) {
							$dbslug = '_'.sanitize_text_field($slug);
							update_post_meta($event->ID, $dbslug, $options[$slug]);
							//printf('<p>updating %s %s %s</p>',$event->ID, $dbslug, $options[$slug]);
						}						  
					}
				printf('<p>Updating %s for %s events and %s templates',esc_html(implode(', ',$_POST['defaultoverride']), $fcount, $tcount ));  
				}
			  }
              
              // URL for form submit, equals our current page
$action_url = admin_url('options-general.php?page=rsvpmaker-admin.php');
global $wpdb;
$defaulthour = (isset($options["defaulthour"])) ? ( (int) $options["defaulthour"]) : 19;
$defaultmin = (isset($options["defaultmin"])) ? ( (int) $options["defaultmin"]) : 0;
$houropt = $minopt ="";

for($i=0; $i < 24; $i++)
	{
	$selected = ($i == $defaulthour) ? ' selected="selected" ' : '';
	$padded = ($i < 10) ? '0'.$i : $i;
	if($i == 0)
		$twelvehour = "12 a.m.";
	elseif($i == 12)
		$twelvehour = "12 p.m.";
	elseif($i > 12)
		$twelvehour = ($i - 12) ." p.m.";
	else		
		$twelvehour = $i." a.m.";

	$houropt .= sprintf('<option  value="%s" %s>%s / %s:</option>',$padded,$selected,$twelvehour,$padded);
	}

for($i=0; $i < 60; $i += 5)
	{
	$selected = ($i == $defaultmin) ? ' selected="selected" ' : '';
	$padded = ($i < 10) ? '0'.$i : $i;
	$minopt .= sprintf('<option  value="%s" %s>%s</option>',$padded,$selected,$padded);
	}

if(isset($_POST['timezone_string']))
{
	$tz = sanitize_text_field($_POST['timezone_string']);
	update_option('timezone_string',$tz);
	echo '<div class="notice notice-info"><p>'. __('Timezone set to','rsvpmaker').' '.$tz.'</p></div>';
}

?>

<div class="wrap" style="max-width:950px !important;">

    <h2 class="rsvpmaker-nav-tab-wrapper nav-tab-wrapper">
      <a class="rsvpmaker-nav-tab nav-tab rsvpmaker-nav-tab-active nav-tab-active" href="#calendar"><?php esc_html_e('Calendar Settings','rsvpmaker');?></a>
      <a class="rsvpmaker-nav-tab nav-tab" href="#security"><?php esc_html_e('Security','rsvpmaker');?></a>
      <a class="rsvpmaker-nav-tab nav-tab" href="#payments"><?php esc_html_e('Payments','rsvpmaker');?></a>
      <a class="rsvpmaker-nav-tab nav-tab" href="#notification_email"><?php esc_html_e('Email Server','rsvpmaker');?></a>
      <a class="rsvpmaker-nav-tab nav-tab" href="#email"><?php esc_html_e('Mailing List','rsvpmaker');?></a>
      <a class="rsvpmaker-nav-tab nav-tab" href="#groupemail"><?php esc_html_e('Group Email','rsvpmaker');?></a>
      <a class="rsvpmaker-nav-tab nav-tab" href="#rsvpforms"><?php esc_html_e('RSVP Forms','rsvpmaker');?></a>
    </h2>

    <div id='sections' class="rsvpmaker">
    <section id="calendar" class="rsvpmaker">
	<?php
if(!isset($_REQUEST['tab']))
{
?>
<input type="hidden" id="activetab" value="calendar" />
<?php	
}
?>
<div style="float: right;">
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="N6ZRF6V6H39Q8">
<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
</div>

	<h2><?php esc_html_e('Calendar Options','rsvpmaker');?></h2>
    
    <?php
if(file_exists(WP_PLUGIN_DIR."/rsvpmaker-custom.php") )
	echo "<p><em>".__('Note: This site also implements custom code in','rsvpmaker').' '.WP_PLUGIN_DIR."/rsvpmaker-custom.php.</em></p>";
	?>
    
	<div id="poststuff" style="margin-top:10px;">

	 <div id="mainblock" style="width:710px">
	 
		<div class="dbx-content">
		 	<form name="caldendar_options" action="<?php echo esc_attr($action_url);?>" method="post">
					
                    <input type="hidden" name="submitted" value="1" /> 
					<?php rsvpmaker_nonce(); ?>
<?php
//legacy feature
if(!empty($options["default_content"])) {
?>
<h3><?php esc_html_e('Default Content for Events (such as standard meeting location)','rsvpmaker'); ?>:</h3>
<textarea name="option[default_content]"  rows="5" cols="80" id="default_content"><?php if(isset($options["default_content"])) echo esc_html($options["default_content"]);?></textarea>
<br />
<?php
}
?>
<strong><?php esc_html_e('Default Time','rsvpmaker'); ?></strong><br /> <?php esc_html_e('Hour','rsvpmaker'); ?>: <select name="option[defaulthour]"> 
<?php echo $houropt;?>
</select> 
 
<?php esc_html_e('Minutes','rsvpmaker'); ?>: <select name="option[defaultmin]"> 
<?php echo $minopt;?>
</select>
<br />
<?php echo __('See also','rsvpmaker') . ' <a href="'.admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list').'">'.__('Event Templates','rsvpmaker').'</a> '.__('for events held an a recurring schedule.','rsvpmaker'); ?><br />
<input type="checkbox" name="option[autorenew]" value="1" <?php if(isset($options["autorenew"]) && $options["autorenew"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Autorenew Events from Template','rsvpmaker'); ?></strong> <?php esc_html_e('check to turn on by default','rsvpmaker'); ?> - <?php esc_html_e('means new events are automatically added according to the schedule set in the template.','rsvpmaker');?>
<br />
<strong><?php esc_html_e('RSVP TO','rsvpmaker'); ?>:</strong><br />
<input type="radio" name="option[rsvp_to_current]" value="0" <?php if(!isset($options["rsvp_to_current"]) || ! $options["rsvp_to_current"] ) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Use this address','rsvpmaker'); ?></strong>: 
<input type="text" name="option[rsvp_to]" id="rsvp_to" value="<?php if(isset($options["rsvp_to"])) echo esc_attr($options["rsvp_to"]);?>" /><br />
<input type="radio" name="option[rsvp_to_current]" value="1" <?php if(isset($options["rsvp_to_current"]) && $options["rsvp_to_current"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Use email of current user (event author)','rsvpmaker'); ?></strong>
<br />
<br />
<input type="checkbox" name="option[rsvp_on]" value="1" <?php if(isset($options["rsvp_on"]) && $options["rsvp_on"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('RSVP On','rsvpmaker'); ?></strong>
<?php esc_html_e('check to turn on by default','rsvpmaker'); ?>	<br />    

<input type="checkbox" name="option[rsvp_captcha]" value="1" <?php if(isset($options["rsvp_captcha"]) && $options["rsvp_captcha"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('RSVP CAPTCHA On','rsvpmaker'); ?></strong> <?php esc_html_e('check to turn on by default','rsvpmaker'); ?><br />
<?php
if(function_exists('rsvpmaker_recaptcha_output'))
{
?>
<strong>Or use Google ReCaptcha (v2) </strong> <a href="https://www.google.com/recaptcha/admin" target="_blank">register</a><br />
ReCaptcha (v2) Site Key: <input type="text" name="option[rsvp_recaptcha_site_key]" value="<?php if(isset($options["rsvp_recaptcha_site_key"]) && $options["rsvp_recaptcha_site_key"]) echo esc_attr($options["rsvp_recaptcha_site_key"]);?>"><br />
ReCaptcha (v2) Secret: <input type="text" name="option[rsvp_recaptcha_secret]" value="<?php if(isset($options["rsvp_recaptcha_site_key"]) && $options["rsvp_recaptcha_secret"]) echo esc_attr($options["rsvp_recaptcha_secret"]);?>"><br />
<?php
}
?>
<input type="checkbox" name="option[login_required]" value="1" <?php if(isset($options["login_required"]) && $options["login_required"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Login Required to RSVP','rsvpmaker'); ?></strong> <?php esc_html_e('check to turn on by default','rsvpmaker'); ?>
<br />

  <input type="checkbox" name="option[show_attendees]" value="1" <?php if(isset($options["show_attendees"]) && $options["show_attendees"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('RSVPs Attendees List Public','rsvpmaker'); ?></strong> <?php esc_html_e('check to turn on by default','rsvpmaker'); ?>
	<br />

  <input type="checkbox" name="option[rsvp_count]" value="1" <?php if(isset($options["rsvp_count"]) && $options["rsvp_count"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Show RSVP Count','rsvpmaker'); ?></strong> <?php esc_html_e('check to turn on by default','rsvpmaker'); ?>
	<br />

  <input type="checkbox" name="option[rsvp_yesno]" value="1" <?php if(isset($options["rsvp_yesno"]) && $options["rsvp_yesno"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Show RSVP Yes/No Radio Buttons','rsvpmaker'); ?></strong> <?php esc_html_e('check to turn on by default','rsvpmaker'); ?>
	<br />

  <input type="checkbox" name="option[calendar_icons]" value="1" <?php if(isset($options["calendar_icons"]) && $options["calendar_icons"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Show Add to Google / Download to Outlook (iCal) icons','rsvpmaker'); ?></strong> 
	<br />

  <input type="checkbox" name="option[convert_timezone]" value="1" <?php if(isset($options["convert_timezone"]) && $options["convert_timezone"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Show timezone conversion button next to calendar icons','rsvpmaker'); ?></strong> 
	<br />

<input type="checkbox" name="option[add_timezone]" value="1" <?php if(isset($options["add_timezone"]) && $options["add_timezone"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Display timezone code as part of date/time','rsvpmaker'); ?></strong> 
	<br />

  <input type="checkbox" name="option[social_title_date]" value="1" <?php if(isset($options["social_title_date"]) && $options["social_title_date"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Include date with title shown on Facebook/Twitter previews (og:title and twitter:title metatags)','rsvpmaker'); ?></strong> 
	<br />

  <input type="checkbox" name="option[missing_members]" value="1" <?php if(isset($options["missing_members"]) && $options["missing_members"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('RSVP Form Shows Members Not Responding','rsvpmaker'); ?></strong><br /><em><?php esc_html_e('if members log in to RSVP, this shows user accounts NOT associated with an RSVP (tracking WordPress user IDs)','rsvpmaker'); ?>.</em>
	<br />

					<h3><?php esc_html_e('Instructions for Form','rsvpmaker'); ?>:</h3>
  <textarea name="option[rsvp_instructions]"  rows="5" cols="80" id="rsvp_instructions"><?php if(isset($options["rsvp_instructions"]) ) echo esc_textarea($options["rsvp_instructions"]);?></textarea>
	<br />
					<h3><?php esc_html_e('Confirmation Message','rsvpmaker'); ?>:</h3>
<?php
$confirm = get_post($options['rsvp_confirm']);
echo (strpos($confirm->post_content,'</p>')) ? $confirm->post_content : wpautop($confirm->post_content);
$confedit = admin_url('post.php?action=edit&post='.$confirm->ID);
printf('<div id="editconfirmation"><a target="_blank" href="%s">'.__('Edit','rsvpmaker').'</a></div>',$confedit);				
?>
<br />
 <input type="checkbox" name="option[rsvpmaker_send_confirmation_email]" id="rsvpmaker_send_confirmation_email" <?php if( isset($options["rsvpmaker_send_confirmation_email"]) && $options["rsvpmaker_send_confirmation_email"] ) echo ' checked="checked" ' ?> > <?php esc_html_e('Send confirmation emails','rsvpmaker'); ?> <input type="checkbox" name="option[confirmation_include_event]" id="rsvp_confirmation_include_event" <?php if( isset($options["confirmation_include_event"]) && $options["confirmation_include_event"] ) echo ' checked="checked" ' ?> > <?php esc_html_e('Include event listing with confirmation and reminders','rsvpmaker'); ?>
	<br />
<?php
if(isset($options["rsvp_form"]) && is_numeric($options["rsvp_form"]))
{
echo '<h3>Default RSVP Form</h3>';
$fpost = get_post($options["rsvp_form"]);
if(empty($fpost))
	{
	$options["rsvp_form"] = upgrade_rsvpform();
	$fpost = get_post($options["rsvp_form"]);
	}
echo rsvpmaker_form_summary($fpost);
$edit = admin_url('post.php?action=edit&post='.$options["rsvp_form"]);
printf('<div id="editconfirmation"><a href="%s">%s</a></div>',$edit,__('Edit','rsvpmaker'));
printf('<div><a style="display: block; margin-top: 15px; color: red;" href="%s">%s</a></div>',admin_url('options-general.php?page=rsvpmaker-admin.php&rsvp_form_reset='. (int) $options['rsvp_form']),__('Reset Form to Default','rsvpmaker'));
printf('<div>%s <a href="%s">%s</a></div>',__('For alternative forms, see ','rsvpmaker'),admin_url('options-general.php?page=rsvpmaker-admin.php&tab=rsvpforms'), __('RSVP Forms tab','rsvpmaker'));
}
else
{
?>
<h3><?php esc_html_e('RSVP Form','rsvpmaker'); ?> (<a href="#" id="enlarge"><?php esc_html_e('Enlarge','rsvpmaker'); ?></a>):</h3>
  <textarea name="option[rsvp_form]"  rows="5" cols="80" id="rsvpform"><?php if( isset($options["rsvp_form"]) ) echo htmlentities($options["rsvp_form"]);?></textarea>
<br /><button id="create-form">Generate Form</button> or <a href="<?php echo admin_url('options-general.php?page=rsvpmaker-admin.php&reset_form=1'); ?>"><?php esc_html_e('Reset to default','rsvpmaker'); ?></a>
<br /><?php esc_html_e("This is a customizable template for the RSVP form, introduced as part of the Aug. 2012 update. With the exception of the yes/no radio buttons and the notes textarea, fields are represented by the shortcodes [rsvpfield textfield=&quot;fieldname&quot;] or [rsvpfield selectfield=&quot;fieldname&quot; options=&quot;option1,option2&quot;]. There is also a [rsvpprofiletable show_if_empty=&quot;phone&quot;] shortcode which is an optional block that will not be displayed if the required details (such as a phone number) are already &quot;on file&quot; from a prior RSVP. For this to work, there must also be a [/rsvpprofiletable] closing tag. The guest section of the form is represented by [rsvpguests] (no parameters). If you don't want the guest blanks to show up, you can remove this. The form code you supply will be wrapped in a form tag with the CSS ID of",'rsvpmaker'); ?> &quot;rsvpform&quot;.
<script>
jQuery('#enlarge').click(function() {
  jQuery('#rsvpform').attr('rows','40');
  return false;
});
</script>
<?php
rsvp_form_setup_form($options["rsvp_form"]);
}
?>
	<br />
					<h3><?php esc_html_e('RSVP Link','rsvpmaker'); ?>:</h3>

  <textarea name="option[rsvplink]"  rows="5" cols="80" id="rsvplink"><?php if(isset($options["rsvplink"]) ) echo wp_kses_post($options["rsvplink"]);?></textarea>
	<br />Example:
<?php if(isset($options["rsvplink"]) ) echo wp_kses_post($options["rsvplink"]);?>
<h3><?php esc_html_e('Label for Updates','rsvpmaker'); ?>:</h3>
  <input type="text" name="option[update_rsvp]"  rows="5" cols="80" id="update_rsvp" value="<?php if(isset($options["update_rsvp"]) ) echo esc_attr($options["update_rsvp"]);?>" />
	<br />
					<h3><?php esc_html_e('RSVP Form Title','rsvpmaker'); ?>:</h3>
  <input type="text" name="option[rsvp_form_title]"  rows="5" cols="80" id="rsvp_form_title" value="<?php if(isset($options["rsvp_form_title"]) ) echo esc_attr($options["rsvp_form_title"]);?>" />
	<br />
<h3 id="privacy_consent"><?php esc_html_e('Privacy Consent','rsvpmaker'); ?>:</h3>
				<p>For compliance with the European Union's General Data Protection Regulation (GDPR) and other data privacy and security regulations, you can add a checkbox to your form requiring the user to agree to your privacy policy. WordPress 4.9.6 added a privacy policy tool, and you can consult the <a href="https://rsvpmaker.com/privacy-policy/#rsvpmaker">rsvpmaker.com privacy policy</a> for suggested language about the use of this plugin. RSVPMaker also hooks into the WordPress tools for exporting or deleting a user's information on demand. See the <a href="https://rsvpmaker.com/blog/2018/05/20/control-of-personal-data-gdpr-compliance/">blog post</a>.</p>
				<?php 
			  	$privacy_page = rsvpmaker_check_privacy_page();
			  if($privacy_page)
			  {
				  $privacy_url = get_permalink($privacy_page);
?>
				<p><input type="radio" name="option[privacy_confirmation]" value="1" <?php if(!empty($options["privacy_confirmation"]) && ($options["privacy_confirmation"]) ) echo 'checked="checked"'; ?> /> Yes <input type="radio" name="option[privacy_confirmation]" value="no" <?php if(empty($options["privacy_confirmation"]) || ($options["privacy_confirmation"] == 'no')) echo 'checked="checked"'; ?> /> <?php esc_html_e('No - Add checkbox?','rsvpmaker');?></p>
				<p><textarea name="option[privacy_confirmation_message]" style="width: 95%"><?php if(empty($options['privacy_confirmation_message'])) printf('I consent to the <a target="_blank" href="%s">privacy policy</a> site of this site for purposes of follow up to this registration.',$privacy_url); else echo wp_kses_post($options['privacy_confirmation_message']); ?></textarea></p>
<?php				  
			  }
			  else
			  	echo'<p>'.__('First, you must register a privacy page with WordPress','rsvpmaker').': <a href="'.admin_url('options-privacy.php').'">'.admin_url('options-privacy.php').'</a></p>';
			  
				?>
				
			<h3><?php esc_html_e('Date Format (long)','rsvpmaker'); ?>:</h3>
  <input type="text" name="option[long_date]"  id="long_date" value="<?php if(isset($options["long_date"]) ) echo esc_attr($options["long_date"]);?>" /> (used at the top of event listings)
	<br />
					<h3><?php esc_html_e('Date Format (short)','rsvpmaker'); ?>:</h3>
  <input type="text" name="option[short_date]"  id="short_date" value="<?php if(isset($options["short_date"]) ) echo esc_attr($options["short_date"]);?>" /> (used in headlines for event_listing shortcode, also sidebar widget)
	
<br />For reference, see PHP <a target="_blank" href="https://www.php.net/manual/en/datetime.format.php">date format strings</a>
<br />Examples:<br />
<?php
echo 'l F j, Y = '.rsvpmaker_date('l F j, Y').'<br />'; 
echo 'j F Y = '.rsvpmaker_date('j F Y').'<br />'; 
echo 'm-d-Y = '.rsvpmaker_date('m-d-Y').'<br />'; 
?>
<br />
<h3><?php esc_html_e('Time Format','rsvpmaker'); ?>:</h3>
<p>
<input type="radio" name="option[time_format]" value="g:i A" <?php if( isset($options["time_format"]) && ($options["time_format"] == "g:i A")) echo ' checked="checked"';?> /> 12 hour AM/PM 
<input type="radio" name="option[time_format]" value="H:i" <?php if( isset($options["time_format"]) && ($options["time_format"] == "H:i")) echo ' checked="checked"';?> /> 24 hour 

<input type="radio" name="option[time_format]" value="g:i A T" <?php if( isset($options["time_format"]) && ($options["time_format"] == "g:i A T")) echo ' checked="checked"';?> /> 12 hour AM/PM (include timezone)
<input type="radio" name="option[time_format]" value="H:i T" <?php if( isset($options["time_format"]) && ($options["time_format"] == "H:i T")) echo ' checked="checked"';?> /> 24 hour (include timezone)

<br />
					<h3><?php esc_html_e('Event Page','rsvpmaker'); ?>:</h3>
  <input type="text" name="option[eventpage]" value="<?php if(isset($options["eventpage"]))  echo esc_attr($options["eventpage"]);?>" size="80" />

<br /><h3><?php esc_html_e('Custom CSS','rsvpmaker'); ?>:</h3>
  <input type="text" name="option[custom_css]" value="<?php if(isset($options["custom_css"]) ) echo esc_attr($options["custom_css"]);?>" size="80" />
<?php
if(isset($options["custom_css"]) && $options["custom_css"])
	{

		$file_headers = @get_headers($options["custom_css"]);
		if($file_headers[0] == 'HTTP/1.1 404 Not Found') {
			echo ' <span style="color: red;">'.__('Error: CSS not found','rsvpmaker').'</span>';
		}
		else {
			echo ' <span style="color: green;">'.__('OK','rsvpmaker').'</span>';
		}

	}
$dstyle = plugins_url('style.css',__FILE__);
?>

	<br /><em><?php esc_html_e('Allows you to override the standard styles from','rsvpmaker'); ?> <br /><a href="<?php echo esc_attr($dstyle);?>"><?php echo esc_html($dstyle);?></a></em>
	<br /><em><?php esc_html_e('Probably a better option: use the Customize utility built into WordPress to override the defaults','rsvpmaker'); ?></em>
<h3><?php esc_html_e('Theme Template for Events'); ?></h3>
<br /><select name="option[rsvp_template]"><?php
$current_template = (empty($options["rsvp_template"])) ? 'page.php' : $options["rsvp_template"];
$templates = get_page_templates();
$templates['Page'] = 'page.php';
$templates['Single'] = 'single.php';
foreach($templates as $tname => $tfile)
	{
	$s = ($tfile == $current_template) ? ' selected="selected" ' : '';
	printf('<option value="%s" %s>%s</option>',$tfile,$s,$tname);
	}
?></select> <br /><em><?php esc_html_e('Template from your theme to be used in the absence of a single-rsvpmaker.php file.','rsvpmaker'); ?></em>


<h3><?php esc_html_e('Dashboard','rsvpmaker');?></h3>
<select name="option[dashboard]">
<option value=""><?php esc_html_e('No Widget','rsvpmaker');?></option>
<option value="show" <?php if(isset($options["dashboard"]) && ($options["dashboard"] == 'show')) echo ' selected="selected" '; ?> ><?php esc_html_e('Show Widget','rsvpmaker');?></option>
<option value="top" <?php if(isset($options["dashboard"]) && ($options["dashboard"] == 'top')) echo ' selected="selected" '; ?> ><?php esc_html_e('Show Widget on Top','rsvpmaker');?></option>
</select>
<br /><?php esc_html_e('Note','rsvpmaker'); ?>
<br />
<textarea name="option[dashboard_message]" style="width:90%;"><?php echo esc_html($options["dashboard_message"]); ?></textarea>

<h3><?php esc_html_e('Event Submissions','rsvpmaker'); ?></h3>
<p>Notify <input style="width: 90%" type="text" name="option[submissions_to]" id="rsvp_to" value="<?php if(isset($options["submissions_to"])) echo esc_attr($options["submissions_to"]); elseif(isset($options["rsvp_to"])) echo esc_attr($options["rsvp_to"]);?>" />
<br />Attribute to <?php $submission_author = (isset($options['submission_author'])) ? $options['submission_author'] : 1; wp_dropdown_users(array('name' => 'option[submission_author]','selected' => $submission_author)); ?>
</p>
<p>To accept event submissions on the front end of your website, include the RSVPMaker Event Submission block or [rsvpmaker_submission] shortcode. Submissions are saved as drafts for an editor's approval.</p>

<h3><?php esc_html_e('Apply to Existing Events','rsvpmaker'); ?></h3>
<p>Check here if you want any of the following variables to be applied to existing events and event templates (will override any customizations).</p>
<p><input  type="checkbox"  name="defaultoverride[]" value="rsvp_on" /> Collect RSVPs <input type="checkbox" name="defaultoverride[]" value="rsvp_form" /> Form <input type="checkbox" name="defaultoverride[]" value="rsvp_confirm" /> Confirmation Message </p>

<h3><?php esc_html_e('Troubleshooting and Logging','rsvpmaker'); ?></h3>
  <input type="checkbox" name="option[debug]" value="1" <?php if(isset($options["debug"]) && $options["debug"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Debug and log. Log messages will be saved in the uploads directory with file names in the pattern rsvpmaker_log_2018-01-01.txt','rsvpmaker'); ?></strong>
	<br />
  <input type="checkbox" name="option[log_email]" value="1" <?php if(isset($options["log_email"]) && $options["log_email"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Log email','rsvpmaker'); ?>: Monitor notification/confirmation messages generated</strong>
	<br />
			  <div class="submit"><input type="submit" name="Submit" value="<?php esc_html_e('Update','rsvpmaker'); ?>" /></div>
			</form>

	    </div>
		
	 </div>

	</div>

</section>
<section id="security" class="rsvpmaker">
<form name="rsvpmaker_security_options" action="<?php echo esc_attr($action_url);?>" method="post">
<?php rsvpmaker_nonce(); ?>
<h3><?php esc_html_e('Menu Security','rsvpmaker'); ?>:</h3>
<?php
rsvpmaker_menu_security( __("RSVP Report",'rsvpmaker'),  "menu_security", $options );
rsvpmaker_menu_security(__("Event Templates",'rsvpmaker'),"rsvpmaker_template",$options );
rsvpmaker_menu_security( __("Recurring Event",'rsvpmaker'), "recurring_event", $options );
rsvpmaker_menu_security( __("Multiple Events",'rsvpmaker'), "multiple_events",$options );
rsvpmaker_menu_security( __("Documentation",'rsvpmaker'), "documentation",$options );
?>
<p><em><?php esc_html_e('Security level required to access custom menus (RSVP Report, Documentation)','rsvpmaker'); ?></em></p>
<h3><?php esc_html_e('Additional Editors / Co-Authors','rsvpmaker'); ?></h3>
  <input type="checkbox" name="security_option[additional_editors]" value="1" <?php if(isset($options["additional_editors"]) && $options["additional_editors"]) echo ' checked="checked" ';?> /> <strong><?php esc_html_e('Additional Editors','rsvpmaker'); ?></strong> <em><?php esc_html_e('Allow users to share editing rights for event templates and related events.','rsvpmaker'); ?></em>
	<p><strong>How this works: </strong> When this function is enabled, event authors have the option of allowing other users to be additional editors or co-authors for an event or a series of events based  on a template. This is useful for community websites where multiple organizations post their events. The organization can appoint multiple officers or representatives to have equal rights to update the events template for their meetings and all the individual events based on that template.</p>
	<p>Note that to unlock events for editing, RSVPMaker changes the author ID for a post to the ID of the authorized user editing the post.</p>				
<?php
if(isset($_REQUEST['tab']) && $_REQUEST['tab'] == 'security')
{
?>
<input type="hidden" id="activetab" value="security" />
<?php	
}
?>
<input type="hidden" name="tab" value="security">
				 				 				
<div class="submit"><input type="submit" name="Submit" value="<?php esc_html_e('Update','rsvpmaker'); ?>" /></div>
	</form>
</section>
<section id="payments" class="rsvpmaker">
<?php do_action('rsvpmaker_payments_setting_top'); ?>
<form name="rsvpmaker_payment_options" action="<?php echo esc_attr($action_url);?>" method="post">
<?php rsvpmaker_nonce(); ?>
<h1>Online Payments</h1>
<p>If you wish to collect online payments, please set up API access to the payment gateway of your choice.</p>
<?php do_action('rsvpmaker_payment_settings'); ?>
<h3><?php esc_html_e('Track RSVP as &quot;invoice&quot; number','rsvpmaker'); ?>:</h3>
<div>
<input type="radio" name="payment_option[paypal_invoiceno]" value ="1" <?php if($options["paypal_invoiceno"]) echo ' checked="checked" ' ?> /> Yes
	<input type="radio" name="payment_option[paypal_invoiceno]" value ="0" <?php if(!$options["paypal_invoiceno"]) echo ' checked="checked" ' ?> /> No</div>
	<div><em><?php esc_html_e('Must be enabled for RSVPMaker to track payments','rsvpmaker'); ?></em></div>
<h3><?php esc_html_e('Send Payment Reminder','rsvpmaker'); ?>:</h3>
<div>
<input type="radio" name="payment_option[send_payment_reminders]" value ="1" <?php if($options["send_payment_reminders"]) echo ' checked="checked" ' ?> /> Yes
	<input type="radio" name="payment_option[send_payment_reminders]" value ="0" <?php if(!$options["send_payment_reminders"]) echo ' checked="checked" ' ?> /> No</div>
	<div><em><?php esc_html_e('If someone RSVPs but does not pay, send an email reminder that their registration is not complete without payment.','rsvpmaker'); ?></em></div>

<h3><?php esc_html_e('Payment Currency','rsvpmaker'); ?>:</h3>
<div><input type="text" name="payment_option[paypal_currency]" value="<?php if(isset($options["paypal_currency"])) echo esc_attr($options["paypal_currency"]);?>" size="5" /> <a href="https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_nvp_currency_codes">(list of codes)</a>

<select name="currency_format">
<option value="<?php if(isset($options["currency_decimal"]) ) echo esc_attr($options["currency_decimal"]);?>|<?php if(isset($options["currency_thousands"])) echo esc_attr($options["currency_thousands"]);?>"><?php echo esc_attr(number_format(1000.00, 2, $options["currency_decimal"], $options["currency_thousands"])); ?></option>
<option value=".|,"><?php echo esc_html(number_format(1000.00, 2, '.',  ',')); ?></option>
<option value=",|."><?php echo esc_html(number_format(1000.00, 2, ',',  '.')); ?></option>
<option value=",| "><?php echo esc_html(number_format(1000.00, 2, ',',  ' ')); ?></option>
</select>    
</div>

<h3><?php esc_html_e('Minimum Amount','rsvpmaker'); ?>:</h3>
<div><input type="text" name="payment_option[payment_minimum]" value="<?php if(isset($options["payment_minimum"])) {echo esc_attr($options["payment_minimum"]);} else echo '5.00';?>" size="5" /> <br /><em><?php _e('Prevents fraudulent uses such as $1 donations to test stolen cards','rsvpmaker'); ?></em>
</div>

<h3>PayPal (REST API)</h3>
<p><?php esc_html_e('Keys may be obtained from','rsvpmaker'); ?> <a target="_blank" href="https://developer.paypal.com/developer/applications">developer.paypal.com/developer/applications/</a></p>
<?php
$test_keys = sprintf(' <a href="%s">%s</a> ',admin_url('options-general.php?page=rsvpmaker-admin.php&payment_key_test=1'),__('Test Keys','rsvpmaker'));
$pp_on = false;
$paypal_rest_keys = get_option('rsvpmaker_paypal_rest_keys');
if(empty($paypal_rest_keys))
	$paypal_rest_keys = array('client_id' => '','client_secret' => '','sandbox_client_id' => '','sandbox_client_secret' => '');
$checkboxes = (empty($paypal_rest_keys['sandbox'])) ? '<input type="radio" name="rsvpmaker_paypal_rest_keys[sandbox]" value="1" /> Sandbox <input type="radio" name="rsvpmaker_paypal_rest_keys[sandbox]" value="0" checked="checked" /> Production' : '<input type="radio" name="rsvpmaker_paypal_rest_keys[sandbox]" value="1"  checked="checked" /> Sandbox <input type="radio" name="rsvpmaker_paypal_rest_keys[sandbox]" value="0" /> Production';
if(!empty($paypal_rest_keys['client_id']) && !empty($paypal_rest_keys['client_secret']))
{
	$pp_on = true;
?>
<div id="paypal_production"><?php esc_html_e('Production keys set','rsvpmaker'); echo ' '.  $test_keys; ?> <p><button id="reset_paypal_production"><?php esc_html_e('Reset PayPal','rsvpmaker'); ?></button></p></div>
<?php
}
else
{
?>
<p>Client ID (Production):<br />
<input name="rsvpmaker_paypal_rest_keys[client_id]"  class="paypal_keys" value="<?php echo esc_attr($paypal_rest_keys['client_id']); ?>"></p>
<p>Client Secret (Production):<br />
<input name="rsvpmaker_paypal_rest_keys[client_secret]" class="paypal_keys"  value="<?php echo esc_attr($paypal_rest_keys['client_secret']); ?>"></p>
<?php
}
if(!empty($paypal_rest_keys['sandbox_client_id']) && !empty($paypal_rest_keys['sandbox_client_secret']))
{
	$pp_on = true;
?>
<div id="paypal_sandbox"><?php esc_html_e('Sandbox keys set','rsvpmaker'); echo ' '.$test_keys; ?> <p><button id="reset_paypal_sandbox"><?php esc_html_e('Reset PayPal Sandbox','rsvpmaker'); ?></button></p></div>
<?php
}
else {
?>
<p>Client ID (Sandbox):<br />
<input name="rsvpmaker_paypal_rest_keys[sandbox_client_id]"  class="paypal_keys" value="<?php echo esc_attr($paypal_rest_keys['sandbox_client_id']); ?>"></p>
<p>Client Secret (Sandbox):<br />
<input name="rsvpmaker_paypal_rest_keys[sandbox_client_secret]"  class="paypal_keys" value="<?php echo esc_attr($paypal_rest_keys['sandbox_client_secret']); ?>"></p>
<?php
}

?>
<p>Operating Mode: <?php echo $checkboxes; ?></p>

<h3>Stripe</h3>
<p><?php esc_html_e('Keys may be obtained from','rsvpmaker'); ?> <a target="_blank" href="https://dashboard.stripe.com/apikeys">dashboard.stripe.com/apikeys</a></p>
<?php 
$stripe_on = false;
$stripe_keys = get_rsvpmaker_stripe_keys_all (); 
$checkboxes = ($stripe_keys['mode'] == 'production') ? '<input type="radio" name="rsvpmaker_stripe_keys[mode]" value="sandbox" /> Sandbox <input type="radio" name="rsvpmaker_stripe_keys[mode]" value="production" checked="checked" /> Production' : '<input type="radio" name="rsvpmaker_stripe_keys[mode]" value="sandbox"  checked="checked" /> Sandbox <input type="radio" name="rsvpmaker_stripe_keys[mode]" value="production" /> Production';
if(!empty($stripe_keys['pk']) && !empty($stripe_keys['sk']))
{
$stripe_on = true;
?>
<div id="stripe_production"><?php esc_html_e('Production keys set','rsvpmaker'); echo ' '.$test_keys; ?> <p><button id="reset_stripe_production"><?php esc_html_e('Reset Stripe','rsvpmaker'); ?></button></p></div>
<?php
}
else
{
?>
<p>Publishable Key (Production):<br />
	<input name="rsvpmaker_stripe_keys[pk]"  class="stripe_keys" value="<?php echo esc_attr($stripe_keys['pk']); ?>"></p>
<p>Secret Key (Production):<br />
	<input name="rsvpmaker_stripe_keys[sk]"  class="stripe_keys" value="<?php echo esc_attr($stripe_keys['sk']);  ?>"></p>
<?php	
}
if(!empty($stripe_keys['sandbox_pk']) && !empty($stripe_keys['sandbox_sk']))
{
$stripe_on =true;
?>
<div id="stripe_sandbox"><?php esc_html_e('Sandbox keys set','rsvpmaker'); echo ' '.$test_keys; ?> <p><button id="reset_stripe_sandbox"><?php esc_html_e('Reset Stripe Sandbox','rsvpmaker'); ?></button></p></div>
<?php
}
else
{
?>
<p>Publishable Key (Sandbox):<br />
<input name="rsvpmaker_stripe_keys[sandbox_pk]"  class="stripe_keys" value="<?php echo esc_attr($stripe_keys['sandbox_pk']); ?>"></p>
<p>Secret Key (Sandbox):<br />
<input name="rsvpmaker_stripe_keys[sandbox_sk]" class="stripe_keys" value="<?php echo esc_attr($stripe_keys['sandbox_sk']);  ?>"></p>
<?php
}
?>
<p>Notification Email for Stripe (optional):<br />
	<input name="rsvpmaker_stripe_keys[notify]" value="<?php echo esc_attr($stripe_keys['notify']); ?>"></p>	
<p>Operating Mode: <?php echo $checkboxes; ?></p>
	<?php
if (class_exists('Stripe_Checkout_Functions'))
	{
	echo '<h3>'.__('WP Simple Pay Lite for Stripe plugin detected','rsvpmaker').'</h3>';
	printf('<div>Use WP Simple Pay <select name="stripe"><option value ="1" %s>Yes</option><option value ="0" %s>No</option></select></div>', ((!empty($options["stripe"])) ? 'selected="selected"' : ''), ((empty($options["stripe"])) ? 'selected="selected"' : '') );
	echo '<div><em>'.__('Note: RSVPMaker now includes its own independent support for Stripe. You can enter the API keys below.').'</em></div>';
	}

?>
<p><?php echo __('Developers also have the option of hooking into the "rsvpmaker_cash_or_custom" action hook (<a href="https://rsvpmaker.com/blog/2017/10/18/custom-payment-gateway/" target="_blank">documentation</a>)','rsvpmaker'); ?></p>

<?php
if(isset($_REQUEST['tab']) && $_REQUEST['tab'] == 'payments')
{
?>
<input type="hidden" id="activetab" value="payments" />
<?php	
}
$gateways = get_rsvpmaker_payment_options ();
$chosen_gateway = get_rsvpmaker_payment_gateway ();
$o = '';
foreach($gateways as $gateway)
	{
		$s = ($chosen_gateway == $gateway) ? ' selected="selected" ' : '';
		$o .= sprintf('<option %s value="%s">%s</option>',$s,$gateway,$gateway);
	}
?>
<input type="hidden" id="paypal_on" value="<?php echo $pp_on; ?>">
<input  type="hidden"  id="stripe_on" value="<?php echo $stripe_on; ?>">
<h3><?php esc_html_e('Preferred Payment Gateway','rsvpmaker');?></h3>
<p><select id="payment_gateway" name="payment_option[payment_gateway]"><?php echo $o; ?></select></p>
<?php
echo default_gateway_check($chosen_gateway);
?>
<p><em><?php esc_html_e('If you have set up more than one, specify the one to be used by default.','rsvpmaker');?></em></p>

<input type="hidden" name="tab" value="payments">

<div class="submit"><input type="submit" name="Submit" value="<?php esc_html_e('Update','rsvpmaker'); ?>" /></div>
</form>

</section>
<section id="notification_email" class="rsvpmaker">
<form name="notify_options" action="<?php echo esc_attr($action_url);?>" method="post">
<?php rsvpmaker_nonce(); ?>
<?php do_action('rsvpmaker_email_settings'); ?>
<p><?php esc_html_e('These settings are related to transactional emails, such as registration confirmation messages. If you are using another plugin that improves the delivery of other WordPress generated emails such as password resets, you may be able to leave these settings at their defaults.','rsvpmaker'); ?></p>

<p>
<?php esc_html_e('From Email Address for All Notifications','rsvpmaker'); ?><br />
<input type="text" name="enotify_option[from_always]" value="<?php if(!empty($options["from_always"])) echo esc_attr($options["from_always"]); elseif(!empty($options["smtp_useremail"])) echo esc_attr($options["smtp_useremail"]);?>" size="15" />
</p>
<h3 id="smtp"><?php esc_html_e('SMTP for Notifications','rsvpmaker'); ?></h3>
<p><?php esc_html_e('For more reliable delivery of email notifications, enable delivery through the SMTP email protocol. Standard server parameters will be used for Gmail and the SendGrid service, or specify the server port number and security protocol','rsvpmaker'); ?>.</p>
<p><?php esc_html_e('If you are using another plugin that improves the delivery of email notifications, such one of the <a href="https://wordpress.org/plugins/sendgrid-email-delivery-simplified/">SendGrid plugin</a> (which uses the SendGrid API rather than SMTP), leave this set to "None - use wp_mail()."','rsvpmaker'); ?>.</p>
  <select name="enotify_option[smtp]" id="smtp">
  <option value="" <?php if(isset($options["smtp"]) && ($options["smtp"] == '' )) {echo ' selected="selected" ';}?> ><?php esc_html_e('None - use wp_mail()','rsvpmaker'); ?></option>
  <option value="gmail" <?php if(isset($options["smtp"]) && ($options["smtp"] == 'gmail')) {echo ' selected="selected" ';}?> >Gmail</option>
  <option value="sendgrid" <?php if(isset($options["smtp"]) && ($options["smtp"] == 'sendgrid')) {echo ' selected="selected" ';}?> >SendGrid (SMTP)</option>
  <option value="other" <?php if(isset($options["smtp"]) && ($options["smtp"] == 'other')) {echo ' selected="selected" ';}?> ><?php esc_html_e('Other SMTP (specified below)','rsvpmaker'); ?></option>
  </select>
<br />
<?php esc_html_e('Email Account for Notifications','rsvpmaker'); ?>
<br />
<input type="text" name="enotify_option[smtp_useremail]" value="<?php if(isset($options["smtp_useremail"])) echo esc_attr($options["smtp_useremail"]);?>" size="15" />
<br />
<?php esc_html_e('Email Username','rsvpmaker'); ?>
<br />
<input type="text" name="enotify_option[smtp_username]" value="<?php if(isset($options["smtp_username"])) echo esc_attr($options["smtp_username"]);?>" size="15" />
<br />
<?php esc_html_e('Email Password','rsvpmaker'); ?>
<br />
<input type="text" name="enotify_option[smtp_password]" value="<?php if(isset($options["smtp_password"])) echo esc_attr($options["smtp_password"]);?>" size="15" />
<br />
<?php esc_html_e('Server (parameters below not necessary if you specified Gmail or SendGrid)','rsvpmaker'); ?><br />
<input type="text" name="enotify_option[smtp_server]" value="<?php if(isset($options["smtp_server"])) echo esc_attr($options["smtp_server"]);?>" size="15" />
<br />
<?php esc_html_e('SMTP Security Prefix (ssl or tls, leave blank for non-encrypted connections)','rsvpmaker'); ?> 
<br />
<input type="text" name="enotify_option[smtp_prefix]" value="<?php if(isset($options["smtp_prefix"])) echo esc_attr($options["smtp_prefix"]);?>" size="15" />
<br />
<?php esc_html_e('SMTP Port','rsvpmaker'); ?>
<br />
<input type="text" name="enotify_option[smtp_port]" value="<?php if(isset($options["smtp_port"])) echo esc_attr($options["smtp_port"]);?>" size="15" />
<br />

<p><?php esc_html_e('See <a href="http://www.wpsitecare.com/gmail-smtp-settings/">this article</a> for additional guidance on using Gmail (requires a tweak to security settings in your Google account). If you have trouble getting Gmail or ssl or tls connections to work, an unencrypted port 25 connection to an email account on the same server that hosts your website should be reasonably secure since no data will be passed over the network.','rsvpmaker');?></p>

<?php 
if(!empty($options["smtp"]))
	{
?>
<a href="<?php echo admin_url('options-general.php?page=rsvpmaker-admin.php&smtptest=1'); ?>"><?php esc_html_e('Send SMTP Test to RSVP To address','rsvpmaker'); ?></a>
<?php
	}
?>
<?php
if(isset($_REQUEST['tab']) && $_REQUEST['tab'] == 'notification_email')
{
?>
<input type="hidden" id="activetab" value="notification_email" />
<?php	
}
?>
<input type="hidden" name="tab" value="notification_email">

<div class="submit"><input type="submit" name="Submit" value="<?php esc_html_e('Update','rsvpmaker'); ?>" /></div>
</form>
</section>
<section id="email" class="rsvpmaker">

<?php
global $RSVPMaker_Email_Options;
if(empty($RSVPMaker_Email_Options))
$RSVPMaker_Email_Options = new RSVPMaker_Email_Options();
$RSVPMaker_Email_Options->handle_options();
?>

    </section>
<section id="groupemail" class="rsvpmaker">
<form action="<?php echo admin_url('options-general.php?page=rsvpmaker-admin.php'); ?>" method="post">
<?php rsvpmaker_nonce(); ?>
<h2><?php esc_html_e('Group Email','rsvpmaker'); ?></h2>
<?php
do_action('group_email_admin_notice');

echo '<p>'.__('Membership oriented websites can use this feature to relay messages from any member with a user account to all other members. Designed to work with POP3 email accounts. Members can unsubscribe.','rsvpmaker').'</p>';

$hooksays = wp_get_schedule('rsvpmaker_relay_init_hook');

if(isset($_POST['rsvpmaker_discussion_server']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')))
	update_option('rsvpmaker_discussion_server',sanitize_text_field($_POST['rsvpmaker_discussion_server']));
if(isset($_POST['rsvpmaker_discussion_member']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key'))) {
	$newarray = array();
	foreach($_POST['rsvpmaker_discussion_member'] as $index => $value)
		$newarray[$index] = sanitize_textarea_field($value);
	update_option('rsvpmaker_discussion_member',$newarray);	
}
if(isset($_POST['rsvpmaker_discussion_officer']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key'))) {
	$newarray = array();
	foreach($_POST['rsvpmaker_discussion_officer'] as $index => $value)
		$newarray[$index] = sanitize_textarea_field($value);
	update_option('rsvpmaker_discussion_officer',$newarray);
}
if(isset($_POST['rsvpmaker_discussion_extra']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key'))) {
	$newarray = array();
	foreach($_POST['rsvpmaker_discussion_extra'] as $index => $value)
		$newarray[$index] = sanitize_textarea_field($value);
	update_option('rsvpmaker_discussion_extra',$newarray);
}
if(isset($_POST['rsvpmaker_discussion_bot']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key'))) {
	$newarray = array();
	foreach($_POST['rsvpmaker_discussion_bot'] as $index => $value)
		$newarray[$index] = sanitize_textarea_field($value);
	update_option('rsvpmaker_discussion_bot',$newarray);
}
if(isset($_POST['rsvpmaker_discussion_active']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key'))) {
	update_option('rsvpmaker_discussion_active',(int) $_POST['rsvpmaker_discussion_active']);
	deactivate_plugins('wp-mailster/wp-mailster.php',false,false);
	if(!wp_get_schedule('rsvpmaker_relay_init_hook')) {
		wp_schedule_event( strtotime('+2 minutes'), 'doubleminute', 'rsvpmaker_relay_init_hook' );
		echo '<p>Activating rsvpmaker_relay_init_hook</p>';
	}
}
elseif(isset($_POST))
	wp_unschedule_hook( 'rsvpmaker_relay_init_hook' );

$active = (int) get_option('rsvpmaker_discussion_active');

$server = get_option('rsvpmaker_discussion_server');
if(empty($server))
	{
	$server = '{localhost:995/pop3/ssl/novalidate-cert}';
	update_option('rsvpmaker_discussion_server',$server);
	}
$member = get_option('rsvpmaker_discussion_member');
$officer = get_option('rsvpmaker_discussion_officer');

if(is_plugin_active( 'wp-mailster/wp-mailster.php' ) )
	{
	echo '<div style="border: thin dotted red; padding: 10px; margin: 5px;">';
		$sql = "SELECT * FROM ".$wpdb->prefix."mailster_lists WHERE name LIKE 'Member%' ";
		$row = $wpdb->get_row($sql);
		if(!empty($row->list_mail) && empty($member) ){
			$member = array('user' => $row->list_mail,'password' => $row->mail_in_pw, 'subject_prefix' => 'Members:'.get_option('blogname'), 'whitelist' => '','additional_recipients' => '', 'blocked' => '');
			update_option('rsvpmaker_discussion_member',$member);
			echo '<p>'.__('Importing Member List settings from WP Mailster','rsvpmaker').'</p>';
		}
		$sql = "SELECT * FROM ".$wpdb->prefix."mailster_lists WHERE name LIKE 'Officer%' ";
		$row = $wpdb->get_row($sql);
		if(!empty($row->list_mail) && empty($officer) ){
			$officer = array('user' => $row->list_mail,'password' => $row->mail_in_pw, 'subject_prefix' => 'Officers:'.get_option('blogname'), 'whitelist' => '','additional_recipients' => '', 'blocked' => '');
			update_option('rsvpmaker_discussion_officer',$officer);
			echo '<p>'.__('Importing Officer List settings from WP Mailster','rsvpmaker').'</p>';
		}
	echo '<p>'.__('If you activate this feature, WP Mailster will be deactivated','rsvpmaker').'</p>';
	echo '</div>';
	}

echo rsvpmaker_relay_get_pop('member');
echo rsvpmaker_relay_get_pop('officer');
echo rsvpmaker_relay_get_pop('extra');
echo rsvpmaker_relay_get_pop('bot');

printf('<p><label>Activate</label> <input type="radio" name="rsvpmaker_discussion_active" value="1" %s /> Yes <input type="radio" name="rsvpmaker_discussion_active" value="0" %s /> No</p>',($active) ? ' checked="checked" ' : '',(!$active) ? ' checked="checked" ' : '');

printf('<p><label>Server</label> <input type="text" name="rsvpmaker_discussion_server" value="%s" /></p>',esc_attr($server));

$member = get_option('rsvpmaker_discussion_member');
if(empty($member))
	$member = array('user' => '','password' => '','subject_prefix' => 'Members:'.get_option('blogname'), 'whitelist' => '', 'blocked' => '','additional_recipients' => '');
print_group_list_options('member', $member);

if(is_plugin_active( 'rsvpmaker-for-toastmasters/rsvpmaker-for-toastmasters.php' ))
{
	//officers section
	$officer = get_option('rsvpmaker_discussion_officer');
	if(empty($officer))
		$officer = array('user' => '','password' => '', 'subject_prefix' => 'Officer:'.get_option('blogname'),  'whitelist' => '', 'blocked' => '','additional_recipients' => '');
	print_group_list_options('officer', $officer);
}

$extra = get_option('rsvpmaker_discussion_extra');
if(empty($extra))
	$extra = array('user' => '','password' => '', 'subject_prefix' => '', 'whitelist' => get_option('admin_email'), 'blocked' => '','additional_recipients' => '');
print_group_list_options('extra', $extra);
echo '<p><em>'.__('Use for small custom distribution lists. Or use to forward an email you want to share to WordPress, then edit further with RSVP Mailer before sending.','rsvpmaker').'</em></p>';

$bot = get_option('rsvpmaker_discussion_bot');
if(empty($bot))
	$bot = array('user' => '','password' => '', 'subject_prefix' => '', 'whitelist' => get_option('admin_email'), 'blocked' => '','additional_recipients' => '');
print_group_list_options('bot', $bot);
echo '<p><em>'.__('Use for automations triggered by an email.','rsvpmaker').'</em></p>';

if(isset($_REQUEST['tab']) && $_REQUEST['tab'] == 'groupemail')
{
?>
<input type="hidden" id="activetab" value="groupemail" />
<?php	
}
?>
<input type="hidden" name="tab" value="groupemail">
<button>Submit</button>
</form>

<h3>Using the Bot Account</h3>
<p>Email sent to the bot account can be processed using a WordPress action where the email content is past in the form of post variables (subject as post_title, content as post_content). Example:</p>
<pre>
add_action('rsvpmaker_autoreply','my_post_to_email',10,5);
function my_post_to_email($email_as_post, $email_user, $from, $to, $fromname = '') {
	$myemail = 'mytrustedemail@gmail.com';
	if($from == $myemail) {
	$email_as_post['post_type'] = 'post';
	$email_as_post['post_status'] = 'draft';
	$id = wp_insert_post($email_as_post);
	wp_mail($myemail,'Draft '.$id.' '.$email_as_post['post_title'],'Edit draft '.$id);
	}
}
</pre>

</section>
<section id="rsvpforms" class="rsvpmaker">
<?php
printf('<p>%s</p>',__('Use this page to manage alternative RSVP Forms. Like the default form, any of these alternative forms can be customized for an event post or template. If you created a custom form that worked well, you can assign it a name so that it can be reused with other events and templates.','rsvpmaker'));
printf('<form method="get" action="%s"><input type="hidden" name="post_type" value="rsvpmaker" /><h3>%s</h3><p><label>%s</label> <input type="text" name="rsvp_form_new" value=""></p><p><em>%s</em></p><p> %s</p></form>',admin_url('edit.php'),__('Create a Reusable RSVP Form','rsvpmaker'),__('Label','rsvpmaker'),__('A form will be created with a sampling of input fields you can copy, modify, or delete to create a form that meets your needs.','rsvpmaker'),get_submit_button('Create'));
$forms = rsvpmaker_get_forms();
echo '<h3>Default Form</h3>';
$fpost = get_post($options["rsvp_form"]);
if(empty($fpost))
	{
	$options["rsvp_form"] = upgrade_rsvpform();
	$fpost = get_post($options["rsvp_form"]);
	}
echo rsvpmaker_form_summary($fpost);
$edit = admin_url('post.php?action=edit&post='.$options["rsvp_form"]);
printf('<div id="editconfirmation"><a href="%s">%s</a></div>',$edit,__('Edit','rsvpmaker'));

foreach($forms as $label => $form_id) {
	$fpost = get_post($form_id);
	if($fpost && $fpost->post_status != 'trash') {
		printf('<h3>Form: %s</h3>',$label);
		echo rsvpmaker_form_summary($fpost);
		$edit = admin_url('post.php?action=edit&post='.$form_id);
		printf('<div id="editconfirmation"><a href="%s">%s</a></div>',$edit,__('Edit','rsvpmaker'));
	}
}

$results = $wpdb->get_results("SELECT ID, post_title, post_parent, post_content FROM $wpdb->posts JOIN $wpdb->postmeta ON ID=post_id WHERE meta_key='_rsvpmaker_special' AND meta_value='RSVP Form' AND post_parent ORDER BY ID DESC ");
if($results)
{
	printf('<h2>%s</h2>',__('Save Existing Forms for Reuse','rsvpmaker'));

$tab = (isset($_REQUEST['tab']) && $_REQUEST['tab'] == 'rsvpforms') ? '<input type="hidden" id="activetab" value="rsvpforms" />' : '';
$tab .= '<input type="hidden" name="tab" value="rsvpforms">';

	foreach($results as $fpost) {
		if(in_array($fpost->ID,$forms)) // if already in collection
			continue;
		printf('<h3>%s %s</h3>',get_the_title($fpost->post_parent),get_rsvp_date($fpost->post_parent));
		echo rsvpmaker_form_summary($fpost);
		$edit = admin_url('post.php?action=edit&post='.$fpost->ID);
		printf('<form action="%s" method="post"><input type="hidden" name="form_id" value="%d">%s: <input type="text" name="rsvpmaker_save_form">%s <button>%s</button>%s</form>',admin_url('options-general.php?page=rsvpmaker-admin.php'),$fpost->ID,__('Name','rsvpmaker'),$tab,__('Save','rsvpmaker'),rsvpmaker_nonce('return'));
	}
}

?>
</section>
<section id="rsvpforms" class="rsvpmaker">
<?php
printf('<p>%s</p>',__('Use this page to manage alternative RSVP Forms. Like the default form, any of these alternative forms can be customized for an event post or template. If you created a custom form that worked well, you can assign it a name so that it can be reused with other events and templates.','rsvpmaker'));
printf('<form method="get" action="%s"><input type="hidden" name="post_type" value="rsvpmaker" /><h3>%s</h3><p><label>%s</label> <input type="text" name="rsvp_form_new" value=""></p><p><em>%s</em></p><p> %s</p></form>',admin_url('edit.php'),__('Create a Reusable RSVP Form','rsvpmaker'),__('Label','rsvpmaker'),__('A form will be created with a sampling of input fields you can copy, modify, or delete to create a form that meets your needs.','rsvpmaker'),get_submit_button('Create'));
$forms = rsvpmaker_get_forms();
echo '<h3>Default Form</h3>';
$fpost = get_post($options["rsvp_form"]);
if(empty($fpost))
	{
	$options["rsvp_form"] = upgrade_rsvpform();
	$fpost = get_post($options["rsvp_form"]);
	}
echo rsvpmaker_form_summary($fpost);
$edit = admin_url('post.php?action=edit&post='.$options["rsvp_form"]);
printf('<div id="editconfirmation"><a href="%s">%s</a></div>',$edit,__('Edit','rsvpmaker'));

foreach($forms as $label => $form_id) {
	$fpost = get_post($form_id);
	if($fpost) {
		printf('<h3>Form: %s</h3>',$label);
		echo rsvpmaker_form_summary($fpost);
		$edit = admin_url('post.php?action=edit&post='.$form_id);
		printf('<div id="editconfirmation"><a href="%s">%s</a></div>',$edit,__('Edit','rsvpmaker'));
	}
}

$results = $wpdb->get_results("SELECT ID, post_title, post_parent, post_content FROM $wpdb->posts JOIN $wpdb->postmeta ON ID=post_id WHERE meta_key='_rsvpmaker_special' AND meta_value='RSVP Form' AND post_parent ORDER BY ID DESC ");
if($results)
{
	printf('<h2>%s</h2>',__('Save Existing Forms for Reuse','rsvpmaker'));

$tab = (isset($_REQUEST['tab']) && $_REQUEST['tab'] == 'rsvpforms') ? '<input type="hidden" id="activetab" value="rsvpforms" />' : '';
$tab .= '<input type="hidden" name="tab" value="rsvpforms">';

	foreach($results as $fpost) {
		if(in_array($fpost->ID,$forms)) // if already in collection
			continue;
		printf('<h3>%s %s</h3>',get_the_title($fpost->post_parent),get_rsvp_date($fpost->post_parent));
		echo rsvpmaker_form_summary($fpost);
		$edit = admin_url('post.php?action=edit&post='.$fpost->ID);
		printf('<form action="%s" method="post"><input type="hidden" name="form_id" value="%d">%s: <input type="text" name="rsvpmaker_save_form">%s <button>%s</button>%s</form>',admin_url('options-general.php?page=rsvpmaker-admin.php'),$fpost->ID,__('Name','rsvpmaker'),$tab,__('Save','rsvpmaker'),rsvpmaker_nonce('return'));
	}
}

?>
</section>
</sections>

</div>

<?php              

          }
      }
  
  else
      : exit("Class already declared!");
  endif;
  

  // create new instance of the class
  $RSVPMAKER_Options = new RSVPMAKER_Options();
  ////print_r($RSVPMAKER_Options);
  if (isset($RSVPMAKER_Options)) {
      // register the activation function by passing the reference to our instance
      register_activation_hook(__FILE__, array(&$RSVPMAKER_Options, 'install'));
}

function rsvpmaker_form_summary($fpost) {
	$guest = (strpos($fpost->post_content,'rsvpmaker-guests')) ? __('Yes','rsvpmaker') : __('No','rsvpmaker');
	$note = (strpos($fpost->post_content,'name="note"') || strpos($fpost->post_content,'formnote')) ?  __('Yes','rsvpmaker') : __('No','rsvpmaker');
	preg_match_all('/"slug":"([^"]+)/',$fpost->post_content,$matches);
	if(!empty($matches[1]))
	foreach($matches[1] as $match)
		$fields[$match] = $match;
	if(empty($fields))
		return;
	return sprintf('<div>'.__('Fields','rsvpmaker').': %s<br />'.__('Guests','rsvpmaker').': %s<br />'.__('Note field','rsvpmaker').': %s</div>',implode(', ',$fields),$guest,$note);
}

function print_group_list_options($list_type, $vars) {
	printf('<h3>%s List</h3>',ucfirst($list_type));
	$fields = array('user','password','subject_prefix','whitelist','blocked','additional_recipients');
	foreach($fields as $field)
		{
			if(empty($vars[$field]))
				$vars[$field] = '';
		}
	////print_r($vars);
	printf('<p><label>%s</label><br /><input type="text" name="rsvpmaker_discussion_'.esc_attr($list_type).'[user]" value="%s" /> </p>',__('Email/User','rsvpmaker'),esc_attr($vars["user"]));	
	printf('<p><label>%s</label><br /><input type="text" name="rsvpmaker_discussion_'.esc_attr($list_type).'[password]" value="%s" /> </p>',__('Password','rsvpmaker'),esc_attr($vars["password"]));
	if($list_type != 'bot') {
		printf('<p><label>%s</label><br /><input type="text" name="rsvpmaker_discussion_'.esc_attr($list_type).'[subject_prefix]" value="%s" /> </p>',__('Subject Prefix','rsvpmaker'),esc_attr($vars["subject_prefix"]));
		printf('<p><label>%s</label> <br /><textarea rows="3" cols="80" name="rsvpmaker_discussion_'.esc_attr($list_type).'[whitelist]">%s</textarea> </p>',__('Whitelist - additional allowed sender emails','rsvpmaker'),esc_attr($vars["whitelist"]));	
		printf('<p><label>%s</label> <br /><textarea rows="3" cols="80" name="rsvpmaker_discussion_'.esc_attr($list_type).'[blocked]">%s</textarea> </p>',__('Blocked - not allowed to send to list','rsvpmaker'),esc_attr($vars["blocked"]));	
		printf('<p><label>%s</label> <br /><textarea  rows="3" cols="80" name="rsvpmaker_discussion_'.esc_attr($list_type).'[additional_recipients]">%s</textarea> </p>',__('Additional Recipients','rsvpmaker'),esc_attr($vars["additional_recipients"]));
	}	
}

function rsvpmaker_default_event_content($content) {
global $post;
global $rsvp_options;
global $rsvp_template;
if(empty($post->post_type))
	return $content;
if(($post->post_type == 'rsvpmaker') && ($content == ''))
{
if(isset($rsvp_template->post_content))
	return $rsvp_template->post_content;
return wp_kses_post($rsvp_options['default_content']);
}
else
return $content;
}

function rsvpmaker_title_from_template($title) {
global $rsvp_template;
global $post;
global $wpdb;
if(isset($_GET["from_template"]) ) 
	{
	$t = (int) $_GET["from_template"];
	$sql = "SELECT post_title, post_content FROM $wpdb->posts WHERE ID=$t";
	$rsvp_template = $wpdb->get_row($sql);
	return $rsvp_template->post_title;
	}
return $title;
}

add_filter('the_editor_content','rsvpmaker_default_event_content');
add_filter('default_title','rsvpmaker_title_from_template');

function rsvpmaker_doc () {
global $rsvp_options;
?>
<style>
#docpage ul {
margin-left: 10px;
}
#docpage li {
margin-left: 10px;
list-style-type: circle;
}
</style>
<div id="docpage">
<h2>Documentation</h2><p>More detailed documentation at <a href="http://www.rsvpmaker.com/documentation/">http://www.rsvpmaker.com/documentation/</a></p><h3>Shortcodes and Event Listing / Calendar Views</strong></h3><p>RSVPMaker provides the following shortcodes for listing events, listing event headlines, and displaying a calendar with links to events.</p><p><strong>[rsvpmaker_upcoming]</strong> displays the index of upcoming events. If an RSVP is requested, the event includes the RSVP button link to the single post view, which will include your RSVP form. The calendar icon in the WordPress visual editor simplifies adding the rsvpmaker_upcoming code to any page or post.</p><p>[rsvpmaker_upcoming calendar=&quot;1&quot;] displays the calendar, followed by the index of upcoming events.</p><p>Attributes can be added in the format [rsvpmaker_upcoming attribute_name="attribute_value"]<p><ul><li>type="type_name" displays only the events with the matching event type, as set in the editor (example: type="featured") </li><li>no_event="message" message to display if no events are in the database (example="We are workin on scheduling new events. Check back soon")</li><li>one="ID|slug|next" embed a single message, identified by either post ID number, slug, or "next" to display the next upcoming event. (examples one="123" or one="special-event" or one="next")</li><li>limit="posts_per_page" limits the number of posts to display. If not specified, this will be the same as the number of posts displayed on your blog index page. (example: limit="30")</li><li>add_to_query="querystring" adds an arbitrary command to the WordPress query (example: add_to_query="posts_per_page=30&amp;post_status=draft" would retrieve 30 draft posts)</li><li>hideauthor="1" set this to prevent the author displayname from being shown at the bottom of an event post.</li>
</ul>
  
            <div style="background-color: #FFFFFF; padding: 15px; text-align: center;">
            <img src="<?php echo plugins_url('/shortcode.png',__FILE__);?>" width="535" height="412" />
<br /><em><?php esc_html_e('Contents for an events page.','rsvpmaker');?></em>
            </div>

<p><strong>[rsvpmaker_calendar]</strong> displays the calendar by itself.</p><p><strong>[rsvpmaker_calendar nav="top"]</strong> displays the calendar with the next / previous month navigation on the top rather than the bottom. By default, navigation is displayed on the bottom.</p><p>Attributes: type="type_name" and add_to_query="querystring" also work with rsvpmaker_calendar.</p><p><strong>[event_listing format=&quot;headlines&quot;]</strong> displays a list of headlines</p><p>[event_listing format=&quot;calendar&quot;] OR [event_listing calendar=&quot;1&quot;] displays the calendar (recommend using [rsvpmaker_calendar] instead)</p><p>Other attributes:</p><ul><li>limit=&quot;posts_per_page&quot; limits the number of posts to display. If not specified, this will be the same as the number of posts displayed on your blog index page. (example: limit=&quot;30&quot;)</li><li>past=&quot;1&quot; will show a listing of past events, most recent first, rather than upcoming events.</li><li>title=&quot;Title Goes Here&quot; Specifies a title to be displayed in bold at the top of the listing.</li></ul>

<h3>To Embed a Single Event</h3>

<p><strong>[rsvpmaker_next]</strong>, displays just the next scheduled event. If the type attribute is set, that becomes the next event of that type. Example: [rsvpmaker_next type="webinar"]. Also, this displays the complete form rather than the RSVP Now! button unless showbutton="1" is set.</p>
<p><strong>[rsvpmaker_one post_id="10"]</strong> displays a single event post with ID 10. Shows the complete form unless the attribute showbutton="1" is set</p>
<p><strong>[rsvpmaker_form post_id="10"]</strong> displays just the form associated with an event (ID 10 in this example. Could be useful for embedding the form in a landing page that describes the event but where you do not want to include the full event content.</p>

<p>The rsvpmaker_one and rsvpmaker_form shortcodes also accept one="10" as equivalent to post_id="10"</p>

<?php esc_html_e('<h3>RSVP Form</h3><p>The RSVP from is also now formatted using shortcodes, which you can edit in the RSVP Form section of the Settings screen. You can also vary the form on a per-event basis, which can be handy for capturing an extra field. This is your current default form:</p>','rsvpmaker');?>
<pre>
<?php echo(htmlentities($rsvp_options["rsvp_form"])); ?>
</pre>
<?php esc_html_e('<p>Explanation:</p><p>[rsvpfield textfield=&quot;myfield&quot;] outputs a text field coded to capture data for &quot;myfield&quot;</p><p>[rsvpfield textfield=&quot;myfield&quot; required=&quot;1&quot;] treats &quot;myfield&quot; as a required field.</p><p>[rsvpfield selectfield=&quot;phone_type&quot; options=&quot;Work Phone,Mobile Phone,Home Phone&quot;] HTML select field with specified options</p><p>[rsvpfield checkbox=&quot;checkboxtest&quot; value=&quot;1&quot;] Checkbox named checkboxtext with a value of 1 when checked.</p><p>[rsvpfield checkbox=&quot;checkboxtest&quot; value=&quot;1&quot; checked=&quot;1&quot;] Checkbox checked by default.</p><p>[rsvpfield radio=&quot;radiotest&quot; options=&quot;one,two,three,four&quot;] When checked, records one of the 4 values for the field &quot;radiotest&quot;</p><p>[rsvpfield radio=&quot;radiotest&quot; options=&quot;one,two,three,four&quot; checked=&quot;two&quot;] choice &quot;two&quot; is checked by default</p><p>[rsvpfield radio=&quot;radiotest&quot; options=&quot;one,two,three,four&quot; checked=&quot;two&quot; sep=&quot; &quot;] separate choices with a space (by default, each appears on a separate line)</p><p>[rsvpprofiletable show_if_empty=&quot;phone&quot;]CONDITIONAL CONTENT GOES HERE[/rsvpprofiletable] This section only shown if the required field is empty; otherwise displays a message that the info is &quot;on file&quot;. Because RSVPMaker is capable of looking up profile data based on an email address, you may want some data to be hidden for privacy reasons.</p><p>[rsvpguests] Outputs the guest blanks.</p>','rsvpmaker'); ?>

<p><?php esc_html_e("If you're having trouble with the form fields not being formatted correctly",'rsvpmaker')?>, <a href="<?php echo admin_url('options-general.php?page=rsvpmaker-admin.php&amp;reset_form=1');?>"><?php esc_html_e('Reset default RSVP Form','rsvpmaker');?></a></p>

<h3>Timed Content</h3>

<p>To make a set a start or end time for the display of a block of content, wrap it in the rsvpmaker_timed shortcode.</p>

<p>Example:</p>

<p>[rsvpmaker_timed start="June 1, 2018" end="June 15, 2018" too_soon="Sorry, too soon" too_late="Sorry, too late"]</p>

<p>Timed Content goes here ...<br />continues until close tag.</p>

<p>[/rsvpmaker_timed]</p>

<p>Include either a start or end attribute, or both. For more precision, use a database style YYYY-MM-DD format for the date. Example: start="2018-06-01 13:00" for the content to start displaying June 1 after 1 pm local time.</p>
<p>The too_soon and too_late attributes are optional, for the output of messages before and after the specified time time period. Leave them out or leave them blank, and no content will be displayed outside the specified time period. </p>

<h3>YouTube Live webinars</h3>
<p>When embedding a YouTube Live stream in a page or post of your WordPress site, you can use the shortcode [ylchat] to embed the associated comment stream (which can be used to take questions from the audience). This extracts the video ID from the YouTube link included in the page and constructs the iframe for the chat window, according to Google's specifications. You can add attributes for width and height to override the default values (100% wide x 200 pixels tall). You can add a note to be displayed above the comments field using the note parameter, as in [ylchat note=&quot;During the program, please post questions and comments in the chat box below.&quot;]</p>

<p>RSVPMaker will stop displaying the chat field once the live event is over and the live chat is no longer active. Because this requires RSVPMaker to keep polling YouTube to see if the chat is live, you may wish to remove the shortcode from the page for replay views.</p>

<?php

}

function rsvpmaker_admin_menu() {
	
global $rsvp_options;
//do_action('rsvpmaker_admin_menu_top');
add_submenu_page('edit.php?post_type=rsvpmaker', __("Event Setup",'rsvpmaker'), __("Event Setup",'rsvpmaker'), 'edit_rsvpmakers', "rsvpmaker_setup", "rsvpmaker_setup" );
add_submenu_page('edit.php?post_type=rsvpmaker', __("New Template",'rsvpmaker'), __("New Template",'rsvpmaker'), 'edit_rsvpmakers', "rsvpmaker_setup&new_template=1", "rsvpmaker_setup" );
add_submenu_page('edit.php?post_type=rsvpmaker', __("Event Templates",'rsvpmaker'), __("Event Templates",'rsvpmaker'), $rsvp_options["rsvpmaker_template"], "rsvpmaker_template_list", "rsvpmaker_template_list" );
if(!empty($rsvp_options['additional_editors']))
	add_submenu_page('edit.php?post_type=rsvpmaker', __("Share Templates",'rsvpmaker'), __("Share Templates",'rsvpmaker'), 'edit_rsvpmakers', "rsvpmaker_share", "rsvpmaker_share" );
if($rsvp_options["show_screen_recurring"])
	add_submenu_page('edit.php?post_type=rsvpmaker', __("Recurring Event",'rsvpmaker'), __("Recurring Event",'rsvpmaker'), $rsvp_options["recurring_event"], "add_dates", "add_dates" );
if(!empty($rsvp_options["show_screen_multiple"]))
	add_submenu_page('edit.php?post_type=rsvpmaker', __("Multiple Events","rsvpmaker"), __("Multiple Events",'rsvpmaker'), $rsvp_options["multiple_events"], "multiple", "multiple" );
add_submenu_page('edit.php?post_type=rsvpmaker', __("Multiple Events (without a template)",'rsvpmaker'), __("Multiple Events (without a template)",'rsvpmaker'), 'edit_rsvpmakers', "rsvpmaker_setup&quick=5", "rsvpmaker_setup" );
add_submenu_page('edit.php?post_type=rsvpmaker', __("Event Options",'rsvpmaker'), __("Event Options",'rsvpmaker'), 'edit_rsvpmakers', "rsvpmaker_details", "rsvpmaker_details" );
add_submenu_page('edit.php?post_type=rsvpmaker', __("Confirmation / Reminders",'rsvpmaker'), __("Confirmation / Reminders",'rsvpmaker'), 'edit_rsvpmakers', "rsvp_reminders", "rsvp_reminders" );
add_submenu_page('edit.php?post_type=rsvpmaker', __("RSVP Report",'rsvpmaker'), __("RSVP Report",'rsvpmaker'), $rsvp_options["menu_security"], "rsvp", "rsvp_report" );
add_submenu_page( 'edit.php?post_type=rsvpmaker', __( 'RSVPMaker Payments', 'rsvpmaker' ), __( 'RSVPMaker Payments', 'rsvpmaker' ), 'edit_rsvpmakers', 'rsvpmaker_stripe_report', 'rsvpmaker_stripe_report' );
add_submenu_page('tools.php',__('Import/Export RSVPMaker'),__('Import/Export RSVPMaker'),'manage_options','rsvpmaker_export_screen','rsvpmaker_export_screen');
add_submenu_page('tools.php',__('Cleanup RSVPMaker'),__('Cleanup RSVPMaker'),'manage_options','rsvpmaker_cleanup','rsvpmaker_cleanup');

}

add_filter('manage_posts_columns', 'rsvpmaker_columns');
function rsvpmaker_columns($defaults) {
	if(!empty($_GET["post_type"]) && ($_GET["post_type"] == 'rsvpemail'))
    	$defaults['rsvpmaker_cron'] = __('Scheduled','rsvpmaker');
    return $defaults;
}

function rsvpmaker_custom_column($column_name, $post_id) {
	global $wpdb, $rsvp_options;
	
    if( $column_name == 'rsvpmaker_end' ) {
		$event = get_rsvpmaker_event($post_id);
		if($event)
		echo esc_html($event->enddate);
	}
    elseif( $column_name == 'rsvpmaker_display' ) {
		$end_type = get_rsvpmaker_meta($post_id,'_firsttime');
		if(empty($end_type))
			echo 'End Time Not Shown';
		else {
			$options = array('set' => 'Show End Time','allday' => 'All Day/Times Not Shown','multi|2' => '2 Days','multi|3' => '3 Days','multi|4' => '4 Days','multi|5' => '5 Days','multi|6' => '6 Days','multi|7' => '7 Days');
			if(!empty($options[$end_type]))
				echo esc_html($options[$end_type]);
		}
		printf('<input type="hidden" class="end_display_code" value="%s" />',$end_type);
		$rsvp_on = get_rsvpmaker_meta($post_id,'_rsvp_on');
		$convert_timezone = get_rsvpmaker_meta($post_id,'_convert_timezone',true);
		$add_timezone = get_rsvpmaker_meta($post_id,'_add_timezone',true);
		if(!empty($rsvp_on))
			echo '<br />RSVP On';
		if(!empty($add_timezone))
			echo '<br />Timezone code added to time';
		if(!empty($convert_timezone))
			echo '<br /><em>Show in my timezone</em> button displayed';
	}
    elseif( $column_name == 'event_dates' ) {

$datetime = get_rsvp_date($post_id);
$template = get_template_sked($post_id);
$rsvpmaker_special = get_rsvpmaker_meta($post_id,'_rsvpmaker_special',true);

$s = $dateline = '';

if($datetime)
{
		printf('<span class="rsvpmaker-date" id="rsvpmaker-date-%d">%s</span>',esc_attr($post_id),esc_attr($datetime));
}
elseif($template)
	{
echo __("Template",'rsvpmaker').": ";
//backward compatability
if(is_array($template["week"]))
	{
		$weeks = $template["week"];
		$dows = $template["dayofweek"];
	}
else
	{
		$weeks[0] = $template["week"];
		$dows[0] = (isset($template["dayofweek"])) ? $template["dayofweek"] : 0;
	}

$weekarray = Array(__("Varies",'rsvpmaker'),__("First",'rsvpmaker'),__("Second",'rsvpmaker'),__("Third",'rsvpmaker'),__("Fourth",'rsvpmaker'),__("Last",'rsvpmaker'),__("Every",'rsvpmaker'));
$dayarray = Array(__("Sunday",'rsvpmaker'),__("Monday",'rsvpmaker'),__("Tuesday",'rsvpmaker'),__("Wednesday",'rsvpmaker'),__("Thursday",'rsvpmaker'),__("Friday",'rsvpmaker'),__("Saturday",'rsvpmaker'));

if($weeks[0] == 0)
	echo __('Schedule varies','rsvpmaker');
else
	{
	foreach($weeks as $week)
		{
		if(!empty($s))
			$s .= '/ ';
		$s .= $weekarray[(int) $week].' ';
		}
	foreach($dows as $dow)
		$s .= $dayarray[(int) $dow] . ' ';	
	echo esc_html($s);
		
	}

	} // end sked
	elseif($rsvpmaker_special)
		{
			echo __('Special Page','rsvpmaker').': '.$rsvpmaker_special;
		}
	} // end event dates column
	elseif($column_name == 'rsvpmaker_cron') {
		echo rsvpmaker_next_scheduled($post_id);	
	}
}

function rsvpmaker_reminders_list($post_id)
{
global $wpdb;
$sql = "SELECT  `meta_key` 
FROM  `$wpdb->postmeta` 
WHERE  `meta_key` LIKE  '_rsvp_reminder_msg%' AND post_id = $post_id
ORDER BY  `meta_key` ASC ";
$results = $wpdb->get_results($sql);
$txt = '';
if($results)
	{
		foreach ($results as $row)
			{
				$p = explode('_msg_',$row->meta_key);
				$hours[] = (int) $p[1];
			}
	sort($hours);
	foreach($hours as $hour)
		{
			$hour = (int) $hour;
			if($hour > 0)
				$label = __('Follow up','rsvpmaker').': '.$hour.' '.__('hours after','rsvpmaker');
			else
				$label = __('Reminder','rsvpmaker').': '.abs($hour).' '.__('hours before','rsvpmaker');
		$txt .= sprintf(' | <a href="%s">%s</a>',admin_url('edit.php?post_type=rsvpmaker&hours='.$hour.'&page=rsvp_reminders&message_type=reminder&post_id=').$post_id,$label);
		}
	}
return $txt;
}

//add_action('admin_init','rsvpmaker_create_calendar_page');

function rsvpmaker_create_calendar_page() {
global $current_user;
if(isset($_GET["create_calendar_page"]))
	{
	$content = (function_exists('register_block_type')) ? '<!-- wp:rsvpmaker/upcoming {"calendar":"1","nav":"both"} /-->' : '[rsvpmaker_upcoming calendar="1" days="180" posts_per_page="10" type="" one="0" hideauthor="1" past="0" no_events="No events currently listed" nav="bottom"]';
	$post = array(
	  'post_content'   => $content,
	  'post_name'      => 'calendar',
	  'post_title'     => 'Calendar',
	  'post_status'    => 'publish',
	  'post_type'      => 'page',
	  'post_author'    => $current_user->ID,
	  'ping_status'    => 'closed'
	);
	$id = wp_insert_post($post);
	wp_redirect(admin_url('post.php?action=edit&post=').$id);
	exit();
	}
}

function rsvpmaker_essentials () {
	global $rsvp_options, $current_user;
	$cleared = get_option('cleared_rsvpmaker_notices');
	$cleared = is_array($cleared) ? $cleared : array();
	$message = '';
	if(isset($_POST["create_calendar_page"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {
		$content = (function_exists('register_block_type')) ? '<!-- wp:rsvpmaker/upcoming {"calendar":"1","nav":"both"} /-->' : '[rsvpmaker_upcoming calendar="1" days="180" posts_per_page="10" type="" one="0" hideauthor="1" past="0" no_events="No events currently listed" nav="bottom"]';
		$post = array(
		  'post_content'   => $content,
		  'post_name'      => 'calendar',
		  'post_title'     => 'Calendar',
		  'post_status'    => 'publish',
		  'post_type'      => 'page',
		  'post_author'    => $current_user->ID,
		  'ping_status'    => 'closed'
		);
		$id = wp_insert_post($post);
		$link = get_permalink($id);
		$message .= '<p>'.__('Calendar page created: ','rsvpmaker').sprintf('<a href="%s">%s</a>',$link,$link).'</p>';
	}
	if(isset($_POST["clear_calendar_page_notice"]) && !isset($_POST["create_calendar_page"])) {
		update_option('noeventpageok',1);
		$message .= '<p>Calendar notice cleared.</p>';		
	}
	if(isset($_POST["timezone_string"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {
		$tz = sanitize_text_field($_POST["timezone_string"]);
		update_option('timezone_string',$tz);
		$message .= '<p>Timezone set: '.$tz.'.</p>';		
	}
	if(isset($_POST["privacy_confirmation"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key'))) {
		$rsvp_options["privacy_confirmation"] = (int) $_POST["privacy_confirmation"];
		$message .= '<p>Privacy confirmation option set.</p>';
		$privacy_page = get_option('wp_page_for_privacy_policy');
		if($privacy_page)
		{
			$privacy_url = get_permalink($privacy_page);
			$conf_message = sprintf('I consent to the <a target="_blank" href="%s">privacy policy</a> site of this site for purposes of follow up to this registration.',$privacy_url);
			$rsvp_options['privacy_confirmation_message'] = $conf_message;
			$message .= '<p>Confirmation message (can be edited in RSVPMaker Settings): '.$conf_message.'</p>';
		}
		else
			$message .= printf('<p><a href="%s">%s</a></p>',admin_url('options-privacy.php'),__('Set up your privacy page','rsvpmaker'));
		update_option('RSVPMAKER_Options',$rsvp_options);
	}
	$message .= '<p>'.__('You can set additional options, including default settings for RSVPMaker events, on the','rsvpmaker').' <a href="'.admin_url('options-general.php?page=rsvpmaker-admin.php').'">'.__('RSVPMaker settings page','rsvpmaker').'</a>.</p>';
	echo '<div class="notice notice-success is-dismissible">'.$message.'</div>';
}

function rsvpmaker_corrupted_dates_check() {
global $wpdb;
$fixed = $corrupt = '';

if(isset($_POST['fixrsvpyear']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
{
foreach($_POST['fixrsvpyear'] as $post_id => $year)
{
	$year = (int) $year;
	$month = (int) $_POST['fixrsvpmonth'][$post_id];
	$day = (int) $_POST['fixrsvpday'][$post_id];
	$hour = (int) $_POST['fixrsvphour'][$post_id];
	$minutes = (int) $_POST['fixrsvpminutes'][$post_id];
	if($month < 10)
		$month = '0'.$month;
	if($day < 10)
		$day = '0'.$day;
	if($hour < 10)
		$hour = '0'.$hour;
	if($minutes < 10)
		$minutes = '0'.$minutes;
	$datestring = $year.'-'.$month.'-'.$day.' '.$hour.':'.$minutes.':00';
	$fixed .= '<div>Fixed date: '.$datestring.' for '.get_the_title($post_id).'</div>';
	update_post_meta($post_id,'_rsvp_dates',$datestring);
}

echo '<div class="notice notice-info">'.$fixed.'</div>';

}

//first try to clean up errors automatically 
	$manualcheck = false;
	$sql = "SELECT ID, post_title, meta_value
	FROM $wpdb->posts JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id
	where meta_key='_rsvp_dates' AND post_status='publish' AND meta_value NOT REGEXP '[0-9]{4}-[0-9]{2}-[0-9]{2} {1,2}[0-9]{2}:[0-9]{2}:[0-9]{2}'
	AND meta_value > CURDATE()
	ORDER BY post_title, meta_value";
	$results1 = $wpdb->get_results($sql);
	if($results1)
	{
		foreach($results1 as $row) {
		$dateparts = preg_split('/[-: ]/',$row->meta_value);
		if(empty($dateparts[3]) || empty($dateparts[4]))
			{
				//if not a complete date
				$manualcheck = true;
				continue;
			}
		$year = $dateparts[0];
		$month = str_pad($dateparts[1],2,'0',STR_PAD_LEFT);
		$day = str_pad($dateparts[2],2,'0',STR_PAD_LEFT);
		$hour = str_pad($dateparts[3],2,'0',STR_PAD_LEFT);
		$minutes = empty($dateparts[4]) ? '00' : str_pad($dateparts[4],2,'0',STR_PAD_LEFT);
		$newdate = sprintf('%s-%s-%s %s:%s:00',$year,$month,$day,$hour,$minutes);
		update_post_meta($row->ID,'_rsvp_dates',$newdate);
		}
	}	
if($manualcheck) {
	$sql = "SELECT ID, post_title, meta_value
	FROM $wpdb->posts JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id
	where meta_key='_rsvp_dates' AND post_status='publish' AND meta_value NOT REGEXP '[0-9]{4}-[0-9]{2}-[0-9]{2} {1,2}[0-9]{2}:[0-9]{2}:[0-9]{2}' 
	AND meta_value > CURDATE()
	ORDER BY post_title, meta_value";
	$results = $wpdb->get_results($sql);
	if($results)
	{
		foreach($results as $row) {
		$dateparts = preg_split('/[-: ]/',$row->meta_value);
		$corrupt .= sprintf('<div><label style="display: inline-block; width: 200px;">%s</label> <input type="text" name="fixrsvpyear[%d]" value="%s" size="4" >-<input type="text" name="fixrsvpmonth[%d]" value="%s" size="2" >-<input type="text" name="fixrsvpday[%d]" value="%s" size="2" > <input type="text" name="fixrsvphour[%d]" value="%s"  size="2" >:<input type="text" name="fixrsvpminutes[%d]" value="%s" size="2" >:00 %s</div>',$row->post_title, $row->ID, $dateparts[0], $row->ID,  (empty($dateparts[1])) ? '' : $dateparts[1], $row->ID,  (empty($dateparts[2])) ? '' : $dateparts[2], $row->ID,  (empty($dateparts[3])) ? '' : $dateparts[3], $row->ID, (empty($dateparts[4])) ? '' : $dateparts[4], $row->meta_value);
		}
	printf('<div class="notice notice-error"><h3>%s</h3><p>%s</p><form method="post" action="%s">%s<p><button>Repair</button></p>%s</form></div>',__('Date Variables Corrupted','rsvpmaker'),__('A correct date would be in the format YEAR-MONTH-DAY HOUR:MINUTES:SECONDS or 2030-01-01 19:30:00 for January 1, 2030 at 7:30 pm','rsvpmaker'),admin_url(),$corrupt,rsvpmaker_nonce('return') );
	}	
}

}

function rsvpmaker_admin_notice() {

if(isset($_GET['action']) && ($_GET['action'] == 'edit'))
	return; //don't clutter edit page with admin notices. Gutenberg hides them anyway.
if(isset($_GET['post_type']) && ($_GET['post_type'] == 'rsvpmaker') && !isset($_GET['page']))
	return; //don't clutter post listing page with admin notices
if(isset($_POST["rsvpmaker_essentials"]))
	rsvpmaker_essentials();

if(isset($_GET['payment_key_test'])) {
	echo '<div class="notice notice-info"><p><div>Checking payment API connections</div>';
	$paypal_rest_keys = get_option('rsvpmaker_paypal_rest_keys');
	if(!empty($paypal_rest_keys['client_secret']) && !empty($paypal_rest_keys['client_id']))
		echo '<div>PayPal production key: '.rsvpmaker_paypal_test_connection().'</div>';
	if(!empty($paypal_rest_keys['sandbox_client_secret']) && !empty($paypal_rest_keys['sandbox_client_id']) )
		echo '<div>PayPal sandbox key: '.rsvpmaker_paypal_test_connection('sandbox').'</div>';
	$stripe_keys = get_option('rsvpmaker_stripe_keys');
	if(!empty($stripe_keys['pk']) && !empty($stripe_keys['sk']))
		echo '<div>Stripe production key :'.rsvpmaker_stripe_validate($stripe_keys['pk'],$stripe_keys['sk']).'</div>';
	if(!empty($stripe_keys['sandbox_sk']) && !empty($stripe_keys['sandbox_pk']) )
		echo '<div>Stripe sandbox key: '.rsvpmaker_stripe_validate($stripe_keys['sandbox_pk'],$stripe_keys['sandbox_sk']).'</div>';
	$chosen_gateway = get_rsvpmaker_payment_gateway ();
	if($chosen_gateway)
		printf('<p>Payment gateway defaults to: %s</p>',$chosen_gateway);
	echo '</p></div>';
}

global $wpdb;
global $rsvp_options;
global $current_user;
global $post;
$timezone_string = get_option('timezone_string');
$cleared = get_option('cleared_rsvpmaker_notices');
$cleared = is_array($cleared) ? $cleared : array();
$basic_options = '';

rsvpmaker_corrupted_dates_check();

if( empty($rsvp_options["eventpage"]) && !get_option('noeventpageok') && !is_plugin_active('rsvpmaker-for-toastmasters/rsvpmaker-for-toastmasters.php') )
	{
	$sql = "SELECT ID from $wpdb->posts WHERE post_type='page' AND post_status='publish' AND post_content LIKE '%rsvpmaker_upcoming%' ";
	$front = get_option('page_on_front');
	if($front)
		$sql .= " AND ID != $front ";
	if($id =$wpdb->get_var($sql))
		{
		$rsvp_options["eventpage"] = get_permalink($id);
		update_option('RSVPMAKER_Options',$rsvp_options);
		}
	else {
		$message = __('RSVPMaker can add a calendar or events listing page to your site automatically, which you can then add to your website menu.','rsvpmaker');
		$message2 = __('Create page','rsvpmaker');
		$message3 = __('Turn off this warning','rsvpmaker');
		$basic_options = sprintf('<p>%s</p>
		<p><input type="checkbox" id="create_calendar" name="create_calendar_page" value="1" checked="checked"> %s</p>
		<p id="create_calendar_clear"><input type="checkbox" name="clear_calendar_page_notice" value="1" checked="checked"> %s<p>',$message,$message2,$message3);
		$basic_options .= "<script>
		jQuery(document).ready(function( $ ) {
		$('#create_calendar_clear').hide();
		$('#create_calendar').click(function(event) {
			$('#create_calendar_clear').show();
		});		
	});
		</script>";
		}	
}

if((empty($timezone_string) || isset($_GET['timezone'])) && !isset($_POST['timezone_string']) ) {
$choices = wp_timezone_choice('');
$choices = str_replace('selected="selected"','',$choices);
$message = sprintf('<p>%s %s. %s</p>',__('RSVPMaker needs you to','rsvpmaker'),__('set the timezone for your website','rsvpmaker'), __('Confirm if the choice below is correct or make another choice by region/city.','rsvpmaker') );
$basic_options .= sprintf('<p>%s</p>
<p>
<select id="timezone_string" name="timezone_string">
<script>'."
var tz = jstz.determine();
var tzstring = tz.name();
document.write('<option selected=\"selected\" value=\"' + tzstring + '\">' + tzstring + '</option>');
</script>
%s
</select>
",$message,$choices);
}

if(!isset($rsvp_options["privacy_confirmation"]) || isset($_GET['test']) )
	{
		$privacy_page = rsvpmaker_check_privacy_page();
		if($privacy_page)
			{
				$message = __('Please decide whether your RSVPMaker forms should include a privacy policy confirmation checkbox. This may be important if some of your website visitors may be covered by the European Union\'s GDPR privacy regulation','rsvpmaker').' <a href="'.admin_url('options-general.php?page=rsvpmaker-admin.php#privacy_consent').'">('.__('more details','rsvpmaker').')</a>';
				$basic_options .= sprintf('<p>%s</p><input type="radio" name="privacy_confirmation" value="1" checked="checked" /> %s <input type="radio" name="privacy_confirmation" value="no" /> %s - %s</p>',$message,__('Yes','rsvpmaker'),__('No','rsvpmaker'),__('Add checkbox?','rsvpmaker'));
			}
		else
			$basic_options .= '<p>'.__('You may want for your RSVPMaker forms to include a privacy policy confirmation checkbox, particularly if any site visitors are covered by the European Union\'s GDPR or similar privacy regulations.','rsvpmaker').' '.__('First, you must register a privacy page with WordPress','rsvpmaker').': <a href="'.admin_url('options-privacy.php').'">'.admin_url('options-privacy.php').'</a></p>';
	}

if(!empty($basic_options)) {
	$message = sprintf('<h3>%s</h3><form method="post" action="%s">
	%s
	<p><input type="submit" name="submit" id="submit" class="button button-primary" value="%s"></p>
	<input type="hidden" name="rsvpmaker_essentials" value="1">
	%s</form>',__('RSVPMaker Essential Settings','rsvpmaker'),site_url(sanitize_text_field($_SERVER['REQUEST_URI'])),$basic_options,__('Save Changes','rsvpmaker'),rsvpmaker_nonce('return'));
	$notice[] = rsvpmaker_admin_notice_format($message, 'rsvp_timezone', $cleared, $type='warning');
}

$ver = phpversion();
if (version_compare($ver, '7.1', '<') && (!isset($_GET['page']) || ($_GET['page'] != 'rsvpmaker_email_template') ) ){
	$message = sprintf('<p>The Emogrifier CSS inliner library, which is included to improve formatting of HTML email, relies on PHP features introduced in version 7.1 -- and is disabled because your site is on %s</p>',$ver);
	$notice[] = rsvpmaker_admin_notice_format($message, 'Emogrifier', $cleared, $type='warning');
}

if(isset($_GET['update_messages']) && isset($_GET['t']))
{
echo get_post_meta((int) $_GET['t'],'update_messages',true);
delete_post_meta((int) $_GET['t'],'update_messages');
}

if(isset($post->post_type) && ($post->post_type == 'rsvpmaker') ) {
if($landing = get_post_meta($post->ID,'_webinar_landing_page_id',true))
	{
	$message = '<div class="notice notice-info"><p>'.__('Edit the','rsvpmaker').' <a href="'.admin_url('post.php?action=edit&post='.$landing).'">'.__("webinar landing page",'rsvpmaker').'</a> '.__('associated with this event').'.</p>';
	$message .= '<p>';
	$message .= __('Related messages:','rsvpmaker');
	$message .= sprintf(' <a href="%s">%s</a>',admin_url('edit.php?post_type=rsvpmaker&page=rsvp_reminders&message_type=confirmation&post_id=').$post->ID,__('Confirmation','rsvpmaker'));
	$message .= rsvpmaker_reminders_list($post->ID);
	$message .=  '</p></div>';
	$notice[] = rsvpmaker_admin_notice_format($message, 'Landing page', $cleared, $type='notice');
	}
if($event = get_post_meta($post->ID,'_webinar_event_id',true))
	{
	$message = '<div class="notice notice-info"><p>'.__('Edit the','rsvpmaker').' <a href="'.admin_url('post.php?action=edit&post='.$event).'">'.__("webinar event post",'rsvpmaker').'</a> '.__('associated with this landing page').'.</p>';
	$message .=  '<p>';
	$message .=  __('Related messages:','rsvpmaker');
	$message .=  sprintf(' <a href="%s">%s</a>',admin_url('edit.php?post_type=rsvpmaker&page=rsvp_reminders&message_type=confirmation&post_id=').$event,__('Confirmation','rsvpmaker'));	
	$message .=  rsvpmaker_reminders_list($event);
	$message .=  '</p></div>';
	$notice[] = rsvpmaker_admin_notice_format($message, 'Webinar event', $cleared, $type='notice');
	}
}
	
	if(isset($_GET["smtptest"]))
		{
		$mail["to"] = $rsvp_options["rsvp_to"];
	$mail["from"] = "david@carrcommunications.com";
	$mail["fromname"] = "RSVPMaker";
	$mail["subject"] = __("Testing SMTP email notification",'rsvpmaker');
	$mail["html"] = '<p>'. __('Test from RSVPMaker.','rsvpmaker').'</p>';
	$result = rsvpmailer($mail);
	echo '<div class="updated" style="background-color:#fee;">'."<strong>".__('Sending test email','rsvpmaker').' '.$result .'</strong> <a href="'.admin_url('index.php?smtptest=1&debug=1').'">(debug)</a></div>';
		}

	if(!empty($_GET["rsvp_form_reset"]))
		{
		$target = (int) $_GET["rsvp_form_reset"];
		upgrade_rsvpform (true, $target);
		echo '<div class="updated" ><p>'."<strong>".__('RSVP Form Updated (default and future events)','rsvpmaker').'</strong></p></div>';
		}
	if(!empty($notice))
	{
		if(isset($_GET['show_rsvpmaker_notices']))
			echo implode("\n",$notice);
		else {
			$size = sizeof($notice);
			$message = __('RSVPMaker notices for administrator','rsvpmaker').': '.$size;
			$message .= sprintf(' - <a href="%s">%s</a>',admin_url('?show_rsvpmaker_notices=1'),__('Display','rsvpmaker'));
			echo rsvpmaker_admin_notice_format($message, 'RSVPMaker', $cleared, $type='info');	
		}
	}
?>
<script>
jQuery(document).ready(function( $ ) {
$( document ).on( 'click', '.rsvpmaker-notice .notice-dismiss', function () {
	// Read the "data-notice" information to track which notice
	// is being dismissed and send it via AJAX
	var type = $( this ).closest( '.rsvpmaker-notice' ).data( 'notice' );
	$.ajax( rsvpmaker_rest.ajaxurl,
	  {
		type: 'POST',
		data: {
		  action: 'rsvpmaker_dismissed_notice_handler',
		  type: type,
		}
	  } );
  } );
});
</script>
<?php

}

function set_rsvpmaker_order_in_admin( $wp_query ) {
if(!is_admin() || empty($_GET['post_type']) || ($_GET['post_type'] != 'rsvpmaker') ) // don't mess with front end or other post types
	return $wp_query;

global $current_user;

if(isset($_GET["rsvpsort"])) {
	$sort = sanitize_text_field($_GET["rsvpsort"]);
update_user_meta($current_user->ID,'rsvpsort',$sort);
}
elseif(isset($_GET['all_posts']) || isset($_GET['post_status'])) {
	$sort = 'all';
	update_user_meta($current_user->ID,'rsvpsort',$sort);
}
else
	$sort = get_user_meta($current_user->ID,'rsvpsort',true);
if(empty($sort))
	$sort = 'future';
if(isset($_GET['s']))
	return;
if($sort == 'all')
	return;

if(($sort == "past") || ($sort == "future")) {
	add_filter('posts_join', 'rsvpmaker_join',99 );
	add_filter('posts_groupby', 'rsvpmaker_groupby',99 );
	}
if($sort == 'past')
	{
	add_filter('posts_where', 'rsvpmaker_where_past',99 );
	add_filter('posts_orderby', 'rsvpmaker_orderby_past',99 );
	}
elseif($sort == 'templates')
	{
	add_filter('posts_join', 'rsvpmaker_join_template',99 );
	add_filter('posts_where', function($content) {return " AND post_type='rsvpmaker'";}, 9999 );
	add_filter('posts_orderby', function($content) {return ' ID DESC ';}, 99  );
	}
elseif($sort == 'special')
	{
	add_filter('posts_join', 'rsvpmaker_join_special',99 );
	add_filter('posts_where', function($content) {return '';}, 99 );
	add_filter('posts_orderby', function($content) {return ' ID DESC ';}, 99  );
	}
elseif($sort == 'all')
	{
	add_filter('posts_where', function($content) {return " AND post_type='rsvpmaker' AND (wp_posts.post_status = 'publish' OR wp_posts.post_status = 'future' OR wp_posts.post_status = 'draft' OR wp_posts.post_status = 'pending' OR wp_posts.post_status = 'private')";}, 99 );
	add_filter('posts_orderby', function($content) {return ' ID DESC ';}, 99  );
	}
else
	{
	add_filter('posts_where', 'rsvpmaker_where',99 );
	add_filter('posts_orderby', 'rsvpmaker_orderby',99 );
	}
}
add_filter('pre_get_posts', 'set_rsvpmaker_order_in_admin',1 );

function rsvpmaker_admin_months_dropdown($bool, $post_type) {
return ($post_type == 'rsvpmaker');
}

add_filter( 'disable_months_dropdown', 'rsvpmaker_admin_months_dropdown',10,2 );

function rsvpmaker_join_template($join) {
  global $wpdb;
    return $join." JOIN ".$wpdb->postmeta." rsvpdates ON rsvpdates.post_id = $wpdb->posts.ID AND (BINARY rsvpdates.meta_key REGEXP '_sked_[A-Z].+')"; //  AND rsvpdates.meta_value
}
function rsvpmaker_join_special($join) {
  global $wpdb;
return $join." JOIN ".$wpdb->postmeta." rsvpdates ON rsvpdates.post_id = $wpdb->posts.ID AND rsvpdates.meta_key='_rsvpmaker_special'";
}

function rsvpmaker_sort_message() {
	if((basename($_SERVER['SCRIPT_NAME']) == 'edit.php') && isset($_GET["post_type"]) &&  ($_GET["post_type"]=="rsvpmaker") && !isset($_GET["page"]))
	{
	global $current_user;
	$sort = get_user_meta($current_user->ID,'rsvpsort',true);
	if(empty($sort))
		$sort = 'future';
		$current_sort = $o = $opt = '';
		$sortoptions = array('future' => __('Future Events','rsvpmaker'),'past' => __('Past Events','rsvpmaker'),'all' => __('All RSVPMaker Posts','rsvpamker'),'templates' => __('Event Templates','rsvpmaker'),'special' => __('Special','rsvpmaker'));
		foreach($sortoptions as $key => $option)
		{
			if(isset($_GET['s']))
			{
				$current_sort = __('Showing','rsvpmaker').': '.__('Search Results','rsvpmaker');
			}
			if($key == $sort)
			{
				$opt .= sprintf('<option value="%s" selected="selected">%s</option>',$key,$option);
				$current_sort = __('Showing','rsvpmaker').': '.$sortoptions[$key];
			}
			else
			{	
				$opt .= sprintf('<option value="%s">%s</option>',$key,$option);
				$o .= '<a class="add-new-h2" href="'.admin_url('edit.php?post_type=rsvpmaker&rsvpsort='.$key).'">'.$option.'</a> ';
			}
		}
		$opt = '<div class="alignleft actions rsvpsortwrap" style="margin-top: -10px;" ><select name="rsvpsort" class="rsvpsort">'.$opt.'</select> </div>';
		echo '<div style="padding: 10px; ">'.$opt;//.$current_sort.$o;
		echo '</div>';
	}
}

function rsvpmaker_projected_datestring($dow,$week,$template,$t = 0) {
	if(!$t) 
		$t = time();
	$weektext = rsvpmaker_week($week);
	if($week == '0')
		return rsvpmaker_date('Y-m',$t).'-01 '.$template['hour'].':'.$template['minutes'].':00';
	elseif($week == '6')
		return rsvpmaker_day($dow,'rsvpmaker_strtotime').' '.rsvpmaker_date('Y-m',$t).' '.$template['hour'].':'.$template['minutes'].':00';
	else
	return $weektext.' '.rsvpmaker_day($dow,'rsvpmaker_strtotime').' of '.rsvpmaker_date('F',$t).' '.rsvpmaker_date('Y',$t).' '.$template['hour'].':'.$template['minutes'].':00';
}

function rsvpmaker_get_projected($template) {

if(!isset($template["week"]))
	return;

//backward compatability
if(is_array($template["week"]))
	{
		$weeks = $template["week"];
		$dows = (empty($template["dayofweek"])) ? 0 : $template["dayofweek"];
	}
else
	{
		$weeks[0] = $template["week"];
		$dows[0] = isset($template["dayofweek"]) ? $template["dayofweek"] : 0;
	}

if(empty($template['hour']))
	$template['hour'] = '00';
if(empty($template['minutes']))
	$template['minutes'] = '00';

$th = (int) $template['hour'];

$cy = date("Y");
$cm = date("m");

if(!empty(trim($template["stop"])))
	{
	$stopdate = rsvpmaker_strtotime($template["stop"].' 23:59:59');
	}

if(empty($dows))
	$dows = array(0 => 0);
foreach($weeks as $week)
foreach($dows as $dow) {
$i = 0;
$startdaytxt = rsvpmaker_projected_datestring($dow,$week,$template);//rsvpmaker_day($dow,'rsvpmaker_strtotime').' '.$template['hour'].':'.$template['minutes'];
$ts = rsvpmaker_strtotime($startdaytxt);
if(!$ts) {
	echo 'Error parsing '.$startdaytxt;
	return;
}
if($week == 6)
	{
	$i = 0;
	if(empty($stopdate))
		$stopdate = rsvpmaker_strtotime('+6 months');
	else
		echo 'stopdate set';
	if(isset($_GET["start"]))
		$ts = rsvpmaker_strtotime($_GET["start"]);
	while($ts < $stopdate)
		{
		$projected[$ts] = $ts; // add numeric value for 1 week
		$ts = $ts + WEEK_IN_SECONDS;
		$i++;
		if($i > 52)
			break;
		}
	}
else {
	if(isset($_GET["start"]))
		$ts = rsvpmaker_strtotime($_GET["start"]);
	if(empty($stopdate))
		$stopdate = rsvpmaker_strtotime('+12 months');
	if($week == 0) {
		$i = 0;
		$firstdaytxt = rsvpmaker_projected_datestring($dow,0,$template);//rsvpmaker_day($dow,'rsvpmaker_strtotime').' '.$template['hour'].':'.$template['minutes'];
		$tcount = rsvpmaker_strtotime($firstdaytxt);
		for($tcount; $tcount < $stopdate; $tcount+= MONTH_IN_SECONDS )
		{
		$firstdaytxt = rsvpmaker_projected_datestring($dow,0,$template,$tcount);//rsvpmaker_day($dow,'rsvpmaker_strtotime').' '.$template['hour'].':'.$template['minutes'];
		$ts = rsvpmaker_strtotime($firstdaytxt);
		$projected[$ts] = $ts;
		$i++;
		if($i > 10)
			break;
		}	
	}
	else
		{
			$i = 0;
			$ts = time();
			$startmonth = rsvpmaker_date('F Y',$ts);
			for($i = 0; $i < 50; $i++) //($ts; $ts < $stopdate; $ts+= MONTH_IN_SECONDS )
			{
				$ts = strtotime($startmonth." + $i month");
				$datetext = rsvpmaker_projected_datestring($dow,$week,$template,$ts);//rsvpmaker_day($dow,'rsvpmaker_strtotime').' '.$template['hour'].':'.$template['minutes'];

				$ts = rsvpmaker_strtotime($datetext);
				if(!$ts)
				{
					echo 'Error parsing date string '.$datetext;
					return;
				}
				if(isset($stopdate) && $ts > $stopdate) {
					break;
				}
				$projected[$ts] = $ts;
				}
		}
	}
}

//order by timestamp
if(empty($projected))
	return array();
//timezone correction
foreach($projected as $index => $value) {
	$checkhour = (int) rsvpmaker_date('G',$value);
	$diff =  $th - $checkhour; 
	if($diff) {
		$value+= ($diff * HOUR_IN_SECONDS);
		unset($projected[$index]);
		$projected[$value] = $value;
	}
}
ksort($projected);

return $projected;
}

// RSVPMaker Replay Follow up

function rsvpmaker_replay_cron($post_id, $rsvp_id, $hours) {
//Convert start time from local time to GMT since WP Cron sends based on GMT
$start_time_gmt = time();
$time_difference = $hours * 60 * 60; 
$reminder_time = $start_time_gmt + $time_difference;

wp_clear_scheduled_hook( 'rsvpmaker_replay_email', array( $post_id, $rsvp_id, $hours ) );

//Schedule the reminder
wp_schedule_single_event( $reminder_time, 'rsvpmaker_replay_email', array( $post_id, $rsvp_id, $hours ) );
}

function rsvpmaker_replay_email ( $post_id, $rsvp_id, $hours ) {
global $wpdb;
global $rsvp_options;
$wpdb->show_errors();
	$confirm_slug = '_rsvp_reminder_msg_'.$hours;
	$confirm = get_post_meta($post_id, $confirm_slug, true);
	$subject = get_post_meta($post_id, '_rsvp_reminder_subject_'.$hours, true);

	if(!empty($confirm))
	{
	$confirm = wpautop($confirm);				
	}

	$rsvpto = get_post_meta($post_id,'_rsvp_to',true);			
	
$sql = "SELECT email FROM ".$wpdb->prefix."rsvpmaker WHERE event=$post_id AND id=".$rsvp_id;
	$notify = $wpdb->get_var($sql);							
	$mail["subject"] = $subject;
	$mail["html"] = $confirm;
	$mail["to"] = $notify;
	$mail["from"] = $rsvp_to;
	$mail["fromname"] = get_bloginfo('name');
	rsvpmaker_tx_email(get_post($post_id), $mail);
}

// RSVPMaker Reminders

function rsvpmaker_reminder_cron($hours, $start_time, $post_id) {
$hours = (int) $hours;
$post_id = (int) $post_id;
//Convert start time from local time to GMT since WP Cron sends based on GMT
if(is_int($start_time))
	$start_time_gmt = $start_time;
else
	$start_time_gmt = rsvpmaker_strtotime( get_gmt_from_date( $start_time ) . ' GMT' );

$time_difference = $hours * 60 * 60; 
$reminder_time = $start_time_gmt + $time_difference;

//Remove existing cron event for this post if one exists
//We pass $post_id because cron event arguments are required to remove the scheduled event
wp_clear_scheduled_hook( 'rsvpmaker_send_reminder_email', array( $post_id, $hours ) );

//Schedule the reminder
wp_schedule_single_event( $reminder_time, 'rsvpmaker_send_reminder_email', array( $post_id, $hours ) );
}

function rsvpmaker_send_reminder_email ( $post_id, $hours ) {
global $wpdb, $post;
global $rsvp_options;
$marker = $post_id.':'.$hours;
$check = get_option('rsvpmaker_last_reminder');

if($check == $marker)
	return; //this already ran
$success = update_option('rsvpmaker_last_reminder',$marker);
if(!$success)
	return;
$wpdb->show_errors();
	$post = get_post($post_id);
	$reminder = rsvp_get_reminder($post_id,$hours);
	$confirm = $reminder->post_content;
	$subject = $reminder->post_title;
	$include_event = get_post_meta($post_id, '_rsvp_confirmation_include_event', true);
	$rsvpto = get_post_meta($post_id,'_rsvp_to',true);
	//handle codes like [datetime] and [rsvpdate]
	$subject = do_shortcode($subject);
	if(!empty($confirm))
	{
	$confirm = wpautop($confirm);				
	}

	if($hours < 0)
	{	
	$confirm .= "<p>".__("This is an automated reminder that we have you on the RSVP list for the event shown below. If your plans have changed, you can update your response by clicking on the RSVP button again.",'rsvpmaker')."</p>";
		if($include_event)
		{
			$event_content = event_to_embed($post_id);
		}
		else
			$event_content = get_rsvp_link($post_id);
	}
			
			$sql = "SELECT * FROM ".$wpdb->prefix."rsvpmaker WHERE event=$post_id AND yesno=1";
			if(get_post_meta($post_id,'paid_only_confirmation',true))
				$sql .= " AND amountpaid";

			$rsvps = $wpdb->get_results($sql,ARRAY_A);
			if($rsvps)
			foreach($rsvps as $row)
				{
				$notify = $row["email"];
				$notification = $confirm;
				$notification .= '<h3>'.$row["first"]." ".$row["last"]." ".$row["email"];
				if(!empty($row["guestof"]))
					$notification .=  " (". __('guest of','rsvpmaker')." ".$row["guestof"].")";
				$notification .=  "</h3>\n";
				$notification .=   "<p>";
				if(!empty($row["details"]))
					{
					$details = unserialize($row["details"]);
					foreach($details as $name => $value)
						if($value) {
							$notification .=  "$name: $value<br />";
							}
					}
				if(!empty($row["note"]))
					$notification .= "note: " . nl2br($row["note"])."<br />";
				$t = rsvpmaker_strtotime($row["timestamp"]);
				$notification .= 'posted: '.rsvpmaker_date($rsvp_options["short_date"],$t);
				$notification .=  "</p>";
				$notification .=  "<h3>Event Details</h3>\n".str_replace('#rsvpnow">','#rsvpnow">'.__('Update','rsvptoast').' ',str_replace('*|EMAIL|*',$notify, $event_content));
				
				echo "Notification for $notify<br />$notification";

			//if this is a follow up, we don't need all the RSVP data
			if($hours > 0)
				$notification = $confirm;

				$mail["subject"] = $subject;
				$mail["html"] = $notification;
				$mail["to"] = $notify;
				$mail["from"] = $rsvpto;
				$mail["fromname"] = get_bloginfo('name');

				rsvpmaker_tx_email(get_post($post_id), $mail);
				}
}

function rsvp_reminder_options($hours = -2) {
$ho = array(-1,-2,-3,-4,-5,-6,-7,-8,-12,-16,-20,-24,-48,-72,1,2,3,4,5,6,7,8,12,16,20,24,28,32,36,40,44,48,72);
$o = "";
foreach($ho as $h)
	{
	$s = ($h == $hours) ? ' selected="selected" ' : '';
	if($h < 0)
		$o .= sprintf('<option value="%s" %s>%s ',$h,$s, abs($h) ).__('hours before','rsvpmaker').'</option>';
	else
		$o .= sprintf('<option value="%s" %s>%s ',$h,$s,$h).__('hours after event starts','rsvpmaker').'</option>';
	}
	return $o;
}

function rsvpmaker_youtube_live($post_id, $ylive, $show = false) {
global $rsvp_options;
global $current_user;
		
		$event = get_post($post_id);
		$start_time = $date = get_rsvp_date($post_id);
		$date = utf8_encode(rsvpmaker_date($rsvp_options["long_date"].' '.$rsvp_options['time_format'],rsvpmaker_strtotime($date)));
		$landing["post_type"] = 'rsvpmaker';
		$landing["post_title"] = __('Live','rsvpmaker').': '.$event->post_title;
		$landing["post_content"] = __('The event starts','rsvpmaker').' '.$date."\n\n".$ylive;
		if(!empty($ylive))
			$landing["post_content"] .= "\n\n[ylchat note=\"During the program, please post questions and comments in the chat box below.\"]";
		$landing["post_author"] = $current_user->ID;
		$landing["post_status"] = 'publish';
		$landing_id = get_post_meta($post_id,'_webinar_landing_page_id',true);
		if($landing_id)
			{
			$landing["ID"] = $landing_id;
			wp_update_post( $landing );
			}
		else
			{
			$landing_id = wp_insert_post( $landing );
			}
		update_post_meta($post_id,'_webinar_landing_page_id',$landing_id);
		$landing_permalink = get_permalink($landing_id);
		$landing_permalink .= (strpos($landing_permalink,'?')) ? '&webinar=' : '?webinar=';
		$landing_permalink .= $passcode = wp_generate_password(14, false); // 14 characters, no special characters
		update_post_meta($landing_id,'_rsvpmaker_special','Landing Page');
		update_post_meta($landing_id,'_webinar_event_id',$post_id);
		update_post_meta($landing_id,'_webinar_passcode',$passcode);
		if(isset($_REQUEST["youtube_require_passcode"]))
			update_post_meta($landing_id,'_require_webinar_passcode',$passcode);
	$subject = 'Reminder: '.$event->post_title;
	$message = __('Thanks for registering for','rsvpmaker').' '.$event->post_title."\n\n".__('The event will start at','rsvpmaker').' '.$date."\n\n".__('Tune in here','rsvpmaker').":\n".'<a href="'.$landing_permalink.'">'.$landing_permalink."</a>\n\n".__('You will be able to post questions or comments to the live chat on the event page').'.';
	$hours = -2;
	update_post_meta($post_id, '_rsvp_confirm',$message);
	update_post_meta($post_id, '_rsvp_reminder_msg_'.$hours,$message);
	update_post_meta($post_id, '_rsvp_reminder_subject_'.$hours,$subject);
	rsvpmaker_reminder_cron($hours, $start_time, $post_id);

	$hours = 2;
	$subject = 'Follow up: '.$event->post_title;
	$message = __('Thanks for your interest in ','rsvpmaker').' '.$event->post_title."\n\n".__('If you missed all or part of the program, a replay is waiting for you here','rsvpmaker').":\n".'<a href="'.$landing_permalink.'">'.$landing_permalink."</a>\n\n";
	update_post_meta($post_id, '_rsvp_reminder_msg_'.$hours,$message);
	update_post_meta($post_id, '_rsvp_reminder_subject_'.$hours,$subject);
	rsvpmaker_reminder_cron($hours, $start_time, $post_id);

	if($show)
		printf('<p>%s <a href="%s">%s</a> (<a href="%s">%s</a>)</p>',__('YouTube Live landing page created at'),$landing_permalink,$landing_permalink, admin_url('post.php?action=edit&post='.$landing_id), __('Edit','rsvpmaker'));
	
}

function no_mce_plugins( $p ) { return array(); }

function rsvpmaker_template_reminder_add($hours,$post_id) {
	$cron = get_post_meta($post_id,'rsvpmaker_template_reminder',true);
	if(!is_array($cron))
		$cron = array();
	if(!in_array($hours,$cron))
		$cron[] = $hours;
	update_post_meta($post_id, 'rsvpmaker_template_reminder', $cron);
}

function rsvp_get_confirm($post_id, $return_post = false) {
	global $rsvp_options, $post, $wpdb, $wp_styles;
	$content = get_post_meta($post_id,'_rsvp_confirm',true);
	if(empty($content))
		$content = $rsvp_options['rsvp_confirm'];
	if(is_numeric($content))
	{
		$id = $content;
		$conf_post=get_post($id);
		if(empty($conf_post))
		{
			//maybe got deleted or something?
			if(is_numeric($rsvp_options['rsvp_confirm']))
				$conf_post= get_post($rsvp_options['rsvp_confirm']);
		}
		if(empty($conf_post->post_content)) {
			//if the default confirmation post is missing, recreate it
			$conf_array = array('post_title'=>'Confirmation:Default', 'post_content'=>'Thank you!','post_type' => 'rsvpemail','post_status' => 'publish');
			$conf_array['ID'] = $id = wp_insert_post($conf_array);
			$rsvp_options['rsvp_confirm'] = $id;
			update_option('RSVPMAKER_Options',$rsvp_options);
			$conf_post = (object) $conf_array;
		}			
		if(!strpos($conf_post->post_content,'!-- /wp'))//if it's not Gutenberg content
			$conf_post->post_content = wpautop($conf_post->post_content);
		if(function_exists('do_blocks'))
			$conf_post->post_content = do_blocks($conf_post->post_content);
		$title = (!empty($post->post_title)) ? $post->post_title : 'not set';
		$context = (is_admin()) ? 'admin' : 'not admin';
		$log = sprintf('retrieve conf post ID %s for %s %s',$id,$title,$context);
	}
	else {
		if(function_exists('do_blocks'))
			$content = wp_kses_post(rsvpautog($content));
		$conf_post = array('post_title'=>'Confirmation:'.$post_id, 'post_content'=>$content,'post_type' => 'rsvpemail','post_status' => 'publish','post_parent' => $post_id);
		$conf_post['ID'] = $id = wp_insert_post($conf_post);
		$conf_post = (object) $conf_post;
		update_post_meta($post_id,'_rsvp_confirm',$id);
		$title = (!empty($post->post_title)) ? $post->post_title : 'not set';
		$context = (is_admin()) ? 'admin' : 'not admin';
		$log = sprintf('adding conf post ID %s for %s %s',$id,$title,$context);
	}
	if($return_post)
		return $conf_post;
	return $conf_post->post_content;
}

function rsvp_list_reminders_all_events() {
	global $wpdb;
	$events = get_future_events();
	if(empty($events))
		return;
	$output = '';
	foreach($events as $event) {
		$sql = "SELECT * FROM $wpdb->postmeta WHERE meta_key LIKE '_rsvp_reminder_msg_%' AND post_id=".$event->ID." ORDER BY meta_key ";
		$results = $wpdb->get_results($sql);
		foreach($results as $row) {
			$time = str_replace('_rsvp_reminder_msg_','',$row->meta_key);
			$output .= sprintf('<p><input type="checkbox" name="delete_reminder[]" value="%d:%s"> <a href="%s">Edit Reminders</a> %s %s %s hours</p>',$event->ID, $row->meta_key, admin_url('edit.php?page=rsvp_reminders&post_type=rsvpmaker&post_id='.$event->ID),$event->post_title,$event->datetime,$time);
		}
	}
	if(!empty($output))
		return 	sprintf('<form method="post" action="%s">',admin_url('edit.php?post_type=rsvpmaker&page=rsvp_reminders')).$output.'<p><button>Delete Checked</button>'.rsvpmaker_nonce('return').'</p></form>';
}

function rsvp_get_reminder($post_id,$hours) {
	global $rsvp_options, $wpdb;
	$key = '_rsvp_reminder_msg_'.$hours;
	$reminder_id = get_post_meta($post_id, $key,true);
	if(empty($reminder_id) && ($t = rsvpmaker_has_template($post_id)) &&!isset($_GET['was']) )
		$reminder_id = get_post_meta($t, $key,true);
	if(empty($reminder_id) || !is_numeric($reminder_id))
		return;
	$conf_post = get_post($reminder_id);
	if(empty($conf_post))
		return;
	if(function_exists('do_blocks'))
		$conf_post->post_content = do_blocks($conf_post->post_content);
	return $conf_post;
}

function rsvp_reminders () {
global $wpdb;
global $rsvp_options;
global $current_user;
$existing = $options = '';
$templates = rsvpmaker_get_templates();
$post_id = (isset($_REQUEST["post_id"])) ? (int) $_REQUEST["post_id"] : false;

if(isset($_POST['defaults']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
{
	foreach($_POST['defaults'] as $index => $value) {
		if($index == 'confirmation') {
			delete_post_meta($post_id,'_rsvp_confirm');
		}
		if($index == 'payment_confirmation') {
			delete_post_meta($post_id,'payment_confirmation_message');
		}
		if($index == 'reminders')
		{
			$sql = "DELETE FROM $wpdb->postmeta WHERE post_id=$post_id AND meta_key LIKE '_rsvp_reminder_msg_%'";
			$wpdb->query($sql);
		}
	}
}

$documents = get_related_documents();
?>
<style>
<?php 
$styles = rsvpmaker_included_styles();
echo $styles; ?>
</style>
<div class="wrap"> 
	<div id="icon-edit" class="icon32"><br /></div>
<h1><?php echo __('Confirmation / Reminder Messages','rsvpmaker').': '.get_the_title($post_id); ?></h1> 
<?php

if($post_id)
	$start_time = get_rsvp_date($post_id);

$hours = (isset($_REQUEST["hours"])) ? (int) $_REQUEST["hours"] : false;

if(isset($_GET["webinar"]))
	{
		$post_id = (int) $_GET["post_id"];
		$ylive = sanitize_text_field($_GET["youtube_live"]);	
		rsvpmaker_youtube_live($post_id, $ylive, true);
	}	
	
if(isset($_GET['delete']))
{
	$key = '_rsvp_reminder_msg_'. (int) $_GET['delete'];
	printf('<p>Deleting %s</p>',$key);
	delete_post_meta($post_id,$key);
}

if(isset($_POST['delete_reminder']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {
	foreach($_POST['delete_reminder'] as $delete_reminder) {
		$delete_reminder = sanitize_text_field($delete_reminder);
		$p = explode(':',$delete_reminder);
		delete_post_meta($p[0],$p[1]);
	}
}

if(isset($_GET['paid_only_confirmation'])) {
	$reminder_id = (int) $_GET['reminder_post_id'];
	update_post_meta($reminder_id, 'paid_only_confirmation', (int) $_GET['paid_only_confirmation']);
	printf('<div class="notice notice-success"><p>%s, post_id: %d</p></div>',__('Reminder updated','rsvpmaker'),$reminder_id);
}

if($post_id && $hours)
{
	$reminder = rsvp_get_reminder($post_id,$hours);
	if(!empty($reminder))
	{
		printf('<p>%s %s %s</p><h2>%s</h2>%s<p><a href="%s">%s</a></p>',__('Added reminder ','rsvpmaker'), (int) $_GET['hours'],__('hours','rsvpmaker'),esc_html($reminder->post_title),wp_kses_post($reminder->post_content),admin_url('post.php?action=edit&post='.intval($reminder->ID)),__('Edit','rsvpmaker'));	
	if(rsvpmaker_is_template($post_id))
	{
		echo 'This is a template';
		rsvpmaker_template_reminder_add($hours,$post_id);
		rsvpautorenew_test (); // will add to the next scheduled event associated with template
	}
	else
	{
		$start_time = get_rsvp_event_time($post_id);
		rsvpmaker_reminder_cron($hours, $start_time, $post_id);
	}
		
	}
	else '<h2>Error Adding Reminder</h2>';
}

if($post_id)
{
if(rsvpmaker_is_template($post_id))
	printf('<p><em>%s</em></p>',__('This is an event template: The confirmation and reminder messages you set here will become the defaults for future events based on this template. The [datetime] placeholder in subject lines will be replaced with the specific event date.','rsvpmaker'));

//$confirm = rsvp_get_confirm($post_id, true);
printf('<form action="%s" method="post">',admin_url('edit.php?post_type=rsvpmaker&page=rsvp_reminders&message_type=confirmation&post_id=').$post_id);
rsvpmaker_nonce();
echo get_confirmation_options($post_id, $documents);
echo '<button>Save</button></form>';

$reminder_copy = sprintf('<option value="%d">%s</option>',get_post_meta($post_id,'_rsvp_confirm',true),__('Confirmation Message'));

printf('<h3>%s</h3>',__('Payment Confirmation','rsvpmaker'));
$payment_confirmation = (int) get_post_meta($post_id,'payment_confirmation_message',true);
if($payment_confirmation)
{
	$pconf = get_post($payment_confirmation);
	echo (empty($pconf->post_content)) ? '<p>[not set]</p>' : $pconf->post_content;
}

foreach($documents as $d) {
	$id = $d['id'];
	if(($id == 'edit_payment_confirmation') || ($id == 'edit_payment_confirmation_custom'))
	printf('<p><a href="%s">Edit: %s</a></p>',$d['href'],$d['title']);
}

if(!empty($pconf->post_content))
	$reminder_copy .= sprintf('<option value="%d">%s</option>',$pconf->ID,__('Payment Confirmation','rsvpmaker'));

echo '<div style="border: thin solid #555; padding: 10px;"><h2>Reminders</h2>';

$sql = "SELECT * FROM $wpdb->postmeta WHERE post_id=$post_id AND meta_key LIKE '_rsvp_reminder_msg_%' ORDER BY meta_key";

$results = $wpdb->get_results($sql);
$delete_reminder_options = '';
if(!$results)
	echo '<p>No reminders active</p>';
else {
	foreach($results as $row)
	{
		$hours = str_replace('_rsvp_reminder_msg_','',$row->meta_key);
		$type = ($hours > 0) ? 'FOLLOW UP' : 'REMINDER';
		$reminder = rsvp_get_reminder($post_id,$hours);
		$reminder_copy .= sprintf('<option value="%d">%s %s</option>',$reminder->ID,$type,$hours);
		$delete_reminder_options .= sprintf('<option value="%s">%s %s</option>',esc_attr($post_id.':'.$row->meta_key),esc_html($type),esc_html($hours));
		printf('<h2>%s (%s hours)</h2><h3>%s</h3>%s',esc_html($type),esc_html($hours),esc_html($reminder->post_title),wp_kses_post($reminder->post_content));
		$parent = $reminder->post_parent;
		if($parent != $post_id)
			printf('<p>%s<br /><a href="%s">%s</a></p>',__('This is the standard reminder from the event template','rsvpmaker'), admin_url('edit.php?post_type=rsvpmaker&page=rsvp_reminders&post_id='.$post_id.'&hours='.$hours.'&was='. $reminder->ID),__('Customize for this event','rsvpmaker'));
		foreach($documents as $d) {
			$id = $d['id'];
			if(($id == 'reminder'.$hours) || ($id == 'reminder'.$hours.'custom'))
			printf('<p><a href="%s">Edit: %s</a></p>',esc_attr($d['href']),esc_html($d['title']));
		}
		$paid_only = get_post_meta($reminder->ID,'paid_only_confirmation',true);
		if($paid_only)
			$radio = sprintf('<input type="radio" name="paid_only_confirmation" value="1" checked="checked" /> Yes <input type="radio" name="paid_only_confirmation" value="0" /> No ');
		else
			$radio = sprintf('<input type="radio" name="paid_only_confirmation" value="1" /> Yes <input type="radio" name="paid_only_confirmation" value="0"  checked="checked"  /> No ');
		printf('<form action="%s" method="get">
		<input type="hidden" name="post_type" value="rsvpmaker" />
		<input type="hidden" name="page" value="rsvp_reminders" />
		<input type="hidden" name="message_type" value="confirmation" />
		<input type="hidden" name="post_id" value="%d" />
		<input type="hidden" name="reminder_post_id" value="%d" />
		<p>%s %s %s
		<button>Update</button></p>
		</form>',admin_url('edit.php'),esc_attr($post_id),esc_attr($reminder->ID),__('Send only after payment','rsvpmaker'),$radio,rsvpmaker_nonce('return'));
	}
printf('<h3>Delete Reminder</h3><form method="post" action="%s"><select name="delete_reminder[]">%s</select><br /><button>Delete</button>%s</form>',admin_url('edit.php?page=rsvp_reminders&post_type=rsvpmaker&post_id='.$post_id),$delete_reminder_options,rsvpmaker_nonce('return'));
}

$reminder_copy .= '<option value="">'.__('Blank message','rsvpmaker').'</option>';

$hour_options = rsvp_reminder_options();
printf('<h3>Add Reminders and Follow Up Messages</h3>
<form method="post" action="%s"><input type="hidden" name="create_reminder_for" value="%s">
<p><select name="hours">%s</select>
%s
<select name="copy_from">%s</select></p>
<p><input type="checkbox" name="paid_only" value="1"> Send for PAID registrations only</p>
<p><button>Submit</button></p>%s</form>',admin_url('edit.php'),esc_attr($post_id),$hour_options,__('Based on','rsvpmaker'),$reminder_copy,rsvpmaker_nonce('return'));

echo '</div>';//end reminders section

printf('<h3>Reset to Defaults</h3>
<form method="post" action="%s">
<p><input type="checkbox" name="defaults[confirmation]" value="1" /> Confirmation</p>
<p><input type="checkbox" name="defaults[payment_confirmation]" value="1"> Payment Confirmation</p>
<p><input type="checkbox" name="defaults[reminders]" value="1"> Remove Reminders</p>
<p><button>Submit</button></p>%s</form>',admin_url('edit.php?post_type=rsvpmaker&page=rsvp_reminders&message_type=confirmation&post_id='.$post_id),rsvpmaker_nonce('return'));

?>
<h3><?php esc_html_e('Webinar Setup','rsvpmaker'); ?></h3>
<form method="get" action = "<?php echo admin_url('edit.php'); ?>">
<p><?php esc_html_e('This utility sets up a landing page and suggested confirmation and reminder messages, linked to that page. RSVPMaker explicitly supports webinars based on YouTube Live, but you can also embed the coding required for another webinar of your choice.','rsvpmaker'); ?></p>
<input type="hidden" name="post_type" value="rsvpmaker" >
<input type="hidden" name="page" value="rsvp_reminders" >
<input type="hidden" name="webinar" value="1" >
<input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
<p>YouTube Live url: <input type="text" name="youtube_live" value=""> <input type="checkbox" name="youtube_require_passcode" value="1" /> <?php esc_html_e('Require passcode to view','rsvpmaker');?></p>
<?php rsvpmaker_nonce(); ?>
<p><button><?php esc_html_e('Create','rsvpmaker');?></button></p>
</form>
<?php

}
else {
	$o = '<option value="">Select Event or Event Template</option>';
	$templates = rsvpmaker_get_templates();
	if($templates)
	foreach($templates as $event)
	{
		if(current_user_can('edit_post',$event->ID))
		$o .= sprintf('<option value="%s">TEMPLATE: %s</option>',esc_attr($event->ID),esc_html($event->post_title));
	}
	$future = get_future_events();
	if($future)
	foreach($future as $event)
	{
		if(current_user_can('edit_post',$event->ID))
		$o .= sprintf('<option value="%s">%s - %s</option>',esc_attr($event->ID),esc_html($event->post_title),esc_html($event->date));
	}	
	printf('<form method="get" action="%s"><input type="hidden" name="page" value="rsvp_reminders"><input type="hidden" name="post_type" value="rsvpmaker"><select name="post_id">%s</select><button>Get</button>%s</form>',admin_url('edit.php'),$o,rsvpmaker_nonce('return'));
}

rsvpmaker_reminders_nudge ();

$list = rsvp_list_reminders_all_events();
if($list)
	echo '<h2>Reminders for All Upcoming Events</h2>'.$list;

?>
<h3><?php esc_html_e('A Note on More Reliable Scheduling','rsvpmaker');?></h3>
<p><?php esc_html_e('RSVPMaker takes advantage of WP Cron, a standard WordPress scheduling mechanism. Because it only checks for scheduled tasks to be run when someone visits your website, WP Cron can be imprecise -- which could be a problem if you want to make sure a reminder will go out an hour before your event, if that happens to be a low traffic site. Caching plugins can also get in the way of regular WP Cron execution. Consider following <a href="http://code.tutsplus.com/articles/insights-into-wp-cron-an-introduction-to-scheduling-tasks-in-wordpress--wp-23119">these directions</a> to make sure your server checks for scheduled tasks to run on a more regular schedule, like once every 5 or 15 minutes.','rsvpmaker');?></p>

<p><?php esc_html_e('Using Unix cron, the command you would set to execute would be','rsvpmaker');?>:</p>
<code>
curl <?php echo site_url('/wp-cron.php?doing_wp_cron=1');?> > /dev/null 2>&1
</code>
<p><?php esc_html_e('If curl does not work, you can also try this variation (seems to work better on some systems)','rsvpmaker');?>:</p>
<code>
wget -qO- <?php echo site_url('/wp-cron.php?doing_wp_cron=1');?>  &> /dev/null
</code>
</div>
<?php

}

function rsvpmaker_placeholder_image () {
$impath = dirname( __FILE__ ).DIRECTORY_SEPARATOR.'placeholder.png';
$im = imagecreatefrompng($impath);
if(!$im)
{
$im = imagecreate(800, 50);
imagefilledrectangle($im,5,5,790,45, imagecolorallocate($im, 50, 50, 255));
}

$bg = imagecolorallocate($im, 200, 200, 255);
$border = imagecolorallocate($im, 0, 0, 0);
$textcolor = imagecolorallocate($im, 255, 255, 255);

$text = (isset($_GET['post_id'])) ? __('Event','rsvpmaker').': ' : __('Events','rsvpmaker').': ';
$tip = '('.__('double-click for popup editor','rsvpmaker').')';

foreach ($_GET as $name => $value)
	{
	if($name == 'rsvpmaker_placeholder')
		continue;
	if(empty($value))
		continue;
	$text .= $name.'='.$value.' '; 
	}

// Write the string at the top left
imagestring($im, 5, 10, 10, $text, $textcolor);
imagestring($im, 5, 10, 25, $tip, $textcolor);

// Output the image
header('Content-type: image/png');

imagepng($im);
imagedestroy($im);
exit();
}

function rsvp_mce_buttons( $buttons ) {
	global $post;
	if(empty($post)) return $buttons;
	if(($post->post_type=='rsvpmaker') || (isset($_GET["post_type"]) && ($_GET["post_type"] == 'rsvpmaker')) )
		return $buttons;
    array_push( $buttons, 'rsvpmaker_upcoming' );
    array_push( $buttons, 'rsvpmaker_one' );
    return $buttons;
}
add_filter( 'mce_buttons', 'rsvp_mce_buttons' ); //, 10000 priority for Beaver Builder

function rsvp_mce_plugins ( $plugin_array ) {
	global $post;
	if(empty($post)) return $plugin_array;
	if(($post->post_type=='rsvpmaker') || (isset($_GET["post_type"]) && ($_GET["post_type"] == 'rsvpmaker')) )
		return $plugin_array;
	
    $plugin_array['rsvpmaker_upcoming'] = plugins_url( 'mce.js?v=2.2' , __FILE__ );
    $plugin_array['rsvpmaker_one'] = plugins_url( 'mce-single-event.js?v=2.4' , __FILE__ );
    return $plugin_array;
}
add_filter( 'mce_external_plugins', 'rsvp_mce_plugins', 10000);

function rsvpmaker_upcoming_admin_js() {
if(function_exists('do_blocks'))
	return; //don't need this on Gutenberg-enabled sites

    global $current_screen;
	global $post;
	global $wp_query;
	global $wpdb;
	global $showbutton;
	global $startday;
	global $rsvp_options;
	
	$showbutton = true;
	
	$backup = $wp_query;

    $type = $current_screen->post_type;

    if (is_admin() && $type != 'rsvpmaker') {
     
	 	$sql = "SELECT *, $wpdb->postmeta.meta_value as datetime, $wpdb->posts.ID as postID, 1 as current
FROM `".$wpdb->postmeta."`
JOIN $wpdb->posts ON ".$wpdb->postmeta.".post_id = $wpdb->posts.ID AND meta_key='_rsvp_dates'
WHERE  meta_value >= '".get_sql_now()."' AND $wpdb->posts.post_status = 'publish'
ORDER BY meta_value";
	 $results = $wpdb->get_results($sql);
	 $row[] = "{text: 'Pick One?', value: '0'}";
	$row[] = "{text: 'Next Event', value: 'next'}";
	$row[] = "{text: 'Next Event - RSVP On', value: 'nextrsvp'}";
	if($results)
	foreach ($results as $r)
	 	$row[] = sprintf("{text: '%s', value: '%d'}",addslashes($r->post_title).' '.rsvpmaker_date('r',rsvpmaker_strtotime($r->datetime)),$r->ID);   

$terms = get_terms('rsvpmaker-type', array('hide_empty' => false));
$t[] = "{text: 'Any', value: ''}";
if ( ! empty( $terms ) && ! is_wp_error( $terms ) ){
     foreach ( $terms as $term ) {
       $t[] = sprintf("{text: '%s', value: '%s'}",$term->name,$term->slug);
     }
}
	?>
        <script type="text/javascript">
        var upcoming = [<?php echo implode(",\n",$row); ?>];
		var rsvpmaker_types = [<?php echo implode(",\n",$t); ?>];
        </script>
        <?php
    }
}

function rsvpmaker_clone_title($title) {
	if(isset($_GET["clone"]))
		{
			$id = (int) $_GET["clone"];
			$clone = get_post($id);
			$title = $clone->post_title;
		}
	return $title;
}
add_filter('default_title','rsvpmaker_clone_title');

function rsvpmaker_clone_content ($content) {
	if(isset($_GET["clone"]))
		{
			$id = (int) $_GET["clone"];
			$clone = get_post($id);
			$content = $clone->post_content;
		}
	return $content;
}
add_filter('default_content','rsvpmaker_clone_content');

function export_rsvpmaker () {
//pack data from custom tables into wordpress metadata
global $wpdb;
$results = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'rsvpmaker ORDER BY event',ARRAY_A);
if($results)
	{
	foreach($results as $row)
		{
			array_shift($row); // id becomes irrelevant
			$events[$row['event']][] = $row; 		
		}
	if($events && is_array($events))
	foreach($events as $event => $meta)
		update_post_meta($event,'_export_rsvpmaker',$meta);
	}
$results = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'rsvp_volunteer_time ORDER BY event',ARRAY_A);
if($results)
	{
	foreach($results as $row)
		{
			array_shift($row); // id becomes irrelevant
			$v[$row['event']][] = $row; 		
		}
	foreach($v as $event => $meta)
		update_post_meta($event,'_export_rsvp_volunteer_time',$meta);
	}

}

function import_rsvpmaker() {
global $wpdb;
// import routine (transfer from another site)

global $wpdb;
$wpdb->show_errors();

$results = $wpdb->get_results("SELECT * FROM $wpdb->postmeta WHERE meta_key='_export_rsvpmaker' ");
if($results)
{
foreach($results as $row)
	{
	$data = unserialize($row->meta_value);
	if(is_array($data))
	foreach($data as $newrow)
	{
	$sql = "INSERT INTO ".$wpdb->prefix.'rsvpmaker SET ';
	$count = 0;
	foreach($newrow as $key => $value)
		{
		if($count)
			$sql .= ', ';
		$sql .= $wpdb->prepare("`$key` = %s",$value);
		$count++;
		}
	$wpdb->query($sql);
	}
	
	}
$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key='_export_rsvpmaker' ");
}

$results = $wpdb->get_results("SELECT * FROM $wpdb->postmeta WHERE meta_key='_export_rsvp_volunteer_time' ");
if($results)
{
foreach($results as $row)
	{
	$data = unserialize($row->meta_value);
	foreach($data as $newrow)
	{
	$sql = "INSERT INTO ".$wpdb->prefix.'rsvp_volunteer_time SET ';
	$count = 0;
	foreach($newrow as $key => $value)
		{
		if($count)
			$sql .= ', ';
		$sql .= $wpdb->prepare("`$key` = %s",$value);
		$count++;
		}
	$wpdb->query($sql);
	}
	
	}
$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key='_export_rsvp_volunteer_time' ");
}

}

function rsvpmaker_paypal_config_ajax () {
$filename = rsvpmaker_paypal_config_write(sanitize_text_field($_POST["user"]),sanitize_text_field($_POST["password"]),sanitize_text_field($_POST["signature"]));
die($filename);
}

function rsvpmaker_paypal_config_write($user,$password,$signature) {
$up = wp_upload_dir();
$filename = trailingslashit($up['path']);
$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    for ($i = 0; $i < 20; $i++) {
        $filename .= $characters[rand(0, $charactersLength - 1)];
    }
$filename .= '.php';

$paypal_config_template = sprintf("<?php
if( !defined( 'ABSPATH' ) )
	die( 'Fatal error: Call to undefined function paypal_setup() in %s on line 5' );
define('API_USERNAME', '%s');
define('API_PASSWORD', '%s');
define('API_SIGNATURE', '%s');
define('API_ENDPOINT', 'https://api-3t.paypal.com/nvp');
define('USE_PROXY',FALSE);
define('PROXY_HOST', '127.0.0.1');
define('PROXY_PORT', '808');
define('PAYPAL_URL', 'https://www.paypal.com/webscr&cmd=_express-checkout&useraction=commit&token=');
define('VERSION', '3.0');
?>",$filename,$user,$password,$signature);
$myfile = fopen($filename, "w") or die("Unable to open file!");
fwrite($myfile, $paypal_config_template);
fclose($myfile);
update_option('paypal_config',$filename);
return $filename;
}

function future_rsvpmakers_by_template($template_id) {
	$ids = array();
	$sched_result = get_events_by_template($template_id);
	if($sched_result)
	foreach($sched_result as $row)
		$ids[] = $row->ID;
	return $ids;
}

function rsvptimes ($time,$fieldname) {
if(empty($time))
	$time = '01:00:00';
printf('%s <input type="time" name="%s" value="%s">',__('Time'),$fieldname,$time);
}

function rsvpmaker_add_one () {

if(!empty($_POST["rsvpmaker_add_one"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
{
global $wpdb;
global $current_user;

$t = (int) $_POST["template"];
$post = get_post($t);
$template = get_template_sked($t);

$hour = (isset($template["hour"]) ) ? (int) $template["hour"] : 17;
$minutes = isset($template["minutes"]) ? $template["minutes"] : '00';

	$my_post['post_title'] = $post->post_title;
	$my_post['post_content'] = $post->post_content;
	$my_post['post_status'] = 'draft';
	$my_post['post_author'] = $current_user->ID;
	$my_post['post_type'] = 'rsvpmaker';
	foreach($_POST["recur_check"] as $index => $on)
		{
			if(!empty($_POST["recur_title"][$index]))
				$my_post['post_title'] = sanitize_text_field($_POST["recur_title"][$index]);
			$year = sanitize_text_field($_POST["recur_year"][$index]);
			$cddate = format_cddate($year, sanitize_text_field($_POST["recur_month"][$index]), sanitize_text_field($_POST["recur_day"][$index]), $hour, $minutes);
			$dpart = explode(':',$template["duration"]);
			
			if( is_numeric($dpart[0]) )
				{
				$dtext = $cddate.' +'.$dpart[0].' hours';
				if(!empty($dpart[1]))
					$dtext .= ' +'.$dpart[1].' minutes';
				$dt = rsvpmaker_strtotime($dtext);
				$duration = rsvpmaker_date('Y-m-d H:i:s',$dt);
				}
			else
				$duration = $template["duration"];
			$y = (int) $_POST["recur_year"][$index];
			$m = (int) $_POST["recur_month"][$index];
			if($m < 10) $m = '0'.$m;
			$d = (int) $_POST["recur_day"][$index];
			if($d < 10) $d = '0'.$d;
			$date = $y.'-'.$m.'-'.$d;

			$my_post['post_name'] = sanitize_text_field($my_post['post_title'] . '-' .$date );
			$singular = __('Event','rsvpmaker');
// Insert the post into the database
  			if($post_id = wp_insert_post( $my_post ) )
				{
				add_rsvpmaker_date($post_id,$cddate,$duration);				
				add_post_meta($post_id,'_meet_recur',$t,true);
				$ts = $wpdb->get_var("SELECT post_modified from $wpdb->posts WHERE ID=".$post_id);
				update_post_meta($post_id,"_updated_from_template",$ts);

				wp_set_object_terms( $post_id, $rsvptypes, 'rsvpmaker-type', true );

				$results = $wpdb->get_results("SELECT * FROM $wpdb->postmeta WHERE meta_key LIKE '_rsvp%' AND post_id=".$t);
				if($results)
				foreach($results as $row)
					{
					if($row->meta_key == '_rsvp_reminder')
						continue;
					$wpdb->query($wpdb->prepare("INSERT INTO $wpdb->postmeta SET meta_key=%s,meta_value=%s,post_id=%d",$row->meta_key,$row->meta_value,$post_id));
					}
				//copy rsvp options
				$editurl = admin_url('post.php?action=edit&post='.$post_id);
				wp_redirect($editurl);
				exit;
				}		
		break;
		}
	}
}//end rsvpmaker_add_one

function rsvpmaker_admin_page_top($headline) {

/*
$hook = rsvpmaker_admin_page_top(__('Headline','rsvpmaker'));
rsvpmaker_admin_page_bottom($hook);
*/
$hook = '';
if(is_admin()) { // if not full screen view
	$screen = get_current_screen();
	$hook = $screen->id;
}

$print = (isset($_GET["page"]) && !isset($_GET["rsvp_print"])) ? '<div style="width: 200px; text-align: right; float: right;"><a target="_blank" href="'.admin_url(str_replace('/wp-admin/','',sanitize_text_field($_SERVER['REQUEST_URI']))).'&rsvp_print=1">Print</a></div>' : '';
printf('<div id="wrap" class="%s toastmasters">%s<h1>%s</h1>',$hook,$print,$headline);
return $hook;
}

function rsvpmaker_admin_page_bottom($hook = '') {
if(is_admin() && empty($hook))
	{
	$screen = get_current_screen();
	$hook = $screen->id;
	}
printf("\n".'<hr /><p><small>%s</small></p></div>',$hook);
}

function rsvpmaker_editors() {
if(isset($_GET['page']) && ($_GET['page'] == 'rsvp_reminders'))
	wp_enqueue_editor();
}

function rsvpmaker_admin_notice_format($message, $slug, $cleared, $type='info')
{
if(in_array($slug,$cleared))
	return;
return sprintf('<div class="notice notice-%s rsvpmaker-notice is-dismissible" data-notice="%s">
<p>%s</p>
</div>',esc_attr($type),esc_attr($slug),$message);
}

/**
 * AJAX handler to store the state of dismissible notices.
 */
function rsvpmaker_ajax_notice_handler() {
$cleared = get_option('cleared_rsvpmaker_notices');
$cleared = is_array($cleared) ? $cleared : array();
    // Pick up the notice "type" - passed via jQuery (the "data-notice" attribute on the notice)
    $cleared[] = sanitize_text_field($_REQUEST['type']);
    update_option('cleared_rsvpmaker_notices',$cleared);
}

function rsvpmaker_debug_log($msg, $label = '', $filename_base = '') {
	global $rsvp_options;
		if(empty($rsvp_options["debug"]))
			return;
		if(empty($filename_base))
			$filename_base = 'rsvpmaker';
			
	if(!is_string($msg))
		$msg = var_export($msg,true);
	if(!empty($label))
		$msg = $label.":\n".$msg;
	$upload_dir   = wp_upload_dir();
	 
	if ( ! empty( $upload_dir['basedir'] ) ) {
		$fname = $upload_dir['basedir'].'/'.$filename_base.'_log_'.date('Y-m-d').'.txt';
		file_put_contents($fname, date('r')."\n".$msg."\n\n", FILE_APPEND);
		//clean old logs
		$oldlog = $upload_dir['basedir'].'/'.$filename_base.'_log_'.date('Y-m-d',time() - 172800).'.txt';
		if (file_exists($oldlog)) {
			unlink($oldlog);
		}
	}
}
	

function rsvpmaker_map_meta_cap( $caps, $cap, $user_id, $args ) {
    if (!empty($args[0]) && ( 'edit_post' == $cap || strpos($cap,'rsvpmaker') ) )
    {
        global $wpdb;
		$post_id = $args[0];
		$author = $wpdb->get_var("SELECT post_author FROM $wpdb->posts WHERE ID=".intval($post_id));
		$eds = get_additional_editors($post_id);
		if(!current_user_can($cap[0]) && ($author != $user_id) && in_array($user_id, $eds) )
        {
            /* Set an empty array for the caps. */
            $caps = array(); 
            $caps[] = 'edit_rsvpmakers';
			if(isset($_GET['action']) && ($_GET['action'] == 'edit'))
			{
			//if the current author is not already on the editors list, add them
			if(!$wpdb->get_var("SELECT meta_id FROM $wpdb->postmeta WHERE post_id=$post_id AND meta_key='_additional_editors' AND meta_value=$author"))
				add_post_meta($post_id, '_additional_editors',$author);				
			wp_update_post(array('ID' => $post_id, 'post_author' => $user_id));
			}
        }
    }
    /* Return the capabilities required by the user. */
    return $caps;
}

function auto_renew_project ($template_id, $notify = true) {
global $rsvp_options;

$sofar = get_events_by_template($template_id);
$fts = 0;
if(!empty($sofar))
{
	$farthest = array_pop($sofar);
	$fts = rsvpmaker_strtotime($farthest->datetime);
}
if($fts > (time() + (2 * MONTH_IN_SECONDS)) )
	return; // cancel if more than 2 months worth of events in system
$sked = get_template_sked($template_id);
$hour = str_pad($sked['hour'],2,'0',STR_PAD_LEFT);
$minutes = str_pad($sked['minutes'],2,'0',STR_PAD_LEFT);
	
//printf('<pre>%s</pre>',var_export($sked,true));
if(!isset($sked["week"]))
	return;
$added = ($fts) ? sprintf('<p>In addition to previously published dates ending %s</p>',rsvpmaker_date($rsvp_options['long_date'],$fts))."\n" : '';
$projected = rsvpmaker_get_projected($sked);
if($projected)
foreach($projected as $i => $ts)
{
if(($ts < current_time('timestamp')))
	continue; // omit dates past
if(isset($fts) && $ts <= $fts)
	continue;
$post = get_post($template_id);
$date = rsvpmaker_date('Y-m-d',$ts).' '.$hour.':'.$minutes.':00';
$added .= add_rsvpmaker_from_template($post, $sked, $date, $ts);
} // end for loop

if($notify && !empty($added))
	{
		$admin = get_option('admin_email');
		$mail['subject'] = __('Dates added for '.$post->post_title,'rsvpmaker');
		$mail['html'] = "<p>Dates added according to recurring event schedule.</p>\n".$added;
		$mail['to'] = $admin;
		$mail['from'] = $admin;
		$mail['fromname'] = get_bloginfo('name');
		rsvpmailer($mail);
	}
}

function add_rsvpmaker_from_template($post, $template, $date, $ts) {
	global $wpdb, $rsvp_options;
	if($post->post_status != 'publish')
		return;
	$t = $post->ID;
	$my_post['post_title'] = $post->post_title;
	$my_post['post_content'] = $post->post_content;
	$my_post['post_status'] = 'publish';
	$my_post['post_author'] = $post->post_author;
	$my_post['post_type'] = 'rsvpmaker';
			if(empty($template["duration"]))
				$template["duration"] = '';			
			$dpart = explode(':',$template["duration"]);
			
			if( is_numeric($dpart[0]) )
				{
				$dtext = $cddate.' +'.$dpart[0].' hours';
				if(!empty($dpart[1]))
					$dtext .= ' +'.$dpart[1].' minutes';
				$dt = rsvpmaker_strtotime($dtext);
				$duration = rsvpmaker_date('Y-m-d H:i:s',$dt);
				}
			else
				$duration = (isset($template["duration"])) ? $template["duration"] : '';
			$my_post['post_name'] = sanitize_title($my_post['post_title'] . '-' .$date );
  			$added = '';
			if($post_id = wp_insert_post( $my_post ) )
				{
				$prettydate = rsvpmaker_date($rsvp_options['long_date'],$ts);
				$added = sprintf('<p>%s <a href="%s">%s</a> / <a href="%s">%s</a> / <a href="%s">%s</a> </p>',$prettydate,get_permalink($post_id),__('View','rsvpmaker'),admin_url("post.php?post=$post_id&action=edit"),__('Edit','rsvpmaker'),admin_url("edit.php?post_type=rsvpmaker&page=rsvpmaker_details&post_id=$post_id&trash=1"),__('Trash','rsvpmaker'));
				add_rsvpmaker_date($post_id,$date,$duration);				
				add_post_meta($post_id,'_meet_recur',$t,true);
				$upts = $wpdb->get_var("SELECT post_modified from $wpdb->posts WHERE ID=".$post_id);
				update_post_meta($post_id,"_updated_from_template",$upts);
				rsvpmaker_copy_metadata($t, $post_id);
				rsvpmaker_update_event_row($post_id);
			}
	return $added;
}

function rsvpautorenew_test () {
global $rsvp_options;
	
	global $wpdb;
	$wpdb->show_errors();

	$sql = "SELECT * FROM $wpdb->posts JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id WHERE meta_key='rsvpautorenew' AND meta_value=1 AND $wpdb->posts.post_status='publish' ";
	$results = $wpdb->get_results($sql);
	if(is_array($results))
	foreach($results as $row)
	{
		auto_renew_project ($row->ID);
	}
	$sql = "SELECT * FROM $wpdb->posts JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id WHERE meta_key='rsvpmaker_template_reminder' ";
	$results = $wpdb->get_results($sql);
	if(is_array($results))
	foreach($results as $row)
	{		
		$thours = unserialize($row->meta_value);
		$next = rsvpmaker_next_by_template($row->ID);
		if(empty($next))
			return;
		$message = get_post_meta($next->ID, '_rsvp_reminder_msg_'.$thours[0], true);
		if(!empty($message))
			continue; // already set
		$start_time = rsvpmaker_strtotime($next->datetime);
		$prettydate = rsvpmaker_date('l F jS g:i A T',rsvpmaker_strtotime($next->datetime));
		$include_event = get_post_meta($row->ID, '_rsvp_confirmation_include_event', true);
		update_post_meta($next->ID, '_rsvp_confirmation_include_event',$include_event);
		foreach($thours as $hours) {
			$message = get_post_meta($row->ID, '_rsvp_reminder_msg_'.$hours, true);
			$subject = get_post_meta($row->ID, '_rsvp_reminder_subject_'.$hours, true);
			$subject = str_replace('[datetime]',$prettydate,$subject);
			update_post_meta($next->ID, '_rsvp_reminder_msg_'.$hours,$message);
			update_post_meta($next->ID, '_rsvp_reminder_subject_'.$hours,$subject);
			rsvpmaker_reminder_cron($hours, $start_time, $next->ID);
		}
	}
}

function rsvpmaker_template_checkbox_post () {

if(empty($_POST) || empty($_REQUEST['t']) || empty($_REQUEST['page']) || ($_REQUEST['page'] != 'rsvpmaker_template_list'))
	return;
global $wpdb, $current_user;
$t = (int) $_REQUEST['t'];
$post = get_post($t);
$template = $sked = get_template_sked($t);
$template['hour'] = (int) $template['hour'];
if($template['hour'] < 10)
	$template['hour'] = $sked['hour'] = '0'.$template['hour']; // make sure of zero padding
$hour = $sked['hour'];
$minutes = $sked['minutes'];
$update_messages = '';
	
if(!empty($_POST['trash_template']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {
	foreach($_POST['trash_template'] as $id)
		wp_trash_post((int) $id);
	$count = sizeof($_POST['trash_template']);
	$update_messages = '<div class="updated">'.$count.' '.__('event posts moved to trash','rsvpmaker').'</div>';
}

if(isset($_POST["timechange"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	{
		foreach($_POST["timechange"] as $id => $time)
			update_post_meta($id,'_rsvp_dates',sanitize_text_field($time));
		delete_transient('rsvpmakerdates');
	}

if(isset($_POST["update_from_template"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	{
		foreach($_POST["update_from_template"] as $target_id)
			{
				$target_id = (int) $target_id;
				if(!current_user_can('publish_rsvpmakers'))
					{
						$update_messages .= '<div class="updated">Error</div>';
						break;
					}
				$update_post['ID'] = $target_id;
				$update_post['post_title'] = $post->post_title;
				$update_post['post_content'] = $post->post_content;
				wp_update_post($update_post);
				rsvpmaker_copy_metadata($t, $target_id);
				$ts = $wpdb->get_var("SELECT post_modified from $wpdb->posts WHERE ID=".$target_id);
				update_post_meta($target_id,"_updated_from_template",$ts);
				$duration = (empty($template["duration"])) ? '' : $template["duration"];
				$end_time = (empty($template['end'])) ? '' : $template['end'];
				$cddate = get_rsvpmaker_meta($target_id,'_rsvp_dates',true);
				if(!empty($cddate))
					{
					$parts = explode(' ',$cddate);
					$cddate = $parts[0].' '.$template['hour'].':'.$template['minutes'].':00';		
					update_rsvpmaker_date($target_id,$cddate,$duration,$end_time);				
					}
				if(isset($rsvptypes))
					wp_set_object_terms( $target_id, $rsvptypes, 'rsvpmaker-type', true );

				$update_messages .= '<div class="updated">Updated: event #'.$target_id.' <a href="post.php?action=edit&post='.$target_id.'">Edit</a> / <a href="'.get_post_permalink($target_id).'">View</a></div>';	
			}
	}

if(isset($_POST["detach_from_template"])  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
{
	if(!current_user_can('publish_rsvpmakers'))
	{
		$update_messages .= '<div class="updated">Error</div>';
	}
	else
	foreach($_POST["detach_from_template"] as $target_id)
		{
			$target_id = (int) $target_id;
			$sql = $wpdb->prepare("UPDATE $wpdb->postmeta SET meta_key='_detached_from_template' WHERE meta_key='_meet_recur' AND post_id=%d", $target_id);
			$result = $wpdb->query($sql);
			$update_messages .= '<div class="updated">Detached from Template: event #'.$target_id.' <a href="post.php?action=edit&post='.$target_id.'">Edit</a> / <a href="'.get_post_permalink($target_id).'">View</a></div>';	
		}
}


if(isset($_POST["recur_check"])  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
{

	$my_post['post_title'] = $post->post_title;
	$my_post['post_content'] = $post->post_content;
	$my_post['post_status'] = (($_POST['newstatus'] == 'publish') && current_user_can('publish_rsvpmakers')) ? 'publish' : 'draft';
	$my_post['post_author'] = $current_user->ID;
	$my_post['post_type'] = 'rsvpmaker';

	foreach($_POST["recur_check"] as $index => $on)
		{
			$year = $y = (int) $_POST["recur_year"][$index];
			$cddate = format_cddate($year, sanitize_text_field($_POST["recur_month"][$index]), sanitize_text_field($_POST["recur_day"][$index]), $hour, $minutes);
			$m = (int) $_POST["recur_month"][$index];
			$d = (int) $_POST["recur_day"][$index];
			if($m < 10) $m = '0'.$m;
			$d = (int) $_POST["recur_day"][$index];
			if($d < 10) $d = '0'.$d;
			$date = $y.'-'.$m.'-'.$d;
			if(empty($template["duration"]))
				$template["duration"] = '';			
			$dpart = explode(':',$template["duration"]);
			
			if( is_numeric($dpart[0]) )
				{
				$dtext = $cddate.' +'.$dpart[0].' hours';
				if(!empty($dpart[1]))
					$dtext .= ' +'.$dpart[1].' minutes';
				$dt = rsvpmaker_strtotime($dtext);
				$duration = rsvpmaker_date('Y-m-d H:i:s',$dt);
				}
			else{
				$duration = (isset($template["duration"])) ? $template["duration"] : '';
			}
			
			if(!empty($_POST["recur_title"][$index])  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
				$my_post['post_title'] = sanitize_text_field($_POST["recur_title"][$index]);

			$my_post['post_name'] = $my_post['post_title'] . '-' .$date;
			$singular = __('Event','rsvpmaker');
// Insert the post into the database
  			if($post_id = wp_insert_post( $my_post ) )
				{
				$end_time = (empty($template['end'])) ? '' : $template['end'];	
				update_rsvpmaker_date($post_id,$cddate,$duration,$end_time);
				$timezone = rsvpmaker_get_timezone_string($t);
				rsvpmaker_add_event_row ($post_id, $cddate, $end_time, $duration,$timezone,$my_post['post_title']);
				if($my_post["post_status"] == 'publish')
					$update_messages .=  '<div class="updated">Posted: event for '.$cddate.' <a href="post.php?action=edit&post='.$post_id.'">Edit</a> / <a href="'.get_post_permalink($post_id).'">View</a></div>';
				else
					$update_messages .= '<div class="updated">Draft for '.$cddate.' <a href="post.php?action=edit&post='.$post_id.'">Edit</a> / <a href="'.get_post_permalink($post_id).'">Preview</a></div>';
				
				add_post_meta($post_id,'_meet_recur',$t,true);
				$ts = $wpdb->get_var("SELECT post_modified from $wpdb->posts WHERE ID=".$post_id);
				update_post_meta($post_id,"_updated_from_template",$ts);
				rsvpmaker_copy_metadata($t, $post_id);
				rsvpmaker_update_event_row($post_id);
				}
		
		}
}

if(isset($_POST["nomeeting"])  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key'))  )
{
	$my_post['post_title'] = __('No Meeting','rsvpmaker').': '.$post->post_title;
	$my_post['post_content'] = sanitize_textarea_field($_POST["nomeeting_note"]);
	$my_post['post_status'] = current_user_can('publish_rsvpmakers') ? 'publish' : 'draft';
	$my_post['post_author'] = $current_user->ID;
	$my_post['post_type'] = 'rsvpmaker';
	
	if(!strpos($_POST["nomeeting"],'-'))
		{ //update vs new post
			$id = (int) $_POST["nomeeting"];
			$sql = $wpdb->prepare("UPDATE $wpdb->posts SET post_title=%s, post_content=%s WHERE ID=%d",$my_post['post_title'],$my_post['post_content'],$id);
			$wpdb->show_errors();
			$return = $wpdb->query($sql);
			if($return == false)
				$update_messages .= '<div class="updated">'."Error: $sql.</div>\n";
			else
				$update_messages .=  '<div class="updated">Updated: no meeting <a href="post.php?action=edit&post='.$post_id.'">Edit</a> / <a href="'.get_post_permalink($id).'">View</a></div>';	
		}
	else
		{
			$cddate = sanitize_text_field($_POST["nomeeting"]).' 00:00:00';
			$my_post['post_name'] = $my_post['post_title'] . '-' .$cddate;

// Insert the post into the database
  			if($post_id = wp_insert_post( $my_post ) )
				{
				add_rsvpmaker_date($post_id,$cddate,'allday');
				$update_messages .=  '<div class="updated">Posted: event for '.$cddate.' <a href="post.php?action=edit&post='.$post_id.'">Edit</a> / <a href="'.get_post_permalink($post_id).'">View</a></div>';	
				add_post_meta($post_id,'_meet_recur',$t,true);
				rsvpmaker_update_event_row($post_id);
				}
		}		
}
	update_post_meta($t,'update_messages',$update_messages);
	header('Location: ' . admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&update_messages=1&t='.$t));
	die();
}

function rsvpmaker_copy_metadata($source_id, $target_id) {
global $wpdb;
$log = '';
//copy metadata
$meta_keys = array();
$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$source_id");
$post_meta_infos = apply_filters('rsvpmaker_meta_update_from_template',$post_meta_infos);

		if (count($post_meta_infos)!=0) {
			foreach ($post_meta_infos as $meta_info) {
				$meta_key = $meta_info->meta_key;
				if(in_array($meta_key,$meta_keys))
					continue;
				$meta_keys[] = $meta_key;
				$meta_protect = array('_rsvp_reminder', '_sked', '_edit_lock','_additional_editors','rsvpautorenew','_meet_recur','_rsvp_dates');
				if(in_array($meta_key, $meta_protect) || strpos($meta_key,'sked') )
				{
					$log .= 'Skip '.$meta_key.'<br />';
					continue;					
				}
				elseif(strpos($meta_key,'_note') || preg_match('/^_[A-Z]/',$meta_key) ) //agenda note or any other note
					{
						$log .= 'Skip '.$meta_key.'<br />';
						continue;	
					}
				else
				{
					$log .= 'Copy '.$meta_key.': '.$meta_info->meta_value.'<br />';			
				}
				if(is_serialized($meta_info->meta_value))
					update_post_meta($target_id,$meta_key,unserialize($meta_info->meta_value));
				else
					update_post_meta($target_id,$meta_key,$meta_info->meta_value);
				if($meta_key == '_rsvp_deadline_daysbefore')
					$deadlinedays = $meta_info->meta_value;		
				if($meta_key == '_rsvp_deadline_hours')
					$deadlinehours = $meta_info->meta_value;		
				if($meta_key == '_rsvp_reg_daysbefore')
					$regdays = $meta_info->meta_value;		
				if($meta_key == '_rsvp_reg_hours')
					$reghours = $meta_info->meta_value;		
			}
		}

if(!empty($deadlinedays) || !empty($deadlinehours))
	rsvpmaker_deadline_from_template($target_id,$deadlinedays,$deadlinehours);
if(!empty($regdays) || !empty($reghours))
	rsvpmaker_reg_from_template($target_id,$regdays,$reghours);

$terms = get_the_terms( $source_id, 'rsvpmaker-type' );						
if ( $terms && ! is_wp_error( $terms ) ) { 
	$rsvptypes = array();

	foreach ( $terms as $term ) {
		$rsvptypes[] = $term->term_id;
	}
wp_set_object_terms( $target_id, $rsvptypes, 'rsvpmaker-type', true );

	} // if terms

}

function rsvpmaker_deadline_from_template($target_id,$deadlinedays,$deadlinehours) {
	$t = get_rsvpmaker_timestamp($target_id);
	if(!empty($deadlinedays))
		$t -= ($deadlinedays * 60 * 60 * 24);
	if(!empty($deadlinehours))
		$t -= ($deadlinehours * 60 * 60);
	update_post_meta($target_id,'_rsvp_deadline',$t);
}
function rsvpmaker_reg_from_template($target_id,$days,$hours) {
	$t = get_rsvpmaker_timestamp($target_id);
	if(!empty($days))
		$t -= ($days * 60 * 60 * 24);
	if(!empty($hours))
		$t -= ($hours * 60 * 60);
	update_post_meta($target_id,'_rsvp_start',$t);
}

function rsvp_time_options ($post_id) {
global $rsvp_options;
$forms = rsvpmaker_get_forms();
if(empty($post_id))
{
	$icons = $rsvp_options["calendar_icons"];
	$add_timezone = $rsvp_options["add_timezone"];
	$convert_timezone = $rsvp_options["convert_timezone"];
	$rsvp_timezone = '';
}
else {
	$icons = get_rsvpmaker_meta($post_id,"_calendar_icons",true);
	$add_timezone = get_rsvpmaker_meta($post_id,"_add_timezone",true);
	$convert_timezone = get_rsvpmaker_meta($post_id,"_convert_timezone",true);
	$rsvp_timezone = get_rsvpmaker_meta($post_id,"_rsvp_timezone_string",true);	
}
if(isset($_GET['page']) && ( ($_GET['page'] == 'rsvpmaker_details') ) )
{
?>
<input type="checkbox" name="calendar_icons" value="1" <?php if($icons) echo ' checked="checked" ';?> /> <?php esc_html_e('Show Add to Google / Download to Outlook (iCal) icons','rsvpmaker'); ?> 
<br />
<p id="timezone_options">
<?php
if(!strpos($rsvp_options["time_format"],'T') )
{
?>
<input type="checkbox" name="add_timezone" value="1" <?php if($add_timezone) echo ' checked="checked" '; ?> /><?php esc_html_e('Display timezone code as part of date/time','rsvpmaker'); echo ' '; ?>
<?php
}
?>
<input type="checkbox" name="convert_timezone" value="1" <?php if($convert_timezone) echo ' checked="checked" '; ?> /><?php esc_html_e('Show timezone conversion button next to calendar icons','rsvpmaker'); ?>
</p>
<p>Timezone <select id="timezone_string" name="setrsvp[timezone_string]">
	<option value="<?php echo esc_attr($rsvp_timezone);?>"><?php echo (empty($rsvp_timezone)) ? __('Default','rsvpmaker') : $rsvp_timezone?></option>
<optgroup label="U.S. Mainland">
<option value="America/New_York">New York</option>
<option value="America/Chicago">Chicago</option>
<option value="America/Denver">Denver</option>
<option value="America/Los_Angeles">Los Angeles</option>
</optgroup>
<optgroup label="Africa">
<option value="Africa/Abidjan">Abidjan</option>
<option value="Africa/Accra">Accra</option>
<option value="Africa/Addis_Ababa">Addis Ababa</option>
<option value="Africa/Algiers">Algiers</option>
<option value="Africa/Asmara">Asmara</option>
<option value="Africa/Bamako">Bamako</option>
<option value="Africa/Bangui">Bangui</option>
<option value="Africa/Banjul">Banjul</option>
<option value="Africa/Bissau">Bissau</option>
<option value="Africa/Blantyre">Blantyre</option>
<option value="Africa/Brazzaville">Brazzaville</option>
<option value="Africa/Bujumbura">Bujumbura</option>
<option value="Africa/Cairo">Cairo</option>
<option value="Africa/Casablanca">Casablanca</option>
<option value="Africa/Ceuta">Ceuta</option>
<option value="Africa/Conakry">Conakry</option>
<option value="Africa/Dakar">Dakar</option>
<option value="Africa/Dar_es_Salaam">Dar es Salaam</option>
<option value="Africa/Djibouti">Djibouti</option>
<option value="Africa/Douala">Douala</option>
<option value="Africa/El_Aaiun">El Aaiun</option>
<option value="Africa/Freetown">Freetown</option>
<option value="Africa/Gaborone">Gaborone</option>
<option value="Africa/Harare">Harare</option>
<option value="Africa/Johannesburg">Johannesburg</option>
<option value="Africa/Juba">Juba</option>
<option value="Africa/Kampala">Kampala</option>
<option value="Africa/Khartoum">Khartoum</option>
<option value="Africa/Kigali">Kigali</option>
<option value="Africa/Kinshasa">Kinshasa</option>
<option value="Africa/Lagos">Lagos</option>
<option value="Africa/Libreville">Libreville</option>
<option value="Africa/Lome">Lome</option>
<option value="Africa/Luanda">Luanda</option>
<option value="Africa/Lubumbashi">Lubumbashi</option>
<option value="Africa/Lusaka">Lusaka</option>
<option value="Africa/Malabo">Malabo</option>
<option value="Africa/Maputo">Maputo</option>
<option value="Africa/Maseru">Maseru</option>
<option value="Africa/Mbabane">Mbabane</option>
<option value="Africa/Mogadishu">Mogadishu</option>
<option value="Africa/Monrovia">Monrovia</option>
<option value="Africa/Nairobi">Nairobi</option>
<option value="Africa/Ndjamena">Ndjamena</option>
<option value="Africa/Niamey">Niamey</option>
<option value="Africa/Nouakchott">Nouakchott</option>
<option value="Africa/Ouagadougou">Ouagadougou</option>
<option value="Africa/Porto-Novo">Porto-Novo</option>
<option value="Africa/Sao_Tome">Sao Tome</option>
<option value="Africa/Tripoli">Tripoli</option>
<option value="Africa/Tunis">Tunis</option>
<option value="Africa/Windhoek">Windhoek</option>
</optgroup>
<optgroup label="America">
<option value="America/Adak">Adak</option>
<option value="America/Anchorage">Anchorage</option>
<option value="America/Anguilla">Anguilla</option>
<option value="America/Antigua">Antigua</option>
<option value="America/Araguaina">Araguaina</option>
<option value="America/Argentina/Buenos_Aires">Argentina - Buenos Aires</option>
<option value="America/Argentina/Catamarca">Argentina - Catamarca</option>
<option value="America/Argentina/Cordoba">Argentina - Cordoba</option>
<option value="America/Argentina/Jujuy">Argentina - Jujuy</option>
<option value="America/Argentina/La_Rioja">Argentina - La Rioja</option>
<option value="America/Argentina/Mendoza">Argentina - Mendoza</option>
<option value="America/Argentina/Rio_Gallegos">Argentina - Rio Gallegos</option>
<option value="America/Argentina/Salta">Argentina - Salta</option>
<option value="America/Argentina/San_Juan">Argentina - San Juan</option>
<option value="America/Argentina/San_Luis">Argentina - San Luis</option>
<option value="America/Argentina/Tucuman">Argentina - Tucuman</option>
<option value="America/Argentina/Ushuaia">Argentina - Ushuaia</option>
<option value="America/Aruba">Aruba</option>
<option value="America/Asuncion">Asuncion</option>
<option value="America/Atikokan">Atikokan</option>
<option value="America/Bahia">Bahia</option>
<option value="America/Bahia_Banderas">Bahia Banderas</option>
<option value="America/Barbados">Barbados</option>
<option value="America/Belem">Belem</option>
<option value="America/Belize">Belize</option>
<option value="America/Blanc-Sablon">Blanc-Sablon</option>
<option value="America/Boa_Vista">Boa Vista</option>
<option value="America/Bogota">Bogota</option>
<option value="America/Boise">Boise</option>
<option value="America/Cambridge_Bay">Cambridge Bay</option>
<option value="America/Campo_Grande">Campo Grande</option>
<option value="America/Cancun">Cancun</option>
<option value="America/Caracas">Caracas</option>
<option value="America/Cayenne">Cayenne</option>
<option value="America/Cayman">Cayman</option>
<option value="America/Chicago">Chicago</option>
<option value="America/Chihuahua">Chihuahua</option>
<option value="America/Costa_Rica">Costa Rica</option>
<option value="America/Creston">Creston</option>
<option value="America/Cuiaba">Cuiaba</option>
<option value="America/Curacao">Curacao</option>
<option value="America/Danmarkshavn">Danmarkshavn</option>
<option value="America/Dawson">Dawson</option>
<option value="America/Dawson_Creek">Dawson Creek</option>
<option value="America/Denver">Denver</option>
<option value="America/Detroit">Detroit</option>
<option value="America/Dominica">Dominica</option>
<option value="America/Edmonton">Edmonton</option>
<option value="America/Eirunepe">Eirunepe</option>
<option value="America/El_Salvador">El Salvador</option>
<option value="America/Fortaleza">Fortaleza</option>
<option value="America/Glace_Bay">Glace Bay</option>
<option value="America/Godthab">Godthab</option>
<option value="America/Goose_Bay">Goose Bay</option>
<option value="America/Grand_Turk">Grand Turk</option>
<option value="America/Grenada">Grenada</option>
<option value="America/Guadeloupe">Guadeloupe</option>
<option value="America/Guatemala">Guatemala</option>
<option value="America/Guayaquil">Guayaquil</option>
<option value="America/Guyana">Guyana</option>
<option value="America/Halifax">Halifax</option>
<option value="America/Havana">Havana</option>
<option value="America/Hermosillo">Hermosillo</option>
<option value="America/Indiana/Indianapolis">Indiana - Indianapolis</option>
<option value="America/Indiana/Knox">Indiana - Knox</option>
<option value="America/Indiana/Marengo">Indiana - Marengo</option>
<option value="America/Indiana/Petersburg">Indiana - Petersburg</option>
<option value="America/Indiana/Tell_City">Indiana - Tell City</option>
<option value="America/Indiana/Vevay">Indiana - Vevay</option>
<option value="America/Indiana/Vincennes">Indiana - Vincennes</option>
<option value="America/Indiana/Winamac">Indiana - Winamac</option>
<option value="America/Inuvik">Inuvik</option>
<option value="America/Iqaluit">Iqaluit</option>
<option value="America/Jamaica">Jamaica</option>
<option value="America/Juneau">Juneau</option>
<option value="America/Kentucky/Louisville">Kentucky - Louisville</option>
<option value="America/Kentucky/Monticello">Kentucky - Monticello</option>
<option value="America/Kralendijk">Kralendijk</option>
<option value="America/La_Paz">La Paz</option>
<option value="America/Lima">Lima</option>
<option value="America/Los_Angeles">Los Angeles</option>
<option value="America/Lower_Princes">Lower Princes</option>
<option value="America/Maceio">Maceio</option>
<option value="America/Managua">Managua</option>
<option value="America/Manaus">Manaus</option>
<option value="America/Marigot">Marigot</option>
<option value="America/Martinique">Martinique</option>
<option value="America/Matamoros">Matamoros</option>
<option value="America/Mazatlan">Mazatlan</option>
<option value="America/Menominee">Menominee</option>
<option value="America/Merida">Merida</option>
<option value="America/Metlakatla">Metlakatla</option>
<option value="America/Mexico_City">Mexico City</option>
<option value="America/Miquelon">Miquelon</option>
<option value="America/Moncton">Moncton</option>
<option value="America/Monterrey">Monterrey</option>
<option value="America/Montevideo">Montevideo</option>
<option value="America/Montserrat">Montserrat</option>
<option value="America/Nassau">Nassau</option>
<option value="America/New_York">New York</option>
<option value="America/Nipigon">Nipigon</option>
<option value="America/Nome">Nome</option>
<option value="America/Noronha">Noronha</option>
<option value="America/North_Dakota/Beulah">North Dakota - Beulah</option>
<option value="America/North_Dakota/Center">North Dakota - Center</option>
<option value="America/North_Dakota/New_Salem">North Dakota - New Salem</option>
<option value="America/Ojinaga">Ojinaga</option>
<option value="America/Panama">Panama</option>
<option value="America/Pangnirtung">Pangnirtung</option>
<option value="America/Paramaribo">Paramaribo</option>
<option value="America/Phoenix">Phoenix</option>
<option value="America/Port-au-Prince">Port-au-Prince</option>
<option value="America/Port_of_Spain">Port of Spain</option>
<option value="America/Porto_Velho">Porto Velho</option>
<option value="America/Puerto_Rico">Puerto Rico</option>
<option value="America/Rainy_River">Rainy River</option>
<option value="America/Rankin_Inlet">Rankin Inlet</option>
<option value="America/Recife">Recife</option>
<option value="America/Regina">Regina</option>
<option value="America/Resolute">Resolute</option>
<option value="America/Rio_Branco">Rio Branco</option>
<option value="America/Santa_Isabel">Santa Isabel</option>
<option value="America/Santarem">Santarem</option>
<option value="America/Santiago">Santiago</option>
<option value="America/Santo_Domingo">Santo Domingo</option>
<option value="America/Sao_Paulo">Sao Paulo</option>
<option value="America/Scoresbysund">Scoresbysund</option>
<option value="America/Sitka">Sitka</option>
<option value="America/St_Barthelemy">St Barthelemy</option>
<option value="America/St_Johns">St Johns</option>
<option value="America/St_Kitts">St Kitts</option>
<option value="America/St_Lucia">St Lucia</option>
<option value="America/St_Thomas">St Thomas</option>
<option value="America/St_Vincent">St Vincent</option>
<option value="America/Swift_Current">Swift Current</option>
<option value="America/Tegucigalpa">Tegucigalpa</option>
<option value="America/Thule">Thule</option>
<option value="America/Thunder_Bay">Thunder Bay</option>
<option value="America/Tijuana">Tijuana</option>
<option value="America/Toronto">Toronto</option>
<option value="America/Tortola">Tortola</option>
<option value="America/Vancouver">Vancouver</option>
<option value="America/Whitehorse">Whitehorse</option>
<option value="America/Winnipeg">Winnipeg</option>
<option value="America/Yakutat">Yakutat</option>
<option value="America/Yellowknife">Yellowknife</option>
</optgroup>
<optgroup label="Antarctica">
<option value="Antarctica/Casey">Casey</option>
<option value="Antarctica/Davis">Davis</option>
<option value="Antarctica/DumontDUrville">DumontDUrville</option>
<option value="Antarctica/Macquarie">Macquarie</option>
<option value="Antarctica/Mawson">Mawson</option>
<option value="Antarctica/McMurdo">McMurdo</option>
<option value="Antarctica/Palmer">Palmer</option>
<option value="Antarctica/Rothera">Rothera</option>
<option value="Antarctica/Syowa">Syowa</option>
<option value="Antarctica/Troll">Troll</option>
<option value="Antarctica/Vostok">Vostok</option>
</optgroup>
<optgroup label="Arctic">
<option value="Arctic/Longyearbyen">Longyearbyen</option>
</optgroup>
<optgroup label="Asia">
<option value="Asia/Aden">Aden</option>
<option value="Asia/Almaty">Almaty</option>
<option value="Asia/Amman">Amman</option>
<option value="Asia/Anadyr">Anadyr</option>
<option value="Asia/Aqtau">Aqtau</option>
<option value="Asia/Aqtobe">Aqtobe</option>
<option value="Asia/Ashgabat">Ashgabat</option>
<option value="Asia/Baghdad">Baghdad</option>
<option value="Asia/Bahrain">Bahrain</option>
<option value="Asia/Baku">Baku</option>
<option value="Asia/Bangkok">Bangkok</option>
<option value="Asia/Beirut">Beirut</option>
<option value="Asia/Bishkek">Bishkek</option>
<option value="Asia/Brunei">Brunei</option>
<option value="Asia/Chita">Chita</option>
<option value="Asia/Choibalsan">Choibalsan</option>
<option value="Asia/Colombo">Colombo</option>
<option value="Asia/Damascus">Damascus</option>
<option value="Asia/Dhaka">Dhaka</option>
<option value="Asia/Dili">Dili</option>
<option value="Asia/Dubai">Dubai</option>
<option value="Asia/Dushanbe">Dushanbe</option>
<option value="Asia/Gaza">Gaza</option>
<option value="Asia/Hebron">Hebron</option>
<option value="Asia/Ho_Chi_Minh">Ho Chi Minh</option>
<option value="Asia/Hong_Kong">Hong Kong</option>
<option value="Asia/Hovd">Hovd</option>
<option value="Asia/Irkutsk">Irkutsk</option>
<option value="Asia/Jakarta">Jakarta</option>
<option value="Asia/Jayapura">Jayapura</option>
<option value="Asia/Jerusalem">Jerusalem</option>
<option value="Asia/Kabul">Kabul</option>
<option value="Asia/Kamchatka">Kamchatka</option>
<option value="Asia/Karachi">Karachi</option>
<option value="Asia/Kathmandu">Kathmandu</option>
<option value="Asia/Khandyga">Khandyga</option>
<option value="Asia/Kolkata">Kolkata</option>
<option value="Asia/Krasnoyarsk">Krasnoyarsk</option>
<option value="Asia/Kuala_Lumpur">Kuala Lumpur</option>
<option value="Asia/Kuching">Kuching</option>
<option value="Asia/Kuwait">Kuwait</option>
<option value="Asia/Macau">Macau</option>
<option value="Asia/Magadan">Magadan</option>
<option value="Asia/Makassar">Makassar</option>
<option value="Asia/Manila">Manila</option>
<option value="Asia/Muscat">Muscat</option>
<option value="Asia/Nicosia">Nicosia</option>
<option value="Asia/Novokuznetsk">Novokuznetsk</option>
<option value="Asia/Novosibirsk">Novosibirsk</option>
<option value="Asia/Omsk">Omsk</option>
<option value="Asia/Oral">Oral</option>
<option value="Asia/Phnom_Penh">Phnom Penh</option>
<option value="Asia/Pontianak">Pontianak</option>
<option value="Asia/Pyongyang">Pyongyang</option>
<option value="Asia/Qatar">Qatar</option>
<option value="Asia/Qyzylorda">Qyzylorda</option>
<option value="Asia/Rangoon">Rangoon</option>
<option value="Asia/Riyadh">Riyadh</option>
<option value="Asia/Sakhalin">Sakhalin</option>
<option value="Asia/Samarkand">Samarkand</option>
<option value="Asia/Seoul">Seoul</option>
<option value="Asia/Shanghai">Shanghai</option>
<option value="Asia/Singapore">Singapore</option>
<option value="Asia/Srednekolymsk">Srednekolymsk</option>
<option value="Asia/Taipei">Taipei</option>
<option value="Asia/Tashkent">Tashkent</option>
<option value="Asia/Tbilisi">Tbilisi</option>
<option value="Asia/Tehran">Tehran</option>
<option value="Asia/Thimphu">Thimphu</option>
<option value="Asia/Tokyo">Tokyo</option>
<option value="Asia/Ulaanbaatar">Ulaanbaatar</option>
<option value="Asia/Urumqi">Urumqi</option>
<option value="Asia/Ust-Nera">Ust-Nera</option>
<option value="Asia/Vientiane">Vientiane</option>
<option value="Asia/Vladivostok">Vladivostok</option>
<option value="Asia/Yakutsk">Yakutsk</option>
<option value="Asia/Yekaterinburg">Yekaterinburg</option>
<option value="Asia/Yerevan">Yerevan</option>
</optgroup>
<optgroup label="Atlantic">
<option value="Atlantic/Azores">Azores</option>
<option value="Atlantic/Bermuda">Bermuda</option>
<option value="Atlantic/Canary">Canary</option>
<option value="Atlantic/Cape_Verde">Cape Verde</option>
<option value="Atlantic/Faroe">Faroe</option>
<option value="Atlantic/Madeira">Madeira</option>
<option value="Atlantic/Reykjavik">Reykjavik</option>
<option value="Atlantic/South_Georgia">South Georgia</option>
<option value="Atlantic/Stanley">Stanley</option>
<option value="Atlantic/St_Helena">St Helena</option>
</optgroup>
<optgroup label="Australia">
<option value="Australia/Adelaide">Adelaide</option>
<option value="Australia/Brisbane">Brisbane</option>
<option value="Australia/Broken_Hill">Broken Hill</option>
<option value="Australia/Currie">Currie</option>
<option value="Australia/Darwin">Darwin</option>
<option value="Australia/Eucla">Eucla</option>
<option value="Australia/Hobart">Hobart</option>
<option value="Australia/Lindeman">Lindeman</option>
<option value="Australia/Lord_Howe">Lord Howe</option>
<option value="Australia/Melbourne">Melbourne</option>
<option value="Australia/Perth">Perth</option>
<option value="Australia/Sydney">Sydney</option>
</optgroup>
<optgroup label="Europe">
<option value="Europe/Amsterdam">Amsterdam</option>
<option value="Europe/Andorra">Andorra</option>
<option value="Europe/Athens">Athens</option>
<option value="Europe/Belgrade">Belgrade</option>
<option value="Europe/Berlin">Berlin</option>
<option value="Europe/Bratislava">Bratislava</option>
<option value="Europe/Brussels">Brussels</option>
<option value="Europe/Bucharest">Bucharest</option>
<option value="Europe/Budapest">Budapest</option>
<option value="Europe/Busingen">Busingen</option>
<option value="Europe/Chisinau">Chisinau</option>
<option value="Europe/Copenhagen">Copenhagen</option>
<option value="Europe/Dublin">Dublin</option>
<option value="Europe/Gibraltar">Gibraltar</option>
<option value="Europe/Guernsey">Guernsey</option>
<option value="Europe/Helsinki">Helsinki</option>
<option value="Europe/Isle_of_Man">Isle of Man</option>
<option value="Europe/Istanbul">Istanbul</option>
<option value="Europe/Jersey">Jersey</option>
<option value="Europe/Kaliningrad">Kaliningrad</option>
<option value="Europe/Kiev">Kiev</option>
<option value="Europe/Lisbon">Lisbon</option>
<option value="Europe/Ljubljana">Ljubljana</option>
<option value="Europe/London">London</option>
<option value="Europe/Luxembourg">Luxembourg</option>
<option value="Europe/Madrid">Madrid</option>
<option value="Europe/Malta">Malta</option>
<option value="Europe/Mariehamn">Mariehamn</option>
<option value="Europe/Minsk">Minsk</option>
<option value="Europe/Monaco">Monaco</option>
<option value="Europe/Moscow">Moscow</option>
<option value="Europe/Oslo">Oslo</option>
<option value="Europe/Paris">Paris</option>
<option value="Europe/Podgorica">Podgorica</option>
<option value="Europe/Prague">Prague</option>
<option value="Europe/Riga">Riga</option>
<option value="Europe/Rome">Rome</option>
<option value="Europe/Samara">Samara</option>
<option value="Europe/San_Marino">San Marino</option>
<option value="Europe/Sarajevo">Sarajevo</option>
<option value="Europe/Simferopol">Simferopol</option>
<option value="Europe/Skopje">Skopje</option>
<option value="Europe/Sofia">Sofia</option>
<option value="Europe/Stockholm">Stockholm</option>
<option value="Europe/Tallinn">Tallinn</option>
<option value="Europe/Tirane">Tirane</option>
<option value="Europe/Uzhgorod">Uzhgorod</option>
<option value="Europe/Vaduz">Vaduz</option>
<option value="Europe/Vatican">Vatican</option>
<option value="Europe/Vienna">Vienna</option>
<option value="Europe/Vilnius">Vilnius</option>
<option value="Europe/Volgograd">Volgograd</option>
<option value="Europe/Warsaw">Warsaw</option>
<option value="Europe/Zagreb">Zagreb</option>
<option value="Europe/Zaporozhye">Zaporozhye</option>
<option value="Europe/Zurich">Zurich</option>
</optgroup>
<optgroup label="Indian">
<option value="Indian/Antananarivo">Antananarivo</option>
<option value="Indian/Chagos">Chagos</option>
<option value="Indian/Christmas">Christmas</option>
<option value="Indian/Cocos">Cocos</option>
<option value="Indian/Comoro">Comoro</option>
<option value="Indian/Kerguelen">Kerguelen</option>
<option value="Indian/Mahe">Mahe</option>
<option value="Indian/Maldives">Maldives</option>
<option value="Indian/Mauritius">Mauritius</option>
<option value="Indian/Mayotte">Mayotte</option>
<option value="Indian/Reunion">Reunion</option>
</optgroup>
<optgroup label="Pacific">
<option value="Pacific/Apia">Apia</option>
<option value="Pacific/Auckland">Auckland</option>
<option value="Pacific/Chatham">Chatham</option>
<option value="Pacific/Chuuk">Chuuk</option>
<option value="Pacific/Easter">Easter</option>
<option value="Pacific/Efate">Efate</option>
<option value="Pacific/Enderbury">Enderbury</option>
<option value="Pacific/Fakaofo">Fakaofo</option>
<option value="Pacific/Fiji">Fiji</option>
<option value="Pacific/Funafuti">Funafuti</option>
<option value="Pacific/Galapagos">Galapagos</option>
<option value="Pacific/Gambier">Gambier</option>
<option value="Pacific/Guadalcanal">Guadalcanal</option>
<option value="Pacific/Guam">Guam</option>
<option value="Pacific/Honolulu">Honolulu</option>
<option value="Pacific/Johnston">Johnston</option>
<option value="Pacific/Kiritimati">Kiritimati</option>
<option value="Pacific/Kosrae">Kosrae</option>
<option value="Pacific/Kwajalein">Kwajalein</option>
<option value="Pacific/Majuro">Majuro</option>
<option value="Pacific/Marquesas">Marquesas</option>
<option value="Pacific/Midway">Midway</option>
<option value="Pacific/Nauru">Nauru</option>
<option value="Pacific/Niue">Niue</option>
<option value="Pacific/Norfolk">Norfolk</option>
<option value="Pacific/Noumea">Noumea</option>
<option value="Pacific/Pago_Pago">Pago Pago</option>
<option value="Pacific/Palau">Palau</option>
<option value="Pacific/Pitcairn">Pitcairn</option>
<option value="Pacific/Pohnpei">Pohnpei</option>
<option value="Pacific/Port_Moresby">Port Moresby</option>
<option value="Pacific/Rarotonga">Rarotonga</option>
<option value="Pacific/Saipan">Saipan</option>
<option value="Pacific/Tahiti">Tahiti</option>
<option value="Pacific/Tarawa">Tarawa</option>
<option value="Pacific/Tongatapu">Tongatapu</option>
<option value="Pacific/Wake">Wake</option>
<option value="Pacific/Wallis">Wallis</option>
</optgroup>
<optgroup label="UTC">
<option value="UTC">UTC</option>
</optgroup>
<optgroup label="Manual Offsets">
<option value="UTC-12">UTC-12</option>
<option value="UTC-11.5">UTC-11:30</option>
<option value="UTC-11">UTC-11</option>
<option value="UTC-10.5">UTC-10:30</option>
<option value="UTC-10">UTC-10</option>
<option value="UTC-9.5">UTC-9:30</option>
<option value="UTC-9">UTC-9</option>
<option value="UTC-8.5">UTC-8:30</option>
<option value="UTC-8">UTC-8</option>
<option value="UTC-7.5">UTC-7:30</option>
<option value="UTC-7">UTC-7</option>
<option value="UTC-6.5">UTC-6:30</option>
<option value="UTC-6">UTC-6</option>
<option value="UTC-5.5">UTC-5:30</option>
<option value="UTC-5">UTC-5</option>
<option value="UTC-4.5">UTC-4:30</option>
<option value="UTC-4">UTC-4</option>
<option value="UTC-3.5">UTC-3:30</option>
<option value="UTC-3">UTC-3</option>
<option value="UTC-2.5">UTC-2:30</option>
<option value="UTC-2">UTC-2</option>
<option value="UTC-1.5">UTC-1:30</option>
<option value="UTC-1">UTC-1</option>
<option value="UTC-0.5">UTC-0:30</option>
<option value="UTC+0">UTC+0</option>
<option value="UTC+0.5">UTC+0:30</option>
<option value="UTC+1">UTC+1</option>
<option value="UTC+1.5">UTC+1:30</option>
<option value="UTC+2">UTC+2</option>
<option value="UTC+2.5">UTC+2:30</option>
<option value="UTC+3">UTC+3</option>
<option value="UTC+3.5">UTC+3:30</option>
<option value="UTC+4">UTC+4</option>
<option value="UTC+4.5">UTC+4:30</option>
<option value="UTC+5">UTC+5</option>
<option value="UTC+5.5">UTC+5:30</option>
<option value="UTC+5.75">UTC+5:45</option>
<option value="UTC+6">UTC+6</option>
<option value="UTC+6.5">UTC+6:30</option>
<option value="UTC+7">UTC+7</option>
<option value="UTC+7.5">UTC+7:30</option>
<option value="UTC+8">UTC+8</option>
<option value="UTC+8.5">UTC+8:30</option>
<option value="UTC+8.75">UTC+8:45</option>
<option value="UTC+9">UTC+9</option>
<option value="UTC+9.5">UTC+9:30</option>
<option value="UTC+10">UTC+10</option>
<option value="UTC+10.5">UTC+10:30</option>
<option value="UTC+11">UTC+11</option>
<option value="UTC+11.5">UTC+11:30</option>
<option value="UTC+12">UTC+12</option>
<option value="UTC+12.75">UTC+12:45</option>
<option value="UTC+13">UTC+13</option>
<option value="UTC+13.75">UTC+13:45</option>
<option value="UTC+14">UTC+14</option>
</optgroup></select>
<?php
	printf('<a href="%s" >%s</a>',admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_details&post_id='.$post_id),__('More Event Options','rsvpmaker')); 
}//end content not displayed on initial setup page	
?>

</p>
<?php
}

function rsvpmaker_details_post() {
	$output = '';
	if(isset($_POST['publish_draft']) && isset($_REQUEST['post_id'])  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	wp_publish_post((int) $_REQUEST['post_id']);
	
if(isset($_REQUEST['post_id']))
	$post = get_post((int) $_REQUEST['post_id']);

if(isset($_GET['template_to_event']))
	{
	delete_post_meta($post->ID,'_sked');
	delete_post_meta($post->ID,'_sked_Varies');
	update_post_meta($post->ID,'_rsvp_dates',date('Y-m-d H:').'00:00',rsvpmaker_strtotime('+2 hours') );
	}

if(isset($post->post_status) && ($post->post_status != 'publish') )
	$output .= sprintf('<h2>Post not published, status = <span style="color:red">%s</span></h2>',$post->post_status);
if(isset($_POST["_require_webinar_passcode"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	{
	update_post_meta($post->ID,'_require_webinar_passcode',sanitize_text_field($_POST["_require_webinar_passcode"]));
	}
if(isset($_POST['post_id'])  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
{
	$template_prompt = (isset($_POST['sked'])) ? sprintf(' - <a href="%s">%s</a> %s',admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&t=') . $post->ID, __('Create/update events','rsvpmaker'),__('based on this template','rsvpmaker') ) : '';
	$output .= sprintf('<div class="notice notice-info"><p>%s%s</p></div>',__('Saved RSVP Options','rsvpmaker'),$template_prompt);
	rsvpmaker_save_calendar_data($post->ID);
	cache_rsvp_dates(50);
	if(isset($_POST['setrsvp']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	{
	save_rsvp_meta($post->ID);		
	}
	else
		do_action('save_post',$post->ID);
	$output .= sprintf('<p><a href="%s">View event post</a></p>',get_permalink($post->ID));
}
return $output;
}

function rsvpmaker_details() {
	global $post;
	global $custom_fields;
	global $rsvp_options;

?>
<style>
<?php 
$styles = rsvpmaker_included_styles();
echo $styles; ?>
</style>
<div class="wrap" style="margin-right: 200px;"> 
	<div id="icon-edit" class="icon32"><br /></div>
<h1 id="headline"><?php esc_html_e('RSVP / Event Options','rsvpmaker'); ?></h1>
<div id="rsvpmaker_details_status"></div>
<?php

if(isset($_GET['trash']) && isset($_GET['post_id'])) {
	wp_trash_post(intval($_GET['post_id']));
	echo '<div class="notice notice-info"><p>'.__('Event post moved to','rsvpmaker').' <a href="'.admin_url('edit.php?post_status=trash&post_type=rsvpmaker').'">Trash</a></p></div>';
}

echo rsvpmaker_details_post();

if(empty($post->ID))
{
global $wpdb;
$sql = "SELECT DISTINCT $wpdb->posts.ID as post_id, $wpdb->posts.*, date_format(a1.meta_value,'%M %e, %Y') as date
	 FROM ".$wpdb->posts."
	 JOIN ".$wpdb->postmeta." a1 ON ".$wpdb->posts.".ID =a1.post_id AND a1.meta_key='_rsvp_dates'
	 WHERE a1.meta_value > '".get_sql_now()."' ORDER BY a1.meta_value";

$results = $wpdb->get_results($sql);
$options = '<optgroup label="'.__('Future Events','rsvpmaker').'">';
if(!empty($results))
foreach($results as $row)
	{
		$s = '';
		if(isset($_REQUEST["post_id"]) && ($row->ID == $_REQUEST["post_id"]))
			$s = ' selected="selected" ';
		$options .= sprintf('<option value="%d" %s>%s %s</option>',$row->ID,$s,$row->post_title,$row->date);
	}
$options .= '</optgroup><optgroup label="'.__('Event Templates','rsvpmaker').'">';
$results = rsvpmaker_get_templates();
if(!empty($results))
foreach($results as $row)
	{
		if(!rsvpmaker_is_template($row->ID))
			continue;
		$s = '';
		if(isset($_REQUEST["post_id"]) && ($row->ID == $_REQUEST["post_id"]))
			$s = ' selected="selected" ';
		$options .= sprintf('<option value="%d" %s>%s</option>',$row->ID,$s,$row->post_title);
	}
$options .= '</optgroup>';
	
printf('<form method="get" action="%s"><input type="hidden" name="post_type" value="rsvpmaker" /><input type="hidden" name="page" value="rsvpmaker_details" /><select name="post_id">%s</select> <button>Get</button>%s</form>',admin_url('edit.php'),$options,rsvpmaker_nonce('return'));

}
else
{
	?>
<p><?php esc_html_e('Use this form for additional RSVPMaker settings.','rsvpmaker')?> <?php printf('<a href="%s">%s</a>',admin_url('post.php?post='.intval($post->ID).'&action=edit'),__('Return to editor','rsvpmaker'))?></p>	
	<?php
printf('<form method="post" action="%s" id="rsvpmaker_details">',admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_details&post_id='.intval($post->ID)));
$date = get_rsvp_date($post->ID);
	$datef = (empty($date)) ? '' : rsvpmaker_date('F j, Y',rsvpmaker_strtotime($date));
	$custom_fields = get_rsvpmaker_custom($post->ID);
	
	$t = rsvpmaker_strtotime($date);
	printf('<h3>%s<br />%s</h3>',$post->post_title,$datef);
	$drawresult = draw_eventdates();
	if($drawresult != 'special')
	{
	GetRSVPAdminForm($post->ID);
	if(isset($rsvp_options["additional_editors"]) && $rsvp_options["additional_editors"])
		additional_editors();		
	}

echo '<div style="position: fixed; top: 50px; right: 30px; width: 100px;">';
if($post->post_status == 'draft')
	printf('<div><input type="checkbox" name="publish_draft" value="1" />%s</div>',__('Publish','rsvpmaker'));
submit_button();
echo '</div>';
rsvpmaker_nonce();
printf('<input type="hidden" name="post_id" value="%d" /></form>',$post->ID);
?>
<script>
jQuery(document).ready(function( $ ) {
var unsaved = false;
$(":input").change(function(){ //triggers change in all input fields including text type
    unsaved = true;
});

$('#rsvpmaker_details').submit(function() {
    unsaved = false;
});

function unloadPage(){ 
    if(unsaved){
        return "Changes you made may not be saved.";
    }
}
window.onbeforeunload = unloadPage;
	
});

</script>
<?php
}

?>
</div>
<?php
}

function ajax_rsvpmaker_date_handler() {
	$post_id = (int) $_REQUEST['post_id'];
	if(!$post_id)
		wp_die();
	if(isset($_REQUEST['date']))
	{
	$t = rsvpmaker_strtotime($_REQUEST['date']);
	$date = rsvpmaker_date("Y-m-d H:i:s",$t);
	$current_date = get_rsvp_date($post_id);
	update_post_meta($post_id,'_rsvp_dates',$date,$current_date);
	delete_transient('rsvpmakerdates');
	}
    wp_die();
}

function rsvp_customize_form_url($post_id) {
	global $rsvp_options;
	$current_form = get_post_meta($post_id,'_rsvp_form',true);
	if(empty($current_form))
		$current_form = $rsvp_options['rsvp_form'];
	if(!is_numeric($current_form))
		return;
	return admin_url('?post_id='.$post_id.'&customize_form='.$current_form); // customize url 
}

function rsvp_form_url($post_id) {
	global $rsvp_options;
	$current_form = get_post_meta($post_id,'_rsvp_form',true);
	if(empty($current_form))
		$current_form = $rsvp_options['rsvp_form'];
	if(!is_numeric($current_form))
		return;
	$form_post = get_post($current_form);
	if(empty($form_post->post_parent) ||($form_post->post_parent != $post_id))
		return admin_url('?post_id='.$post_id.'&customize_form='.$current_form); // customize url 
	else
		return admin_url('post.php?action=edit&post=').$current_form; // edit url
}

function rsvp_confirm_url($post_id) {
	global $rsvp_options;
	$current = get_post_meta($post_id,'_rsvp_confirm',true);
	if(empty($current))
		$current = $rsvp_options['rsvp_confirm'];
	if(!is_numeric($current))
		return;
	$confirm = get_post($current);
	if(empty($confirm->post_parent) || ($confirm->post_parent != $post_id))
		return admin_url('?post_id='.$post_id.'&customize_rsvpconfirm='.$current); // customize url 
	else
		return admin_url('post.php?action=edit&post=').$current; // edit url
}

function rsvpmaker_templates_dropdown ($select = 'template') {
	$templates = rsvpmaker_get_templates();
	$o = '';
	if(is_array($templates))
	foreach($templates as $template)
	{
		$o .= sprintf('<option value="%d">%s</option>',$template->ID,$template->post_title);
	}
return sprintf('<select name="%s">%s</select>',$select,$o);
}

function toolbar_rsvpmaker( $wp_admin_bar ) {
global $post;
$args = array(
	'parent'    => 'new-rsvpmaker',
	'id' => 'rsvpmaker_setup_template',
	'title' => 'New Event Template',
	'href'  => admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_setup&new_template=1'),
	'meta'  => array( 'class' => 'rsvpmaker_setup')
);
$wp_admin_bar->add_node( $args );
$templates = rsvpmaker_get_templates();
foreach($templates as $template) {
	$args = array(
		'parent'    => 'new-rsvpmaker',
		'id' => 'template'.$template->ID,
		'title' => 'Create/Update: '.$template->post_title,
		'href'  => admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&t='.$template->ID),
		'meta'  => array( 'class' => 'new_from_template')
	);
	$wp_admin_bar->add_node( $args );
}

if(!empty($post->post_type) && ($post->post_type != 'rsvpemail'))
{
	if($post->post_type == 'rsvpmaker') {
		$args = array(
			'parent'    => 'new-rsvpemail',
			'id' => 'embed_to_email',
			'title' => __('Embed Event in Email','rsvpmaker'),
			'href'  => admin_url('edit.php?post_type=rsvpemail&rsvpevent_to_email='.intval($post->ID)),
			'meta'  => array( 'class' => 'rsvpmaker_embed')
		);	
		$wp_admin_bar->add_node( $args );
	}
	$args = array(
		'parent'    => 'new-rsvpemail',
		'id' => 'post_to_email',
		'title' => __('Copy to Email','rsvpmaker'),
		'href'  => admin_url('edit.php?post_type=rsvpemail&post_to_email='.intval($post->ID)),
		'meta'  => array( 'class' => 'rsvpmaker')
	);
	$wp_admin_bar->add_node( $args );
	if($post->post_type != 'rsvmpaker')
	{
		$args = array(
			'parent'    => 'new-rsvpemail',
			'id' => 'excerpt_to_email',
			'title' => __('Excerpt to Email','rsvpmaker'),
			'href'  => admin_url('edit.php?post_type=rsvpemail&excerpt=1&post_to_email='.intval($post->ID)),
			'meta'  => array( 'class' => 'rsvpmaker')
		);
		$wp_admin_bar->add_node( $args );	
	}
}

$noview = true;
$argarg = get_related_documents ();
if(empty($argarg))
return;
	foreach($argarg as $args) {
		$wp_admin_bar->add_node($args);
		if($args['id'] == 'view-event')
		$wp_admin_bar->remove_node( 'view' );
	}
}

function rsvpmaker_quick_post() {
	global $current_user;
	if( ! wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
		return;
	$_POST = stripslashes_deep($_POST);
	if(!empty($_POST['type']))
		$types[] = (int) $_POST['type'];
	if(!empty($_POST['type2']))
		$types[] = (int) $_POST['type2'];
	if(!empty($_POST['newtype']))
	{
		$result = wp_insert_term(sanitize_text_field($_POST['newtype']),'rsvpmaker-type');
		if(is_array($result) && !empty($result["term_id"]))
			$types[] = $result["term_id"];
	}

	foreach($_POST["quicktitle"] as $index => $title) {
		if(!empty($title)) {
		$datetime = trim(sanitize_text_field($_POST["quick_rsvp_date"][$index].' '.$_POST["quick_rsvp_time"][$index]));
		if(!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/',$datetime)) {
			echo 'invalid time'.$datetime;
			continue;
		}
		$title = sanitize_text_field($title);
		$content = (empty($_POST["quickcontent"][$index])) ? '' : wp_kses_post( rsvpautog($_POST["quickcontent"][$index]));
		$post_id = wp_insert_post(array('post_type' => 'rsvpmaker', 'post_title' => $title, 'post_content' => $content, 'post_author' => $current_user->ID, 'post_status' => sanitize_text_field($_POST['status'])));
		add_post_meta($post_id,'_rsvp_dates',$datetime);
		$end_type = sanitize_text_field($_POST["quick_end_time_type"][$index]);
		$end_time = sanitize_text_field($_POST["quick_rsvp_time_end"][$index]);
		if(empty($end_time)) {
			$t = strtotime($datetime." +1 hour");
			$end_time = date('H:i',$t);
		}
		add_post_meta($post_id,'_firsttime',$end_type);
		add_post_meta($post_id,'_endfirsttime',$end_time);
		rsvpmaker_add_event_row($post_id,$datetime,$end_time,$end_type);
		if(!empty($types)) {
			wp_set_object_terms( $post_id, $types, 'rsvpmaker-type' );
		}
		if(!empty($_POST['rsvp_on']))
			add_post_meta($post_id,'_rsvp_on',1);
		if(!empty($_POST['calendar_icons']))
			add_post_meta($post_id,'_calendar_icons',1);
		if(!empty($_POST['add_timezone']))
			add_post_meta($post_id,'_add_timezone',1);
		if(!empty($_POST['convert_timezone']))
			add_post_meta($post_id,'_convert_timezone',1);
		
		if(empty($confirmation)) {
			$confirmation = sprintf('<h3>%s</h3>',__('Event Posts Created','rsvpmaker'));
			echo wp_kses_post($confirmation);
		}
		printf('<p><a href="%s">View</a> <a href="%s">Edit</a> %s %s</p>',get_permalink($post_id),admin_url("post.php?post=$post_id&action=edit"),$title,$datetime);
		}		
	}
}

function print_quick_date_entry($i,$date_text_default,$datedefault) {
	echo '<div class="quickentry">';
	echo '<div id="event_date'.$i.'" >
	<p><label>Date</label>
	<input name="quick_rsvp_date[]" type="date" class="quick-rsvp-date" id="quick-rsvp-date-'.$i.'" count="'.$i.'"> <span id="date-weekday-'.$i.'"></span>
	</p>
	<p><label>Time</label> 
	<input name="quick_rsvp_time[]" type="time" class="quick-rsvp-time" id="quick-rsvp-time-'.$i.'" count="'.$i.'" value="12:00:00"> <span id="date-weekday-'.$i.'"></span>
	</p>
	<p><label>End Time</label> <input type="hidden" id="end_time_type-'.$i.'" name="quick_end_time_type[]" class="end_time_type" value="set" >
	<input name="quick_rsvp_time_end[]" type="time" class="quick-rsvp-time-end" id="quick-rsvp-time-end-'.$i.'" size="5" count="'.$i.'" value="13:00:00">
	</p></div>';
	printf('<div class="quickfield"><label>%s</label><input type="text" id="quicktitle-'.$i.'" class="quicktitle" name="quicktitle[]"></div>',__('Title','rsvpmaker'));
	printf('<div class="quickfield"><label>%s</label><br /><textarea name="quickcontent[]" rows="2" cols="100"></textarea></div>',__('Starter Text','rsvpmaker'));
	echo '<div id="quickmessage-'.$i.'"></div>';
	echo '</div>';
}

function rsvpmaker_quick_ui() {
	global $rsvp_options;
	$t = strtotime('tomorrow noon');
	$datedefault = rsvpmaker_date('Y-m-d H:i:s',$t);
	$date_text_default = rsvpmaker_date('F j, Y '.$rsvp_options['time_format'],$t);
	$limit = (int) $_GET['quick'];
	if(!$limit)
		$limit = 5;
	printf('<h3>%s</h3>',__('Quickly Setup Multiple Event Posts','rsvpmaker'));
	printf('<p>%s</p>',__('Enter start time and title for each event to be created. Optionally, you can include post body text. Can include multiple paragraphs, separated by a blank line. Events can be published immediately or saved as drafts for further editing.','rsvpmaker'));
	printf('<p>%s</p>',__('You must enter at least a title for the event to be recorded.','rsvpmaker'));
	printf('<p>%s</p>',__('Setting the date changes the default date for all the events that follow, which is useful for example for setting up a conference program or other series of events on the same day or subsequent days.','rsvpmaker'));
	printf('<form method="post" action="%s">',admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_setup'));
	for($i = 0; $i < 50; $i++) {
		if($i >= $limit)
			echo '<div class="quick-extra-blank" id="quick-extra-blank-'.$i.'">';
		echo '<div>Entry '.($i+1).'</div>';
		print_quick_date_entry($i,$date_text_default,$datedefault);
		if($i >= $limit)
			echo '</div>';
	}
	echo '<div class="quick-extra-blank" id="quick-extra-blank-'.$i.'"><em>Maximum Entries Reached</em></div>';
	echo '<div><button id="add-quick-blank" start="'.$limit.'">+ Show more entries</button></div>';
	//echo '<div id="quick_entry_more"></div><p><button id="quick_entry_add">Add More</button></p><div id="quick_entry_hidden">';
	//print_quick_date_entry('x',$date_text_default,$datedefault);
	//echo '</div>';
	echo '<div>';
	wp_dropdown_categories( array(
		'taxonomy'      => 'rsvpmaker-type',
		'hide_empty'    => 0,
		'orderby'       => 'name',
		'order'         => 'ASC',
		'name'          => 'type',
		'show_option_none' => __('Event Type (optional)','rsvpmaker'),
		'option_none_value' => 0
	) );
	wp_dropdown_categories( array(
		'taxonomy'      => 'rsvpmaker-type',
		'hide_empty'    => 0,
		'orderby'       => 'name',
		'order'         => 'ASC',
		'name'          => 'type2',
		'show_option_none' => __('Event Type (optional)','rsvpmaker'),
		'option_none_value' => 0
	) );
	echo '<label>New Event Type </label> <input type="text" name="newtype"> <div>';
	?>
	<p>
	<?php esc_html_e('Collect RSVPs','rsvpmaker');?>
	  <input type="radio" name="rsvp_on" id="setrsvpon" value="1" <?php if( !empty($rsvp_options['rsvp_on']) ) echo 'checked="checked" ';?> />
	<?php esc_html_e('YES','rsvpmaker');?> <input type="radio" name="rsvp_on" id="setrsvpon" value="0" <?php if( empty($rsvp_options['rsvp_on']) ) echo 'checked="checked" ';?> />
	<?php esc_html_e('NO','rsvpmaker');?> </p>
	<p><input type="checkbox" name="calendar_icons" value="1" <?php if($rsvp_options["calendar_icons"]) echo ' checked="checked" ';?> /> <?php esc_html_e('Show Add to Google / Download to Outlook (iCal) icons','rsvpmaker'); ?> 
	<br />
	<p id="timezone_options">
	<?php
	if(!strpos($rsvp_options["time_format"],'T') )
	{
	?>
	<input type="checkbox" name="add_timezone" value="1" <?php if($rsvp_options["add_timezone"]) echo ' checked="checked" '; ?> /><?php esc_html_e('Display timezone code as part of date/time','rsvpmaker'); echo ' '; ?>
	<?php
	}
	?>
	<input type="checkbox" name="convert_timezone" value="1" <?php if($rsvp_options["convert_timezone"]) echo ' checked="checked" '; ?> /><?php esc_html_e('Show timezone conversion button next to calendar icons','rsvpmaker'); ?>
	</p>
	<?php
	rsvpmaker_nonce();
	echo '<div><input type="radio" name="status" value="draft" checked="checked"> Draft <input type="radio" name="status" value="publish"> Publish </div><p><button>Submit</button></p></form>';
}

function rsvpmaker_setup () {

global $rsvp_options, $current_user;

?>
<style>
select {
	max-width: 228px;
}
.quickentry label {
	display: inline-block;
	width: 100px;
	font-weight: bold;
}
</style>
<div class="wrap">
	<div id="icon-edit" class="icon32"><br /></div> 
<h2><?php esc_html_e('Event Setup','rsvpmaker'); ?></h2> 
<?php
if(isset($_POST["quicktitle"]))
	rsvpmaker_quick_post();
elseif(isset($_GET["quick"])) {
	rsvpmaker_quick_ui();
	echo '</div>';
	return;
}

$title = '';
$template = 0;
if(isset($_GET['t']))
{
	$post = get_post((int) $_GET['t']);
	$title = htmlentities($post->post_title);
	$template = $post->ID;
	$future = get_events_by_template($template);
	$shortlist = $morelist = '';
	if($future) {
		foreach($future as $index => $event) {
			$temp = sprintf('<p><a href="%s">Edit event</a>: %s %s</p>',admin_url('post.php?action=edit&post='.$event->ID),esc_html($event->post_title),esc_html($event->date));
			if($index < 5)
				$shortlist .= $temp;
			else
				$morelist .= $temp;
		}
	if(!empty($morelist))
		$morelist = '<p id="morelink"><a onclick="document.getElementById'."('moreprojected').style.display='block'".';document.getElementById'."('morelink').style.display='none'".'" >Show More</a></p><div id="moreprojected" style="display: none;">'.$morelist.'</div>';
	echo '<div style="border: medium solid #999; padding: 15px;"><h2>'.__('Previously Scheduled','rsvpmaker').'</h2>'.$shortlist.$morelist.'</div>';
	
	echo '<p><em>'.__('To create a new event based on this template, use the form below.','rsvpmaker').'</em><p>';
	}
}

?>
<h2><?php esc_html_e('Set Event Title and Schedule','rsvpmaker'); ?></h2> 
<?php
printf('<p><em>%s</em></p>',__('Start by entering an event title and date or schedule details. A draft event post will be created and loaded into the editor.'));
printf('<form id="rsvpmaker_setup" action="%s" method="post"><input type="hidden" name="template" value="%d">', admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_setup'),$template);
echo '<h1 style="font-size: 20px;">Title: <input type="text" name="rsvpmaker_new_post" style="font-size: 20px; width: 60%" value="'.$title.'" /></h1>';
draw_eventdates();
if(isset($_GET['t']))
	echo '<p><em>'.__('Event will inherit defaults from template for RSVPs, date format options.','rsvpmaker').'</em></p>';
else
{
$rsvp_on = $rsvp_options['rsvp_on'];
?>
<p>
<?php esc_html_e('Collect RSVPs','rsvpmaker');?>
  <input type="radio" name="setrsvp[on]" id="setrsvpon" value="1" <?php if( $rsvp_on ) echo 'checked="checked" ';?> />
<?php esc_html_e('YES','rsvpmaker');?> <input type="radio" name="setrsvp[on]" id="setrsvpon" value="0" <?php if( !$rsvp_on ) echo 'checked="checked" ';?> />
<?php esc_html_e('NO','rsvpmaker');?> </p>
<p><input type="checkbox" name="calendar_icons" value="1" <?php if($rsvp_options["calendar_icons"]) echo ' checked="checked" ';?> /> <?php esc_html_e('Show Add to Google / Download to Outlook (iCal) icons','rsvpmaker'); ?> 
<br />
<p id="timezone_options">
<?php
if(!strpos($rsvp_options["time_format"],'T') )
{
?>
<input type="checkbox" name="add_timezone" value="1" <?php if($rsvp_options["add_timezone"]) echo ' checked="checked" '; ?> /><?php esc_html_e('Display timezone code as part of date/time','rsvpmaker'); echo ' '; ?>
<?php
}
?>
<input type="checkbox" name="convert_timezone" value="1" <?php if($rsvp_options["convert_timezone"]) echo ' checked="checked" '; ?> /><?php esc_html_e('Show timezone conversion button next to calendar icons','rsvpmaker'); ?>
</p>
<div><label>Timezone</label> 
<select id="timezone_string" name="timezone_string">
<script>
var tz = jstz.determine();
var tzstring = tz.name();
document.write('<option selected="selected" value="' + tzstring + '">' + tzstring + '</option>');
</script>
<option value=""><?php esc_html_e('Default','rsvpmaker'); ?></option>
<optgroup label="U.S. (Common Choices)">
<option value="America/New_York">New York</option>
<option value="America/Chicago">Chicago</option>
<option value="America/Denver">Denver</option>
<option value="America/Los_Angeles">Los Angeles</option>
</optgroup>
<?php $choices = wp_timezone_choice('');
echo str_replace('<option selected="selected" value="">Select a city</option>','',$choices);
?>
</select> <br /><em><?php esc_html_e('Choose a city in the same timezone as you','rsvpmaker'); ?>.</em>

<?php
$o = sprintf('<option value="%d">%s</option>',esc_attr($rsvp_options['rsvp_form']),__('Default','rsvpmaker'));
$forms = rsvpmaker_get_forms();
foreach($forms as $label => $form_id)
{
$o .= sprintf('<option value="%d">%s</option>',esc_attr($form_id),esc_html($label));
}
printf('<p class="rsvp_form_select"><label>%s</label> <select name="rsvp_form">%s</select></p>',__('RSVP Form','rsvpmaker'),$o);

}
rsvpmaker_nonce();
submit_button();
echo '</form></div>';
	
	if(isset($_GET['t']))
		return;

if(!isset($_GET['new_template']) && !isset($_GET['t'])){
	echo '<div style="background-color: #fff; padding: 10px; border: thin dotted #555; width: 90%;">';
	printf('%s %s<br /><a href="%s">%s</a>',__('For recurring events','rsvpmaker'),__('create a' ,'rsvpmaker'),admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_setup&new_template=1'),__('New Template','rsvpmaker'));
	printf('<form method="get" action="%s"><input type="hidden" name="post_type" value="rsvpmaker" /><br />%s <select name="page"><option value="rsvpmaker_setup">%s</option><option value="rsvpmaker_template_list">%s</option></select> %s %s<br >%s</form>',admin_url('edit.php'),__('Or add','rsvpmaker'),__('One event','rsvpmaker'),__('Multiple events','rsvpmaker'),__('based on','rsvpmaker'),rsvpmaker_templates_dropdown('t'),get_submit_button('Submit'));
	do_action('rsvpmaker_setup_template_prompt');
	printf('<form method="get" action="%s"><input type="hidden" name="post_type" value="rsvpmaker" /><input type="hidden" name="page" value="rsvpmaker_setup">%s <select name="quick"><option value="5">5</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="30">30</option><option value="40">40</option><option value="50">50</option></select> %s %s</form>',admin_url('edit.php'),__('Or quickly create up to','rsvpmaker'),__('events without a template','rsvpmaker'),get_submit_button('Show Form'));
	echo '</div>';
}				

	$myevents = get_events_by_author($current_user->ID);
	if($myevents)
	{
		printf('<h3>%s</h3>',__('Your Event Posts','rsvpmaker'));
		foreach($myevents as $event){
			$draft = ($event->post_status == 'draft') ? ' <strong>(draft)</strong>' : '';
			printf('<p><a href="%s">Edit event</a>: %s %s %s</p>',admin_url('post.php?action=edit&post='. $event->ID),esc_html($event->post_title),esc_html($event->date),esc_html($draft));
		}
	}
	$templates = rsvpmaker_get_templates();
	$tedit = $list = '';
	if(is_array($templates))
	foreach($templates as $template)
	{
	$eds = get_additional_editors($template->ID);
	if(($current_user->ID == $template->post_author) || (!empty($eds) && in_array($current_user->ID,$eds) ) )
	{
		$tedit .= sprintf('<option value="%s">%s</option>',esc_attr($template->ID),esc_html($template->post_title));
		$list .= '<p><strong>'.$template->post_title.'</strong></p>';
		$event = rsvpmaker_next_by_template($template->ID);
		if($event)
		{
		$draft = ($event->post_status == 'draft') ? ' <strong>(draft)</strong>' : '';
		$list .= sprintf('<p><a href="%s">Edit next event</a>: %s %s %s</p>',admin_url('post.php?action=edit&post='.$event->ID),esc_html($event->post_title),esc_html($event->date), esc_html($draft));			
		}
		$list .= sprintf('<p><a href="%s">Add event</a> based on template: %s</p>',admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_setup&t='.$template->ID),esc_html($template->post_title));			
		$list .= sprintf('<p><a href="%s">Create / Update</a> multiple events based on: %s</p>',admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&t='.$template->ID),esc_html($template->post_title));
		$list .= sprintf('<p><a href="%s">Edit template</a> %s</p>',admin_url('post.php?action=edit&post='.$template->ID),esc_html($template->post_title));		
	}

	}

	if(!empty($tedit))
	{
		printf('<h3>%s</h3><p>%s</p>',__('Your Templates','rsvpmaker'),__('Your templates and any others you have editing rights to are listed here. Templates allow you to generate multiple events based on a recurring schedule and common details for events in the series.','rsvpmaker'));
		echo $list;

		printf('<form action="%s" method="get"><p><input type="hidden" name="action" value="edit"><select name="post">%s</select>%s</p></form>',admin_url('post.php'),$tedit,get_submit_button(__('Edit Template','rsvpmaker')));

		printf('<form action="%s" method="get">
		<input type="hidden" name="post_type" value="rsvpmaker">
		<input type="hidden" name="page" value="rsvpmaker_template_list">
		<p><select name="t">%s</select>%s</p></form>',admin_url('edit.php'),$tedit,get_submit_button(__('Create/Update','rsvpmaker')));
	}
	rsvpmaker_debug_log(memory_get_peak_usage(),'peak memory used');
}

function rsvpmaker_setup_post ($ajax = false) {
if(!empty($_POST["rsvpmaker_new_post"])  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	{
		$t = 0;
		$slug = $title = sanitize_text_field(stripslashes($_POST["rsvpmaker_new_post"]));
		$content = array('post_title' => $title,'post_name' => $slug, 'post_type' => 'rsvpmaker','post_status' => 'draft','post_content' => '');
		if(!empty($_POST['template']))
		{	
			$t = (int) $_POST['template'];
			$template = get_post($t);
			$content['post_content'] = $template->post_content;
		}
		$post_id = wp_insert_post($content);
		if($post_id)
		{		
		if($t) {
			add_post_meta($post_id,'_meet_recur',$t);
			rsvpmaker_copy_metadata($t, $post_id);
		}
		else {
			save_rsvp_meta($post_id, true);
		}
		if(!empty($_POST['rsvp_form']))
			update_post_meta($post_id,'_rsvp_form', (int) $_POST['rsvp_form']);
		if(!empty($_POST['timezone_string']))
			update_post_meta($post_id,'_rsvp_timezone_string', sanitize_text_field($_POST['timezone_string']));
		
		rsvpmaker_save_calendar_data($post_id);
		$date = sanitize_text_field($_POST['newrsvpdate'].' '.$_POST['newrsvptime']);
		$end = sanitize_text_field($_POST['rsvpendtime']);
		$display_type = sanitize_text_field($_POST['end_time_type']);
		$timezone = sanitize_text_field($_POST['timezone_string']);
		rsvpmaker_add_event_row($post_id,$date,$end,$display_type,$timezone);
		$editurl = admin_url('post.php?action=edit&post='.$post_id);
		if($ajax)
			return $editurl;
		wp_redirect($editurl);
		die();			
		}
	}
	
	//post-new.php?post_type=rsvpmaker
	if(strpos(sanitize_text_field($_SERVER['REQUEST_URI']),'post-new.php?post_type=rsvpmaker'))
	{
		wp_redirect(admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_setup'));
		die();
	}
}

function rsvpmaker_import_cleanup () {
	global $wpdb;
	$sql = "SELECT ID, post_title from $wpdb->posts WHERE post_type='rsvpmaker' AND post_title LIKE '%rsvpid%' ";
	$results = $wpdb->get_results($sql);
	if(is_array($results))
	foreach($results as $post)
	{
	$title = preg_replace('/rsvpid.+/','',$post->post_title);
	$sql = $wpdb->prepare("UPDATE $wpdb->posts SET post_title=%s WHERE ID=%d",$title,$post->ID);
	$wpdb->query($sql);
	}
}

function rsvpmaker_export_screen () {
	global $wpdb, $rsvp_options;
?>
	<h1>Import/Export RSVPMaker Events</h1>
	<?php
	?>
	<p>RSVPMaker posts are excluded from the standard WordPress export function because event posts require special handling, particularly those that have the same title but different dates (which by default WordPress rejects as duplicate posts).</p>
	<h3>Export Events</h3>
<?php
if(isset($_GET['resetrsvpcode'])) {
	$jt = strtotime('+ 24 hour');
	$export_code = rand().':'.$jt;
	update_option('rsvptm_export_lock',$export_code);
}
else {
	$export_code = get_option('rsvptm_export_lock');
	$parts = explode(':',$export_code);
	$jt = (empty($parts[1])) ? 0 : (int) $parts[1]; 	
}
if(empty($export_code) || ($jt < time())) {
	printf('<p>Coded url is expired or has not been set. To enable importing of event records from this site into another site, (<a href="%s">set code</a>)</p>',admin_url('tools.php?page=rsvpmaker_export_screen&resetrsvpcode=1'));
}
else {
	$url = rest_url('/rsvpmaker/v1/import/'.$export_code);
	printf('<p>To move your club\'s event records to another website that also uses this software, copy this web address:</p>
	<pre>%s</pre>
	<p>This link will expire at %s. (<a href="%s">reset</a>)</p>',$url,rsvpmaker_date($rsvp_options['short_date'].' '.$rsvp_options['time_format'].' T',$jt),admin_url('tools.php?page=rsvpmaker_export_screen&resetrsvpcode=1'));	
}
?>
<h3>Import Events</h3>
<p>Copy the link from the site you are <em>exporting from</em> and enter it here on the site you are <em>importing events into</em>.</p>
<form method="post" id="importform" action="<?php echo admin_url('tools.php?page=rsvpmaker_export_screen'); ?>">
<div><input type="text" name="importrsvp" id="importrsvp" value="<?php if(isset($_POST['importurl'])) echo sanitize_text_field($_POST['importrsvp']); ?>" /></div>
<input type="hidden" id="importnowurl" value="<?php echo rest_url('/rsvpmaker/v1/importnow'); ?>" />
<div><button id="import-button">Import</button></div>
</form>
<div id="import-result"></div>
<p><em>Note: This function does not automatically import images or correct links that may point to the old website.</em></p>
<?php
rsvpmaker_jquery_inline('import');									 
}

function rsvpmaker_override () {
	global $post, $current_user;
	if(isset($_POST['rsvp_tx_template'])  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
		update_post_meta((int) $_POST['rsvp_tx_post_id'],'rsvp_tx_template',sanitize_text_field($_POST['rsvp_tx_template']));
	if(!empty($_GET['post']) && !empty($_GET['action']) && ($_GET['action'] == 'edit') )
	{
		$post_id = (int) $_GET['post'];
		if(current_user_can('edit_post',$post_id))
			return; // don't mess with it
		if(empty($post))
		$post = get_post($post_id);
		if(isset($post->post_author) && ($post->post_author != $current_user->ID)) {
			$eds = get_additional_editors($post_id);
			if(in_array($current_user->ID,$eds))
			{
			if(!in_array($post->post_author,$eds))
			{
			add_post_meta($post_id, '_additional_editors',$post->post_author);
			}
			wp_update_post(array('ID' => $post_id, 'post_author' => $current_user->ID));
			}
		}
	}
}

//add_action('admin_init','rsvpmaker_override',1);

function rsvpmaker_share() {
?>	
	<h1>Share Templates</h1>
	<p>When you create an event template, you have the option of designating other users who will have the same authoring / editing rights to that template (and all the events based on it) as you do. This is helpful for organizations where more than one person needs to be able to post and update events.</p>
	<p>Be careful to only grant this permission to trusted collaborators.</p>
<?php	
	global $current_user;
	if(isset($_REQUEST['t']))
		{
			$t = (int) $_REQUEST['t'];
			$post = get_post($t);
		}
	
	if(!empty($_POST['editor_email']) && !empty($t)  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {
		$email = sanitize_text_field($_POST['editor_email']);
		if(!is_email($email))
		{
			echo '<p>Invalid email</p>';
		}
		else {
			$user = get_user_by('email',$email);
			if($user) {
				add_post_meta($t,'_additional_editors',$user->ID);
				echo '<p>Adding '.$email.'</p>';
			}
			else {
			$user["user_login"] = $email;
			$user["user_email"] = $email;
			$user["display_name"] = 'Editor added by '.$current_user->user_email;
			$user["user_pass"] = wp_generate_password();
			$user['role'] = 'author';
			$id = wp_insert_user($user);
			if($id)
			{
			add_post_meta($t,'_additional_editors',$id);
$lostpassword = site_url('wp-login.php?action=lostpassword');
?>
<h3>Editor account created</h3>
<p>Email and username are both set to <?php echo esc_html($email); ?></p>
<p><strong>IMPORTANT</strong>: Please contact the person you have added and let them know to set their password so they will be able to assist you. Send them this link:</p>
<p><a href="<?php echo esc_attr($lostpassword); ?>"><?php echo esc_html($lostpassword); ?></a></p>
<?php
			}
				
			}
		}
	}
	
	if(isset($_POST['remove_editor']) && is_array($_POST['remove_editor']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	foreach($_POST['remove_editor'] as $ed)
		delete_post_meta($t,'_additional_editors',(int) $ed);
	
	if(!empty($t))
	{
	$template = get_post($t);
	$editors = '';
	$eds = get_additional_editors($template->ID);
	if(!is_array($eds))
		$eds = array();
	if(current_user_can('edit_rsvpmaker',$template->ID) || (!empty($eds) && in_array($current_user->ID,$eds) ) )
	{
		if(!in_array($template->post_author,$eds))
		{
			$eds[] = $template->post_author;
		}
		foreach($eds as $ed) {
			$user = get_userdata($ed);
			$remove = (isset($_GET['remove'])) ? sprintf('<input type="checkbox" name="remove_editor[]" value="%s" /> Remove ',$ed) : '';
			$editors .= '<div>'.$remove.$user->user_email.' '.$user->display_name.'</div>';
		}
	}
	
	if(!empty($editors))
	{
		$editors = '<h3>Current Editors</h3>'.$editors;
	}
		printf('<h2>Update Editors List: %s</h2><form action="%s" method="post">%s
		<p><input type="hidden" name="t" value="%s" />
		Add by Email: <input type="email" name="editor_email" />
		%s</p>%s</form>',esc_html($post->post_title), admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_share'), $editors,$t,get_submit_button(__('Save','rsvpmaker')),rsvpmaker_nonce('return'));
	}
	
	$templates = rsvpmaker_get_templates();
	$tedit = $list = '';
	if(is_array($templates))
	foreach($templates as $template)
	{
	$eds = get_additional_editors($template->ID);
	if(current_user_can('edit_rsvpmaker',$template->ID) || (!empty($eds) && in_array($current_user->ID,$eds) ) )
	{
		$s = (!empty($t) && ($t == $template->ID)) ? ' selected="selected" ' : '';
		$tedit .= sprintf('<option value="%s" %s>%s</option>',$template->ID,$s,esc_html($template->post_title));
	}

	}
if(empty($tedit))
	echo "<p>You don't have any templates</p>";
else
{
		printf('<form action="%s" method="get">
		<input type="hidden" name="post_type" value="rsvpmaker">
		<input type="hidden" name="page" value="rsvpmaker_share">
		<p><select name="t">%s</select>%s</p>%s</form>',admin_url('edit.php'),$tedit,get_submit_button(__('Choose Template','rsvpmaker')),rsvpmaker_nonce('return'));
	
		printf('<form action="%s" method="get">
		<input type="hidden" name="post_type" value="rsvpmaker">
		<input type="hidden" name="page" value="rsvpmaker_share">
		<input type="hidden" name="remove" value="1">
		<p><select name="t">%s</select>%s</p>%s</form>',admin_url('edit.php'),$tedit,get_submit_button(__('Remove Editors','rsvpmaker')),rsvpmaker_nonce('return'));
}
	
}

function rsvpmaker_submission ($atts) {
global $rsvp_options;
$defaultto = isset($rsvp_options['submissions_to']) ? $rsvp_options['submissions_to'] : $rsvp_options['rsvp_to'];
$to = (isset($atts['to'])) ? $atts['to'] : $defaultto;
ob_start();
?>
<style>#rsvpmaker_submission label {
	display: inline-block;
	width: 100px;
}
</style>
<script>
tinymce.init({
selector:"textarea",plugins: "link",
block_formats: 'Paragraph=p',
menu: {
format: { title: 'Format', items: 'bold italic | removeformat' },
style_formats: [
{ title: 'Inline', items: [
	{ title: 'Bold', format: 'bold' },
	{ title: 'Italic', format: 'italic' },
]},]},
toolbar: 'bold italic link',
relative_urls: false,
remove_script_host : false,
document_base_url : "'.site_url().'/",
});	
</script>
<?php
printf('<form method="post" action="%s" id="rsvpmaker_submission">',get_permalink());
rsvpmaker_nonce();
if(isset($_GET['submission_error']))
{
	echo '<h2 id="results">Error</h2>';
	printf('<p>%s</p>',esc_html($_GET['submission_error']));
}

if(isset($_GET['success']))
{
echo '<h2 id="results">Event Submitted for Review</h2>';
$post_id = (int) $_GET['success'];
$post = get_post($post_id);
$expired = rsvpmaker_strtotime('-5 minutes');
$submitted_at = rsvpmaker_strtotime($post->post_modified);
if($submitted_at < $expired)
{
	echo '<p>Preview expired</p>';
}
else {
	echo '<p>Preview</p><div style="border: thin dotted #111; padding: 10px; margin: 10px;">';
	$t = get_rsvpmaker_timestamp($post_id);
	$date = rsvpmaker_date($rsvp_options['long_date'].' '.$rsvp_options['time_format'],$t);
	printf('<h3>%s</h3><h3>%s</h3>%s',esc_html($post->post_title),esc_html($date),wp_kses_post($post->post_content));	
	echo '</div>';
}
}
	$month = (int) date('m');
	$year = (int) date('Y');
	$day = (int) date('j');
	$hour = 12;
	$endhour = 13;
	$minutes = 0;
	$months = array('January','February','March','April','May','June','July','August','September','October','November','December');
printf('<input type="hidden" name="pagelink" value="%s">',get_permalink());
rsvphoney_ui();
?>	
<h2>Event Title: <input name="event_title"></h2>
	<div id="date"><label><?php echo __('Date','rsvpmaker');?></label> <input type="date" name="date">
	</div> 
	<div><label><?php echo __('Time','rsvpmaker');?></label> <input id="time" type="time" name="time" value="12:00"> to <input id="endtime" type="time" name="endtime" value="13:00">
<?php
if(!empty($atts['timezone']))
{
?>
<div><label>Timezone</label> 
<select id="timezone_string" name="timezone_string">
<script>
var tz = jstz.determine();
var tzstring = tz.name();
document.write('<option selected="selected" value="' + tzstring + '">' + tzstring + '</option>');
</script>
<optgroup label="U.S. (Common Choices)">
<option value="America/New_York">New York</option>
<option value="America/Chicago">Chicago</option>
<option value="America/Denver">Denver</option>
<option value="America/Los_Angeles">Los Angeles</option>
</optgroup>
<?php $choices = wp_timezone_choice('');
echo str_replace('<option selected="selected" value="">Select a city</option>','',$choices);
?>
</select> <br /><em>Choose a city in the same timezone as you.</em>
</div>
<?php
}//end display timezone
?>
<div><label>Your Name</label><input name="rsvpmaker_submission_contact" id="rsvpmaker_submission_contact" /></div>
<div><label>Email</label><input name="rsvpmaker_submission_email" id="rsvpmaker_submission_email" /></div>
<div><em>If you want your contact information to be published as part of the event listing, also include it in the description below.</em></div>
<p>Event Details<br /><textarea id="rsvpmaker_submission_description" name="rsvpmaker_submission_description" rows="5" cols="100"></textarea></p>
<input type="hidden" name="to" value="<?php echo esc_attr($to); ?>" /> 
<input type="hidden" name="rsvpmaker_submission_post" value="<?php echo get_permalink(); ?>" />
<?php rsvpmaker_recaptcha_output(); ?>
	<p><button>Submit</button></p></form>
<script>
jQuery(document).ready(function( $ ) {

var addhour = 1;

$('#time').change(function() {
	var time = $( this ).val();
	var date = new Date('2000-01-01T'+time);
	date.setTime(date.getTime()+(60*60*1000));
	$('#endtime').val(date.toLocaleTimeString('en-GB'));
});

});
</script>
	<?php
	return ob_get_clean();
}

function rsvpmaker_submission_post() {
	global $rsvp_options, $post;

	if(isset($_POST['rsvpmaker_submission_post']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	{
		$permalink = sanitize_text_field($_POST['rsvpmaker_submission_post']);
		$author = isset($rsvp_options['submission_author']) ? $rsvp_options['submission_author'] : 1;
		$title = sanitize_text_field(stripslashes($_POST['event_title']));
		$datetime = sanitize_text_field($_POST['date']) .' '.sanitize_text_field($_POST['time']);
		$t = rsvpmaker_strtotime($datetime);
		$endtime = sanitize_text_field($_POST['endtime']);
		$contact = sanitize_text_field(stripslashes($_POST['rsvpmaker_submission_contact']));
		$email = sanitize_text_field($_POST['rsvpmaker_submission_email']);
		$description = sanitize_textarea_field(stripslashes($_POST['rsvpmaker_submission_description']));
		$description = strip_tags($description,'<strong><em><a><b><i>');
		$description = wp_kses_post(rsvpautog($description));
		if($t < time()) {
			return; //ignore dates from the past. common spam pattern. no error message to give them cues	
		}

		if(!is_admin() && !empty($rsvp_options["rsvp_recaptcha_site_key"]) && !empty($rsvp_options["rsvp_recaptcha_secret"]))
		{
		if(!rsvpmaker_recaptcha_check ($rsvp_options["rsvp_recaptcha_site_key"],$rsvp_options["rsvp_recaptcha_secret"]))	{
			$r = add_query_arg('submission_error','Failed security check',$permalink).'#results';
			wp_redirect($r);
			exit();
			}	
		}

		$to = sanitize_text_field($_POST['to']);
		if(!is_email($to))
			$to = $rsvp_options['rsvp_to'];
		if(empty($title))
			$missing[] = 'event title';
		if(empty($datetime))
			$missing[] = 'date of event';
		if(empty($description))
			$missing[] = 'description';
		if(empty($contact))
			$missing[] = 'contact name';
		if(empty($email))
			$missing[] = 'contact email';
		if(!empty($missing))
		{
			$r = add_query_arg('submission_error',sprintf('missing data %s',implode("\n",$missing)),$permalink).'#results';
			wp_redirect($r);
			exit();
		}
		if(!is_email($email))
		{
			$r = add_query_arg('submission_error','invalid email address',$permalink).'#results';
			wp_redirect($r);
			exit();
		}
		
		$data['post_title'] = $title;
		$data['post_content'] = $description.'<!-- wp:rsvpmaker/placeholder {"text":"Submitted by '.$contact.' '.$email.'"} /-->';
		$data['post_author'] = $author;
		$data['post_status'] = 'draft';
		$data['post_type'] = 'rsvpmaker';
		$post_id = wp_insert_post($data);

		add_rsvpmaker_date($post_id,$datetime,'set',$endtime );
		if(!empty($_POST['timezone_string']) )
		{
			add_post_meta($post_id,"_add_timezone",true);
			add_post_meta($post_id,"_convert_timezone",true);
			add_post_meta($post_id,"_rsvp_timezone_string",sanitize_text_field($_POST['timezone_string']));		
		}
		add_post_meta($post_id,'_rsvpmaker_submission',date('Y-m-d H:i:s'));
	
		$mail['subject'] = "Event submission: ".$title.' '.$cddate;
		$mail['html'] = $description.sprintf('<hr />
		<p><a href="%s">Edit / Approve</a></p>
		<p>Submitted by %s %s / <a href="%s">submission page</a></p>',admin_url('post.php?action=edit&post='.$post_id),esc_html($contact),esc_html($email),esc_url_raw($_POST['pagelink']));
		$mail['fromname'] = $contact;
		$mail['from'] = $email;
		$mail['to'] = $to;
		rsvpmailer($mail);
		$r = add_query_arg('success',$post_id,$permalink).'#results';
		wp_redirect($r);
		exit();
	}
}

add_shortcode('rsvpmaker_submission','rsvpmaker_submission');

/*
 * New columns
 */
add_filter('manage_rsvpmaker_posts_columns', 'rsvpmaker_edit_columns');
// the above hook will add columns only for default 'post' post type, for CPT:
// manage_{POST TYPE NAME}_posts_columns
function rsvpmaker_edit_columns( $column_array ) {
	unset($column_array["tags"]);
	$column_array['event_dates'] = __('Event Dates','rsvpmaker');
	$column_array['rsvpmaker_end'] = __('End Time','rsvpmaker');
	$column_array['rsvpmaker_display'] = __('Display','rsvpmaker');
	return $column_array;
}

/*
 * quick_edit_custom_box allows to add HTML in Quick Edit
 * Please note: it files for EACH column, so it is similar to manage_posts_custom_column
 */

function rsvpmaker_quick_edit_fields( $column_name, $post_type ) {
global $post;
if(!get_rsvp_date($post->ID))
	return; // only for dated events, not templates etc
	// you can check post type as well but is seems not required because your columns are added for specific CPT anyway

switch( $column_name ) :
		case 'event_dates': {

			// you can also print Nonce here, do not do it ouside the switch() because it will be printed many times
			rsvpmaker_nonce();

			// please not the fieldset classes could be:
			// inline-edit-col-left, inline-edit-col-center, inline-edit-col-right
			// each class for each column, all columns are float:left,
			// so, if you want a left column, use clear:both element before
			// the best way to use classes here is to look in browser "inspect element" at the other fields

			// for the FIRST columns only, it opens <fieldset> element, all our fields will be there
			echo '<fieldset class="inline-edit-col-right"><div class="inline-edit-col"><div class="inline-edit-group wp-clearfix">';

			echo '<label class="alignleft">
					<span class="title">Datetime</span>
					<span class="input-text-wrap"><input type="text" class="quick_event_date" id="quick_event_date-'.$post->ID.'" post_id="'.$post->ID.'" name="event_dates" value=""></span>
					<span id="quick_event_date_text-'.$post->ID.'"></span>
				</label>';

			break;

		}
		case 'rsvpmaker_end': {
			$end_type = get_rsvpmaker_meta($post->ID,'_firsttime',true);
			$end = get_rsvpmaker_meta($post->ID,'_endfirsttime',true);
			echo '<label class="alignleft">
			<span class="title">End Time</span>
			<span class="input-text-wrap"><input type="time" class="quick_end_time" id="quick_end_time-'.$post->ID.'" post_id="'.$post->ID.'" name="end_time" value=""></span>
			<span id="quick_end_time_text-'.$post->ID.'"></span>
</label>';

			break;

		}
		case 'rsvpmaker_display': {
			$end_type = get_rsvpmaker_meta($post->ID,'_firsttime',true);
			$end = get_rsvpmaker_meta($post->ID,'_endfirsttime',true);
			//if(!empty($end_type) && (strpos($end,':') > 0))
			$options = array('set' => 'Show End Time','allday' => 'All Day/Times Not Shown','multi|2' => '2 Days','multi|3' => '3 Days','multi|4' => '4 Days','multi|5' => '5 Days','multi|6' => '6 Days','multi|7' => '7 Days');
			echo '<label class="alignleft">
			<span class="title">Time Display</span>
			<select name="quick_time_display" class="quick_time_display">
			<option value="">End Time Not Shown</option>';
			foreach($options as $key => $option)
				printf('<option value="%s">%s</option>',$key,$option);
			echo '</select>
</label>';

			// for the LAST column only - closing the fieldset element
			echo '</div></div></fieldset>';

			break;

		}

	endswitch;
}

/*
 * Quick Edit Save
 */

function rsvpmaker_quick_edit_save( $post_id ){
	if(empty($_POST['event_dates']))
		return;
	// check user capabilities
	if ( !current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// check nonce
	if(!wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
		return;

	if ( isset( $_POST['event_dates'] ) ) {
		if(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$_POST['event_dates'])) {
			$date = sanitize_text_field($_POST['event_dates']);
			update_post_meta( $post_id, '_rsvp_dates', $date );
		}
		else
			return;
	}
	if ( isset( $_POST['end_time'] ) ) {
		if(preg_match('/^\d{2}:\d{2}$/',$_POST['end_time'])) {
		 $end = sanitize_text_field($_POST['end_time']);
		 update_post_meta( $post_id, '_endfirsttime', $end );
		} 
	}
	 if ( isset( $_POST['quick_time_display'] ) ) {
		update_post_meta( $post_id, '_firsttime', sanitize_text_field($_POST['quick_time_display']) );
		$display_type = sanitize_text_field($_POST['quick_time_display']);
		$enddatetime = rsvpmaker_make_end_date ($date,$display_type,$end);
		update_post_meta($post_id,'_rsvp_end_date',$enddatetime);
   	}
}
