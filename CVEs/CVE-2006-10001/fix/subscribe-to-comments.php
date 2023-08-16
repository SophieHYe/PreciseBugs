<?php
/*
Plugin Name: Subscribe To Comments
Version: 2.0.8
Plugin URI: http://txfx.net/code/wordpress/subscribe-to-comments/
Description: Allows readers to recieve notifications of new comments that are posted to an entry.  Based on version 1 from <a href="http://scriptygoddess.com/">Scriptygoddess</a>
Author: Mark Jaquith
Author URI: http://txfx.net/
*/



/* ================= */
/* Display Functions */
/* ================= */

/* -------------------------
What follows are the functions that display things in your comments form.
Feel free to customize them to your needs
------------------------- */

/* -------------------------
This is the code that is inserted into your comment form.  You may modify it, if you wish.
------------------------- */
function show_subscription_checkbox ($id='0') {
	global $sg_subscribe;
	sg_subscribe_start();

	if ( $sg_subscribe->checkbox_shown ) return $id;
	if ( !$email = $sg_subscribe->current_viewer_subscription_status() ) : ?>

<?php /* ------------------------------------------------------------------- */ ?>
<?php /* This is the text that is displayed for users who are NOT subscribed */ ?>
<?php /* ------------------------------------------------------------------- */ ?>

	<p <?php if ($sg_subscribe->clear_both) echo 'style="clear: both;" '; ?>class="subscribe-to-comments">
        <input type="checkbox" name="subscribe" id="subscribe" value="subscribe" style="width: auto;" <?php if ($sg_subscribe->default_subscribed) echo 'checked="checked" '; ?>/>
        <label for="subscribe"><?php echo $sg_subscribe->not_subscribed_text; ?></label>
	</p>

<?php /* ------------------------------------------------------------------- */ ?>

<?php elseif ( $email == 'admin' && current_user_can('manage_options') ) : ?>

<?php /* ------------------------------------------------------------- */ ?>
<?php /* This is the text that is displayed for the author of the post */ ?>
<?php /* ------------------------------------------------------------- */ ?>

	<p <?php if ($sg_subscribe->clear_both) echo 'style="clear: both;" '; ?>class="subscribe-to-comments">
	<?php echo str_replace('[manager_link]', $sg_subscribe->manage_link($email, true, false), $sg_subscribe->author_text); ?>
	</p>

<?php else : ?>

<?php /* --------------------------------------------------------------- */ ?>
<?php /* This is the text that is displayed for users who ARE subscribed */ ?>
<?php /* --------------------------------------------------------------- */ ?>

	<p <?php if ($sg_subscribe->clear_both) echo 'style="clear: both;" '; ?>class="subscribe-to-comments">
	<?php echo str_replace('[manager_link]', $sg_subscribe->manage_link($email, true, false), $sg_subscribe->subscribed_text); ?>
	</p>

<?php /* --------------------------------------------------------------- */ ?>

<?php endif;

$sg_subscribe->checkbox_shown = true;
return $id;
}



/* -------------------------------------------------------------------- */
/* This function outputs a "subscribe without commenting" form.         */
/* Place this somewhere within "the loop", but NOT within another form  */
/* This is NOT inserted automaticallly... you must place it yourself    */
/* -------------------------------------------------------------------- */
function show_manual_subscription_form () {
	global $id, $sg_subscribe, $user_email;
	sg_subscribe_start();
	$sg_subscribe->show_errors('solo_subscribe', '<div class="solo-subscribe-errors">', '</div>', __('<strong>Error: </strong>', 'subscribe-to-comments'), '<br />');

if ( !$sg_subscribe->current_viewer_subscription_status() ) :
	get_currentuserinfo(); ?>

<?php /* ------------------------------------------------------------------- */ ?>
<?php /* This is the text that is displayed for users who are NOT subscribed */ ?>
<?php /* ------------------------------------------------------------------- */ ?>

	<form action="http://<?php echo $_SERVER['HTTP_HOST'] . wp_specialchars($_SERVER['REQUEST_URI']); ?>" method="post">
	<input type="hidden" name="solo-comment-subscribe" value="solo-comment-subscribe" />
	<input type="hidden" name="postid" value="<?php echo $id; ?>" />
	<input type="hidden" name="ref" value="<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . wp_specialchars($_SERVER['REQUEST_URI'])); ?>" />

	<p class="solo-subscribe-to-comments">
	<?php _e('Subscribe without commenting', 'subscribe-to-comments'); ?>
	<br />
	<label for="solo-subscribe-email"><?php _e('E-Mail:', 'subscribe-to-comments'); ?>
	<input type="text" name="email" id="solo-subscribe-email" size="22" value="<?php echo $user_email; ?>" /></label>
	<input type="submit" name="submit" value="<?php _e('Subscribe', 'subscribe-to-comments'); ?>" />
	</p>
	</form>

<?php /* ------------------------------------------------------------------- */ ?>

<?php endif;
}



