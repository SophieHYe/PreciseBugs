<?php
use RSVPbyDrewM\MailChimp\MailChimp as MailChimpRSVP;

$rsvpmaker_message_type = '';

function rsvpmailer($mail, $description = '') {
	if(defined('RSVPMAILOFF'))
	{
		$log = sprintf('<p style="color:red">RSVPMaker Email Disabled</p><pre>%s</pre>',var_export($mail,true));
		return;
	}
	if(strpos($mail['to'],'@example.com'))
		return; // don't try to send to fake addresses
	if(empty($mail['skip_check']))
		$problem = rsvpmail_is_problem($mail['to']);
	else
		$problem = false;
	if($problem && ($rsvpmailer_rule != 'permit') ) {
		$mail['html'] = '[content omitted]';
		rsvpmaker_debug_log($mail,'rsvpmailer blocked sending to unsubscribed email');
		return $mail['to'].' not sent - '.$problem;
	}

	$mail['html'] = rsvpmaker_personalize_email($mail['html'],$mail['to'],$description);
	if(isset($mail['text']))
		$mail['text'] = rsvpmaker_personalize_email($mail['text'],$mail['to'],$description);
	if(isset($mail['html']) && strpos($mail['html'],'<style'))
		$mail['html'] = rsvpmaker_inliner( $mail['html'] );

	if(isset($mail['subject']))
		$mail['subject'] = do_shortcode($mail['subject']);

	if(isset($mail['html']))
	{
		$mail['html'] = do_shortcode($mail['html']);
	}
	global $post, $rsvp_options, $rsvpmaker_message_type;
	if(isset($mail['message_type']))
		$rsvpmaker_message_type = $mail['message_type'];

	$rsvpmailer_rule = apply_filters('rsvpmailer_rule','',$mail['to'], $rsvpmaker_message_type);
	if($rsvpmailer_rule == 'deny') {
		$mail['html'] = '[content omitted]';
		$message = $mail['to'].' blocks messages of the type: '.$rsvpmaker_message_type;
		return $message;
	}
	
	if(empty($rsvp_options["from_always"]) && !empty($rsvp_options["smtp_useremail"]))
		$rsvp_options["from_always"] = $rsvp_options["smtp_useremail"];
	
	$site_url = get_site_url();
	$p = explode('//',$site_url);
	$via = $p[1];
	if(empty($mail['fromname']))
		$mail['fromname'] = get_bloginfo('name');

	if(!strpos($mail['fromname'],'(via'))
		$mail['fromname'] = $mail['fromname'] . ' (via '.$via.')';

	if(!empty($rsvp_options["log_email"]) && isset($post->ID))
		{
			$mail['timestamp'] = date('Y-m-d H:i');
			add_post_meta($post->ID, '_rsvpmaker_email_log',$mail);
		}
	$rsvp_options = apply_filters('rsvp_email_options',$rsvp_options);
	if(empty($mail['html']))
	$mail['html'] = wpautop($mail['text']);
	if(empty($mail['text']))
	$mail['text'] = strip_tags($mail['html']);

	if(!strpos($mail['text'],'rsvpmail_unsubscribe'))
		$mail['text'] .= "\n\nUnsubscribe from email notifications\n".site_url('?rsvpmail_unsubscribe='.$mail['to']);

	if(!strpos($mail['html'],'/html>'))
		$mail['html'] = "<html><body>\n".$mail['html']."\n</body></html>";		
	if(!strpos($mail['html'],'rsvpmail_unsubscribe'))
		$mail['html'] = str_replace('</html>',"\n<p>".sprintf('Unsubscribe from email notifications<br /><a href="%s">%s</a></p>',site_url('?rsvpmail_unsubscribe='.$mail['to']),site_url('?rsvpmail_unsubscribe='.$mail['to'])).'</html>',$mail['html']);
	
	if(function_exists('rsvpmailer_override'))
		return rsvpmailer_override($mail);
	if(!empty($rsvp_options['from_always']) && ($rsvp_options['from_always'] != $mail['from']))
	{
		if(empty($mail['replyto']))
			$mail['replyto'] = $mail['from'];
		$mail['from'] = $rsvp_options['from_always'];
	}
		
	if(!isset($rsvp_options["smtp"]) || empty($rsvp_options["smtp"]))
		{
		$to = $mail["to"];
		$subject = $mail["subject"];
		if(!empty($mail["html"]))
			{
			$mail["html"] = str_replace('*|UNSUB|*',site_url('?rsvpmail_unsubscribe='.$to),$mail["html"]);
			
				$body = $mail["html"];
				
				if(function_exists('set_html_content_type') ) // if using sendgrid plugin
					add_filter('wp_mail_content_type', 'set_html_content_type');
				else
					$headers[] = 'Content-Type: text/html; charset=UTF-8';
			}
		else {
			$body = $mail["text"];			
		}
		$headers[] = 'From: '.$mail["fromname"]. ' <'.$mail["from"].'>'."\r\n";
		if(!empty($mail["replyto"]))
			$headers[] = 'Reply-To: '.$mail["replyto"] ."\r\n";
		if(!empty($mail['attachments'])) {
			$attachments = $mail['attachments'];
			printf('<p>Attachments: %s</p>',var_export($attachments,true));
		}
		else
			$attachments = NULL;
		if(isset($mail["ical"]))
			{
			$temp = tmpfile();
			fwrite($temp, $mail["ical"]);
			$metaDatas = stream_get_meta_data($temp);
			$tmpFilename = $metaDatas['uri'];
			$icalname = $tmpFilename .'.ics';
			rename($tmpFilename,$icalname);
			$attachments[] = $icalname;
			}
			
		wp_mail( $to, $subject, $body, $headers, $attachments );
		if(function_exists('set_html_content_type') )
			remove_filter('wp_mail_content_type', 'set_html_content_type');
		return;
	}
	global $wp_version;//once 5.5 is out of beta, delete 2nd test
	if(is_wp_version_compatible('5.5')) {
	require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
	require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
	require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
	$rsvpmail = new PHPMailer\PHPMailer\PHPMailer();	
	}
	else
	{
		require_once ABSPATH . WPINC . '/class-phpmailer.php';
		require_once ABSPATH . WPINC . '/class-smtp.php';
		$rsvpmail = new PHPMailer();	
	}
	
	if(!empty($rsvp_options["smtp"]))
	{
		$rsvpmail->IsSMTP(); // telling the class to use SMTP
	
	if($rsvp_options["smtp"] == "gmail") {
		$rsvpmail->SMTPAuth   = true;                  // enable SMTP authentication
		$rsvpmail->SMTPSecure = "tls";                 // sets the prefix to the servier
		$rsvpmail->Host       = "smtp.gmail.com";      // sets GMAIL as the SMTP server
		$rsvpmail->Port       = 587;                   // set the SMTP port for the GMAIL server
	}
	elseif($rsvp_options["smtp"] == "sendgrid") {
	$rsvpmail->SMTPAuth   = true;                  // enable SMTP authentication
	$rsvpmail->Host = 'smtp.sendgrid.net';
	$rsvpmail->Port = 587; 
	}
	elseif(!empty($rsvp_options["smtp"]) ) {
	$rsvpmail->Host = $rsvp_options["smtp_server"]; // SMTP server
	$rsvpmail->SMTPAuth=true;
	if(isset($rsvp_options["smtp_prefix"]) && $rsvp_options["smtp_prefix"] )
		$rsvpmail->SMTPSecure = $rsvp_options["smtp_prefix"];                 // sets the prefix to the server
	$rsvpmail->Port=$rsvp_options["smtp_port"];
	}
 	
	}
	
 $rsvpmail->Username= (!empty($rsvp_options["smtp_username"]) ) ? $rsvp_options["smtp_username"] : '';
 $rsvpmail->Password= (!empty($rsvp_options["smtp_password"]) ) ? $rsvp_options["smtp_password"] : '';
 $rsvpmail->CharSet = 'UTF-8';
 $rsvpmail->AddAddress($mail["to"]);
 if(isset($mail["cc"]) )
	 $rsvpmail->AddCC($mail["cc"]);
if(isset($_GET['debug']))
{
	if(isset($mail['attachments']))
		echo '<p>Attachments set</p>';
	else
		echo '<p>Attachments NOT set</p>';
}
if(isset($mail['attachments']) && is_array($mail['attachments']))
	foreach($mail['attachments'] as $path) {
		$rsvpmail->AddAttachment($path);
		if(isset($_GET['debug']))
			printf('<p>Trying to add %s</p>',$path);
	}
$site_url = get_site_url();
$p = explode('//',$site_url);
$via = "(via ". $p[1].')';
if(is_admin() && isset($_GET['debug']))
	$rsvpmail->SMTPDebug = 4;
if(!empty($rsvp_options["smtp_useremail"]))
 	{
	 $rsvpmail->SetFrom($rsvp_options["smtp_useremail"], $mail["fromname"]. $via);
	 $rsvpmail->AddReplyTo($mail["from"], $mail["fromname"]);
	}
 else
	 $rsvpmail->SetFrom($mail["from"], $mail["fromname"]. $via); 
 $rsvpmail->ClearReplyTos();
 $rsvpmail->AddReplyTo($mail["from"], $mail["fromname"]);
if(!empty($mail["replyto"]))
 $rsvpmail->AddReplyTo($mail["replyto"]);

if(!empty($mail["bcc"]) && is_array($mail["bcc"]))
{
	 foreach($mail["bcc"] as $bcc)
		 $rsvpmail->AddBCC($bcc);
}

 $rsvpmail->Subject = $mail["subject"];
if($mail["html"])
	{
	$rsvpmail->isHTML(true);
	$rsvpmail->Body = $mail["html"];	
	if(isset($mail["text"]) && !strpos($mail["text"],'</')) // make sure there's no html in our text part
		$rsvpmail->AltBody = $mail["text"];
	else
	{
		$striphead = preg_replace('/<html.+\/head>/si','',$mail["html"]);
		$rsvpmail->AltBody = trim(strip_tags($striphead) );		
		$rsvpmail->WordWrap = 150;
	}
	}
	else
		{
			$rsvpmail->Body = $mail["text"];
			$rsvpmail->WordWrap = 150;
		}

	if(isset($mail["ical"]))
		$rsvpmail->Ical = $mail["ical"];
	
	try {
		$rsvpmail->Send();
	} catch (phpmailerException $e) {
		echo esc_html($e->errorMessage());
	} catch (Exception $e) {
		echo esc_html($e->getMessage()); //Boring error messages from anything else!
	}
	return $rsvpmail->ErrorInfo;
}


  // Avoid name collisions.
  if (!class_exists('RSVPMaker_Email_Options'))
      : class RSVPMaker_Email_Options
      {
          // this variable will hold url to the plugin  
          var $plugin_url;
          
          // name for our options in the DB
          var $db_option = 'chimp';
          
          // Initialize the plugin
          function __construct()
          {
              $this->plugin_url = trailingslashit( WP_PLUGIN_URL.'/'. dirname( plugin_basename(__FILE__) ) );

          }
          
          // handle plugin options
          function get_options()
          {
              $email = get_option('admin_email');
			  // default values
              $options = array(
			  'email-from' => $email
			  ,'email-name' => get_bloginfo('name')
			  ,'reply-to' => $email
			  ,'chimp-key' => ''
			  ,'chimp-list' => ''
			  ,'mailing_address' => ''
			  ,'chimp_add_new_users' => ''
			  ,'company' => ''
			  ,"add_notify" => $email
			  );
              
              // get saved options
              $saved = get_option($this->db_option);
              
              // assign them
              if (is_array($saved)) {
                  foreach ($saved as $key => $option)
                      $options[$key] = $option;
              }
              
              // update the options if necessary
              if ($saved != $options)
                  update_option($this->db_option, $options);
              
              //return the options  
              return $options;
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
			if(!empty($_POST['rsvpelist'])) {
				if(!wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
					die('data error');
			}

              $options = $this->get_options();
              
              if (isset($_POST["emailsubmitted"])) {
                 		
				  //$options = array();
				  if(is_array($options))
                  foreach ($options as $name => $value)
				  	{
					if(isset($_POST[$name]))
					$options[$name] = sanitize_text_field($_POST[$name]);
				  	}
				  if(empty($_POST['chimp_add_new_users']))
					 $options['chimp_add_new_users'] = false;
                  update_option($this->db_option, $options);

				if(isset($_POST["add_cap"]))
					{
						foreach($_POST["add_cap"] as $role => $type)
							{
								$role = sanitize_text_field($role);
								if($type == 'publish')
									add_rsvpemail_caps_role($role, true);
								else
									add_rsvpemail_caps_role($role);								
							}
					}

				if(isset($_POST["remove_cap"]))
					{
						foreach($_POST["remove_cap"] as $role => $type)
							{
								remove_rsvpemail_caps_role(sanitize_text_field($role));								
							}
					}
                  
                  echo '<div class="updated fade"><p>'.__('Plugin settings saved - mailing list.','rsvpmaker').'</p></div>';
              }
              
              // URL for form submit, equals our current page
              $action_url = admin_url('options-general.php?page=rsvpmaker-admin.php');
; ?>
<div class="wrap" style="max-width:950px !important;">
	<h3><?php esc_html_e('RSVPMaker Email List','rsvpmaker');?></h3>
	<p><?php esc_html_e("These settings are related to integration with the MailChimp broadcast email service, as well as RSVPMaker's own functions for broadcasting email to website members or people who have registered for your events.",'rsvpmaker');?></p>			
	<p><?php echo __("<strong>New for 2021</strong>: scroll down to see options for MailPoet integration",'rsvpmaker');?></p>			
	<div id="poststuff" style="margin-top:10px;">
	 <div id="mainblock" style="width:710px">
	<div class="dbx-content">
		 	<form name="EmailOptions" action="<?php echo esc_attr($action_url); ?>" method="post">
			 <?php rsvpmaker_nonce(); ?>
<?php
if(isset($_REQUEST['tab']) && $_REQUEST['tab'] == 'email')
{
?>
<input type="hidden" id="activetab" value="email" />
<?php	
}
?>
<input type="hidden" name="tab" value="email">
					<input type="hidden" name="emailsubmitted" value="1" /> 
					
                    <p><?php esc_html_e('Email From','rsvpmaker');?>: 
                      <input type="text" name="email-from" id="email-from" value="<?php echo esc_attr($options["email-from"]); ?>" />
                    </p>
                    <p><?php esc_html_e('Email Name','rsvpmaker');?>: 
                      <input type="text" name="email-name" id="email-name" value="<?php echo esc_attr($options["email-name"]); ?>" />
                    </p>
                    <p><?php esc_html_e('MailChimp API-Key','rsvpmaker');?>: 
                      <input type="text" name="chimp-key" id="chimp-key" value="<?php echo esc_attr($options["chimp-key"]); ?>" />
                    <br /><a target="_blank" href="http://kb.mailchimp.com/integrations/api-integrations/about-api-keys"><?php esc_html_e('Get an API key for MailChimp','rsvpmaker');?></a>
                    </p>
                    <p><?php esc_html_e('Default List','rsvpmaker');?>: 
                      <select name="chimp-list" id="chimp-list" ><?php echo mailchimp_list_dropdown($options["chimp-key"], $options["chimp-list"]); ?></select>
                    </p>
                    <p><?php esc_html_e('Attempt to Subscribe New WordPress user emails','rsvpmaker');?>: 
                      <input type="checkbox" name="chimp_add_new_users" id="chimp_add_new_users" value="1" <?php echo ($options["chimp_add_new_users"]) ? ' checked="checked" ' : ''; ?> />
                    </p>
                    <p><?php esc_html_e('Email to notify on API listSubscribe success/failure (optional)','rsvpmaker');?>: 
                      <input type="text" name="add_notify" id="add_notify" value="<?php echo esc_attr($options["add_notify"]); ?>" />
                    </p>

                    <p><?php esc_html_e('Mailing Address','rsvpmaker');?>: 
                      <input type="text" name="mailing_address" id="mailing_address" value="<?php echo esc_attr($options["mailing_address"]); ?>" />
                    </p>
                    <p><?php esc_html_e('Company','rsvpmaker');?>: 
                      <input type="text" name="company" id="company" value="<?php echo esc_attr($options["company"]); ?>" />
                    </p>
<h3><?php esc_html_e('Who Can Publish and Send Email?','rsvpmaker');?></h3>
<p><?php esc_html_e('By default, only the administrator has this right, but you can add it to other roles.','rsvpmaker');?></p>
<?php $allroles = get_editable_roles(  ); 
foreach($allroles as $slug => $properties)
{
if($slug == 'administrator')
	continue;
	echo esc_html($properties["name"]);
	if(isset($properties["capabilities"]['publish_rsvpemails']))
		printf(' %s <input type="checkbox" name="remove_cap[%s]" value="1" /> %s <br />',__('can publish and send broadcasts','rsvpmaker'),$slug,__('Remove','rsvpmaker'));
	elseif(isset($properties["capabilities"]['edit_rsvpemails']))
		printf(' %s <input type="checkbox" name="remove_cap[%s]" value="1" /> %s <br />',__('can edit draft emails','rsvpmaker'),$slug,__('Remove','rsvpmaker'));
	else
		printf(' %s <input type="radio" name="add_cap[%s]" value="edit" /> %s <input type="radio" name="add_cap[%s]" value="publish" /> %s <br />',__('grant right to','rsvpmaker'),$slug,__('Edit','rsvpmaker'),$slug,__('Publish and Send','rsvpmaker'));
}
?>

              <div class="submit"><input type="submit" name="Submit" value="<?php esc_html_e('Update','rsvpmaker');?>" /></div>
			</form>
<p>See also: <a target="_blank" href="<?php echo admin_url('edit.php?post_type=rsvpemail&page=rsvpmaker_email_template'); ?>">Email Template</a></p>

		</div>
				
	 </div>

	</div>
</div>

<div id="mailpoet">
<h2>MailPoet Integration</h2>
<p>MailPoet is a WordPress plugin and web service for sending email newsletters and other mass email, with the permission of the recipients.</p>
<p>You can add RSVPMaker events or event listings to the content of a MailPoet newsletter using a modified versions of the RSVPMaker Shortcodes (see the <a href="<?php echo admin_url('edit.php?post_type=rsvpemail&page=email_get_content'); ?>">Content for Email</a> screen and the <a href="https://rsvpmaker.com/knowledge-base/shortcodes/" target="_blank">RSVPMaker Shortcodes Documentation</a>).</p>
<?php
	if (class_exists(\MailPoet\API\API::class)) {
		$mailpoet_api = \MailPoet\API\API::MP('v1');
		$lists = $mailpoet_api->getLists();
		if(isset($_POST['rsvpmaker_mailpoet_list'])  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
		{
			$listok = (int) $_POST['rsvpmaker_mailpoet_list'];
			update_option('rsvpmaker_mailpoet_list',$listok);
			echo '<div class="notice notice-success"><p>MailPoet List Set</p></div>';
		}
		else
			$listok = get_option('rsvpmaker_mailpoet_list');
		$o = '<option value="">Choose List</option>';
		foreach($lists as $list) {
			$s = ($list['id'] == $listok) ? ' selected="selected" ' : '';
			$o .= sprintf('<option value="%d" %s>%s</option>',$list['id'], $s, $list['name']);
		}
	printf('<form method="post" action="%s"><p>List to use with "Add me to your email list" checkbox <select name="rsvpmaker_mailpoet_list">%s</select><button>Update</button></p>%s</form>',site_url(sanitize_text_field($_SERVER['REQUEST_URI'])),$o,rsvpmaker_nonce());
	}
	else
		echo '<p>MailPoet not enabled</p>';
?>
<h2>Add to Email Checkbox</h2>
<p>You can include an "Add me to your email list" checkbox on your RSVP forms to enlist people when they sign up for your events. This works with both MailChimp and MailPoet.</p>
<p><image src="<?php echo plugins_url('rsvpmaker/images/add_to_email_block.png'); ?>" width="600" height="348">
<br />Adding the Mailing List Checkbox block</p>
<p><image src="<?php echo plugins_url('rsvpmaker/images/add_to_email_checkbox.png'); ?>" width="468" height="578">
<br />Checkbox as it appears on the form</p>
</div>

<?php              
          }
      }
  
  else
      : exit("Class already declared!");
  endif;
  
  // create new instance of the class
  $RSVPMaker_Email_Options = new RSVPMaker_Email_Options();
  global $RSVPMaker_Email_Options;
  if (isset($RSVPMaker_Email_Options)) {
      // register the activation function by passing the reference to our instance
      register_activation_hook(__FILE__, array(&$RSVPMaker_Email_Options, 'install'));
  }

function RSVPMaker_Chimp_Add($email, $merge_vars, $status = 'pending') {
$chimp_options = get_option('chimp');
if(empty($chimp_options) || empty($chimp_options["chimp-key"]))
	return;

$apikey = $chimp_options["chimp-key"];
$listId = $chimp_options["chimp-list"]; 

try {
	$MailChimp = new MailChimpRSVP($apikey);
} catch (Exception $e) {
		wp_mail($chimp_options["add_notify"],"RSVPMaker_Chimp_Add error for $email ",$e->getMessage() .' email'.$email.' '.var_export($merge_vars,true));
    return;
}

$MailChimp = new MailChimpRSVP($apikey);

$result = $MailChimp->post("lists/$listId/members", array(
                'email_address' => $email,
                'merge_fields'        => $merge_vars,
				'status' => $status));

	if(!empty($chimp_options["add_notify"]))
	{
		 if($MailChimp->success() ) {
			wp_mail($chimp_options["add_notify"],"RSVPMaker_Chimp_Add invite sent to $email ",var_export($merge_vars, true));
		}
		else  {
			// factor out already on list?
			wp_mail($chimp_options["add_notify"],"RSVPMaker_Chimp_Add error for $email ",$MailChimp->getLastError());
		return $MailChimp->getLastError();
		}
	}
}

function RSVPMaker_register_chimpmail($user_id) {
$chimp_options = get_option('chimp');
//attempt to add people who register with website, if specified on user form
if(empty($chimp_options["chimp_add_new_users"]))
	return;
$new_user = get_userdata($user_id);
$email = $new_user->user_email;
$merge_vars["FNAME"] = $new_user->first_name;
$merge_vars["LNAME"] = $new_user->last_name;
RSVPMaker_Chimp_Add($email, $merge_vars);
}

add_filter( 'cron_schedules', 'rsvpmaker_add_weekly_schedule' ); 
function rsvpmaker_add_weekly_schedule( $schedules ) {
  $schedules['weekly'] = array(
    'interval' => 7 * 24 * 60 * 60, //7 days * 24 hours * 60 minutes * 60 seconds
    'display' => __( 'Once Weekly', 'rsvpmaker' )
  );
  return $schedules;
}

function rsvpmaker_next_scheduled( $post_id, $returnint = false ) {
	global $rsvp_options;
	global $rsvpnext_time;
	if($returnint && !empty($rsvpnext_time[$post_id]))
		return $rsvpnext_time[$post_id];
	//
    $crons = _get_cron_array();
    if ( empty($crons) )
        return false;
	$msg = '';
    foreach ( $crons as $timestamp => $cron ) {
		foreach($cron as $hook => $properties)
			{
			if($hook == 'rsvpmaker_cron_email')
				foreach($properties as $key => $property_array)
					{
					if(in_array($post_id,$property_array["args"]))
						{
						$schedule = (empty($property_array["schedule"])) ? '' : $property_array["schedule"];
						$rsvpnext_time[$post_id] = $timestamp;
						if($returnint)
							return $timestamp;
						return utf8_encode(rsvpmaker_date($rsvp_options["long_date"].' '.$rsvp_options["time_format"],$timestamp)).' '.$schedule;
						}
					}
			}
    }
    return false;
}

function rsvpmaker_scheduled_email_list(  ) {
global $wpdb;
global $rsvp_options;
global $post;
?>
<div class="wrap"> 
	<div id="icon-edit" class="icon32"><br /></div>
<h2><?php esc_html_e('Scheduled Email','rsvpmaker'); ?>  </h2> 
<p><?php esc_html_e('Use this screen to create or edit a schedule for sending your email at a specific date and time or on a recurring schedule.','rsvpmaker'); ?></p>
<?php

	if(isset($_REQUEST['post_id']))
	{
		$post = get_post((int) $_REQUEST['post_id']);
		printf('<h3>Email Post: %s</h3><p><a href="post.php?action=edit&post=%s">Edit Post</a> | <a href="%s">View Post</a></p>',esc_html($post->post_title),esc_attr($post->ID),get_permalink($post->ID));
		printf('<form action="%s" method="post">',admin_url('edit.php?post_type=rsvpemail&page=rsvpmaker_scheduled_email_list&post_id=').$post->ID);
		echo '<input type="hidden" name="post_id" value="'.$post->ID.'" />';
		RSVPMaker_draw_blastoptions();
		rsvpmaker_nonce();
		echo '<button>Save</button></form>';
	}
	else {
?>
<form method="get" action="edit.php"><input type="hidden" name="post_type" value="rsvpemail" /><input type="hidden" name="page" value="rsvpmaker_scheduled_email_list" /><h3><?php esc_html_e('Choose a RSVP Mailer Post','rsvpmaker'); ?></h3>
	<select name="post_id"><?php
$sql = "SELECT ID, post_title FROM $wpdb->posts WHERE post_type='rsvpemail' AND (post_status='publish' OR post_status='draft') ORDER BY ID DESC ";
$results = $wpdb->get_results($sql);
if(is_array($results))
foreach($results as $row)
{
	printf('<option value="%d">%s</option>',esc_attr($row->ID),esc_html($row->post_title));
}
		  ?></select>
<button>Get</button>
</form>
<?php
	}

	if(isset($_GET['cancel'])) {
		$args[] = intval($_GET['cancel']);
		$args[] = intval($_GET['user_id']);
		$timestamp = intval($_GET['timestamp']);
		wp_unschedule_event($timestamp,'rsvpmailer_delayed_send',$args);
	}
	
    $crons = _get_cron_array();
    if ( empty($crons) )
        esc_html_e('None','rsvpmaker');
	else
	{
	printf('<h3>%s</h3>',__('Scheduled','rsvpmaker'));
	printf('<table  class="wp-list-table widefat fixed posts" cellspacing="0"><thead><tr><th>%s</th><th>%s</th></tr></thead><tbody>',__('Title','rsvpmaker'),__('Schedule','rsvpmaker'));
    foreach ( $crons as $timestamp => $cron ) {
		foreach($cron as $hook => $properties)
			{
			if($hook == 'rsvpmaker_cron_email') {
				foreach($properties as $key => $property_array)
					{
					////print_r($property_array);
					$post_id = array_shift($property_array["args"]);
					$post = get_post($post_id);
					if(!empty($post))
						{
						printf('<tr><td>%s <br /><a href="%s">%s</a> | <a href="%s">%s</a></td><td>',$post->post_title,admin_url('post.php?post='.$post_id.'&action=edit'),__('Edit Post','rsvpmaker'),admin_url('edit.php?post_type=rsvpemail&page=rsvpmaker_scheduled_email_list&post_id='.$post_id),__('Schedule Options','rsvpmaker'));
						$schedule = (empty($property_array["schedule"])) ? '' : $property_array["schedule"];
						
						echo utf8_encode(rsvpmaker_date($rsvp_options["long_date"].' '.$rsvp_options["time_format"],$timestamp)).' '.$schedule;
						echo '</td></tr>';
						}
					}
				}
				if($hook == 'rsvpmailer_delayed_send') {
					//wp_schedule_single_event( $t, 'rsvpmailer_delayed_send', array($post->ID, $current_user->ID));
					foreach($properties as $key => $property_array)
						{
						////print_r($property_array);
						$post_id = $property_array["args"][0];
						$user_id = $property_array["args"][1];
						$post = get_post($post_id);
						if(!empty($post))
							{
							printf('<tr><td>%s (%s)<br /><a href="%s">%s</a> | <a href="%s">%s</a></td><td>',$post->post_title,__('Delayed Send','rsvpmaker'),admin_url('post.php?post='.$post_id.'&action=edit'),__('Edit Post','rsvpmaker'),admin_url('edit.php?post_type=rsvpemail&page=rsvpmaker_scheduled_email_list&timestamp='.$timestamp.'&cancel='.$post_id.'&user_id='.$user_id),__('Cancel','rsvpmaker'));
							$schedule = (empty($property_array["schedule"])) ? '' : $property_array["schedule"];
							
							echo utf8_encode(rsvpmaker_date($rsvp_options["long_date"].' '.$rsvp_options["time_format"],$timestamp)).' '.$schedule;
							echo '</td></tr>';
							}
						}
					}
			}
    } // end cron loop
	echo '</table>';
	}
?>
<h3><?php esc_html_e('Shortcodes for Scheduled Email Newsletters','rsvpmaker');?></h3>
<p><?php esc_html_e('Shortcodes you can include with scheduled email include [rsvpmaker_upcoming] (which should be used without the calendar grid) and these others, intended specifically for newsletter style messages. The attributes are optional and shown with the default values.','rsvpmaker');?></p>
<p>[rsvpmaker_recent_blog_posts weeks=&quot;1&quot;] (<?php esc_html_e('shows blog posts published within the timeframe, default 1 week','rsvpmaker');?>)</p>
<p>[rsvpmaker_looking_ahead days=&quot;30&quot; limit=&quot;10&quot;] (<?php esc_html_e('include after rsvpmaker_upcoming for a linked listing of just the headlines and dates of events farther out on the schedule','rsvpmaker');?>)</p>
<?php
}

function cron_schedule_options() {
global $post, $wpdb, $rsvp_options;
$event_timestamp = (int) get_post_meta($post->ID,'event_timestamp',true);
$args = array($post->ID);
$cron = get_post_meta($post->ID,'rsvpmaker_cron_email',true);
$notekey = get_rsvp_notekey();
$chimp_options = get_option('chimp');

$ts = rsvpmaker_next_scheduled($post->ID);
if(empty($ts))
	{
	echo '<p>Next broadcast: NOT SET</p>';
	$timestamp = rsvpmaker_strtotime('+1 hour');
	$day = (empty($cron["cron_active"])) ? (int) date('w',$timestamp) : $cron["cronday"];
	$hour = (empty($cron["cron_active"])) ? (int) date('G',$timestamp)  : $cron["cronhour"];
	}
else
	{
	printf('<p>Next broadcast: %s</p>',$ts);
	$ts = rsvpmaker_next_scheduled($post->ID, true);//get the integer value
	$day = date('w',$ts);
	$hour = date('G',$ts);
	}
?>
<p><?php if($chimp_options["chimp-key"]) { ?> <input type="checkbox" name="cron_mailchimp" value="1"  <?php if(!empty($cron["cron_mailchimp"])) echo 'checked="checked"' ?> > <?php echo __('Send to MailChimp List','rsvpmaker'); } ?> <input type="checkbox" name="cron_members" value="1"  <?php if(!empty($cron["cron_members"])) echo 'checked="checked"' ?> > <?php echo __('Send to Website Members','rsvpmaker');?><br />
<?php echo __('Send to This Address','rsvpmaker');?>: <input type="text" name="cron_to" value="<?php if(!empty($cron['cron_to'])) echo esc_attr($cron['cron_to']); ?>" />
</p>
<p><input type="radio" name="cron_active" value="1" <?php if(!empty($cron["cron_active"]) && ($cron['cron_active']) == '1') echo 'checked="checked"' ?> /> <?php echo __('Create schedule relative to this day/time','rsvpmaker');?>: <select name="cronday">
<?php
$days = array(__('Sunday','rsvpmaker'),__('Monday','rsvpmaker'),__('Tuesday','rsvpmaker'),__('Wednesday','rsvpmaker'),__('Thursday','rsvpmaker'),__('Friday','rsvpmaker'),__('Saturday','rsvpmaker'));
foreach($days as $index => $daytext)
	{
	$selected = ($index == $day) ? ' selected="selected" ' : '';
	printf('<option  value="%s" %s>%s</option>',$index,$selected,$daytext);
	}
?>
</select>
 <select name="cronhour"> 
<?php
for($i=0; $i < 24; $i++)
	{
	$selected = ($i == $hour) ? ' selected="selected" ' : '';
	$padded = ($i < 10) ? '0'.$i : $i;
	if($i == 0)
		$twelvehour = "12 a.m.";
	elseif($i == 12)
		$twelvehour = "12 p.m.";
	elseif($i > 12)
		$twelvehour = ($i - 12) ." p.m.";
	else		
		$twelvehour = $i." a.m.";

	printf('<option  value="%s" %s>%s / %s</option>',$padded,$selected,$twelvehour,$padded);
	}
?>
</select>
<?php esc_html_e('Recurrence','rsvpmaker');?> <select name="cronrecur"><option value=""><?php echo __('None','rsvpmaker');?></option>
<?php
$sked_meta = (empty($cron["cronrecur"])) ? ''  : $cron["cronrecur"];
$schedules = array('weekly','daily');
foreach ($schedules as $sked)
	{
	$selected = ($sked == $sked_meta) ? ' selected="selected" ' : '';
	printf('<option  value="%s" %s>%s</option>',esc_attr($sked),$selected,esc_html($sked));
	}
?>
</select>
</p>

<?php
if($event_timestamp)
{
	$evopt = '';
	$i = 1;
	$limit = 24 * 5;
	$days = 0;
	$dtext = '';
	while($i <= $limit)
	{
		if($i < 13)
			$i++;
		elseif($i == 13)
			{
				$i = 24;
				$days = 1;
				$dtext = ' (1 day before)';
			}
		else
			{
				$i += 24;
				$days++;
				$dtext = ' ('.$days .' days before)';
			}
		$deduct = $i * 60 * 60;
		$reminder = $event_timestamp - $deduct;
		$s = ($reminder == $ts) ? ' selected="selected" ' : '';
			
		$evopt .= '<option value="'.esc_attr($reminder).'"'.$s.'>'.rsvpmaker_date($rsvp_options['short_date'].' '.$rsvp_options['time_format'],$reminder).$dtext.'</option>';
	}
	$checked = (!empty($cron["cron_active"]) && ($cron["cron_active"]) == "relative") ? 'checked="checked"' : '';
printf('<p><input type="radio" name="cron_active" value="relative" '.$checked.' /> Set reminder relative to event %s<br /><select name="cron_relative">%s</select></p>',rsvpmaker_date($rsvp_options['short_date'].' '.$rsvp_options['time_format'],$event_timestamp),$evopt);
}
$checked = (!empty($cron["cron_active"]) && ($cron["cron_active"]) == "rsvpmaker_strtotime") ? 'checked="checked"' : '';
$timestring = ($ts) ? date('Y-m-d H:i:s',$ts) : date('Y-m-d H:00:00',rsvpmaker_strtotime('+ 1 hour'));
?>
<p><input type="radio" name="cron_active" value="rsvpmaker_strtotime" <?php echo $checked; ?> /> Custom date time string <input type="text" name="cron_rsvpmaker_strtotime" value="<?php echo esc_attr($timestring); ?>" /></p>
<p><input type="radio" name="cron_active" value="clear" /> Clear schedule</p>

<p>
<?php
$preview = (!empty($cron["cron_preview"]) ) ? (int) $cron["cron_preview"] : 0;
$preview_options = '';
for($i = 0; $i < 25; $i++)
	{
	$s = ($i == $preview) ? ' selected="selected"' : '';
	$label = ($i) ? $i.' hours before' : 'none';
	$preview_options .= sprintf('<option value="%d" %s>%s</option>',$i,$s,$label);
	}
?>
<?php esc_html_e('Preview','rsvpmaker');?> <select name="cron_preview"><?php echo $preview_options; ?></select>
</p>

<p>
<?php
$condition = (!empty($cron["cron_condition"]) ) ? $cron["cron_condition"] : 'none';
$blog_options = $condition_options = '';
$conditions = array('none' => __('none','rsvpmaker'),'events' => __('Future events','rsvpmaker'),'posts' => __('Recent posts','rsvpmaker'),'and' => __('Both events and posts','rsvpmaker'),'or' => __('Either events or posts','rsvpmaker'));
foreach($conditions as $slug => $text)
	{
	$s = ($slug == $condition) ? ' selected="selected"' : '';
	$condition_options .= sprintf('<option value="%s" %s>%s</option>',$slug,$s,$text);
	}
?>
<?php esc_html_e('Test for','rsvpmaker');?>: <select name="cron_condition"><?php echo $condition_options; ?></select>
<br /><em><?php esc_html_e('Broadcast will not be sent if it does not meet this test.','rsvpmaker');?></em>
</p>
<?php
	
$chosen = (int) get_post_meta($post->ID,$notekey,true);
$editorsnote["add_to_head"] = $editorsnote["note"] = '';

$recent = $wpdb->get_results("SELECT ID, post_title, post_status FROM $wpdb->posts WHERE post_type='post' AND (post_status='publish' OR post_status='draft') ORDER BY ID DESC LIMIT 0,20");
if(is_array($recent))
foreach($recent as $blog)
	{
	$s = ($blog->ID == $chosen) ? ' selected="selected"' : '';
	if($blog->ID == $chosen)
		$chosentitle = $blog->post_title;
	$title = ($blog->post_status == 'draft') ? $blog->post_title. ' (draft)' : $blog->post_title;
	$blog_options .= sprintf('<option value="%d" %s>%s</option>',esc_attr($blog->ID),$s,esc_html($title));
	}

if($chosen)
{
	$blog = get_post($chosen);
	$chosentitle = $blog->post_title;
	$blog_options .= sprintf('<option value="%d" selected="selected">%s</option><option value="">(Clear Selection)</option>',esc_attr($blog->ID),esc_html($blog->post_title));
	printf('<p>The current editor\'s note is based on the blog post <strong>%s</strong>. <a href="%s">(Edit)</a></p>',esc_html($chosentitle),admin_url('post.php?action=edit&post='.(int)$chosen));
	echo "<p>notekey $notekey</p>";
}
?>
<h3 id="editorsnote"><?php esc_html_e("Add Editor's Note for",'rsvpmaker'); if(empty($stamp)) echo ' Next broadcast'; else echo ' '.$ts;?></h3>
<input type="hidden" name="notekey" value="<?php echo esc_attr($notekey); ?>">

<p><?php esc_html_e("A blog post, either public or draft, can be featured as the editor's note at the top of your next email newsletter broadcast. The content of the post title will be added to the end of the email subject line, and the content of the post (up to the more tag, if included) will be included in the body of your email. There are two ways to add an Editor's Note blog post.",'rsvpmaker');?></p>

<p><input type="radio" name="status" value="" checked="checked" /> <strong><?php esc_html_e('Pick a blog post to feature','rsvpmaker');?>:</strong> <select name="chosen"><option value=""><?php esc_html_e('Select Blog Post','rsvpmaker');?></option><?php echo $blog_options; ?></select></p>

	<p><input type="radio" name="status" value="draft" /> <strong>Create a draft</strong> based on the headline and message below <br /><input type="radio" name="status" value="publish" /> <strong>Create and publish</strong> blog based on the headline and message below</strong><br /> <em>(<?php esc_html_e('This post will be used as the editors note at the top of your broadcast. Making it public on the blog is optional.','rsvpmaker');?>)</p>

<p><?php esc_html_e('Title/Subject','rsvpmaker');?>: <input type="text" name="notesubject" value="" /></p>
<p>Message:<br />
<textarea cols="100" rows="5" name="notebody"></textarea></p>

<?php

}

function RSVPMaker_draw_blastoptions() {
global $post;
$chimp_options = get_option('chimp');
if(empty($chimp_options["email-from"]))
	{
	printf('<p>%s: <a href="%s">%s</a></p>',__('You must fill in the RSVP Mailer settings before first use','rsvpmaker'),admin_url('options-general.php?page=rsvpmaker-email.php'),__('Settings','rsvpmaker'));
	return;
	}
if(empty($_GET["post_id"]))
	return;
//$post = get_post($_GET["post_id"]);
$scheduled_email = get_post_meta($post->ID,'scheduled_email',true);
if(empty($scheduled_email))
	$scheduled_email = array();
foreach($chimp_options as $label => $value)
{
	if(empty($scheduled_email[$label]))
		$scheduled_email[$label] = $value;
}
	
if(empty($scheduled_email['preview_to']))
	$scheduled_email['preview_to'] = $scheduled_email['email-from'];
if(empty($scheduled_email['template']))
	$scheduled_email['template'] = '';
	
$permalink = get_permalink($post->ID);
$template = get_rsvpmaker_email_template();
?>
<table>
<tr><td><?php esc_html_e('From Name','rsvpmaker');?>:</td><td><input type="text"  size="80" name="scheduled_email[email-name]" value="<?php echo esc_attr($scheduled_email["email-name"]); ?>" /></td></tr>
<tr><td><?php esc_html_e('From Email','rsvpmaker');?>:</td><td><input type="text" size="80"  name="scheduled_email[email-from]" value="<?php echo esc_attr($scheduled_email["email-from"]); ?>" /></td></tr>
<tr><td><?php esc_html_e('Preview To','rsvpmaker');?>:</td><td><input type="text" size="80" name="scheduled_email[preview_to]" value="<?php echo esc_attr($scheduled_email['preview_to']); ?>" />
</td></tr>
</table>

<p><?php esc_html_e('MailChimp List','rsvpmaker');?> <select name="scheduled_email[list]">
<?php
$chosen = (isset($scheduled_email["list"])) ? $scheduled_email["list"] : $chimp_options["chimp-list"];
echo mailchimp_list_dropdown($chimp_options["chimp-key"], $chosen);
?>
</select></p>

<?php
if(current_user_can('publish_rsvpemails'))
	cron_schedule_options();
}

function RSVPMaker_email_notice () {
global $post;
?>
	<div><h3>Email Editor</h3><p>Use the WordPress editor to compose the body of your message, with the post title as your subject line. <a href="<?php echo get_permalink($post->ID); ?>">View Post</a> will display your content in an email template, with a user interface for addressing options.</p>
<p>See also <a href="<?php echo admin_url('edit.php?post_type=rsvpemail&page=rsvpmaker_scheduled_email_list&post_id=').esc_attr($post->ID); ?>">Scheduled email options</a></p>
</div><?php
}

function my_rsvpemails_menu() {
if(!function_exists('do_blocks'))
add_meta_box( 'BlastBox', 'RSVPMaker Email Options', 'RSVPMaker_email_notice', 'rsvpemail', 'normal', 'high' );
}

add_action('admin_init','save_rsvpemail_data');

function save_rsvpemail_data() {

if(empty($_POST) || empty($_REQUEST['post_id']) || empty($_REQUEST['page']) || ($_REQUEST['page'] != 'rsvpmaker_scheduled_email_list'))
	return;
if( ! wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	return;
$post_id = (int) $_REQUEST['post_id'];

if(isset($_POST['scheduled_email'])  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
{
	if(is_array($_POST['scheduled_email']))
		$scheduled_email = array_map('sanitize_text_field',$_POST['scheduled_email']);
	else
		$scheduled_email = sanitize_text_field($_POST['scheduled_email']);
	update_post_meta($post_id,'scheduled_email',$scheduled_email);
}

if(!empty($_POST["email"]["from_name"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	{
	global $wpdb;
	global $current_user;
			
		$ev = array_map('sanitize_text_field',$_POST["email"]);
		if(empty($ev["headline"]))
			$ev["headline"] = 0;
		foreach($ev as $name => $value)
			{
			$value = sanitize_text_field($value);
			$field = '_email_'.$name;
			$single = true;
			$current = get_post_meta($post_id, $field, $single);
			 
			if($value && ($current == "") )
				add_post_meta($post_id, $field, $value, true);
			
			elseif($value != $current)
				update_post_meta($post_id, $field, $value);
			
			elseif($value == "")
				delete_post_meta($post_id, $field, $current);
			}
	}
	if( (isset($_POST["cron_active"]) || !empty($_POST["cron_relative"])) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {
		print_r($_POST);
	$chosen = (int) $_POST["chosen"]; 
	if(empty($_POST['cronday']))
	{
		$cronday = (int) $_POST['cronday'];
		$days = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
		$day = $days[$cronday];
	}
	if(!empty($_POST['notesubject']) || !empty($_POST['notebody']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	{
		global $current_user;
		$newpost['post_title'] = sanitize_text_field(stripslashes($_POST['notesubject']));
		$newpost['post_content'] = wp_kses_post(rsvpautog(stripslashes($_POST['notebody'])));
		$newpost['post_type'] = 'post';
		$newpost['post_status'] = sanitize_text_field($_POST['status']);
		$newpost['post_author'] = $current_user->ID;
		$chosen = wp_insert_post( $newpost );
	}
	
	if(!empty($_POST['notekey']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )	
		update_post_meta($post_id,sanitize_text_field($_POST['notekey']),$chosen);
	$args = array('post_id' => $post_id);
	$cron_checkboxes = array("cron_active", "cron_mailchimp", "cron_members", "cron_preview");
	foreach($cron_checkboxes as $check)
		{
			$cron[$check] = (isset($_POST[$check])) ? sanitize_text_field($_POST[$check]) : 0;
		}
	$cron['cron_to'] = sanitize_text_field($_POST['cron_to']);
	//clear if previously set
	wp_clear_scheduled_hook( 'rsvpmaker_cron_email', $args );
	wp_clear_scheduled_hook( 'rsvpmaker_cron_email_preview', $args );
	update_post_meta($post_id,'rsvpmaker_cron_email',$cron);

	if($cron["cron_active"] == '1')
		{
			$cron_fields = array("cronday", "cronhour", "cronrecur","cron_condition");
			foreach($cron_fields as $field)
				$cron[$field] = sanitize_text_field($_POST[$field]);
			$days = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
			$t = rsvpmaker_strtotime($days[$cron["cronday"]] .' '.$cron["cronhour"].':00');
			if($t < time())
				$t = rsvpmaker_strtotime('next '. $days[$cron["cronday"]] .' '.$cron["cronhour"].':00');
		}
	elseif(($cron["cron_active"] == 'relative') && !empty($_POST["cron_relative"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
		$t = (int) $_POST["cron_relative"];
	elseif(($cron["cron_active"] == 'rsvpmaker_strtotime') && !empty($_POST["cron_rsvpmaker_strtotime"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {
		$t = rsvpmaker_strtotime(sanitize_text_field($_POST["cron_rsvpmaker_strtotime"]));
	}
	
	
	if(!empty($t))
		{
			if($cron["cron_preview"])
				{
					$preview = $t - ($cron["cron_preview"] * 3600);
				}
			else
				$preview = 0;
			if(empty($cron["cronrecur"]))
				{
					// single cron
					wp_schedule_single_event( $t, 'rsvpmaker_cron_email', $args );
					if($preview)
						wp_schedule_single_event( $preview, 'rsvpmaker_cron_email_preview', $args );
				}
			else
				{
					wp_schedule_event( $t, $cron["cronrecur"], 'rsvpmaker_cron_email', $args );
					if($preview)
						wp_schedule_event( $preview, $cron["cronrecur"], 'rsvpmaker_cron_email_preview', $args );
				}
		}
	else
		{
		delete_post_meta($post_id,'rsvpmaker_cron_email');
		wp_clear_scheduled_hook( 'rsvpmaker_cron_email', $args );
		wp_clear_scheduled_hook( 'rsvpmaker_cron_email_preview', $args );
		}
	header('Location: ' . site_url(sanitize_text_field($_SERVER['REQUEST_URI'])));
	die();
	}
}

function rsvpevent_to_email () {
global $current_user, $rsvp_options, $email_context;

if(!current_user_can('edit_posts'))
	return;

if(!empty($_GET["rsvpevent_to_email"]) || !empty($_GET["post_to_email"]))
	{
		$email_context = true;
		if(!empty($_GET["post_to_email"]))
			{
				$id = (int) $_GET["post_to_email"];
				$permalink = get_permalink($id);
				$post = get_post($id);
				$content = '';
				if($post->post_type == 'rsvpmaker')
				{
					$content .= sprintf("<!-- wp:heading -->\n<h2>%s</h2>\n<!-- /wp:heading -->\n",$post->post_title);
					$block = rsvp_date_block($id);
					$blockgraph = str_replace('</div><div class="rsvpcalendar_buttons">','<br />',$block['dateblock']);
					$blockgraph = "<!-- wp:paragraph -->\n<p><strong>".strip_tags($blockgraph,'<br><a>').'</strong></p>'."\n<!-- /wp:paragraph -->";
					$content .= $blockgraph;
				}
				if(!empty($_GET['excerpt'])) {
					$content .= sprintf("<!-- wp:heading -->\n".'<h2><a href="%s" class="article">%s</a></h2>'."\n<!-- /wp:heading -->\n",$permalink,$post->post_title);
					$graphs = explode("<!-- /wp:paragraph -->",$post->post_content);
					for($i = 0; $i < 5; $i++)
					{
						if(!empty($graphs[$i]))
						$content .= $graphs[$i]."<!-- /wp:paragraph -->";
					}
					$content .= sprintf('<!-- wp:paragraph -->
					<p><a href="%s" class="readmore">Read More</a></p>
					<!-- /wp:paragraph -->',$permalink);		
				}
				else
					$content .= $post->post_content;
				if(($post->post_type == 'rsvpmaker') && get_rsvpmaker_meta($post->ID,'_rsvp_on',true))
				{
					$rsvplink = sprintf($rsvp_options['rsvplink'],get_permalink($id).'#rsvpnow');
					$content .= "\n\n<!-- wp:paragraph -->\n".$rsvplink."\n<!-- /wp:paragraph -->";
				}

				$title = $post->post_title;
			}
		else
		{
		$id = sanitize_text_field($_GET["rsvpevent_to_email"]);
		if(is_numeric($id))
			{
				if(empty($content))
					$content = '<!-- wp:rsvpmaker/event {"post_id":"'.$id.'","one_format":"button"} /-->';
				$title = get_the_title($id);
				$date = get_rsvp_date($id);		
				if($date) {
				
				$t = rsvpmaker_strtotime($date);
				global $rsvp_options;
				$title .= ' - '.rsvpmaker_date($rsvp_options["short_date"],$t);
				
				}
			}
		elseif($id == 'upcoming') {
			$content .= '<!-- wp:rsvpmaker/upcoming {"posts_per_page":"20","hideauthor":"true"} /-->';
			$title = 'Upcoming Events';
		}
		else
			return;
		}
		$my_post['post_title'] = $title;
		$my_post['post_content'] = $content;
		$my_post['post_type'] = 'rsvpemail';
		$my_post['post_status'] = 'publish';
		$my_post['post_author'] = $current_user->ID;
		if($post_id = wp_insert_post( $my_post ) )
			{
			if(!empty($t))
				add_post_meta($post_id,'event_timestamp',$t);
			$loc = admin_url("post.php?action=edit&post=".$post_id);
			wp_redirect($loc);
			exit;
			}
	}
}

function create_rsvpemail_post_type() {
global $rsvp_options;
  register_post_type( 'rsvpemail',
    array(
      'labels' => array(
        'name' => __( 'RSVP Mailer','rsvpmaker' ),
        'add_new_item' => __( 'Add New Email','rsvpmaker' ),
        'edit_item' => __( 'Edit Email','rsvpmaker' ),
        'new_item' => __( 'RSVP Emails','rsvpmaker' ),
        'singular_name' => __( 'RSVP Email','rsvpmaker' )
      ),
	'public' => true,
	'exclude_from_search' => true,
    'publicly_queryable' => true,
    'show_ui' => true, 
    'query_var' => true,
    'rewrite' => true,
    'capabilities' => array(
        'edit_post' => 'edit_rsvpemail',
        'edit_posts' => 'edit_rsvpemails',
        'edit_others_posts' => 'edit_others_rsvpemails',
        'publish_posts' => 'publish_rsvpemails',
        'read_post' => 'read_rsvpemail',
        'read_private_posts' => 'read_private_rsvpemails',
        'delete_post' => 'delete_rsvpemail'
    ),
    'hierarchical' => false,
    'menu_position' => 20,
	'menu_icon' => 'dashicons-email-alt',
    'supports' => array('title','editor'),
	'show_in_rest' => true
    )
  );
}

function add_rsvpemail_caps() {
    // gets the administrator role
    $admins = get_role( 'administrator' );
    $admins->add_cap( 'edit_rsvpemail' ); 
    $admins->add_cap( 'edit_rsvpemails' ); 
    $admins->add_cap( 'edit_others_rsvpemails' ); 
    $admins->add_cap( 'publish_rsvpemails' ); 
    $admins->add_cap( 'read_rsvpemail' ); 
    $admins->add_cap( 'read_private_rsvpemails' ); 
    $admins->add_cap( 'delete_rsvpemail' ); 
}

function add_rsvpemail_caps_role($role, $publish = false) {
    // gets the administrator role
    $emailers= get_role( $role );
    $emailers->add_cap( 'edit_rsvpemail' ); 
    $emailers->add_cap( 'edit_rsvpemails' );
    $emailers->add_cap( 'edit_others_rsvpemails' ); 
    $emailers->add_cap( 'read_rsvpemail' ); 
    $emailers->add_cap( 'read_private_rsvpemails' ); 
    $emailers->add_cap( 'delete_rsvpemail' ); 
	if($publish)
    	$emailers->add_cap( 'publish_rsvpemails' ); 
}

function remove_rsvpemail_caps_role($role) {
    // gets the administrator role
    $emailers= get_role( $role );
    $emailers->remove_cap( 'edit_rsvpemail' ); 
    $emailers->remove_cap( 'edit_rsvpemails' );
    $emailers->remove_cap( 'edit_others_rsvpemails' ); 
    $emailers->remove_cap( 'read_rsvpemail' ); 
    $emailers->remove_cap( 'read_private_rsvpemails' ); 
    $emailers->remove_cap( 'delete_rsvpemail' ); 
   	$emailers->remove_cap( 'publish_rsvpemails' ); 
}

// Template selection
function rsvpemail_template_redirect()
{

global $wp;
global $wp_query;

	if (isset($wp->query_vars["post_type"]) && ($wp->query_vars["post_type"] == "rsvpemail"))
	{
		if (have_posts())
		{
			include(WP_PLUGIN_DIR . '/rsvpmaker/rsvpmaker-email-template.php');
			die();
		}
		else
		{
			$wp_query->is_404 = true;
		}
	}
}

function rsvpmaker_text_version($content, $chimpfooter_text = '')
{
//match text links (not link around image, which would start with <)
$content = preg_replace('/<head.+<\/head>/s','',$content);
preg_match_all('/href="([^"]+)[^>]*>([^<]+)/',$content,$matches);
if(!empty($matches))
	{
	$content .= "\n\nLinks:\n\n";
		foreach($matches[1] as $index => $link)
			{
			$content .= $matches[2][$index] ."\n"; //anchor text	
			$content .= $link ."\n\n";
			}
	}
$text = trim(strip_tags($content));
$text = preg_replace("/[\r\n]{3,}/","\n\n",$text);

$text .= $chimpfooter_text;
return $text;
}

function rsvpmaker_personalize_email($content,$to,$description = '', $post_id = 0) {
$chimp_options = get_option('chimp');
if(empty($chimp_options['mailing_address'])) $chimp_options['mailing_address'] = apply_filters('rsvpmaker_mailing_address','[not set in RSVPMaker Mailing List settings]');
global $post;
if($post_id)
	$post = get_post($post_id);
$content = preg_replace('/\*.{1,4}EMAIL.{1,4}\*/',$to,$content);
$content = preg_replace('/\*.{1,4}UNSUB.{1,4}\*/',site_url('?rsvpmail_unsubscribe='.$to),$content);
$content = preg_replace('/\*.{1,4}REWARDS.{1,4}\*/','',$content);
$content = preg_replace('/\*.{1,4}LIST:DESCRIPTION.{1,4}\*/',$description,$content);
$content = preg_replace('/\*.{1,4}LIST:ADDRESS.{1,4}\*/',$chimp_options['mailing_address'],$content);
$content = preg_replace('/\*.{1,4}HTML:LIST_ADDRESS_HTML.{1,4}\*/',$chimp_options['mailing_address'],$content);
$content = preg_replace('/\*.{1,4}LIST:COMPANY.{1,4}\*/',$chimp_options['company'],$content);
$content = preg_replace('/\*.{1,4}CURRENT_YEAR.{1,4}\*/',date('Y'),$content);
if(isset($post->ID))
$content = preg_replace('/\*.{1,4}ARCHIVE.{1,4}\*/',get_permalink($post->ID),$content);
$content = preg_replace('/<a .+FORWARD.+/','',$content);
$content = preg_replace('/\*.+\*/','',$content); // not recognized, get rid of it.
return $content;	
}

add_shortcode('rsvpmaker_personalize_email_test','rsvpmaker_personalize_email_test');

function rsvpmaker_personalize_email_test () {
	$content = '<p>A sorry state of *|EMAIL|* other *%CEMAILxy* stuff *|LIST:DESCRIPTION|*</p>';
	$to = 'mew@here.com';
	$description = 'This is a test';
	return rsvpmaker_personalize_email($content,$to,$description = '');

}

function rsvpmailer_submitted($html,$text,$postvars,$post_id,$user_id) {
	global $wpdb, $current_user;
	$mail['html'] = $html;
	$from = (isset($postvars["user_email"])) ? $current_user->user_email : $postvars["from_email"];
	printf('<p>from %s</p>',$from);
	update_post_meta($post_id,'rsvprelay_from',$from);
	update_post_meta($post_id,'rsvprelay_fromname',$postvars["from_name"]);
	$post = get_post($post_id);
	if(!empty($postvars["preview"]))
		{
		$previewto = trim($postvars["previewto"]);
		if(is_email($previewto))
			{
			echo '<p>Sending preview to '.$previewto.'</p>';
			$mail["to"] = $previewto;
			$mail["from"] = $from;
			$mail["fromname"] = stripslashes($postvars["from_name"]);
			$mail["subject"] = stripslashes($postvars["subject"]);
			$result = rsvpmailer($mail,__('You were sent this message as a preview','rsvpmaker'));
			echo esc_html($result);
			if(!strpos($result,'unsubscribe') && !strpos($result,'blocked'))
				add_post_meta($post->ID,'rsvpmail_sent',$mail['to'].' (preview) '.rsvpmaker_date('r'));
			else
				add_post_meta($post->ID,'rsvpmail_blocked',$result);
			}
		else
			echo '<div style="color:red;">Error: '.$previewto.' - '.__('Error, not a single valid email address','rsvpmaker').'</div>';
			
		}

	if(!empty($postvars['test'])) {
		echo '<p>queuing test</p>';
		for($i=70; $i < 75; $i++)
			add_post_meta($post_id,'rsvprelay_to','test'.$i.'@rsvpmaker.com');
	}
	
	if(!empty($postvars["attendees"]) && !empty($postvars["event"]) )
	{
	
	if($postvars["event"] == 'any')
	{
	$sql = "SELECT DISTINCT email 
	FROM  `".$wpdb->prefix."rsvpmaker`";
	$title = 'one of our previous events';	
	}
	else {
	$event = (int) $postvars["event"];
	$event_post = get_post($event);
	$sql = "SELECT * 
	FROM  `".$wpdb->prefix."rsvpmaker` 
	WHERE  `event` = ".$event." ORDER BY  `email` ASC";
	$title = $event_post->post_title;
	}
	$results = $wpdb->get_results($sql);
	if(!empty($results))
	{
	echo '<p>'.__('Sending to','rsvpmaker').' '.sizeof($results).' '. __('event attendees','rsvpmaker').'</p>';
	foreach($results as $row)
		{
		if($problem = rsvpmail_is_problem($row->email))
			{
				add_post_meta($post_id,'rsvpmail_blocked',$problem);
				continue;
			}
		add_post_meta($post_id,'rsvprelay_to',$row->email);
		}
	}
	
	}
	
	if(!empty($postvars["rsvps_since"]) && !empty($postvars["since"]) )
	{
	$since = (int) $postvars["since"];
	$t = rsvpmaker_strtotime('-'.$since.' days');
	
	$date = date('Y-m-d',$t);
	
	$sql = "SELECT DISTINCT email 
	FROM  `".$wpdb->prefix."rsvpmaker` WHERE `timestamp` > '$date'";
	$title = 'one of our previous events';
	
	$results = $wpdb->get_results($sql);
	if(!empty($results))
	{
	echo '<p>'.__('Sending to','rsvpmaker').' '.sizeof($results).' '. __('RSVPs within the last ','rsvpmaker').' '.sanitize_text_field($postvars["since"]).' days</p>';
	foreach($results as $row)
		{
		if($problem = rsvpmail_is_problem($row->email))
			{
				add_post_meta($post_id,'rsvpmail_blocked',$problem);
				$unsubscribed[] = $row->email;
				continue;
			}
		add_post_meta($post_id,'rsvprelay_to',$row->email);
		}
	}
	
	}	
	
	if(!empty($postvars['custom_list'])) {
		preg_match_all ("/\b[A-z0-9][\w.-]*@[A-z0-9][\w\-\.]+\.[A-z0-9]{2,6}\b/", sanitize_textarea_field($postvars['custom_list']), $emails);
		if(!empty($emails[0]))
		{
			printf('<p>Sending to %s recipients (custom list)</p>',sizeof($emails[0]));
			update_post_meta($post_id,'message_description',__('This message was sent from','rsvpmaker').' '.sanitize_text_field($_SERVER['SERVER_NAME']));
			$from = (isset($postvars["user_email"])) ? $current_user->user_email : $postvars["from_email"];
			update_post_meta($post_id,'rsvprelay_fromname',stripslashes($postvars["from_name"]));
			foreach($emails[0] as $email)
				{
				if( $problem = rsvpmail_is_problem($email) )
					{
						$unsubscribed[] = $email;
						add_post_meta($post_id,'rsvpmail_blocked',$problem);
						continue;
					}
				add_post_meta($post_id,'rsvprelay_to',$email);
				}					
		}
	}

	if(!empty($postvars["members"]))
	{
	$users = get_users('blog='.get_current_blog_id());
	printf('<p>Sending to %s website members</p>',sizeof($users));
	update_post_meta($post_id,'message_description',__('This message was sent to you as a member of','rsvpmaker').' '.sanitize_text_field($_SERVER['SERVER_NAME']));
	$from = (isset($postvars["user_email"])) ? $current_user->user_email : $postvars["from_email"];
	update_post_meta($post_id,'rsvprelay_fromname',stripslashes($postvars["from_name"]));
	foreach($users as $user)
		{
		if( $problem = rsvpmail_is_problem($user->user_email) )
			{
				$unsubscribed[] = $user->user_email;
				add_post_meta($post_id,'rsvpmail_blocked',$problem);
				continue;
			}
		add_post_meta($post_id,'rsvprelay_to',$user->user_email);
		}
	}
	
	if(!empty($postvars["network_members"]) && user_can('manage_network',$user_id) )
	{
	update_post_meta($post_id,'message_description',__('This message was sent to you as a member of ','rsvpmaker').' '.sanitize_text_field($_SERVER['SERVER_NAME']));
	$from = (isset($postvars["user_email"])) ? $current_user->user_email : $postvars["from_email"];
	update_post_meta($post_id,'rsvprelay_fromname',sanitize_text_field(stripslashes($postvars["from_name"])));
	$users = get_users('blog='.get_current_blog_id());
	printf('<p>Sending to %s website members</p>',sizeof($users));
	foreach($users as $user)
		{
		if($problem = rsvpmail_is_problem($user->user_email))
			{
				add_post_meta($post_id,'rsvpmail_blocked',$problem);
				$unsubscribed[] = $user->user_email;
				continue;
			}
		update_post_meta($post_id,'rsvprelay_to',$user->user_email);
		}
	}
	
	if(!empty($postvars["mailchimp"]) )
	{
	$chimp_options = get_option('chimp');
	$MailChimp = new MailChimpRSVP($chimp_options["chimp-key"]);
	$listID = sanitize_text_field($postvars["mailchimp_list"]);
	update_post_meta($post_id, "_email_list",$listID);
	$custom_fields["_email_list"][0] = $listID;
	$segment_opts = array();
	
	if(!empty($postvars["mailchimp_exclude_rsvp"]))
	{
	$event = (int) $postvars["mailchimp_exclude_rsvp"];	
	$sql = "SELECT * 
	FROM  `".$wpdb->prefix."rsvpmaker` 
	WHERE  `event` = ".$event;
	$results = $wpdb->get_results($sql);
	if(is_array($results))
	foreach($results as $row)
		$rsvped[] = array('field' => 'EMAIL','condition_type' => 'EmailAddress','op' => 'not','value' => $row->email);
	if(!empty($rsvped))
		$segment_opts = array('match' => 'all','conditions' => $rsvped );
	}
	
	$input = array(
					'type' => 'regular',
					'recipients'        => array('list_id' => $listID),
					'segment_opts'        => $segment_opts,
					'settings' => array('subject_line' => sanitize_text_field(stripslashes($postvars["subject"])),'from_email' => sanitize_text_field($postvars["from_email"]), 'from_name' => sanitize_text_field($postvars["from_name"]), 'reply_to' => sanitize_text_field($postvars["from_email"]))
	);
	
	$campaign = $MailChimp->post("campaigns", $input);
	if(!$MailChimp->success())
		{
		echo '<div>'.__('MailChimp API error','rsvpmaker').': '.$MailChimp->getLastError().'</div>';
		return;
		}
	if(!empty($campaign["id"]))
	{
	$html = str_replace('<!-- mailchimp -->','<a href="*|FORWARD|*">Forward to a friend</a> | <a href="*|UPDATE_PROFILE|*">Update your profile</a><br>',$html);
	$content_result = $MailChimp->put("campaigns/".$campaign["id"].'/content', array(
	'html' => $html, 'text' => $text) );
	if(!$MailChimp->success())
		{
		echo '<div>'.__('MailChimp API error','rsvpmaker').': '.$MailChimp->getLastError().'</div>';
		return;
		}
	if(empty($postvars["chimp_send_now"]))
		{
		echo '<div>'.__('View draft on mailchimp.com','rsvpmaker').'</div>';
		}
	else // send now
		{
	$send_result = $MailChimp->post("campaigns/".$campaign["id"].'/actions/send');
	if($MailChimp->success())
		echo '<div>'.__('Sent MailChimp campaign','rsvpmaker').': '.$campaign["id"].'</div>';
	else
		echo '<div>'.__('MailChimp API error','rsvpmaker').': '.$MailChimp->getLastError().'</div>';
		}
	}
	
	}
	
	if(!empty($postvars))
		do_action("rsvpmaker_email_send_ui_submit",$postvars, $html, $text);
	
	// $unsubscribed is global, can be modified by action above
	if(!empty($unsubscribed))
		printf(__('Skipped %d unsubscribed emails','rsvpmaker'),count($unsubscribed) );
	
	//if any messages queued, make sure group email schedule is set
	if(get_post_meta($post->ID,'rsvprelay_to',true) && !wp_get_schedule('rsvpmaker_relay_init_hook'))
		wp_schedule_event( time(), 'doubleminute', 'rsvpmaker_relay_init_hook' );
} //end rsvpmailer_submitted

add_shortcode('rsvpmaker_template_inline_test','rsvpmaker_template_inline_test');

function rsvpmaker_template_inline_test($atts) {
	if(is_admin())
		return;
	if(isset($atts['id']))
	$html = rsvpmaker_template_inline(intval($atts['id']));
	$text = get_post_meta(intval($atts['id']),'_rsvpmail_text',true);
	return $html . '<pre>'.$text.'</pre>';
}

function rsvpmailer_delayed_send($post_id,$user_id) {
	$postvars = get_post_meta($post_id,'scheduled_send_vars',true);
	$html = get_post_meta($post_id,'_rsvpmail_html',true);
	$text = get_post_meta($post_id,'_rsvpmail_text',true);
	rsvpmailer_submitted($html,$text,$postvars,$post_id,$user_id);
}

add_action('rsvpmailer_delayed_send','rsvpmailer_delayed_send',10,2);

function rsvpmaker_email_send_ui($html, $text)
{
global $post;
global $custom_fields;
global $wpdb;
global $current_user;
global $rsvpmaker_cron_context;
global $rsvp_options;
if(!empty($rsvpmaker_cron_context))
	return;
$chimp_options = get_option('chimp');

ob_start();

if(isset($_POST['preview_text'])  && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	update_post_meta($post->ID,'_rsvpmailer_preview',sanitize_text_field(stripslashes($_POST['preview_text'])));

if(!current_user_can('publish_rsvpemails') )
	return;

$chimp_options = get_option('chimp');

if(!empty($_POST["subject"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	{
		$subject = sanitize_text_field(stripslashes($_POST["subject"]));
		if($post->post_title != $subject)
		{
			$post->post_title = $subject;
			$postarr["ID"] = $post->ID;
			$postarr["post_title"] = $subject;
			wp_update_post($postarr);
		}
	}

if(!empty($_POST["send_when"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
{
	$postvars = $_POST;
	$postvars = array_map('sanitize_text_field',$postvars);
	$manage_network = current_user_can('manage_network');
	if('now' == $_POST["send_when"])
		rsvpmailer_submitted($html,$text,$postvars,$post->ID,$current_user->ID);
	elseif('schedule' == $_POST["send_when"])
	{
		update_post_meta($post->ID,'scheduled_send_vars',$postvars);
		$t = rsvpmaker_strtotime($_POST['send_date'].' '.$_POST['send_time']);
		wp_schedule_single_event( $t, 'rsvpmailer_delayed_send', array($post->ID, $current_user->ID));
		printf('<p><em>Scheduling to send at %s</em></p>',rsvpmaker_date($rsvp_options['short_date'].' '.$rsvp_options['time_format'],$t));
	}
}

$permalink = get_permalink($post->ID);
$edit_link = get_edit_post_link($post->ID);
$events_dropdown = get_events_dropdown ();	

$o = '';
if($templates && is_array($templates))
foreach($templates as $index => $template)
	{
		$s = ($index == $t_index) ? ' selected="selected" ' : '';
		$o .= sprintf('<option value="%d" %s>%s</option>',$index,$s,$template['slug']);
	}
$queued = get_post_meta($post->ID,'rsvprelay_to');
if($queued) {
	rsvpmaker_relay_queue();
	//make sure this is turned on
	update_option('rsvpmaker_discussion_active',true);
	if(!wp_next_scheduled('rsvpmaker_relay_init_hook'))
		wp_schedule_event( time(), 'doubleminute', 'rsvpmaker_relay_init_hook' );
	$queued = get_post_meta($post->ID,'rsvprelay_to');
	if($queued) {
		//if more in queue
		printf('<p>%s emails queued to send (<a href="%s">Refresh</a></p>',sizeof($queued),$permalink);
		if(isset($_GET['show_log']))
			printf('</p>%s</p>',implode(', ',$queued));
	}
}
$sent = get_post_meta($post->ID,'rsvpmail_sent');
$blocked = get_post_meta($post->ID,'rsvpmail_blocked');
if($sent) {
	printf('<p>%s emails sent, %s blocked (<a href="%s">Refresh</a> | <a href="%s">Show Addresses</a>)</p>',sizeof($sent),sizeof($blocked),$permalink,add_query_arg('show_log',1,$permalink));
	if(isset($_GET['show_log'])){
		printf('</p>%s</p>',implode('<br>',$sent));
		printf('</p>%s</p>',implode('<br>',$blocked));
	}
}
if(!isset($_POST))
{
	$mailchimp_sent = get_post_meta($post->ID,'rsvp_mailchimp_sent');
	if($mailchimp_sent)
		printf('</p>%s</p>',implode(', ',$mailchimp_sent));	
}
?>
<style>
	#email-content {
		width: 650px;
		margin-left:auto;
		margin-right: auto;
		background-color: #fff;
		color: #000;
		padding: 10px;
	}
</style>
<p><a href="<?php echo esc_attr($edit_link); ?>"><?php esc_html_e('Edit','rsvpmaker');?></a> - <a href="<?php echo admin_url('post-new.php?post_type=rsvpemail'); ?>">New Email</a> - <a href="<?php echo admin_url(); ?>"><?php esc_html_e('Dashboard','rsvpmaker');?></a> - <a href="<?php echo site_url(); ?>"><?php esc_html_e('Visit Site','rsvpmaker');?></a></p>
<div style="width: 150px; float:right;"><button onclick="hideControls()">Hide Controls</button></div>
<form method="post" action="<?php echo esc_attr($permalink); ?>">
<?php rsvpmaker_nonce(); ?>
<table>
<tr><td><?php esc_html_e('Subject','rsvpmaker');?>:</td><td><input type="text"  size="50" name="subject" value="<?php echo esc_attr($post->post_title); ?>" /></td></tr>
<tr><td><?php esc_html_e('From Name','rsvpmaker');?>:</td><td><input type="text"  size="50" name="from_name" value="<?php echo (isset($custom_fields["_email_from_name"][0])) ? esc_attr($custom_fields["_email_from_name"][0]) : esc_attr($chimp_options["email-name"]); ?>" /></td></tr>
<tr><td><?php esc_html_e('From Email','rsvpmaker');?>:</td><td><input type="text" size="50"  name="from_email" value="<?php echo (isset($custom_fields["_email_from_email"][0])) ? esc_attr($custom_fields["_email_from_email"][0]) : esc_attr($chimp_options["email-from"]); ?>" />
</td></tr>
<tr><td><?php esc_html_e('Preview Text','rsvpmaker');?>:</td><td><input type="text" size="50"  name="preview_text" value="<?php echo rsvpmailer_preview(array()); ?>" />
</td></tr>
</table>
<div><input type="checkbox" name="user_email" value="1" checked="checked" /><?php esc_html_e('Except for MailChimp, use the email of the logged in user as from email.','rsvpmaker'); ?></div>
<p style="clear:both;"><?php esc_html_e('Send','rsvpmaker');?></p>
<div><input type="checkbox" name="preview" value="1"> <?php esc_html_e('Preview to','rsvpmaker');?>: <input type="text" name="previewto" value="<?php echo (isset($custom_fields["_email_preview_to"][0])) ? $custom_fields["_email_preview_to"][0] : $chimp_options["email-from"]; ?>" />
<br><em>Send yourself a test first to check email formatting. Certain WordPress blocks, such as columns, may not translate to email.</em>
</div>
<div><input type="checkbox" name="members" value="1" <?php if(isset($_GET['list']) && ($_GET['list'] == 'members') ) echo 'checked="checked"'; ?> > <?php esc_html_e('Website members','rsvpmaker');?></div>
<div><?php esc_html_e('Custom List','rsvpmaker');?><br /><textarea name="custom_list" rows="3" cols="80"></textarea></div>
<p><input type="checkbox" name="test" value="1" /> Test</p>
<?php if(is_multisite() && current_user_can('manage_network') && (get_current_blog_id() == 1)) {
?>
<div style="border: thin dotted red;"><strong>Network Administrator Only:</strong><br /> 
<input type="checkbox" name="network_members" value="1"> <?php esc_html_e('All users','rsvpmaker');?>
</div>
<?php
} ?>
<?php
if(!empty($chimp_options["chimp-key"]))
{
?>
<input type="checkbox" name="mailchimp" value="1" <?php if(isset($_GET['mailchimp'])) echo ' checked="checked" '; ?> > <?php esc_html_e('MailChimp list','rsvpmaker');?> <select name="mailchimp_list">
<?php
$chosen = (isset($custom_fields["_email_list"][0])) ? $custom_fields["_email_list"][0] : $chimp_options["chimp-list"];
echo mailchimp_list_dropdown($chimp_options["chimp-key"], $chosen);
?>
</select> <select name="chimp_send_now"><option value="1"><?php esc_html_e('Create and Send','rsvpmaker'); ?></option><option value="" <?php if(isset($_POST["mailchimp"]) && empty($_POST["chimp_send_now"])) echo ' selected="selected" '; ?> ><?php esc_html_e('Save as draft on mailchimp.com','rsvpmaker'); ?></option></select></div>
<?php if(!empty($rsvp_options['debug']))
{ //only if debug is on, show this feature.
?>
<div style="margin-left: 20px;">
<?php esc_html_e('Exclude Recipients who RSVPed to','rsvpmaker');?> <select name="mailchimp_exclude_rsvp">
<option value="">Choose Event</option>
<?php
echo $events_dropdown;
?>
</select>	
</div>
<?php
} // end if debug
}

?>
	<div><input type="checkbox" name="attendees" value="1"> <?php esc_html_e('Attendees','rsvpmaker');?> <select name="event"><option value=""><?php esc_html_e('Select Event','rsvpmaker');?></option><option value="any"><?php esc_html_e('Any event','rsvpmaker');?></option><?php echo $events_dropdown; ?></select></div>

	<div><input type="checkbox" name="rsvps_since" value="1"> <?php esc_html_e('RSVPs more recent than ','rsvpmaker');?> <input type="text" name="since" value="30" /> <?php esc_html_e('Days','rsvpmaker');?></div>
<?php
do_action("rsvpmaker_email_send_ui_options");
?>
<p><input type="submit" name="now" value="<?php esc_html_e('Send','rsvpmaker');?>" /> <input type="radio" name="send_when" value="now" checked="checked"> Now <input type="radio" name="send_when" value="schedule" > Schedule for <input type="date" name="send_date" value="<?php echo rsvpmaker_date('Y-m-d'); ?>"> <input name="send_time" type="time" value="<?php echo rsvpmaker_date('H:i',strtotime('+1 hour')); ?>"></p>
</form>
<script>
function hideControls() {
var x = document.getElementById("control-wrapper");
x.style.display = "none";
}
</script>
<?php

$ts = rsvpmaker_next_scheduled($post->ID);
if($ts)
	printf('<p><a href="%s">Preview scheduled broadcast</a> for %s',add_query_arg('cronemailpreview',$post->ID,$permalink),esc_html($ts));
	
return '<div id="control-wrapper" style="background-color: #FFFFFF; color: #000000;">'.ob_get_clean().'</div>';
}


function RSVPMaker_extract_email() {

global $wpdb;
$inchimp = '';
if(isset($_POST["emails"]) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
	{

$chimp_options = get_option('chimp');

$apikey = $chimp_options["chimp-key"];
$listId = $chimp_options["chimp-list"];
 
	preg_match_all ("/\b[A-z0-9][\w.-]*@[A-z0-9][\w\-\.]+\.[A-z0-9]{2,6}\b/", wp_kses_post($_POST["emails"]), $emails);
	$emails = $emails[0];
	foreach($emails as $email)
		{
			$email = strtolower($email);
			$unique[$email] = $email;
		}
	sort($unique);
	foreach($unique as $email)
		{
		$email = strtolower($email);
		$hash = md5($email);
		if(!empty($_POST["in_mailchimp"]))
			{
			if(!isset($MailChimp) && !empty($apikey))
				$MailChimp = new MailChimpRSVP($apikey);
			$member = $MailChimp->get("/lists/".$listId."/members/".$hash);
			if(!empty($member["id"]) )
				{
				$inchimp .= "\n<br />$email";
				continue;
				}
			}
		echo "\n<br />$email";
		}
if($inchimp)
	echo "<h3>In MailChimp</h3>$inchimp";

	}

; ?>
<div id="icon-options-general" class="icon32"><br /></div>
<h2><?php esc_html_e('Extract Emails','rsvpmaker');?></h2>
<p><?php esc_html_e('You can enter an disorganized list of emails mixed in with other text, and this utility will extract just the email addresses.','rsvpmaker');?></p>
<form id="form1" name="form1" method="post" action="">
<?php rsvpmaker_nonce(); ?>
  <p>
    <textarea name="emails" id="emails" cols="45" rows="5"></textarea>
  </p>
  <p><?php esc_html_e('Filter out emails that','rsvpmaker');?>:</p>
  <p>
    <input name="in_mailchimp" type="checkbox" id="in_mailchimp" checked="checked" />
  <?php esc_html_e('Are Registered in MailChimp','rsvpmaker');?></p>
  <p>
    <input type="submit" name="button" id="button" value="Submit" />
  </p>
</form>
<?php
}

function inline_array($text) {
$lines = explode("\n",$text);
$inline_array = array();
foreach($lines as $line)
	{
		$line = trim($line);
		if(strpos($line,'='))
			{	
			$parts = explode('=',$line);
			$inline_array[$parts[0]] = $parts[1];
			}
	}
return $inline_array;
}	
	
function rsvpemail_template () {
$ver = phpversion();
if (version_compare($ver, '7.1', '<'))
	printf('<div class="notice notice-warning"><p>The Emogrifier CSS inliner library, which is included to improve formatting of HTML email, relies on PHP features introduced in version 7.1 -- and is disabled because your site is on %s</p></div>',$ver);
?>
<div id="icon-options-general" class="icon32"><br /></div>
<?php

if(isset($_POST['extra_email_styles']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')))
	update_option('extra_email_styles',$_POST['extra_email_styles']);

?>
<h2><?php esc_html_e('RSVPMaker Email Design Templates','rsvpmaker');?></h2>
<p>Note: This function is being redesigned.</p>
<form id="email_style" name="email_style" method="post" action="<?php echo admin_url('edit.php?post_type=rsvpemail&page=rsvpmaker_email_template'); ?>">
<?php
rsvpmaker_nonce();
global $rsvp_options;
$chimp_options = get_option('chimp');
if(empty($chimp_options['mailing_address']))
	printf('<p><strong>%s</strong></p>',__('A physical mailing address should be entered in in RSVPMaker Mailing List settings.','rsvpmaker'));
?>
<p>
  <?php esc_html_e('Email Styles','rsvpmaker');?> (<?php esc_html_e('applied to all email templates','rsvpmaker');?>)<br />
    <textarea name="extra_email_styles" cols="80" rows="5"><? echo get_option('extra_email_styles'); ?></textarea>
	  <br /><a target="_blank" href="<?php echo site_url('?show_rsvpmaker_included_styles=1') ?>"><?php esc_html_e('View default email styles','rsvpmaker');?></a>
  </p>
<p>
<button><?php esc_html_e('Save','rsvpmaker');?></button>
</p>

	<p><a href="<?php echo admin_url('edit.php?post_type=rsvpemail&page=rsvpmaker_email_template&reset_email_template=1'); ?>"><?php esc_html_e('Reset to default template','rsvpmaker');?></a></p>

<h3>Key CSS Selectors</h3>
<p>a.rsvplink {/* your CSS here */} - style the RSVP button</p>
<p>figcaption, .wp-caption {/* your CSS here */} - captions for images</p>
<p>#messagefooter {/* your CSS here */} - footer that includes unsubscribe link, info about your site/company</p>
<p>div.rsvpmaker {/* your CSS here */} - an embedded event listing</p>
<p>div.rsvpmaker-entry-title {/* your CSS here */} - title of event</p>
<p>div.dateblock {/* your CSS here */} - date and time</p>
<p>p.rsvpmeta {/* your CSS here */} - event type (category)</p>
<p>.wp-block-column {/* your CSS here */} - adjustments to the Gutenberg column block (media queries not reliably supported in email)</p>

</form>

<?php

} // end rsvpemail template form

function my_rsvpemail_menu() {
global $rsvp_options;

$parent_slug = "edit.php?post_type=rsvpemail";
$page_title = __("Scheduled Email",'rsvpmaker');
$menu_title = $page_title;
$capability = 'edit_others_rsvpemails';
$menu_slug = "rsvpmaker_scheduled_email_list";
$function = "rsvpmaker_scheduled_email_list";

add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

$parent_slug = "edit.php?post_type=rsvpemail";
$page_title = __("Email Design Templates",'rsvpmaker');
$menu_title = $page_title;
$capability = 'edit_others_rsvpemails';
$menu_slug = "rsvpmaker_email_template";
$function = "rsvpemail_template";

add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

$parent_slug = "edit.php?post_type=rsvpemail";
$page_title = __("Notification Templates",'rsvpmaker');
$menu_title = $page_title;
$capability = 'edit_others_rsvpemails';
$menu_slug = "rsvpmaker_notification_templates";
$function = "rsvpmaker_notification_templates";

add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

$parent_slug = "edit.php?post_type=rsvpemail";
$page_title = __("Content for Email",'rsvpmaker');
$menu_title = $page_title;
$capability = 'edit_others_rsvpemails';
$menu_slug = "email_get_content";
$function = "email_get_content";

add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

$parent_slug = "edit.php?post_type=rsvpemail";
$page_title = __("Extract Addresses",'rsvpmaker');
$menu_title = $page_title;
$capability = 'edit_others_rsvpemails';
$menu_slug = "extract";
$function = "RSVPMaker_extract_email";

add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

$parent_slug = "edit.php?post_type=rsvpemail";
$page_title = __("Unsubscribed List",'rsvpmaker');
$menu_title = $page_title;
$capability = 'edit_others_rsvpemails';
$menu_slug = "unsubscribed_list";
$function = "unsubscribed_list";

add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

if(!empty($rsvp_options["log_email"]))
{
$parent_slug = "edit.php?post_type=rsvpemail";
$page_title = __("Email Log",'rsvpmaker');
$menu_title = $page_title;
$capability = 'edit_others_rsvpemails';
$menu_slug = "email_log";
$function = "email_log";

add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);
}

}

function rsvpmaker_mailpoet_notice() {
	$screen = get_current_screen();
	if(strpos($screen->id,'mailpoet-newsletter-editor')) {
		echo '<div class="notice notice-info">';
		echo '<div><p><button id="showhide_mailpoet_shortcodes">Show RSVPMaker Shortcodes for MailPoet</button></p></div>';
		echo '<div id="rsvpmaker_mailpoet_shortcodes_notice">';
		rsvpmaker_mailpoet_shortcodes();
		echo '<div><p><button id="showhide_mailpoet_shortcodes2">Hide RSVPMaker Shortcodes for MailPoet</button></p></div>';
		echo '</div>';
		echo '</div>';
?>
<script>
jQuery(document).ready(function( $ ) {

console.log('mailpoet shortcodes button');

$('#rsvpmaker_mailpoet_shortcodes_notice').hide();
var mailpoetshow = false;
function toggleMailPoetShort()  {
		if(mailpoetshow)
		{
			$('#showhide_mailpoet_shortcodes').text('Show RSVPMaker Shortcodes for MailPoet');
			$('#rsvpmaker_mailpoet_shortcodes_notice').hide();
			mailpoetshow = false;
		}
		else {
			$('#showhide_mailpoet_shortcodes').text('Hide RSVPMaker Shortcodes for MailPoet');
			$('#rsvpmaker_mailpoet_shortcodes_notice').show();
			mailpoetshow = true;
		}
}

$('#showhide_mailpoet_shortcodes').click( function() {
	toggleMailPoetShort();
}
);

$('#showhide_mailpoet_shortcodes2').click( function() {
	toggleMailPoetShort();
}
);

});
</script>
<?php
	} 
}

add_action('admin_notices','rsvpmaker_mailpoet_notice');

function rsvpmaker_mailpoet_shortcodes() {
?>

<p>You can use standard <a href="https://rsvpmaker.com/knowledge-base/shortcodes/" target="_blank">RSVPMaker shortcodes</a> with a custom:prefix. For rsvpmaker_upcoming, rsvpmaker_next, and rsvpmaker_one, you can include a formatting attribtue, such as [custom:rsvpmaker_next format="compact"]<br />
Useful formatting codes for email ("excerpt" works well in most cases):
<br />format="excerpt" - shows the first few paragraphs, or all the content up to the more tag (if included), plus a link to read more and the RSVP button if active.
<br />format="compact" - just the headline, date and button (if RSVPs active).
<br />format="button_only" - embeds just the RSVP button
<br />format="embed_dateblock" - embeds just the date and time block
</p>
<textarea rows="10" style="width:80%;">
[custom:rsvpmaker_upcoming hideauthor="1" limit="5" days="14"] list upcoming events
[custom:event_listing show_time="1" title="Upcoming Events"] links with dates and titles of upcoming events
[custom:rsvpmaker_next format="excerpt"] next event
[custom:rsvpmaker_next rsvp_on="1" format="excerpt"] next event with RSVPs active
[custom:rsvpmaker_youtube url="YOUTUBE URL" link="LINK IF DIFFERENT"] display preview image of a youtube video, with to view
<?php
	$events = get_future_events(array('limit' => 20));
	foreach($events as $event) {
		printf('[custom:rsvpmaker_one post_id="%d" format="excerpt"] %s %s'."\n",$event->ID,$event->post_title,$event->date);
	}
echo '</textarea>';
}

function email_log () {
global $wpdb;
$sql = "SELECT * FROM $wpdb->postmeta WHERE meta_key = '_rsvpmaker_email_log' ORDER BY meta_id DESC LIMIT 0, 100";
$results = $wpdb->get_results($sql);
if($results)
foreach($results as $row)
	{
		$mail = unserialize($row->meta_value);
		if(is_array($mail))
		foreach($mail as $index => $value)
			printf('<p><strong>%s</strong></p><div>%s</div>',$index,$value);
	}
}

function unsubscribed_list () {
global $wpdb;
$table = $wpdb->prefix . "rsvpmailer_blocked";
$action = admin_url('edit.php?post_type=rsvpemail&page=unsubscribed_list');
if(isset($_POST['remove']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) ) {
	foreach($_POST['remove'] as $email) {
		rsvpmail_remove_problem($email);
	}
}

if(isset($_POST['problems']))
{
	$code = sanitize_text_field($_POST['code']);
	preg_match_all ("/\b[A-z0-9][\w.-]*@[A-z0-9][\w\-\.]+\.[A-z0-9]{2,6}\b/", $_POST['problems'], $emails);
	$emails = $emails[0];
	foreach($emails as $email)
		{
			rsvpmail_add_problem($email,$code);
			$email = strtolower($email);
		}
}

printf('<h1>%s</h1><p>%s</p>',__('Unsubscribed and Blocked','rsvpmaker'),__('If recipients have clicked unsubscribe on a confirmation message or any other message sent directly from RSVPMaker (as opposed to via MailChimp) they will be listed here. You can also track messages that are being blocked by the recipient\'s ISP (not currently automated). You can manually remove emails from this list, but should only do so <strong><em>at the request of the recipient</em></strong>.','rsvpmaker'));
$sql = "SELECT * FROM $table ORDER BY code, timestamp DESC";
$results = $wpdb->get_results($sql);
if(!empty($results))
{
printf('<form method="post" action="%s"><table><tr><th>Unblock</th><th>Email</th><th>Issue</th></tr>',$action);
foreach($results as $row)
{
	printf('<tr><td><input type="checkbox" name="remove[]" value="%s" /></td><td>%s</td><td>%s</td></tr>',$row->email,$row->email,$row->code);	
}
echo '</table><p><input type="submit" value="Submit"></p>'.rsvpmaker_nonce('return').'</form>';
}

printf('<h2>Add an Email Addresses as Unsubscribed Or Blocked</h2><form method="post" action="%s">
<p>
<textarea rows="5" cols="60" name="problems"></textarea>
<br><em>Separated by spaces of on separate lines</em>
</p>%s
<p>
<input type="radio" name="code" value="unsubscribed" checked="checked"> Unsubscribed
<input type="radio" name="code" value="blocked"> Blocked
<button>Add</button></form>',$action,rsvpmaker_nonce('return'));

}


function RSVPMaker_chimpshort($atts, $content = NULL ) {

$atts = shortcode_atts( array(
  'query' => 'post_type=post&posts_per_page=5',
  'format' => '',
  ), $atts );

	ob_start();
	query_posts($atts["query"]);

if ( have_posts() ) {
while ( have_posts() ) : the_post(); ?>
<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
<h3 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
<?php
if(isset($atts["format"]) && ($atts["format"] == 'excerpt'))
	{
; ?>
<div class="excerpt-content">

<?php the_excerpt(); ?>

</div><!-- .excerpt-content -->
<?php	
	}
elseif(isset($atts["format"]) && ($atts["format"] == 'full'))
	{
; ?>
<div class="entry-content">

<?php the_content(); ?>

</div><!-- .entry-content -->
<?php
}
?>
</div>
<?php 
endwhile;
wp_reset_query();
} 
	
	$content = ob_get_clean();

	return $content;
}

function email_get_content () {
global $wpdb;
;?>
<div id="icon-options-general" class="icon32"><br /></div>
<h2>Content for Email</h2>

<?php

$event_options = $options = '<option value="">'.__('None selected','rsvpmaker').'</option>';
$event_options .= '<option value="upcoming">'.__('Upcoming Events','rsvpmaker').'</option>';
$posts = '';
$future = get_future_events();
if(is_array($future))
foreach($future as $event)
	{
	$event_options .= sprintf('<option value="%s">%s - %s</option>'."\n",$event->ID,$event->post_title,date('F j, Y',rsvpmaker_strtotime($event->datetime)));
	}


$sql = "SELECT ID, post_title FROM $wpdb->posts WHERE post_status='publish' AND post_type='post' ORDER BY post_date DESC LIMIT 0, 50";
$wpdb->show_errors();
$results = $wpdb->get_results($sql, ARRAY_A);
if($results)
{
foreach ($results as $row)
	{
	$posts .= sprintf("<option value=\"%d\">%s</option>\n",$row["ID"],substr($row["post_title"],0,80));
	}
$posts = '<optgroup label="'.__('Recent Posts','rsvpmaker').'">'.$posts."</optgroup>\n";
}

$po = '';
$pages = get_pages();
foreach($pages as $page)
	$po .= sprintf("<option value=\"%d\">%s</option>\n",$page->ID,substr($page->post_title,0,80));
?>
<form action="<?php echo admin_url('edit.php?post_type=rsvpemail'); ?>" method="get">
<?php rsvpmaker_nonce(); ?>
<p><?php esc_html_e('Email Based on Event','rsvpmaker');?>: <select name="rsvpevent_to_email"><?php echo $event_options; ?></select>
</select>
</p>
<button><?php esc_html_e('Load Content','rsvpmaker');?></button>
</form>	
<form action="<?php echo admin_url('edit.php?post_type=rsvpemail'); ?>" method="get">
<?php rsvpmaker_nonce(); ?>
<p><?php esc_html_e('Email Based on Post','rsvpmaker');?>: <select name="post_to_email"><?php echo $posts; ?></select>
</select>
<br /><input type="radio" name="excerpt" value="0" checked="checked"> <?php esc_html_e('Full text','rsvpmaker');?> <input type="radio" name="excerpt" value="1"> <?php esc_html_e('Excerpt','rsvpmaker');?>
</p>
<button><?php esc_html_e('Load Content','rsvpmaker');?></button>
</form>	
<form action="<?php echo admin_url('edit.php?post_type=rsvpemail'); ?>" method="get">
<?php rsvpmaker_nonce(); ?>
<p><?php esc_html_e('Email Based on Page','rsvpmaker');?>: <select name="post_to_email"><?php echo $po; ?></select>
</select>
<br /><input type="radio" name="excerpt" value="0" checked="checked"> <?php esc_html_e('Full text','rsvpmaker');?> <input type="radio" name="excerpt" value="1"> <?php esc_html_e('Excerpt','rsvpmaker');?>
</p>
<button><?php esc_html_e('Load Content','rsvpmaker');?></button>
</form>	

<h2>Shortcodes for MailPoet</h2>
<p>If you use the MailPoet integration, you can include a variations on the <a href="https://rsvpmaker.com/knowledge-base/shortcodes/" target="_blank">RSVPMaker Shortcodes</a> that include the custom: prefix.</p>

<?php

rsvpmaker_mailpoet_shortcodes();

} // end chimp get content

function rsvpmaker_email_list_okay ($rsvp) {
		$mergevars["FNAME"] = stripslashes($rsvp["first"]);
		$mergevars["LNAME"] = stripslashes($rsvp["last"]);
		RSVPMaker_Chimp_Add($rsvp["email"],$mergevars);
		mailpoet_email_list_okay($rsvp);
}

function get_rsvpmaker_email_template() {
global $rsvpmail_templates;
//$templates = get_option('rsvpmaker_email_template');

$templates[0]['slug'] = 'default';
$templates[0]['html'] = '<html>
<head>
<title>*|MC:SUBJECT|*</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
#background {background-color: #FFFFFF; padding: 10px; margin-top: 0; max-width: 800px;}
#content {padding: 5px; background-color: #FFFFFF; margin-left: auto; margin-right: auto; margin-top: 10px; margin-bottom: 10px; padding-bottom: 50px;}
</style>
</head>
<body>
<div style="display: none">[rsvpmailer_preview]</div>
<div id="background">
<div id="content">

<div style="font-size: small; border: thin dotted #999;">Email not displaying correctly? <a href="*|ARCHIVE|*" class="adminText">View it in your browser.</a></div>

[rsvpmaker_email_content]

</div><!-- end content area -->
</div><!-- end background -->

<div id="messagefooter">
*|LIST:DESCRIPTION|*<br>
<br>
<a href="*|UNSUB|*">Unsubscribe</a> *|EMAIL|* from this list | <a href="*|FORWARD|*">Forward to a friend</a> | <a href="*|UPDATE_PROFILE|*">Update your profile</a>
<br>
<strong>Our mailing address is:</strong><br>
*|LIST:ADDRESS|*<br>
<em>Copyright (C) *|CURRENT_YEAR|* *|LIST:COMPANY|* All rights reserved.</em><br>    
*|REWARDS|*</div>
</body>
</html>';
$templates[1]['slug'] = 'transactional';
$templates[1]['html'] = '<html>
<head>
<title>*|MC:SUBJECT|*</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
<div style="display: none">[rsvpmailer_preview]</div>
<div id="tx-background">
<div id="tx-content">

[rsvpmaker_email_content]

<div id="messagefooter">
*|LIST:DESCRIPTION|*<br>
<br>
<a href="*|UNSUB|*">Unsubscribe</a> *|EMAIL|* from this list | <a href="*|FORWARD|*">Forward to a friend</a> | <a href="*|UPDATE_PROFILE|*">Update your profile</a>
<br>
<strong>Our mailing address is:</strong><br>
*|LIST:ADDRESS|*<br>
<em>Copyright (C) *|CURRENT_YEAR|* *|LIST:COMPANY|* All rights reserved.</em><br>    
*|REWARDS|*</div>

</div><!-- end content area -->
</div><!-- end background -->
</body>
</html>';

$styles = rsvpmaker_included_styles();
foreach($templates as $index => $template)
{
	$html = $template['html'];
	$html = add_style_to_email_html($html);
	$templates[$index]['html'] = $html;
}
$rsvpmail_templates = $templates;	
return $templates;
}

function add_style_to_email_html($html) {
	$styles = rsvpmaker_included_styles();
	if(strpos($html,'<style'))
		$html = preg_replace('/<styl.+>/','<style type="text/css">'."\n".$styles."\n",$html);
	else
		$html = str_replace('</head>',"<style>\n".$styles."\n</style></head>",$html);
	return $html;
}

function rsvpmaker_tx_email($event_post, $mail) {

//used with rsvpmaker_email_content shortcode in template
global $rsvpmaker_tx_content;
$rsvpmaker_tx_content = $mail["html"];
$templates = get_rsvpmaker_email_template();
if(!empty($event_post->ID))
$t_index = (int) get_post_meta($event_post->ID,'rsvp_tx_template',true);
if(empty($t_index))
	$t_index = (int) get_option('rsvpmaker_tx_template');

$template = $templates[$t_index]["html"];
if(!strpos($template,'*|UNSUB')) // if not already in template
$rsvpmaker_tx_content .= '<div id="messagefooter">
    *|LIST:DESCRIPTION|*<br>
    <br>
    <a href="*|UNSUB|*">Unsubscribe</a> *|EMAIL|* from this list
</div>';

$rsvpfooter_text = '

==============================================
*|LIST:DESCRIPTION|*

Unsubscribe *|EMAIL|* from this list:
*|UNSUB|*
';

$text = rsvpmaker_text_version($mail["html"], $rsvpfooter_text);

$mail["html"] = do_blocks(do_shortcode($template));

$mail["html"] = preg_replace('/(?<!")(https:\/\/www.youtube.com\/watch\?v=|https:\/\/youtu.be\/)([a-zA-Z0-9_\-]+)/','<p><a href="$0">Watch on YouTube: $0<br /><img src="https://img.youtube.com/vi/$2/mqdefault.jpg" width="320" height="180" /></a></p>',$mail["html"]);

	if(rsvpmail_is_problem($mail["to"]) )
		{
			return;
		}
	rsvpmailer($mail,__('<div class="rsvpexplain">This message was sent to you as a follow up to your registration for','rsvpmaker').' '.$event_post->post_title.'</div>' );
}

function rsvpmaker_email_content ($atts, $content) {
global $wp_filter;
global $post;
global $templatefooter;
$templatefooter = isset($atts["templatefooter"]);
global $rsvpmaker_tx_content;
if(!empty($rsvpmaker_tx_content))
	return $rsvpmaker_tx_content;
if(function_exists('bp_set_theme_compat_active'))
bp_set_theme_compat_active( false );//stop buddypress from causing trouble

ob_start();
$corefilters = array('convert_chars','wpautop','wptexturize','event_content');
foreach($wp_filter["the_content"] as $priority => $filters)
	foreach($filters as $name => $details)
		{
		//keep only core text processing or shortcode
		if(!in_array($name,$corefilters) && !strpos($name,'hortcode'))
			{
			if(isset($_GET["debug"]))
				echo '<br />Remove '.$name.' '.$priority;
			$r = remove_filter( 'the_content', $name, $priority );
			}
		}
if(isset($_GET["debug"])) {
	echo '<pre>';
	//print_r($wp_filter);
	echo '</pre>';
}

global $rsvp_options;

?>
<!-- editors note goes here -->
<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
<?php if(get_post_meta($post->ID,"_email_headline",true)) { ; ?>
<h1 class="entry-title"><?php the_title(); ?></h1>
<?php } ; ?>
<div class="entry-content">
<?php echo wp_kses_post($post->post_content); ?>
</div><!-- .entry-content -->
</div><!-- #post-## -->
<div class="footer"><!-- footer --></div>
<?php 
$content = ob_get_clean();
if(function_exists('do_blocks'))
	$content = do_blocks($content);
$content = str_replace('<img ','<img style="display: block; max-width: 90%;" ',$content);
return $content;
}

function mailchimp_list_dropdown($apikey, $chosen = '') {
if(empty($apikey))
	return '<option value="">none</option>';
try {
    $MailChimp = new MailChimpRSVP($apikey);
} catch (Exception $e) {
    return '<option value="">none '.$e->getMessage().'</option>';
}

$retval = $MailChimp->get('lists');

$options = '';
if (is_array($retval)){
	foreach ($retval["lists"] as $list){
		$s = ($chosen == $list['id']) ? ' selected="selected" ' : '';
		$options .=  '<option value= "'.esc_attr($list['id']).'"'. " $s >".esc_html($list['name']).'</option>';
	}
}
return $options;
}

function event_to_embed($post_id, $event_post = NULL, $context = '') {
		global $email_context;
		global $rsvp_options;
		global $post;
		$backup = $post;
		$email_context = true;
		if(empty($event_post))
			$event_post = get_post($post_id);
		$event_embed["subject"] = $event_post->post_title;
		$event_embed["content"] = sprintf('<!-- wp:heading -->
<h2 class="email_event"><a href="%s">%s</a></h2>
<!-- /wp:heading -->'."\n",get_permalink($post_id),apply_filters('the_title',$event_post->post_title));
		if($event_post->post_type == 'rsvpmaker')
		{
		$date_array = rsvp_date_block($post_id);
		$dateblock = trim(strip_tags($date_array["dateblock"]));
		$dur = $date_array["dur"];
		$last_time = $date_array["last_time"];
		$tmlogin = (strpos($event_post->post_content,'[toastmaster')) ? sprintf('<!-- wp:paragraph -->
<p><a href="%s">Login</a> to sign up for roles</p>
<!-- /wp:paragraph -->',wp_login_url( get_post_permalink( $post_id ) ) ) : '';
		$event_embed["content"] .= sprintf('<!-- wp:paragraph -->
<p><strong>%s</strong></p>
<!-- /wp:paragraph -->',$dateblock).$tmlogin;			
		}
		$event_embed["content"] .= do_blocks(do_shortcode($event_post->post_content));
		if(get_rsvpmaker_meta($post_id,'_rsvp_on',true))
		{
		if(get_post_meta($post_id,'_rsvp_count',true))
			$event_embed["content"] .= rsvpcount($post_id);
		if($context != 'confirmation')
			{ // add the rsvp button / link except in confirmation messages that include Update RSVP version
				$rsvplink = get_rsvp_link($post_id);
				$event_embed["content"] .= "<!-- wp:paragraph -->\n".$rsvplink."\n<!-- /wp:paragraph -->";		
			}
		}
		$post = $backup;
		if(function_exists('do_blocks')){
			$event_embed["content"] = do_blocks($event_embed["content"]);			
		}
		else 
		$event_embed["content"] = wpautop($event_embed["content"]);
		$post = $backup;
		return $event_embed;
}

function rsvpmaker_upcoming_email($atts) {
	$output = '';
	$weeks = (empty($atts["weeks"])) ? 4 : $atts["weeks"];
	$end = date('Y-m-d',rsvpmaker_strtotime('+'.$weeks.' weeks')). ' 23:59:59';
	$upcoming = get_future_events(' a1.meta_value < "'.$end.'"');
	if(is_array($upcoming))
	foreach($upcoming as $embed)
		{
		$event = event_to_embed($embed->ID,$embed);
		$output .= $event["content"]."\n\n";
		}
	if(isset($atts["looking_ahead"]))
		{
			$weeksmore = $atts["looking_ahead"];
			$label = (empty($atts["looking_ahead_label"])) ? '<h2>Looking Ahead</h2>' : '<h2 class="looking_ahead">'.$atts["looking_ahead_label"].'</h2>';
			$extra = date('Y-m-d',rsvpmaker_strtotime($end .' +'.$weeksmore.' weeks')). ' 23:59:59';
			$upcoming = get_future_events(' a1.meta_value > "'.$end .'" AND  a1.meta_value < "'.$extra.'"');
			if(is_array($upcoming))
				{
					$output .= $label."\n";
					foreach($upcoming as $ahead)
						$output .= sprintf('<p><a href="%s">%s - %s</a></p>',get_permalink($ahead->ID),$ahead->post_title,date('F j',rsvpmaker_strtotime($ahead->datetime)));
				}
		}	
	return $output;
}

function is_email_context () {
		global $email_context;
		return (isset($email_context) && $email_context);
}

function rsvpmaker_cron_email_send($post_id) {
global $rsvpmaker_cron_context;
global $wp_query;
$rsvpmaker_cron_context = 2; // 2 means send live
$wp_query = new WP_Query( array('post_type' => 'rsvpemail','p' => $post_id) );
include plugin_dir_path(__FILE__) . 'rsvpmaker-email-template.php';
}

function rsvpmaker_cron_email_preview($args) {
global $rsvpmaker_cron_context;
global $wp_query;
$rsvpmaker_cron_context = 1; // 1 means preview
if(isset($args['post_id']))
	$post_id = $args['post_id'];
else
	$post_id = (int) $args;// single argument comes as single value
$wp_query = new WP_Query( array('post_type' => 'rsvpemail','p' => $post_id) );
include plugin_dir_path(__FILE__) . 'rsvpmaker-email-template.php';
}

function rsvpmaker_cron_email_preview_now() {
	if(isset($_GET['cronemailpreview']))
	{
		rsvpmaker_cron_email_preview(sanitize_text_field($_GET['cronemailpreview']));
		die('scheduled email preview');
	}
}

add_filter( 'post_row_actions', 'rsvpmaker_row_actions', 10, 2 );
function rsvpmaker_row_actions( $actions, WP_Post $post ) {
	global $current_user;
    if ($post->post_type == 'rsvpemail') {
        return $actions;
    }
	if(current_user_can('edit_post',$post->ID))
	{
		if($post->post_type == 'rsvpmaker') {
			$actions['rsvpmaker_options'] = sprintf('<a href="%s">%s</a>',admin_url('edit.php?post_type=rsvpmaker&page=rsvpmaker_details&post_id=').$post->ID,__('Event Options','rsvpmaker'));
			$actions['rsvpmaker_invite2'] = sprintf('<a href="%s">%s</a>',admin_url('edit.php?post_type=rsvpemail&rsvpevent_to_email=').$post->ID,__('Embed in RSVP Email','rsvpmaker'));	
			}
		$actions['rsvpmaker_invite'] = sprintf('<a href="%s">%s</a>',admin_url('edit.php?post_type=rsvpemail&post_to_email=').$post->ID,__('Copy to RSVP Email','rsvpmaker'));
	}
	else {
	if($post->post_type == 'rsvpmaker')
	{
		$eds = get_additional_editors($post->ID);
		if(!empty($eds) && in_array($current_user->ID,$eds))
			$actions['edit_override'] = sprintf('<a href="%s">%s</a>',admin_url('post.php?action=edit&post=').$post->ID,__('Edit','rsvpmaker'));
	}
	}
return $actions;
}

//based on Austin Matzko's code from wp-hackers email list
function filter_where_recent($where = '') {
global $blog_weeks_ago;

if(0 == (int) $blog_weeks_ago)
	$blog_weeks_ago = 1;
	$week_ago_stamp = rsvpmaker_strtotime('-'.$blog_weeks_ago.' week');
	$week_ago = date('Y-m-d H:i:s',$week_ago_stamp);
    $where .= " AND post_date > '" . $week_ago . "'";
    return $where;
}

function get_rsvp_notekey() {
	global $post, $rsvpmaker_cron_context;
	
	if(!empty($rsvpmaker_cron_context) && $rsvpmaker_cron_context == 2)
	{
		$notekey = 'editnote'.date('YmdH',time()); // live not preview broadcast or editing
	}
	else {
		$stamp = rsvpmaker_next_scheduled($post->ID, true);
		//$stamp = preg_replace('/M [a-z]+$/','M',$stamp);
		$notekey = 'editnote'.date('YmdH',$stamp);//date('YmdH',rsvpmaker_strtotime($stamp));
	}
	return $notekey;
}

function rsvpmaker_recent_blog_posts ($atts) {
global $wp_query;
global $post;
$backup = $wp_query;
$was = $post;
global $blog_weeks_ago;
$blog_weeks_ago = (!empty($atts["weeks"])) ? $atts["weeks"] : 1;

$ts = rsvpmaker_next_scheduled($post->ID);
$cron = get_post_meta($post->ID,'rsvpmaker_cron_email',true);
$notekey = get_rsvp_notekey();
$chosen = (int) get_post_meta($post->ID,$notekey,true);

add_filter('posts_where', 'filter_where_recent');
query_posts('post_type=post');
if (have_posts()) :
while (have_posts()) : the_post(); 
if($post->ID == $chosen)
	{
	continue;
	}
if($post->comment_count)
	$c = sprintf(" (%d comments)",$post->comment_count);
else
	$c = "";
$output .= '<h4><a href="'. get_permalink() .'" rel="bookmark">'. get_the_title() .'</a> By '. get_the_author() . $c . "</h4>\n<p>".get_the_excerpt()."</p>\n";
 endwhile;
endif;
remove_filter('posts_where', 'filter_where_recent');
if(!empty($output))
	$output = '<h3>'.__('From the Blog','rsvpmaker')."</h3>\n".$output;
$wp_query = $backup;
$post = $was;
return $output;
}

function rsvpmaker_cron_active ($cron_active,$cron){
if(empty($cron["cron_condition"]) || ($cron["cron_condition"] == 'none'))
	return $cron_active;
if(! $cron_active)
	return $cron_active;
if($cron["cron_condition"] == 'events')
	{
	if(!empty($_GET["cron_filter_debug"]))
	echo "<p>test:".$cron["cron_condition"]."</p>";
	return count_future_events();
	}
elseif($cron["cron_condition"] == 'posts')
	{
	if(!empty($_GET["cron_filter_debug"]))
	echo "<p>test:".$cron["cron_condition"]."</p>";
	return count_recent_posts();
	}
elseif($cron["cron_condition"] == 'and')
	{
	if(!empty($_GET["cron_filter_debug"]))
	echo "<p>test:".$cron["cron_condition"]."</p>";
	return (count_recent_posts() && count_future_events()) ? 1 : 0;
	}
elseif($cron["cron_condition"] == 'or')
	{
	if(!empty($_GET["cron_filter_debug"]))
	echo "<p>test:".$cron["cron_condition"]."</p>";
	return (count_recent_posts() || count_future_events()) ? 1 : 0;
	}
return $cron_active;
}
add_filter('rsvpmaker_cron_active','rsvpmaker_cron_active',5,2);

function rsvpmail_unsubscribe () {
if(!isset($_REQUEST['rsvpmail_unsubscribe']))
	return;
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?php bloginfo( 'name' ); echo ' - '.__('Email Unsubscribe'); ?></title>
<style>
body {background-color: #000;}
#main {background-color: #FFF; max-width: 600px; margin-left: auto; margin-right: auto; margin-top: 25px; padding: 25px;}
h1 {font-size: 20px;}
</style>
</head>
<body>
<div id="main">
<h1><?php bloginfo( 'name' ); echo ' - '.__('Email Unsubscribe'); ?></h1>
<?php
if(isset($_POST['rsvpmail_unsubscribe']) && wp_verify_nonce(rsvpmaker_nonce_data('data'),rsvpmaker_nonce_data('key')) )
{
$e = sanitize_text_field(strtolower(trim($_POST['rsvpmail_unsubscribe'])));
if(!is_email($e))
	echo 'Error: invalid email address';
else
	{
	rsvpmail_add_problem($e,'unsubscribed');
	echo '<p>'.__('Unsubscribed from website email lists','rsvpmaker').'</p>';
	$msg = 'RSVPMaker unsubscribe: '.$e;
	$chimp_options = get_option('chimp');
	if(!empty($chimp_options) && !empty($chimp_options["chimp-key"]))
	{
	$apikey = $chimp_options["chimp-key"];
	$listId = $chimp_options["chimp-list"];
	$MailChimp = new MailChimpRSVP($apikey);
	$result = $MailChimp->patch("lists/$listId/members/".md5(strtolower($e)), array(
				'status' => 'unsubscribed'));
	if($MailChimp->success())
		{
		echo '<p>'.__('Unsubscribed from MailChimp email list','rsvpmaker').': '.esc_html($listId).'</p>';
		$msg .= "\n\nRemoved from MailChimp list";
		}
	else
		{
		echo '<p>'.__('Error attempting to unsubscribe from MailChimp email list','rsvpmaker').': '.esc_html($listId).'</p>';	
		$msg .= "\n\nMailChimp unsubscribe error";
		}
	}

	wp_mail(get_option('admin_email'), $e.' '.__('unsubscribed','rsvpmaker').': '.get_option('blogname').' (RSVPMaker)',$msg);

	do_action('rsvpmail_unsubscribe',$e);
	}
}
if(isset($_GET['rsvpmail_unsubscribe']))
{
$e = sanitize_text_field(trim($_GET['rsvpmail_unsubscribe']));
?>
<form method="post" action="<?php echo site_url(); ?>">
<?php rsvpmaker_nonce(); ?>
<input type="text" name="rsvpmail_unsubscribe" value="<?php echo esc_attr($e); ?>">
<button><?php esc_html_e('Unsubscribe','rsvpmaker'); ?></button>
</form>
<?php
}

printf('<p>%s <a href="%s">%s</a></p>',__('Continue to','rsvpmaker'),site_url(),site_url());

?>
</div>
</body>
</html>
<?php
exit();
}

add_action('init','rsvpmail_unsubscribe');

function rsvpmaker_notification_templates () {

$hook = rsvpmaker_admin_page_top(__('Notification Templates','rsvpmaker'));
echo '<p>'.__('Use this form to customize notification and confirmation messages and the information to be included in them. Template placeholders such as [rsvpdetails] are documented at the bottom of the page.').'</p>';
	
$sample_data = array('rsvpdetails' => "first: John\nlast: Smith\nemail:js@example.com",'rsvpyesno' => __('YES','rsvpmaker'), 'rsvptitle' => 'Special Event', 'rsvpdate' => 'January 1, 2020','rsvpmessage' => 'Thank you!', 'rsvpupdate' => '<p><a style="width: 8em; display: block; border: medium inset #FF0000; text-align: center; padding: 3px; background-color: #0000FF; color: #FFFFFF; font-weight: bolder; text-decoration: none;" class="rsvplink" href="%s">'. __('RSVP Update','rsvpmaker').'</a></p>');
$sample_data = apply_filters('rsvpmaker_notification_sample_data',$sample_data);
$template_forms = get_rsvpmaker_notification_templates ();
printf('<form id="rsvpmaker_notification_templates" action="%s" method="post">',admin_url('edit.php?post_type=rsvpemail&page=rsvpmaker_notification_templates'));
rsvpmaker_nonce();
foreach($template_forms as $slug => $form)
	{
	if(!is_array($form))
		continue;
	echo '<div style="border: thin dotted #555; margin-bottom: 5px;">';
	printf('<h2>%s</h2>',ucfirst(str_replace('_',' ',$slug)));
	foreach($form as $field => $value)
		{
			printf('<div>%s</div>',ucfirst(str_replace('_',' ',$field)));
			if($field == 'body')
				echo '<p><textarea name="ntemp['.$slug.']['.$field.']" style="width: 90%; height: 100px;">'.esc_attr($value).'</textarea></p>';
			elseif($field == 'sample_data')
				$sample_data = $value;
			else
				echo '<p><input type="text" name="ntemp['.$slug.']['.$field.']" value="'.esc_attr($value).'" style ="width: 90%" /></p>';
		}
	if(isset($_GET[$slug]))
	{
	echo '<h3>Example</h3>';
	$example = '<p><strong>Subject: </strong>'.$form['subject']."</p>\n\n".$form['body'];
	foreach($sample_data as $field => $value)
		$example = str_replace('['.$field.']',$value,$example);
	
	$example = wpautop($example);
	echo do_blocks(do_shortcode($example));
	}
	echo '</div>';//end border

	}
	printf('<h3>%s: <input type="text" name="newtemplate_label"></h3>',__('Custom Label','rsvpmaker-for-toastmasters'));
	echo '<p>Subject<br /><input type="text" name="newtemplate[subject]" value="" style ="width: 90%" /></p>';
	echo '<p>Body<br /><textarea name="newtemplate[body]" style="width: 90%; height: 100px;"></textarea></p>';

echo submit_button().'</form>';

printf('<p><a href="%s">Reset to defaults</a></p>',admin_url('edit.php?post_type=rsvpemail&page=rsvpmaker_notification_templates&reset=1'));
echo   '<p>'.__("RSVPMaker template placeholders:<br />[rsvpyesno] YES/NO<br />[rsvptitle] event post title<br />[rsvpdate] event date<br />[datetime] event date and time<br />[rsvpmessage] the message you supplied when you created/edited the event (default is Thank you!)<br />[rsvpdetails] information supplied by attendee<br />[rsvpupdate] button users can click on to update their RSVP<br />[rsvpcount] number of people registered<br />[event_title_link] a link to the event, with the event title and date/time",'rsvpmaker').'</p>';
echo '<p>[rsvpmessage] and [rsvpdetails] should only be used in a notification template. Other codes can be used in the body of a confirmation message or the subject line of a reminder.</p>';
do_action('rsvpmaker_notification_templates_doc');
rsvpmaker_admin_page_bottom($hook);
}

function get_rsvpmaker_notification_templates () {
global $email_context;
$email_context = true;
$templates = get_option('rsvpmaker_notification_templates');
//$template_forms represents the defaults
$template_forms['notification'] = array('subject' => 'RSVP [rsvpyesno] for [rsvptitle] on [rsvpdate]','body' => "Just signed up:\n\n<div class=\"rsvpdetails\">[rsvpdetails]</div>");
$template_forms['confirmation'] = array('subject' => 'Confirming RSVP [rsvpyesno] for [rsvptitle] on [rsvpdate]','body' => "<div class=\"rsvpmessage\">[rsvpmessage]</div>\n\n<div class=\"rsvpdetails\">[rsvpdetails]</div>\n\nIf you wish to change your registration, you can do so using the button below. [rsvpupdate]");
$template_forms['confirmation_after_payment'] = array('subject' => 'Confirming payment for [rsvptitle] on [rsvpdate]','body' => "<div class=\"rsvpmessage\">[rsvpmessage]</div>\n\n<div class=\"rsvpdetails\">[rsvpdetails]</div>\n\nIf you wish to change your registration, you can do so using the button below. [rsvpupdate]");
$template_forms['payment_reminder'] = array('subject' => 'Payment Required: [rsvptitle] on [rsvpdate]','body' => "We received your registration, but it is not complete without a payment. Please follow the link below to complete your registration and payment.

[rsvpupdate]

<div class=\"rsvpdetails\">[rsvpdetails]<div>");
if(isset($_GET['reset']))
	{

	}

$template_forms = apply_filters('rsvpmaker_notification_template_forms',$template_forms);
if(empty($templates))
	return $template_forms;
if(isset($_GET['reset']))
	{
		$templates = $template_forms;
		update_option('rsvpmaker_notification_templates',$templates);
	}
else {
	//fill in the blanks
	foreach($template_forms as $slug => $form)
	{
	foreach($form as $field => $value)
		{
			if(empty($templates[$slug][$field]))
				$templates[$slug][$field] = $template_forms[$slug][$field];
		}
	}
}
return $templates;
}

function rsvpcount ($atts) {
global $wpdb;
global $post;
if(isset($atts['post_id']))
	$post_id = (int) $atts['post_id'];
elseif(!empty($atts) && is_numeric($atts))
	$post_id = $atts;
else
	$post_id = $post->ID;
	
if(!$post_id)
	return;
$sql = "SELECT count(*) FROM ".$wpdb->prefix."rsvpmaker WHERE event=$post_id AND yesno=1 ORDER BY id DESC";
$total = (int) $wpdb->get_var($sql);
$rsvp_max = get_post_meta($post_id,'_rsvp_max',true);
$output = $total.' '.__('signed up so far.','rsvpmaker');
if($rsvp_max)
	$output .= ' '.__('Limit','rsvpmaker').': '.$rsvp_max;
return '<p class="signed_up">'.$output.'</p>';
}

function rsvp_notifications_via_template ($rsvp,$rsvp_to,$rsvpdata) {
global $post;
global $rsvp_options;
include_once 'rsvpmaker-ical.php';

$templates = get_rsvpmaker_notification_templates();

$notification_subject = $templates['notification']['subject']; 
foreach($rsvpdata as $field => $value)
	$notification_subject = str_replace('['.$field.']',$value,$notification_subject);

$notification_body = $templates['notification']['body']; 
foreach($rsvpdata as $field => $value)
	$notification_body = str_replace('['.$field.']',$value,$notification_body);
	$notification_body = do_blocks(do_shortcode($notification_body));

	$rsvp_to_array = explode(",", $rsvp_to);
	foreach($rsvp_to_array as $to)
	{
	$mail["to"] = $to;
	$mail["from"] = $rsvp["email"];
	$mail["fromname"] = $rsvp["first"].' '.$rsvp["last"];
	$mail["subject"] = $notification_subject;
	$mail["html"] = wpautop($notification_body);
	rsvpmaker_tx_email($post, $mail);
	}

$send_confirmation = get_post_meta($post->ID,'_rsvp_rsvpmaker_send_confirmation_email',true);
$confirm_on_payment = get_post_meta($post->ID,'_rsvp_confirmation_after_payment',true);

if(($send_confirmation ||!is_numeric($send_confirmation)) && $rsvpdata['yesno'] && empty($confirm_on_payment) )//if it hasn't been set to 0, send it
{
$confirmation_subject = $templates['confirmation']['subject']; 
foreach($rsvpdata as $field => $value)
	$confirmation_subject = str_replace('['.$field.']',$value,$confirmation_subject);

$confirmation_body = $templates['confirmation']['body']; 
foreach($rsvpdata as $field => $value)
	$confirmation_body = str_replace('['.$field.']',$value,$confirmation_body);
	
	$confirmation_body = do_blocks(do_shortcode($confirmation_body));	
	$mail["html"] = wpautop($confirmation_body);
	if(isset($post->ID)) // not for replay
	$mail["ical"] = rsvpmaker_to_ical_email ($post->ID, $rsvp_to, $rsvp["email"]);
	$mail["to"] = $rsvp["email"];
	$mail["from"] = $rsvp_to_array[0];
	$mail["fromname"] = get_bloginfo('name');
	$mail["subject"] = $confirmation_subject;
	rsvpmaker_tx_email($post, $mail);	
}

}

function rsvp_payment_reminder ($rsvp_id) {
global $post;
global $rsvp_options;
global $wpdb;
$sql = "SELECT * FROM ".$wpdb->prefix."rsvpmaker WHERE id=$rsvp_id";
$rsvp = (array) $wpdb->get_row($sql);
$post = get_post($rsvp['event']);
$rsvpdata = unserialize($rsvp['details']);
if($rsvpdata['total'] <= $rsvp['amountpaid'])
	return;
	
$details = '';
foreach($rsvpdata as $label => $value)
	$details .= sprintf('%s: %s'."\n",$label,$value);;

$templates = get_rsvpmaker_notification_templates();
$rsvp_to = get_post_meta($post->ID,'_rsvp_to',true);
$rsvp_to_array = explode(",", $rsvp_to);
$notification_subject = $templates['payment_reminder']['subject']; 
foreach($rsvpdata as $field => $value)
	$notification_subject = str_replace('['.$field.']',$value,$notification_subject);

$notification_body = $templates['payment_reminder']['body']; 
foreach($rsvpdata as $field => $value)
	$notification_body = str_replace('['.$field.']',$value,$notification_body);
$notification_body = str_replace('[rsvpdetails]',$details,$notification_body);

$url = get_permalink($rsvp['event']);
$url = add_query_arg('rsvp',$rsvp['id'],$url);
$url = add_query_arg('e',$rsvp['email'],$url);

$notification_body = str_replace('[rsvpupdate]',sprintf('<a href="%s">Complete Registration</a>',$url),$notification_body);
	
$notification_body = do_blocks(do_shortcode($notification_body)).'<p>after shortcode and blocks</p>';
$mail["to"] = $rsvp['email'];
$mail["from"] = $rsvp_to_array[0];
$mail["fromname"] = get_bloginfo('name');
$mail["subject"] = $notification_subject;
$mail["html"] = wpautop($notification_body);
rsvpmaker_tx_email($post, $mail);
}

function rsvp_confirmation_after_payment ($rsvp_id) {
	include_once 'rsvpmaker-ical.php';
	global $post;
	global $rsvp_options;
	global $wpdb;
	$sql = "SELECT * FROM ".$wpdb->prefix."rsvpmaker WHERE id=$rsvp_id";
	$rsvp = (array) $wpdb->get_row($sql);
	$post = get_post($rsvp['event']);
	$rsvpdata = unserialize($rsvp['details']);

	$guests = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."rsvpmaker WHERE master_rsvp=$rsvp_id");
	if($guests) {
		foreach($guests as $guestrow) {
			$guestarr[] = $guestrow->first.' '.$guestrow->last;
		}
		$rsvpdata['guests'] = implode(', ',$guestarr);
	}

	//rsvpmaker_debug_log($rsvpdata,'rsvp_confirmation_after_payment');
		
	$details = '';
	foreach($rsvpdata as $label => $value)
		$details .= sprintf('%s: %s'."\n",$label,$value);

	$templates = get_rsvpmaker_notification_templates();
	$rsvp_to = get_post_meta($post->ID,'_rsvp_to',true);
	$rsvp_to_array = explode(",", $rsvp_to);
	$rsvpdata['rsvpmessage'] = '';
	$message_id = get_post_meta($post->ID,'_rsvp_confirm',true);
	if($message_id)
	{
	  $message_post = get_post($message_id);
	  $rsvpdata['rsvpmessage'] .= do_blocks($message_post->post_content)."\n\n";
	}
	$message_id = get_post_meta($post->ID,'payment_confirmation_message',true);
	if($message_id)
	{
	  $message_post = get_post($message_id);
	  $rsvpdata['rsvpmessage'] .= do_blocks($message_post->post_content);
	}

	$notification_subject = $templates['confirmation_after_payment']['subject'];
	foreach($rsvpdata as $field => $value)
		$notification_subject = str_replace('['.$field.']',$value,$notification_subject);
	
	$notification_body = $templates['confirmation_after_payment']['body']; 
	foreach($rsvpdata as $field => $value)
		$notification_body = str_replace('['.$field.']',$value,$notification_body);
	$notification_body = str_replace('[rsvpdetails]',$details,$notification_body);
	
	$url = get_permalink($rsvp['event']);
	$url = add_query_arg('rsvp',$rsvp['id'],$url);
	$url = add_query_arg('e',$rsvp['email'],$url);
	
	$notification_body = str_replace('[rsvpupdate]',sprintf('<a href="%s">Complete Registration</a>',$url),$notification_body);	
	$notification_body = do_blocks(do_shortcode($notification_body));

	$mail["to"] = $rsvp['email'];
	$mail["from"] = $rsvp_to_array[0];
	$mail["fromname"] = get_bloginfo('name');
	$mail["ical"] = rsvpmaker_to_ical_email ($post->ID, $rsvp_to, $rsvp["email"]);
	$mail["subject"] = $notification_subject;
	$mail["html"] = $payment_confirmation_message . wpautop($notification_body);
	rsvpmaker_tx_email($post, $mail);	
}

add_action('init','rsvp_payment_reminder_test');
function rsvp_payment_reminder_test () {
	if(!isset($_GET['payrem']))
		return;
	rsvp_payment_reminder(sanitize_text_field($_GET['payrem']));
}

add_action('rsvp_payment_reminder','rsvp_payment_reminder',10,1);

function rsvpmaker_payment_reminder_cron ($rsvp_id) {
	$time = rsvpmaker_strtotime('+30 minutes');
	wp_clear_scheduled_hook( 'rsvp_payment_reminder',array($rsvp_id) );
	wp_schedule_single_event($time,'rsvp_payment_reminder',array($rsvp_id));
}

function previewtest () {
		rsvpmaker_cron_email_preview(array('post_id' => (int) $_GET['rsvpmaker_cron_email_preview']));
		die('preview end');
}

function check_mailchimp_email ($email) {
$chimp_options = get_option('chimp');
$apikey = $chimp_options["chimp-key"];
$listId = $chimp_options["chimp-list"];
$email = trim(strtolower($email));
$MailChimp = new MailChimpRSVP($apikey);	
$member = $MailChimp->get("/lists/".$listId."/members/".md5($email));
if(isset($_GET['debug']))
{
	echo '<pre>';
	//print_r($member);
	echo '</pre>';
}
if (!empty($member["id"]) && ($member["status"] == 'subscribed'))
	return $member;
else
	return false;
}

//weed out filters that don't belong in email
function email_content_minfilters() {
	global $wp_filter, $post, $email_context;
	$log = '';
		$corefilters = array('convert_chars','wpautop','wptexturize','event_content','
		wp_make_content_images_responsive');
		foreach($wp_filter["the_content"] as $priority => $filters)
			foreach($filters as $name => $details)
				{
				if(!in_array($name,$corefilters) && !strpos($name,'hortcode') && !strpos($name,'lock'))//don't mess with block/shortcode processing
					{
					$r = remove_filter( 'the_content', $name, $priority );
					}
				}	
}

function rsvpmailer_template_preview() {
	global $wpdb;
	if(isset($_GET['preview_broadcast_in_template'])) {
		$template = (int) $_GET['preview_broadcast_in_template'];
		$title = 'Demo: Broadcast Email Message';
		$id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title='$title' ");
		if(!$id) {
			$postarray['post_title'] = $title;
			$postarray['post_status'] = 'publish';
			$postarray['post_type'] = 'rsvpemail';
			$postarray['post_content'] = '<!-- wp:paragraph {"dropCap":true,"fontSize":"larger"} -->
			<p class="has-drop-cap has-larger-font-size">You have a story to tell about your business, its products, and its services. The catch is the story can\'t be all about you.</p>
			<!-- /wp:paragraph -->
			
			<!-- wp:paragraph -->
			<p>Product features and service quality are important, but you are not the hero of the story. Your customers and future customers must be able to see themselves as the heroes. Your nifty product may be the hot rod spaceship that will ensure their victory, but you want them to envision themselves at the controls.</p>
			<!-- /wp:paragraph -->
			
			<!-- wp:paragraph -->
			<p>Technology companies often let their marketing get lost in the details. We help them tell stories that matter.</p>
			<!-- /wp:paragraph -->
			
			<!-- wp:paragraph -->
			<p>Our storytellers pay attention to the details, of course, and seek a deep understanding of them. But not all details are equally important. Not all details help tell a clear, convincing story.</p>
			<!-- /wp:paragraph -->
			
			<!-- wp:paragraph -->
			<p><a href="https://www.carrcommunications.com/tell-us-your-story/">Tell us your story</a>, the way you tell it today, or the story you want to take to the market. We will help you tell it better, or suggest a different story that would be more effective.</p>
			<!-- /wp:paragraph -->
			
			<!-- wp:paragraph -->
			<p>Learn how <a href="https://carrcommunications.com">Carr Communications</a> can help you tell a clear, convincing story.</p>
			<!-- /wp:paragraph -->';
			$id = wp_insert_post($postarray);
		}
		$permalink = get_permalink($id);
		wp_redirect(add_query_arg('template_preview',1,$permalink));
		exit;
	}
	if(isset($_GET['preview_confirmation_in_template'])) {
		global $rsvp_options;
		$template = (int) $_GET['preview_confirmation_in_template'];
		$title = 'RSVP YES for Demo Event Confirmation on April 1';
		$id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title='$title' ");
		if(!$id || isset($_GET['reset'])) {
			$postarray['post_title'] = $title;
			$postarray['post_status'] = 'publish';
			$postarray['post_type'] = 'rsvpemail';
			$postarray['post_content'] = '<div class="rsvpmessage">
			<p>Thank you! [your confirmation message here]</p>
			</div>
			<div class="rsvpdetails">First Name: David F.<br>
			Last Name: Carr<br>
			Email: david@carrcommunications.com<br>
			Guests: Beth Anne Carr, Theresa Carr</div>
			<p><em>If you wish to change your registration, you can do so using the button below. </em></p>
			<p><a class="rsvplink" href="https://dev.local/rsvpmaker/gallery-talk-edouard-manet/?e=*EMAIL*#rsvpnow" style="width: 8em; display: block; border: medium inset #FF0000; text-align: center; padding: 3px; background-color: #0000FF; color: #FFFFFF; font-weight: bolder; text-decoration: none;">'.$rsvp_options['update_rsvp'].'</a></p>';
			if( isset($_GET['reset']) )
			{
				$postarray["ID"] = $id;
				wp_update_post($postarray);
			}
			else
				$id = wp_insert_post($postarray);
		}
		update_post_meta($id,'_email_template',$template);
		$permalink = get_permalink($id);
		wp_redirect(add_query_arg('template_preview',1,$permalink));
		exit;
	}
}

function event_title_link () {
	global $post, $rsvp_options;
	$time_format = $rsvp_options["time_format"];
	$add_timezone = get_rsvpmaker_meta($post->ID,'_add_timezone',true);	
	if(!strpos($time_format,'T') && $add_timezone )
		{
		$time_format .= ' T';
		}
	$t = get_rsvpmaker_timestamp($post->ID);
	$display_date = utf8_encode(rsvpmaker_date($rsvp_options["long_date"].' '.$time_format,$t));
	$permalink = get_permalink($post->ID);
	return sprintf('<p class="event-title-link"><a href="%s">%s - %s</a></p>',$permalink,esc_html($post->post_title),esc_html($display_date));
}

function rsvpmaker_mailchimp_init() {
$chimp_options = get_option('chimp');
if(empty($chimp_options["chimp-key"]))
	return;
$apikey = $chimp_options["chimp-key"];
return new MailChimpRSVP($apikey);
}

function rsvptitle_shortcode($atts) {
	global $post;
	return esc_html($post->post_title);
}

function rsvpdate_shortcode($atts = array()) {
	global $post, $rsvp_options;
	$format = empty($atts['format']) ? $rsvp_options['long_date'] : $atts['format'];
	$daterow = get_rsvpmaker_event($post->ID);
	$start_date = preg_replace('/ .+/','',$daterow->date);//date not time
	$end_date = preg_replace('/ .+/','',$daterow->enddate);//date not time
	$t = (int) $daterow->ts_start;
	if($start_date == $end_date){
		return rsvpmaker_date($format,$t);
	}
	else {
		$endt = (int) $daterow->ts_end;
		return rsvpmaker_date($format,$t).' - '.rsvpmaker_date($format,$endt);
	}
}

function rsvpdatetime_shortcode($atts) {
	global $post, $rsvp_options;
	$format = empty($atts['format']) ? $rsvp_options['long_date'].' '.$rsvp_options['time_format'] : $atts['format'];
	$t = get_rsvpmaker_timestamp($post->ID);
	return rsvpmaker_date($format,$t);
}

function rsvpmaker_cronmail_check_duplicate($content) {
	$key = 'cronemail'.md5($content);
	$found = get_transient($key);
	if($found)
		return true;
	set_transient($key,time()); // used to set content
	return false;
}

function rsvpmailer_preview($atts = array()) {
global $post, $rsvpmaker_tx_content;
$preview = get_post_meta($post->ID,'_rsvpmailer_preview',true);
if(empty($preview)) {

if(!empty($rsvpmaker_tx_content))
	$preview = $rsvpmaker_tx_content;
else
	$preview = trim(strip_tags($post->post_content));
$preview = trim(strip_tags($preview));
if(strlen($preview) > 200)
	$preview = substr($preview,0,200).' ...';
}
return $preview;
}

add_shortcode('rsvpmailer_preview','rsvpmailer_preview');

function rsvpmailer_block_styles() {
	global $rsvmailer_css;
	if(!empty($rsvmailer_css))
		return $rsvmailer_css;
	$site_url = site_url();
	$updir = wp_get_upload_dir()['basedir'];
	$home_path = preg_replace('/wp-content.+/','',$updir);
	$wp_styles = wp_styles();
	$combined = '';
	foreach($wp_styles->queue as $handle) {
		$item = $wp_styles->registered[$handle]->src;
		$combined .= "/* url $item */\n";
		if(strpos($item,'ttp') && !strpos($item,$_SERVER['SERVER_NAME']))
			continue; // ignore reference to external domains
		$item = $home_path.str_replace($site_url,'',$item);
		if(strpos($home_path,'\\'))
			$item = str_replace('/','\\',$item);
		$item = str_replace("//","/",$item);
		$item = str_replace("\\\\","\\",$item);
		$combined .= "/* path $item */\n";
		$combined .= file_get_contents($item)."\n";
	}
	$combined = rsvpmailer_clean_css($combined);
	return $combined;
}

//remove styles emogrifier chokes on
function rsvpmailer_clean_css($content) {
	//nonesuch ignored by inliner 'any-link','first-of-type','last-of-type','nth-last-of-type','only-of-type','optional','required'
	$unsupported = array('last-of-type','first-of-type','only-of-type');//
	foreach($unsupported as $bad) {
		$content = str_replace(':'.$bad,'nonesuch',$content); // remove pseudo references emogrifier chokes on
	}
	//$content = str_replace('*','nonesuch',$content); // remove all wildcard references
	$content = str_replace('menu','nonesuch',$content); // remove all menu references
	$content = preg_replace('/\bbody/','nonesuch',$content);
	return $content;
}

function rsvpmaker_included_styles () {
	global $rsvpemail_styles;
	if(!empty($rsvpemail_styles))
		return $rsvpemail_styles;
	
	$rsvpemail_styles = '/* =WordPress Core
	-------------------------------------------------------------- */
	.alignnone {
		margin: 5px 20px 20px 0;
	}
	
	.aligncenter,
	div.aligncenter {
		display: block;
		margin: 5px auto 5px auto;
	}
	
	.alignright {
		float:right;
		margin: 5px 0 20px 20px;
	}
	
	.alignleft {
		float: left;
		margin: 5px 20px 20px 0;
	}
	
	a img.alignright {
		float: right;
		margin: 5px 0 20px 20px;
	}
	
	a img.alignnone {
		margin: 5px 20px 20px 0;
	}
	
	a img.alignleft {
		float: left;
		margin: 5px 20px 20px 0;
	}
	
	a img.aligncenter {
		display: block;
		margin-left: auto;
		margin-right: auto;
	}
	
	.wp-caption {
		background: #fff;
		border: 1px solid #f0f0f0;
		max-width: 96%; /* Image does not overflow the content area */
		padding: 5px 3px 10px;
		text-align: center;
	}
	
	.wp-caption.alignnone {
		margin: 5px 20px 20px 0;
	}
	
	.wp-caption.alignleft {
		margin: 5px 20px 20px 0;
	}
	
	.wp-caption.alignright {
		margin: 5px 0 20px 20px;
	}
	
	.wp-caption img {
		border: 0 none;
		height: auto;
		margin: 0;
		max-width: 98.5%;
		padding: 0;
		width: auto;
	}
	
	.wp-caption p.wp-caption-text {
		font-size: 11px;
		line-height: 17px;
		margin: 0;
		padding: 0 4px 5px;
	}
	#email-content {
		background-color: #fff !important;
		color: #000 !important;
		max-width: 600px;
		margin-left: auto;
		margin-right: auto;
		padding: 10px;
	}
	body {
		background-color: #fff;
		color: #000;
		font-weight: normal;
		font-size: initial;
	}
	.has-background {
		padding: 5px;
	}
	a {
		display: inline-block !important;
	}
	#messagefooter {
		margin-top: 20px;
		padding: 20px;
		background-color: #eee;
		color: #222;
	}
	img {
		max-width: 95% !important;
	}
	';
	
	//add common block styles
	$rsvpemail_styles .= rsvpmailer_block_styles();
	$extra_email_styles = get_option('extra_email_styles');
	if(!empty($extra_email_styles))
		$rsvpemail_styles .= "\n".$extra_email_styles."\n";
	$dir = get_stylesheet_directory();
	$rsvmailer_css = $dir.'/rsvpemail-editor-style.css';
	file_put_contents($rsvmailer_css,$rsvpemail_styles);
	return $rsvpemail_styles;
	}
	
	function show_rsvpmaker_included_styles () {
			echo '<pre>';
			echo rsvpmaker_included_styles();
			echo '</pre>';
			die();
	}
	
function rsvpmaker_template_inline($query_post_id = 0) {
	//email_content_minfilters();
	//no javascript
		
		global $post;
		global $email_styles;
		global $custom_fields;
		global $email_context;
		global $chimp_options;
		global $wp_query;
		global $email_context;	
		$email_context = true;
		email_content_minfilters();
		$wp_query_backup = $wp_query;
		if($query_post_id)
		{
			query_posts('post_type=rsvpemail&p='.$query_post_id);
		}
	
		ob_start();
		wp_head();
		$head = ob_get_clean();

		ob_start();
		?>
		<!doctype html>
		<html <?php language_attributes(); ?> >
		<head>
		<title>*|MC:SUBJECT|*</title>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
		<style id="imported">
	<?php
	echo rsvpmaker_included_styles ();
	?>
	</style>
		</head>
		<body class="rsvpmailer">
		<!-- controls go here -->
		<article>
		<div class="entry-content">
		<div id="email-content">
	
		<!-- editors note goes here -->
	
			<?php
			//print_r($post);
			the_post();
			the_content();
			?>
	
	<div id="messagefooter">
	*|LIST:DESCRIPTION|*<br>
	<br>
	<a href="*|UNSUB|*">Unsubscribe</a> *|EMAIL|* from this list <span style="display: none">*|UNSUB|*</span>
	<br>
	<!-- mailchimp -->
	<strong>Our mailing address is:</strong><br>
	*|LIST:ADDRESS|*<br>
	<em>Copyright (C) *|CURRENT_YEAR|* *|LIST:COMPANY|* All rights reserved.</em><br>    
*|REWARDS|*</div>
		</div>
		</div>
		</article>
		</body>
		</html>
		<?php
		$content = ob_get_clean();
		$content = preg_replace('/<style.+<\/style>/is','',$content);
		if(preg_match_all('|<iframe.+src="https://www.youtube.com/embed/([^\?]+)|is',$content,$matches)) {
			foreach($matches[1] as $youtube_id) {
				$link = 'https://youtu.be/'.$youtube_id;
				$img = 'https://img.youtube.com/vi/'.$youtube_id.'/mqdefault.jpg';
				$html = '<p><a href="'.$link.'">Watch on YouTube: '.$link.'<br /><img src="'.$img.'" width="320" height="180" /></a></p>';
				$content = preg_replace('|<iframe.+'.$youtube_id.'[^<]+</iframe>|',$html,$content);
			}
		}
		else
			$content = preg_replace( '/(?<!")(https:\/\/www.youtube.com\/watch\?v=|https:\/\/youtu.be\/)([a-zA-Z0-9_\-]+)/', '<p><a href="$0">Watch on YouTube: $0<br /><img src="https://img.youtube.com/vi/$2/mqdefault.jpg" width="320" height="180" /></a></p>', $content );

update_post_meta($post->ID,'_rsvpmail_html',$content);
update_post_meta($post->ID,'_rsvpmail_text',rsvpmaker_text_version($content));
$wp_query = $wp_query_backup;
return $content;
}