/* -------------------------
Use this function on your comments display - to show whether a user is subscribed to comments on the post or not.
Note: this must be used within the comments loop!  It will not work properly outside of it.
------------------------- */
function comment_subscription_status() {
global $comment;
if ($comment->comment_subscribe == 'Y') {
return true;
} else {
return false;
}
}






/* ============================= */
/* DO NOT MODIFY BELOW THIS LINE */
/* ============================= */

class sg_subscribe_settings
{
	function options_page_contents()
	{
		/** Commit changed options if posted **/
		if($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			update_option('sg_subscribe_settings', $_POST['sg_subscribe_settings']);
		}
		echo '<h2>Subscribe to Comments Options</h2>';
		echo '<ul>';

		echo '<li><label for="name">' . __('"From" name for notifications:', 'subscribe-to-comments') . ' <input type="text" size="40" id="name" name="sg_subscribe_settings[name]" value="' . sg_subscribe_settings::form_setting('name') . '" /></label></li>';
		echo '<li><label for="email">' . __('"From" e-mail addresss for notifications:', 'subscribe-to-comments') . ' <input type="text" size="40" id="email" name="sg_subscribe_settings[email]" value="' . sg_subscribe_settings::form_setting('email') . '" /></label></li>';
		echo '<li><label for="default_subscribed"><input type="checkbox" id="default_subscribed" name="sg_subscribe_settings[default_subscribed]" value="default_subscribed"' . sg_subscribe_settings::checkflag('default_subscribed') . ' /> ' . __('"Subscribe" box should be checked by default', 'subscribe-to-comments') . '</label></li>';
		echo '<li><label for="clear_both"><input type="checkbox" id="clear_both" name="sg_subscribe_settings[clear_both]" value="clear_both"' . sg_subscribe_settings::checkflag('clear_both') . ' /> ' . __('Do a CSS "clear" on the subscription checkbox/message (uncheck this if the checkbox/message appears in a strange location in your theme)', 'subscribe-to-comments') . '</label></li>';
		echo '</ul>';

		echo '<fieldset><legend>' . __('Comment Form Text', 'subscribe-to-comments') . '</legend>';

		echo '<p>' . __('Customize the messages shown to different people.  Use <code>[manager_link]</code> to insert the URI to the Subscription Manager.', 'subscribe-to-comments') . '</p>';

		echo '<ul>';

		echo '<li><label for="not_subscribed_text">' . __('Not subscribed', 'subscribe-to-comments') . '</label><br /><textarea style="width: 98%; font-size: 12px;" rows="2" cols="60" id="not_subscribed_text" name="sg_subscribe_settings[not_subscribed_text]">' . sg_subscribe_settings::textarea_setting('not_subscribed_text') . '</textarea></li>';

		echo '<li><label for="subscribed_text">' . __('Subscribed', 'subscribe-to-comments') . '</label><br /><textarea style="width: 98%; font-size: 12px;" rows="2" cols="60" id="subscribed_text" name="sg_subscribe_settings[subscribed_text]">' . sg_subscribe_settings::textarea_setting('subscribed_text') . '</textarea></li>';

		echo '<li><label for="author_text">' . __('Entry Author', 'subscribe-to-comments') . '</label><br /><textarea style="width: 98%; font-size: 12px;" rows="2" cols="60" id="author_text" name="sg_subscribe_settings[author_text]">' . sg_subscribe_settings::textarea_setting('author_text') . '</textarea></li>';

		echo '</ul></fieldset>';








		echo '<fieldset>';
		echo '<legend><input type="checkbox" id="use_custom_style" name="sg_subscribe_settings[use_custom_style]" value="use_custom_style"' . sg_subscribe_settings::checkflag('use_custom_style') . ' /> <label for="use_custom_style">' . __('Use custom style for Subscription Manager', 'subscribe-to-comments') . '</label></legend>';

		echo '<p>' . __('These settings only matter if you are using a custom style.  <code>[theme_path]</code> will be replaced with the path to your current theme.', 'subscribe-to-comments') . '</p>';

		echo '<ul>';
		echo '<li><label for="sg_sub_header">' . __('Path to header:', 'subscribe-to-comments') . ' <input type="text" size="40" id="sg_sub_header" name="sg_subscribe_settings[header]" value="' . sg_subscribe_settings::form_setting('header') . '" /></label></li>';
		echo '<li><label for="sg_sub_sidebar">' . __('Path to sidebar:', 'subscribe-to-comments') . ' <input type="text" size="40" id="sg_sub_sidebar" name="sg_subscribe_settings[sidebar]" value="' . sg_subscribe_settings::form_setting('sidebar') . '" /></label></li>';
		echo '<li><label for="sg_sub_footer">' . __('Path to footer:', 'subscribe-to-comments') . ' <input type="text" size="40" id="sg_sub_footer" name="sg_subscribe_settings[footer]" value="' . sg_subscribe_settings::form_setting('footer') . '" /></label></li>';


		echo '<li><label for="before_manager">' . __('HTML for before the subscription manager:', 'subscribe-to-comments') . ' </label><br /><textarea style="width: 98%; font-size: 12px;" rows="2" cols="60" id="before_manager" name="sg_subscribe_settings[before_manager]">' . sg_subscribe_settings::textarea_setting('before_manager') . '</textarea></li>';
		echo '<li><label for="after_manager">' . __('HTML for after the subscription manager:', 'subscribe-to-comments') . ' </label><br /><textarea style="width: 98%; font-size: 12px;" rows="2" cols="60" id="after_manager" name="sg_subscribe_settings[after_manager]">' . sg_subscribe_settings::textarea_setting('after_manager') . '</textarea></li>';
		echo '</ul>';
		echo '</fieldset>';
	}

	function checkflag($optname) {
		$options = get_settings('sg_subscribe_settings');
		if ( $options[$optname] != $optname )
			return;
		return ' checked="checked"';
	}

	function form_setting($optname) {
		$options = get_settings('sg_subscribe_settings');
		return htmlspecialchars(stripslashes($options[$optname]), ENT_QUOTES);
	}

	function textarea_setting($optname) {
		$options = get_settings('sg_subscribe_settings');
		return htmlspecialchars(stripslashes($options[$optname]));
	}

	function options_page() {
		/** Display "saved" notification on post **/
		if ( isset($_POST['sg_subscribe_settings_submit']) )
			echo '<div class="updated"><p><strong>' . __('Options saved.', 'SmallOptions') . '</strong></p></div>';

		echo '<form method="post"><div class="wrap">';

		sg_subscribe_settings::options_page_contents();

	  echo '<p class="submit"><input type="submit" name="sg_subscribe_settings_submit" value="';
	  _e('Update Options &raquo;', 'subscribe-to-comments');
	  echo '" /></p></div>';
		echo '</form>';
	}

}







class sg_subscribe {
	var $errors;
	var $messages;
	var $post_subscriptions;
	var $email_subscriptions;
	var $subscriber_email;
	var $site_email;
	var $site_name;
	var $standalone;
	var $form_action;
	var $checkbox_shown;
	var $use_wp_style;
	var $header;
	var $sidebar;
	var $footer;
	var $clear_both;
	var $before_manager;
	var $after_manager;
	var $email;
	var $new_email;
	var $ref;
	var $key;
	var $key_type;
	var $action;
	var $default_subscribed;
	var $not_subscribed_text;
	var $subscribed_text;
	var $author_text;
	var $salt;
	var $settings;


	function sg_subscribe() {
		global $wpdb;
		$this->db_upgrade_check();

		$this->settings = get_settings('sg_subscribe_settings');

		$this->salt = $this->settings['salt'];
		$this->site_email = ( is_email($this->settings['email']) && $this->settings['email'] != 'email@example.com' ) ? $this->settings['email'] : get_bloginfo('admin_email');
		$this->site_name = ( $this->settings['name'] != 'YOUR NAME' && !empty($this->settings['name']) ) ? stripslashes($this->settings['name']) : get_bloginfo('name');
		$this->default_subscribed = ($this->settings['default_subscribed']) ? true : false;

		$this->not_subscribed_text = stripslashes($this->settings['not_subscribed_text']);
		$this->subscribed_text = stripslashes($this->settings['subscribed_text']);
		$this->author_text = stripslashes($this->settings['author_text']);
		$this->clear_both = $this->settings['clear_both'];

		$this->errors = '';
		$this->post_subscriptions = '';
		$this->email_subscriptions = '';
	}


	function manager_init() {
		$this->messages = '';
		$this->use_wp_style = ( $this->settings['use_custom_style'] == 'use_custom_style' ) ? false : true;
		if ( !$this->use_wp_style ) {
			$this->header = str_replace('[theme_path]', get_template_directory(), stripslashes($this->settings['header']));
			$this->sidebar = str_replace('[theme_path]', get_template_directory(), stripslashes($this->settings['sidebar']));
			$this->footer = str_replace('[theme_path]', get_template_directory(), stripslashes($this->settings['footer']));
			$this->before_manager = stripslashes($this->settings['before_manager']);
			$this->after_manager = stripslashes($this->settings['after_manager']);
		}

		// version 2.0.8 -- allow plugin file to be renamed or placed in a subdirectory
		if ( 'edit.php?page=subscribe-to-comments.php' == $this->form_action )
			$this->form_action = 'edit.php?page=' . STC_PLUGIN_BASENAME;


		foreach (array('email', 'key', 'ref', 'new_email') as $var) {
			if ( isset($_REQUEST[$var]) && !empty($_REQUEST[$var]) )
				$this->{$var} = wp_specialchars(trim($_REQUEST[$var]));
		}
		if ( !$this->key )
			$this->key = 'unset';
	}


	function add_error($text='generic error', $type='manager') {
		$this->errors[$type][] = $text;
	}


	function show_errors($type='manager', $before_all='<div class="updated updated-error">', $after_all='</div>', $before_each='<p>', $after_each='</p>'){
		if ( is_array($this->errors[$type]) ) {
			echo $before_all;
			foreach ($this->errors[$type] as $error)
				echo $before_each . $error . $after_each;
			echo $after_all;
		}
		unset($this->errors);
	}


	function add_message($text) {
		$this->messages[] = $text;
	}


	function show_messages($before_all='', $after_all='', $before_each='<div class="updated"><p>', $after_each='</p></div>'){
		if ( is_array($this->messages) ) {
			echo $before_all;
			foreach ($this->messages as $message)
				echo $before_each . $message . $after_each;
			echo $after_all;
		}
		unset($this->messages);
	}


	function subscriptions_from_post($postid) {
		if ( is_array($this->post_subscriptions) ) return $this->post_subscriptions;
		global $wpdb;
		$postid = (int) $postid;
		$this->post_subscriptions = $wpdb->get_results("SELECT comment_author_email FROM $wpdb->comments WHERE comment_post_ID = '$postid' AND comment_subscribe='Y' AND comment_author_email != '' AND comment_approved = '1' GROUP BY LCASE(comment_author_email)");
		$subscribed_without_comment = get_post_meta($postid, '_sg_subscribe-to-comments');
		if ( is_array($subscribed_without_comment) ) {
			foreach ( $subscribed_without_comment as $email )
				$this->post_subscriptions[]->comment_author_email = $email;
		}
		return $this->post_subscriptions;
	}


	function subscriptions_from_email($email='') {
		if ( is_array($this->email_subscriptions) )
			return $this->email_subscriptions;
		if ( !is_email($email) )
			$email = $this->email;
		global $wpdb;
		$email = $wpdb->escape(strtolower($email));
		$i = 0;
		$subscriptions = $wpdb->get_results("SELECT comment_post_ID FROM $wpdb->comments WHERE LCASE(comment_author_email) = '$email' AND comment_subscribe='Y' AND comment_approved = '1' GROUP BY comment_post_ID");
		if ( is_array($subscriptions) ) {
			foreach ( $subscriptions as $subscription ) {
				$this->email_subscriptions[$i] = $subscription->comment_post_ID;
				$i++;
			}
		}
		$subscriptions = $wpdb->get_results("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_sg_subscribe-to-comments' AND LCASE(meta_value) = '$email' GROUP BY post_id");
		if ( is_array($subscriptions) ) {
		foreach ($subscriptions as $subscription) {
			$this->email_subscriptions[$i] = $subscription->post_id;
			$i++;
			}
		}
		if ( $i > 0 ) {
			sort($this->email_subscriptions, SORT_NUMERIC);
			return $this->email_subscriptions;
		}
		// no subscriptions
		return false;
	}


	function solo_subscribe ($email, $postid) {
		global $wpdb, $cache_userdata, $user_email;
		$postid = (int) $postid;
		$email = strtolower($email);
		if ( !is_email($email) ) {
			get_currentuserinfo();
			if ( is_email($user_email) )
				$email = strtolower($user_email);
			else
				$this->add_error(__('Please provide a valid e-mail address.', 'subscribe-to-comments'),'solo_subscribe');
		}

		if ( ( $email == $this->site_email && is_email($this->site_email) ) || ( $email == get_settings('admin_email') && is_email(get_settings('admin_email')) ) )
			$this->add_error(__('This e-mail address may not be subscribed', 'subscribe-to-comments'),'solo_subscribe');

		if ( is_array($this->subscriptions_from_email($email)) )
			if (in_array($postid, $this->subscriptions_from_email($email))) {
				// already subscribed
				setcookie('comment_author_email_' . COOKIEHASH, stripslashes($email), time() + 30000000, COOKIEPATH);
				$this->add_error(__('You appear to be already subscribed to this entry.', 'subscribe-to-comments'),'solo_subscribe');
				}
		$email = $wpdb->escape($email);
		$post = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE ID = '$postid' AND comment_status <> 'closed' AND ( post_status = 'static' OR post_status = 'publish')  LIMIT 1");

		if ( !$post )
			$this->add_error(__('Comments are not allowed on this entry.', 'subscribe-to-comments'),'solo_subscribe');

		if ( empty($cache_userdata[$post->post_author]) && $post->post_author != 0) {
			$cache_userdata[$post->post_author] = $wpdb->get_row("SELECT * FROM $wpdb->users WHERE ID = $post->post_author");
			$cache_userdata[$cache_userdata[$post->post_author]->user_login] =& $cache_userdata[$post->post_author];
		}

		$post_author = $cache_userdata[$post->post_author];

		if ( strtolower($post_author->user_email) == stripslashes($email) )
			$this->add_error(__('You appear to be already subscribed to this entry.', 'subscribe-to-comments'),'solo_subscribe');

		if ( !is_array($this->errors['solo_subscribe']) ) {
			add_post_meta($postid, '_sg_subscribe-to-comments', stripslashes($email));
			setcookie('comment_author_email_' . COOKIEHASH, stripslashes($email), time() + 30000000, COOKIEPATH);
			$location = $this->manage_link(stripslashes($email), false, false) . '&subscribeid=' . $postid;
			header("Location: $location");
			exit();
		}
	}


	function add_subscriber($cid) {
		global $wpdb;
		$id = (int) $id;
    	$email = $wpdb->escape(strtolower($wpdb->get_var("SELECT comment_author_email FROM $wpdb->comments WHERE comment_ID = '$cid'")));
		$postid = $wpdb->get_var("SELECT comment_post_ID from $wpdb->comments WHERE comment_ID = '$cid'");

		$previously_subscribed = ( $wpdb->get_var("SELECT comment_subscribe from $wpdb->comments WHERE comment_post_ID = '$postid' AND LCASE(comment_author_email) = '$email' AND comment_subscribe = 'Y' LIMIT 1") || in_array(stripslashes($email), get_post_meta($postid, '_sg_subscribe-to-comments')) ) ? true : false;

		// If user wants to be notified or has previously subscribed, set the flag on this current comment
		if (($_POST['subscribe'] == 'subscribe' && is_email($email)) || $previously_subscribed) {
			delete_post_meta($postid, '_sg_subscribe-to-comments', stripslashes($email));
			$wpdb->query("UPDATE $wpdb->comments SET comment_subscribe = 'Y' where comment_post_ID = '$postid' AND LCASE(comment_author_email) = '$email'");
		}
		return $cid;
	}


	function is_blocked($email='') {
		global $wpdb;
		if ( !is_email($email) )
			$email = $this->email;
		if ( empty($email) )
			return false;
		$email = strtolower($email);
		// add the option if it doesn't exist
		add_option('do_not_mail', '');
		$blocked = explode (' ', get_settings('do_not_mail'));
		if ( in_array($email, $blocked) )
			return true;
		return false;
	}


	function add_block($email='') {
		if ( !is_email($email) )
			$email = $this->email;
		global $wpdb;
		$email = strtolower($email);

		// add the option if it doesn't exist
		add_option('do_not_mail', '');

		// check to make sure this email isn't already in there
		if ( !$this->is_blocked($email) ) {
			// email hasn't already been added - so add it
			$blocked = get_settings('do_not_mail') . ' ' . $email;
			update_option('do_not_mail', $blocked);
			return true;
			}
		return false;
	}


	function remove_block($email='') {
		if ( !is_email($email) )
			$email = $this->email;
		global $wpdb;
		$email = strtolower($email);

		if ( $this->is_blocked($email) ) {
			// e-mail is in the list - so remove it
			$blocked = str_replace (' ' . $email, '', explode (' ', get_settings('do_not_mail')));
			update_option('do_not_mail', $blocked);
			return true;
			}
		return false;
	}


	function has_subscribers() {
		if ( count($this->get_unique_subscribers()) > 0 )
			return true;
		return false;
	}


	function get_unique_subscribers() {
		global $comments, $comment, $sg_subscribers;
		if ( isset($sg_subscribers) )
			return $sg_subscribers;

		$sg_subscribers = array();
		$subscriber_emails = array();

		// We run the comment loop, and put each unique subscriber into a new array
		foreach ( $comments as $comment ) {
			if ( comment_subscription_status() && !in_array($comment->comment_author_email, $subscriber_emails) ) {
				$sg_subscribers[] = $comment;
				$subscriber_emails[] = $comment->comment_author_email;
			}
		}
		return $sg_subscribers;
	}


	function hidden_form_fields() { ?>
		<input type="hidden" name="ref" value="<?php echo $this->ref; ?>" />
		<input type="hidden" name="key" value="<?php echo $this->key; ?>" />
		<input type="hidden" name="email" value="<?php echo $this->email; ?>" />
	<?php
	}


	function generate_key($data='') {
		if ( '' == $data )
			return false;
		if ( !$this->settings['salt'] )
			die('fatal error: corrupted salt');
		return md5(md5($this->settings['salt'] . $data));
	}


	function validate_key() {
		if ( $this->key == $this->generate_key($this->email) )
			$this->key_type = 'normal';
		elseif ( $this->key == $this->generate_key($this->email . $this->new_email) )
			$this->key_type = 'change_email';
		elseif ( $this->key == $this->generate_key($this->email . 'blockrequest') )
			$this->key_type = 'block';
		elseif ( current_user_can('manage_options') )
			$this->key_type = 'admin';
		else
			return false;
		return true;
	}


	function determine_action() {
		// rather than check it a bunch of times
		$is_email = is_email($this->email);

		if ( is_email($this->new_email) && $is_email && $this->key_type == 'change_email' )
			$this->action = 'change_email';
		elseif ( isset($_POST['removesubscrips']) && $is_email )
			$this->action = 'remove_subscriptions';
		elseif ( isset($_POST['removeBlock']) && $is_email && current_user_can('manage_options') )
			$this->action = 'remove_block';
		elseif ( isset($_POST['changeemailrequest']) && $is_email && is_email($this->new_email) )
			$this->action = 'email_change_request';
		elseif ( $is_email && isset($_POST['blockemail']) )
			$this->action = 'block_request';
		elseif ( isset($_GET['subscribeid']) )
			$this->action = 'solo_subscribe';
		elseif ( $is_email && isset($_GET['blockemailconfirm']) && $this->key == $this->generate_key($this->email . 'blockrequest') )
			$this->action = 'block';
		else
			$this->action = 'none';
	}


	function remove_subscriber($email, $postid) {
		global $wpdb;
		$postid = (int) $postid;
		$email = $wpdb->escape(strtolower($email));

		if ( delete_post_meta($postid, '_sg_subscribe-to-comments', stripslashes($email)) || $wpdb->query("UPDATE $wpdb->comments SET comment_subscribe = 'N' WHERE comment_post_ID  = '$postid' AND LCASE(comment_author_email) ='$email'") )
			return true;
		else
			return false;
		}


	function remove_subscriptions ($postids) {
		global $wpdb;
		$removed = 0;
		for ($i = 0; $i < count($postids); $i++) {
			if ( $this->remove_subscriber($this->email, $postids[$i]) )
				$removed++;
		}
		return $removed;
	}


	function send_notifications($cid) {
		global $wpdb;
		$cid = (int) $cid;
		$comment = $wpdb->get_row("SELECT * FROM $wpdb->comments WHERE comment_ID='$cid' LIMIT 1");
		$post = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE ID='$comment->comment_post_ID' LIMIT 1");

		if ( $comment->comment_approved == '1' && $comment->comment_type == '' ) {
			// Comment has been approved and isn't a trackback or a pingback, so we should send out notifications

			$message  = sprintf(__("There is a new comment on the post \"%s\"", 'subscribe-to-comments') . ". \n%s\n\n", stripslashes($post->post_title), get_permalink($comment->comment_post_ID));
			$message .= sprintf(__("Author: %s\n", 'subscribe-to-comments'), $comment->comment_author);
			$message .= __("Comment:\n", 'subscribe-to-comments') . stripslashes($comment->comment_content) . "\n\n";
			$message .= __("See all comments on this post here:\n", 'subscribe-to-comments');
			$message .= get_permalink($comment->comment_post_ID) . "#comments\n\n";
			//add link to manage comment notifications
			$message .= __("To manage your subscriptions or to block all notifications from this site, click the link below:\n", 'subscribe-to-comments');
			$message .= get_settings('siteurl')."/wp-subscription-manager.php?email=[email]&key=[key]";

			$subject = sprintf(__('New Comment On: %s', 'subscribe-to-comments'), stripslashes($post->post_title));

			$subscriptions = $this->subscriptions_from_post($comment->comment_post_ID);
			if ( is_array($subscriptions) ) {
				foreach ( $subscriptions as $email ) {
					if ( !$this->is_blocked($email->comment_author_email) && $email->comment_author_email != $comment->comment_author_email && is_email($email->comment_author_email) ) {
					        $message_final = str_replace('[email]', urlencode($email->comment_author_email), $message);
					        $message_final = str_replace('[key]', $this->generate_key($email->comment_author_email), $message_final);
						$this->send_mail($email->comment_author_email, $subject, $message_final);
					}
				} // foreach subscription
			} // if subscriptions
		} // end if comment approved
		return $cid;
	}


	function change_email_request() {
		if ( $this->is_blocked() )
			return false;

		$subject = __('E-mail change confirmation', 'subscribe-to-comments');
		$message = sprintf(__("You are receiving this message to confirm a change of e-mail address for your subscriptions at \"%s\"\n\n", 'subscribe-to-comments'), get_bloginfo('blogname'));
		$message .= sprintf(__("To change your e-mail address to %s, click this link:\n\n", 'subscribe-to-comments'), $this->new_email);
		$message .= get_bloginfo('wpurl') . "/wp-subscription-manager.php?email=" . urlencode($this->email) . "&new_email=" . urlencode($this->new_email) . "&key=" . $this->generate_key($this->email . $this->new_email) . ".\n\n";
		$message .= __('If you did not request this action, please disregard this message.', 'subscribe-to-comments');
		return $this->send_mail($this->email, $subject, $message);
	}


	function block_email_request($email) {
		if ( $this->is_blocked($email) )
			return false;
		$subject = __('E-mail block confirmation', 'subscribe-to-comments');
		$message = sprintf(__("You are receiving this message to confirm that you no longer wish to receive e-mail comment notifications from \"%s\"\n\n", 'subscribe-to-comments'), get_bloginfo('name'));
		$message .= __("To cancel all future notifications for this address, click this link:\n\n", 'subscribe-to-comments');
		$message .= get_bloginfo('wpurl') . "/wp-subscription-manager.php?email=" . urlencode($email) . "&key=" . $this->generate_key($email . 'blockrequest') . "&blockemailconfirm=true" . ".\n\n";
		$message .= __("If you did not request this action, please disregard this message.", 'subscribe-to-comments');
		return $this->send_mail($email, $subject, $message);
	}


	function send_mail($to, $subject, $message) {
		$subject = '[' . get_bloginfo('name') . '] ' . $subject;
		$headers  = "From: ".$this->site_name." <".$this->site_email.">\n";
		$headers .= "MIME-Version: 1.0\n" . "Content-Type: text/plain; charset=\"" . get_settings('blog_charset') . "\"\n";
		return wp_mail($to, $subject, $message, $headers);
	}


	function change_email() {
		global $wpdb;
		$new_email = $wpdb->escape(strtolower($this->new_email));
		$email = $wpdb->escape(strtolower($this->email));
		if ( $wpdb->query("UPDATE $wpdb->comments SET comment_author_email = '$new_email' WHERE comment_author_email = '$email'") )
			$return = true;
		if ( $wpdb->query("UPDATE $wpdb->postmeta SET meta_value = '$new_email' WHERE meta_value = '$email' AND meta_key = '_sg_subscribe-to-comments'") )
			$return = true;

		return $return;
	}


	function entry_link($postid, $uri='') {
		if ( empty($uri) )
			$uri = get_permalink($postid);
		$postid = (int) $postid;
		$title = get_the_title($postid);
		if ( empty($title) )
			$title = __('click here', 'subscribe-to-comments');
		$output = '<a href="'.$uri.'">'.$title.'</a>';
		return $output;
	}


	function sg_wp_head() { ?>
		<style type="text/css" media="screen">
		.updated-error {
			background-color: #FF8080;
			border: 1px solid #F00;
		}
		</style>
		<?php
		return true;
	}


	function db_upgrade_check () {
		global $wpdb;

		// add the options
		add_option('sg_subscribe_settings', array('use_custom_style' => '', 'email' => get_bloginfo('admin_email'), 'name' => get_bloginfo('name'), 'header' => '[theme_path]/header.php', 'sidebar' => '', 'footer' => '[theme_path]/footer.php', 'before_manager' => '<div id="content" class="widecolumn subscription-manager">', 'after_manager' => '</div>', 'default_subscribed' => '', 'not_subscribed_text' => __('Notify me of followup comments via e-mail', 'subscribe-to-comments'), 'subscribed_text' => __('You are subscribed to this entry.  <a href="[manager_link]">Manage your subscriptions</a>.', 'subscribe-to-comments'), 'author_text' => __('You are the author of this entry.  <a href="[manager_link]">Manage subscriptions</a>.', 'subscribe-to-comments')));

		$settings = get_option('sg_subscribe_settings');

		if ( !$settings['salt'] ) {
			$settings['salt'] = md5(md5(uniqid(rand() . rand() . rand() . rand() . rand(), true))); // random MD5 hash
			$update = true;
		}

		if ( !$settings['clear_both'] ) {
			$settings['clear_both'] = 'clear_both';
			$update = true;
		}

		if ( $update )
			update_option('sg_subscribe_settings', $settings);

		$column_name = 'comment_subscribe';
		foreach ($wpdb->get_col("DESC $wpdb->comments", 0) as $column )
			if ($column == $column_name)
				return true;

		// didn't find it... create it
		$wpdb->query("ALTER TABLE $wpdb->comments ADD COLUMN comment_subscribe enum('Y','N') NOT NULL default 'N'");
	}


	function current_viewer_subscription_status(){
		global $wpdb, $post, $user_email;

		$comment_author_email = ( isset($_COOKIE['comment_author_email_'. COOKIEHASH]) ) ? trim($_COOKIE['comment_author_email_'. COOKIEHASH]) : '';
		get_currentuserinfo();

		if ( is_email($user_email) ) {
			$email = strtolower($user_email);
			$loggedin = true;
		} elseif ( is_email($comment_author_email) ) {
			$email = strtolower($comment_author_email);
		} else {
			return false;
		}

		$post_author = get_userdata($post->post_author);
		if ( strtolower($post_author->user_email) == $email && $loggedin )
			return 'admin';

		if ( is_array($this->subscriptions_from_email($email)) )
			if ( in_array($post->ID, $this->email_subscriptions) ) return $email;
		return false;
	}


	function manage_link($email='', $html=true, $echo=true) {
		$link  = get_bloginfo('wpurl') . '/wp-subscription-manager.php';
		if ( $email != 'admin' ) {
			$link = add_query_arg('email', urlencode(urlencode($email)), $link);
			$link = add_query_arg('key', $this->generate_key($email), $link);
		}
		$link = add_query_arg('ref', urlencode('http://' . $_SERVER['HTTP_HOST'] . wp_specialchars($_SERVER['REQUEST_URI'])), $link);
		$link = str_replace('+', '%2B', $link);
		if ( $html )
			$link = htmlentities($link);
		if ( !$echo )
			return $link;
		echo $link;
	}


	function add_admin_menu() {
		add_management_page(__('Comment Subscription Manager', 'subscribe-to-comments'), __('Subscriptions', 'subscribe-to-comments'), 8, __FILE__, 'sg_subscribe_admin');

		if ( class_exists('SmallOptions') )
			add_action('small_options_page', array('sg_subscribe_settings', 'options_page_contents'));
		else
			add_options_page(__('Subscribe to Comments', 'subscribe-to-comments'), __('Subscribe to Comments', 'subscribe-to-comments'), 5, basename(__FILE__), array('sg_subscribe_settings', 'options_page'));
	}


} // class sg_subscribe










function sg_subscribe_start() {
	global $sg_subscribe;

	if ( !$sg_subscribe ) {
		load_plugin_textdomain('subscribe-to-comments');
		$sg_subscribe = new sg_subscribe();
	}
}

function sg_subscribe_admin() {
	include (ABSPATH . 'wp-subscription-manager.php');
}


// This will be overridden if the user manually places the function
// in the comments form before the comment_form do_action() call
add_action('comment_form', 'show_subscription_checkbox');

// priority is very low (50) because we want to let anti-spam plugins have their way first.
add_action('comment_post', create_function('$a', 'global $sg_subscribe; sg_subscribe_start(); return $sg_subscribe->send_notifications($a);'), 50);
add_action('comment_post', create_function('$a', 'global $sg_subscribe; sg_subscribe_start(); return $sg_subscribe->add_subscriber($a);'));

add_action('wp_set_comment_status', create_function('$a', 'global $sg_subscribe; sg_subscribe_start(); return $sg_subscribe->send_notifications($a);'));
add_action('admin_menu', create_function('$a', 'global $sg_subscribe; sg_subscribe_start(); $sg_subscribe->add_admin_menu();'));
add_action('admin_head', create_function('$a', 'global $sg_subscribe; sg_subscribe_start(); $sg_subscribe->sg_wp_head();'));



// detect "subscribe without commenting" attempts
add_action('init', create_function('$a','global $sg_subscribe; if ( $_POST[\'solo-comment-subscribe\'] == \'solo-comment-subscribe\' && is_numeric($_POST[\'postid\']) ) {
	sg_subscribe_start();
	$sg_subscribe->solo_subscribe($_POST[\'email\'], $_POST[\'postid\']);
}')
);

define('STC_PLUGIN_BASENAME', plugin_basename(__FILE__));

?>