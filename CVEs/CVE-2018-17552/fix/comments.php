<?php
require_once(NAVIGATE_PATH.'/lib/external/force-utf8/Encoding.php');

function nvweb_comments($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $webgets;
	global $dictionary;
	global $webuser;
	global $theme;
    global $events;
    global $session;

	$webget = 'comments';

	if(!isset($webgets[$webget]))
	{
		$webgets[$webget] = array();

		global $lang;
		if(empty($lang))
		{
			$lang = new language();
			$lang->load($current['lang']);
		}

		// default translations
		$webgets[$webget]['translations'] = array(
            'post_a_comment' => t(379, 'Post a comment'),
            'name' => t(159, 'Name'),
            'email' => t(44, 'E-Mail'),
			'website' => t(177, 'Website'),
            'message' => t(380, 'Message'),
            'email_will_not_be_published' => t(381, 'E-Mail will not be published'),
            'submit' => t(382, 'Submit'),
            'sign_in_or_sign_up_to_post_a_comment' => t(383, 'Sign in or Sign up to post a comment'),
            'comments_on_this_entry_are_closed' => t(384, 'Comments on this entry are closed'),
            'please_dont_leave_any_field_blank' => t(385, 'Please don\'t leave any field blank'),
            'your_comment_has_been_received_and_will_be_published_shortly' => t(386, 'Your comment has been received and will be published shortly'),
            'new_comment' => t(387, 'New comment'),
            'review_comments' => t(388, 'Review comments'),
            'comments_subscription' => t(655, "Subscribe to comments on this entry"),
            'comment_published' => t(555, "Item has been successfully published."),
            'security_error' => t(344, "Security error"),
            'unexpected_error' => t(56, "Unexpected error"),
            'comment_deleted' => t(55, "Item successfully deleted")
		);

		// theme translations
		// if the web theme has custom translations for this string subtypes, use it (for the user selected language)
		/* just add the following translations to your json theme dictionary:
			"post_a_comment": "Post a comment",
			"name": "Name",
			"email": "E-Mail",
			"website": "Website",
			"message": "Message",
			"email_will_not_be_published": "E-Mail will not be published",
			"submit": "Submit",
			"sign_in_or_sign_up_to_post_a_comment": "Sign in or Sign up to post a comment",
			"comments_on_this_entry_are_closed": "Comments on this entry are closed",
			"please_dont_leave_any_field_blank": "Please don't leave any field blank",
			"your_comment_has_been_received_and_will_be_published_shortly": "Your comment has been received and will be published shortly",
			"new_comment": "New comment",
			"review_comments": "Review comments",
		    "comments_subscription": "Subscribe to comments on this entry",
		    "comment_published": "Comment has been successfully published.",
            "security_error": "Security error",
            "unexpected_error": "Unexpected error",
            "comment_deleted": "Comment successfully deleted"
		*/

		if(!empty($website->theme) && method_exists($theme, 't'))
		{
			foreach($webgets[$webget]['translations'] as $code => $text)
			{
				$theme_translation = $theme->t($code);

				if(!empty($theme_translation) && $theme_translation!=$code)
					$webgets[$webget]['translations'][$code] = $theme_translation;
			}
		}
	}

    // set default callback
	if(empty($vars['callback']))
        $vars['callback'] = 'alert';

    // check callback attributes
    $callback = $vars['callback'];
    if(!empty($vars['alert_callback']))
        $callback = $vars['alert_callback'];
	else if(!empty($vars['callback_alert']))
		$callback = $vars['callback_alert'];

    $callback_error = $callback;
    if(!empty($vars['error_callback']))
        $callback_error = $vars['error_callback'];
	else if(!empty($vars['callback_error']))
		$callback_error = $vars['callback_error'];


    $out = '';

    // if the current page belongs to a structure entry
    // we need to get the associated objects to retrieve and post its comments
    // (because structure pages can't have associated comments)
    // so, ONLY the FIRST object associated to a category can have comments in a structure entry page
    // (of course if the element has its own page, it can have its own comments)
    // NOTE: the following procedure does not work for a category of products
    $object = $current['object'];
    $object_type = $current['type'];
    $object_id = $current['id'];
    if($current['type']=='structure')
    {
        $object = $object->elements(0); // item = structure->elements(first)
        $object_id = $object->id;
        $object_type = 'item';
    }

	switch(@$vars['mode'])
	{
		case 'process':

            if(isset($_GET['nv_approve_comment']))
            {
                // process 1-click comment approval
                $comment = new comment();
                $comment->load($_GET['id']);

                if(!empty($comment->id) && $comment->status == -1) // comment is still not reviewed
                {
                    $hash = $_GET['hash'];
                    if($hash == sha1($comment->id . $comment->email . APP_UNIQUE . serialize($website->contact_emails)))
                    {
                        // hash check passed
                        $comment->status = 0;
                        $comment->save();

                        $response = $webgets[$webget]['translations']['comment_published'];

                        if($vars['notify']=='inline' || $callback=='inline')
                            $out = '<div class="comment-success">'.$response.'</div>';
                        else if(!isset($vars['notify']) || $vars['notify']=='callback')
                            nvweb_after_body("js", $callback.'("'.$response.'");');
                    }
                    else
                    {
                        $response = $webgets[$webget]['translations']['security_error'];

                        if($vars['notify']=='inline' || $callback_error=='inline')
                            $out = '<div class="comment-error">'.$response.'</div>';
                        else if(!isset($vars['notify']) || $vars['notify']=='callback')
                            nvweb_after_body("js", $callback_error.'("'.$response.'");');
                    }
                }
                else
                {
                    $response = $webgets[$webget]['translations']['unexpected_error'];

                    if($vars['notify']=='inline' || $callback_error=='inline')
                        $out = '<div class="comment-error">'.$response.'</div>';
                    else if(!isset($vars['notify']) || $vars['notify']=='callback')
                        nvweb_after_body("js", $callback_error.'("'.$response.'");');
                }

                $website->purge_pages_cache();
            }

            if(isset($_GET['nv_remove_comment']))
            {
                // process 1-click comment removal
                $comment = new comment();
                $comment->load($_GET['id']);

                if(!empty($comment->id) && $comment->status <= 0) // comment is still not reviewed or already published
                {
                    $hash = $_GET['hash'];
                    if($hash == sha1($comment->id . $comment->email . APP_UNIQUE . serialize($website->contact_emails)))
                    {
                        // hash check passed
                        $comment->delete();
                        $response = $webgets[$webget]['translations']['comment_deleted'];
                        if($vars['notify']=='inline' || $callback=='inline')
                            $out = '<div class="comment-success">'.$response.'</div>';
                        else if(!isset($vars['notify']) || $vars['notify']=='callback')
                            nvweb_after_body("js", $callback.'("'.$response.'");');
                    }
                    else
                    {
                        $response = $webgets[$webget]['translations']['security_error'];
                        if($vars['notify']=='inline' || $callback_error=='inline')
                            $out = '<div class="comment-error">'.$response.'</div>';
                        else if(!isset($vars['notify']) || $vars['notify']=='callback')
                            nvweb_after_body("js", $callback_error.'("'.$response.'");');
                    }
                }
                else
                {
                    $response = $webgets[$webget]['translations']['unexpected_error'];
                    if($vars['notify']=='inline' || $callback_error=='inline')
                        $out = '<div class="comment-error">'.$response.'</div>';
                    else if(!isset($vars['notify']) || $vars['notify']=='callback')
                        nvweb_after_body("js", $callback_error.'("'.$response.'");');
                }

                $website->purge_pages_cache();
            }

			if($_REQUEST['form-type']=='comment-reply' || isset($_POST[$vars['field-message']]))
			{
				// user wants to add a new comment
				if(empty($vars['field-name']))      $vars['field-name'] = 'reply-name';
				if(empty($vars['field-email']))     $vars['field-email'] = 'reply-email';
				if(empty($vars['field-url']))       $vars['field-url'] = 'reply-url';
				if(empty($vars['field-message']))   $vars['field-message'] = 'reply-message';
				if(empty($vars['field-subscribe'])) $vars['field-subscribe'] = 'reply-subscribe';
				if(empty($vars['field-reply_to']))  $vars['field-reply_to'] = 'reply-to-comment';

				// the following is only used in direct PHP calls to nvweb_comments()
                if(!empty($vars['object']) && $vars['object']!='nvweb')
                    $object = $vars['object'];

                // deprecated
                if(!empty($vars['element']))
                    $object = $vars['element'];

				$comment_name = @$_REQUEST[$vars['field-name']];
				$comment_email = @$_REQUEST[$vars['field-email']];
				$comment_url = @$_REQUEST[$vars['field-url']];
				$comment_message = @$_REQUEST[$vars['field-message']];
                $comment_subscribe = (@in_array($_REQUEST[$vars['field-subscribe']], array('1', true, 'true')))? 1 : 0;
                $comment_reply_to = @$_REQUEST[$vars['field-reply_to']];

				if( ( (empty($comment_name)  ||  empty($comment_email)) && empty($webuser->id) )
                    ||
					empty($comment_message)
                )
				{
                    $response = $webgets[$webget]['translations']['please_dont_leave_any_field_blank'];

                    if($vars['notify']=='inline' || $callback_error=='inline')
	                    $out = '<div class="comment-error">'.$response.'</div>';
                    else if(!isset($vars['notify']) || $vars['notify']=='callback')
	                    nvweb_after_body("js", $callback_error.'("'.$response.'");');
					return $out;
				}

				$status = -1; // new comment, not approved

				if(empty($object->comments_moderator))
                    $status = 0; // all comments auto-approved

                // remove any <nv /> or {{nv}} tag
                $comment_name = core_remove_nvtags($comment_name);
                $comment_name = strip_tags($comment_name);
                $comment_message = core_remove_nvtags($comment_message);

                $comment = new comment();
                $comment->id = 0;
                $comment->website = $website->id;
                $comment->object_type = $object_type;
                $comment->object_id = $object_id;
                $comment->user = (empty($webuser->id)? 0 : $webuser->id);
                $comment->name = $comment_name;
                $comment->email = filter_var($comment_email, FILTER_SANITIZE_EMAIL);
                $comment->url = filter_var($comment_url, FILTER_SANITIZE_URL);
                $comment->ip = core_ip();
                $comment->date_created = core_time();
                $comment->date_modified = 0;
                $comment->status = $status;
                $comment->subscribed = value_or_default($comment_subscribe, 0);
                $comment->reply_to = value_or_default($comment_reply_to, 0);
                $comment->message = $comment_message;

                $properties = array();

                // check if there are comment properties values
                if(isset($vars['field-properties-prefix']))
                {
                    // check every possible property
                    $e_properties = property::elements($object->template, 'comment');
                    for($ep=0; $ep < count($e_properties); $ep++)
                    {
                        if(isset($_POST[$vars['field-properties-prefix'] . $e_properties[$ep]->id]))
                            $properties[$e_properties[$ep]->id] = $_POST[$vars['field-properties-prefix'] . $e_properties[$ep]->id];
                    }
                }

                // trigger the "new_comment" event through the extensions system before inserting it!
                $extensions_messages = $events->trigger(
                    'comment',
                    'before_insert',
                    array(
                        'comment' => $comment,
                        'properties' => $properties
                    )
                );

                foreach($extensions_messages as $ext_name => $ext_result)
                {
                    if(isset($ext_result['error']))
                    {
                        $response = $ext_result['error'];
                        if($vars['notify']=='inline' || $callback_error=='inline')
                            $out = '<div class="comment-error">'.$response.'</div>';
                        else if(!isset($vars['notify']) || $vars['notify']=='callback')
                            nvweb_after_body("js", $callback_error.'("'.$response.'");');
                        return $out;
                    }
                }

                $comment->insert();
                if(!empty($properties))
                    property::save_properties_from_array('comment', $comment->id, $object->template, $properties);

                // reload the object to retrieve the new comments
                if($comment->object_type == "product")
                    $object = new product();
                else
                    $object = new item();

                $object->load($comment->object_id);

                if( in_array($current['type'], array('item', 'product')) &&
                    (!isset($vars['object']) && !isset($vars['element']))
                )
                    $current['object'] = $object;

                // trigger the "new_comment" event through the extensions system
                $events->trigger(
                    'comment',
                    'after_insert',
                    array(
                        'comment' => &$comment,
                        'properties' => $properties
                    )
                );

				if(!empty($comment->id))
                {
                    if($status == -1)
                    {
                        $response = $webgets[$webget]['translations']['your_comment_has_been_received_and_will_be_published_shortly'];
                        if($vars['notify']=='inline' || $callback_error=='inline')
                            $out = '<div class="comment-success">'.$response.'</div>';
                        else if(!isset($vars['notify']) || $vars['notify']=='callback')
                            nvweb_after_body("js", $callback.'("'.$response.'");');
                    }
                    else
                    {
                        $response = $webgets[$webget]['translations']['your_comment_has_been_received_and_will_be_published_shortly'];
                        if($vars['notify']=='inline' || $callback_error=='inline')
                            $out = '<div class="comment-success">'.$response.'</div>';
                        else if(!isset($vars['notify']) || $vars['notify']=='callback')
                            nvweb_after_body("js", $callback.'("'.$response.'");');
                    }
                }

                $notify_addresses = $website->contact_emails;

                if(!empty($object->comments_moderator))
                    $notify_addresses[] = user::email_of($object->comments_moderator);

                $hash = sha1($comment->id . $comment->email . APP_UNIQUE . serialize($website->contact_emails) );

                $base_url = nvweb_source_url($current['type'], $current['id']);

                $fid = $object_type . "s"; // itemS, productS...

                $message = navigate_compose_email(array(
                    array(
                        'title' => t(9, 'Content'),
                        'content' => $object->dictionary[$current['lang']]['title']
                    ),
                    array(
                        'title' => $webgets[$webget]['translations']['name'],
                        'content' => $comment_name.@$webuser->username
                    ),
                    array(
                        'title' => $webgets[$webget]['translations']['email'],
                        'content' => $comment_email.@$webuser->email
                    ),
	                array(
                        'title' => $webgets[$webget]['translations']['website'],
                        'content' => $comment_url.@$webuser->social_website
                    ),
                    array(
                        'title' => $webgets[$webget]['translations']['message'],
                        'content' => nl2br($comment_message)
                    ),
                    array(
                        'footer' =>
                            '<a href="'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?wid='.$website->id.'&fid='.$fid.'&act=2&tab=5&id='.$object_id.'"><strong>'.$webgets[$webget]['translations']['review_comments'].'</strong></a>'.
                            '&nbsp;&nbsp;|&nbsp;&nbsp;'.
                            '<a style=" color: #008830" href="'.$base_url.'?nv_approve_comment&id='.$comment->id.'&hash='.$hash.'">'.t(258, "Publish").'</a>'.
                            '&nbsp;&nbsp;|&nbsp;&nbsp;'.
                            '<a style=" color: #FF0090" href="'.$base_url.'?nv_remove_comment&id='.$comment->id.'&hash='.$hash.'">'.t(525, "Remove comment (without confirmation)").'</a>'
                    )
                ));

                // trying to implement One-Click actions (used in Google GMail)
                // You need to be registered with Google first: https://developers.google.com/gmail/markup/registering-with-google
                $one_click_actions = '
                    <script type="application/ld+json">
                    {
                        "@context": "http://schema.org",
                        "@type": "EmailMessage",
                        "potentialAction":
                        {
                            "@type": "ViewAction",
                            "name": "'.$webgets[$webget]['translations']['review_comments'].'",
                            "url": "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?wid='.$website->id.'&fid='.$fid.'&act=2&tab=5&id='.$object_id.'"
                        }
                    }
                    </script>
				';

				$message = '<html><head>'.$one_click_actions.'</head><body>'.$message.'</body></html>';

                foreach ($website->contact_emails as $contact_address)
                {
                    @nvweb_send_email(
                        $website->name . ' | ' . $webgets[$webget]['translations']['new_comment'],
                        $message,
                        $contact_address,
                        null,
                        true
                    );
                }

                $website->purge_pages_cache();
			}
			break;

		case 'reply':
			if($object->comments_enabled_to==2 && empty($webuser->id))
			{
				// Post a comment form (unsigned users)
				$out = '
					<div class="comments-reply">
						<div><div class="comments-reply-info">'.$webgets[$webget]['translations']['post_a_comment'].'</div></div>
						<br />
						<form action="'.NVWEB_ABSOLUTE.'/'.$current['route'].'" method="post">
							<input type="hidden" name="form-type" value="comment-reply" />
							<input type="hidden" name="reply-to-comment" value="0" />
							<div class="comments-reply-field"><label>'.$webgets[$webget]['translations']['name'].'</label> <input type="text" name="reply-name" value="" /></div>
							<div class="comments-reply-field"><label>'.$webgets[$webget]['translations']['email'].' *</label> <input type="text" name="reply-email" value="" /></div>
							<div class="comments-reply-field"><label>'.$webgets[$webget]['translations']['message'].'</label> <textarea name="reply-message"></textarea></div>
							<div class="comments-reply-field"><label><input type="checkbox" name="reply-subscribe" value="1" /> '.$webgets[$webget]['translations']['comments_subscription'].'</label></div>
							<!-- {{navigate-comments-reply-extra-fields-placeholder}} -->
							<div class="comments-reply-field comments-reply-field-info-email"><label>&nbsp;</label> * '.$webgets[$webget]['translations']['email_will_not_be_published'].'</div>
							<div class="comments-reply-field comments-reply-field-submit"><input class="comments-reply-submit" type="submit" value="'.$webgets[$webget]['translations']['submit'].'" /></div>
						</form>
					</div>
				';

                $extensions_messages = $events->trigger(
                    'comment',
                    'reply_extra_fields',
                    array(
                        'html' => &$out
                    )
                );
                // add any extra field generated
                if(!empty($extensions_messages))
                {
                    $extra_fields = array_map(
                        function($v)
                        {
                            return $v;
                        },
                        array_values($extensions_messages)
                    );

                    $out = str_replace(
                        '<!-- {{navigate-comments-reply-extra-fields-placeholder}} -->',
                        implode("\n", $extra_fields),
                        $out
                    );
                }

			}
			else if($object->comments_enabled_to > 0 && !empty($webuser->id))
			{
				// Post a comment form (signed in users)
                if(empty($vars['avatar_size']))
                    $vars['avatar_size'] = 32;

                $avatar_url = NVWEB_OBJECT.'?type=blank';
                if(!empty($webuser->avatar))
                    $avatar_url = NVWEB_OBJECT.'?wid='.$website->id.'&id='.$webuser->avatar.'&amp;disposition=inline&width='.$vars['avatar_size'].'&height='.$vars['avatar_size'];

				$out = '
					<div class="comments-reply">
						<div><div class="comments-reply-info">'.$webgets[$webget]['translations']['post_a_comment'].'</div></div>
						<br />
						<form action="'.NVWEB_ABSOLUTE.'/'.$current['route'].'" method="post">
							<input type="hidden" name="form-type" value="comment-reply" />
							<input type="hidden" name="reply-to-comment" value="0" />
							<div class="comments-reply-field"><label style="display: none;">&nbsp;</label> <img src="'.$avatar_url.'" width="'.$vars['avatar_size'].'" height="'.$vars['avatar_size'].'" align="absmiddle" /> <span class="comments-reply-username">'.$webuser->username.'</span><a class="comments-reply-signout" href="?webuser_signout">(x)</a></div>
							<br />
							<div class="comments-reply-field"><label>'.$webgets[$webget]['translations']['message'].'</label> <textarea name="reply-message"></textarea></div>
							<div class="comments-reply-field"><label><input type="checkbox" name="reply-subscribe" value="1" /> '.$webgets[$webget]['translations']['comments_subscription'].'</label></div>
							<!-- {{navigate-comments-reply-extra-fields-placeholder}} -->
							<div class="comments-reply-field-submit"><input class="comments-reply-submit" type="submit" value="'.$webgets[$webget]['translations']['submit'].'" /></div>
						</form>
					</div>
				';

                $extensions_messages = $events->trigger('comment', 'reply_extra_fields', array('html' => $out));
                // add any extra field generated
                if(!empty($extensions_messages))
                {
                    $extra_fields = array_map(
                        function($v)
                        {
                            return $v;
                        },
                        array_values($extensions_messages)
                    );

                    $out = str_replace(
                        '<!-- {{navigate-comments-reply-extra-fields-placeholder}} -->',
                        implode("\n", $extra_fields),
                        $out
                    );
                }
			}
			else if($object->comments_enabled_to==1)
			{
				$out = '<div class="comments-reply">
                            <div class="comments-reply-info">'.
                                $webgets[$webget]['translations']['sign_in_or_sign_up_to_post_a_comment'].
                            '</div>
                        </div>';
			}
			else
			{
				$out = '<div class="comments-reply">
                            <div class="comments-reply-info">'.
                                $webgets[$webget]['translations']['comments_on_this_entry_are_closed'].
                            '</div>
                        </div>';
			}
			break;

		case 'comments':
            setlocale(LC_ALL, $website->languages[$session['lang']]['system_locale']);

			list($comments, $comments_total) = nvweb_comments_list(0, NULL, NULL, $vars['order']); // get all comments of the current entry

			if(empty($vars['avatar_size']))
				$vars['avatar_size'] = '48';

			if(empty($vars['date_format']))
				$vars['date_format'] = '%d %B %Y %H:%M';

			for($c=0; $c < $comments_total; $c++)
			{
				$avatar = $comments[$c]->avatar;
				if(!empty($avatar))
					$avatar = '<img src="'.NVWEB_OBJECT.'?type=image&id='.$avatar.'" width="'.$vars['avatar_size'].'px" height="'.$vars['avatar_size'].'px"/>';
				else
					$avatar = '<img src="data:image/gif;base64,R0lGODlhAQABAPAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" width="'.$vars['avatar_size'].'px" height="'.$vars['avatar_size'].'px"/>';

                $comment = new comment();
                $comment->load_from_resultset(array($comments[$c]));
                $depth = 'data-depth="'.$comment->depth().'"';

				$out .= '
					<div class="comment"'.$depth.'>
						<div class="comment-avatar">'.$avatar.'</div>
						<div class="comment-username">'.(!empty($comments[$c]->username)? $comments[$c]->username : $comments[$c]->name).'</div>
						<div class="comment-date">'.Encoding::toUTF8(strftime($vars['date_format'], $comments[$c]->date_created)).'</div>
						<div class="comment-message">'.nl2br($comments[$c]->message).'</div>
						<div style="clear:both"></div>
					</div>
				';
			}
			break;
	}

	return $out;
}

function nvweb_comments_list($offset=0, $limit=NULL, $permission=NULL, $order='oldest')
{
	global $DB;
	global $website;
	global $current;

    $limit = value_or_default($limit, 2147483647);

    if($order=='newest' || $order=='hierarchy_newest')
        $orderby = "nvc.date_created DESC";
    else
        $orderby = "nvc.date_created ASC";

    $object = $current['object'];
    $object_id = $current['id'];
    $object_type = $current['type'];

    if($object_type == 'structure')
    {
        $object = $object->elements(0); // item = structure->elements(first)
        $object_id = $object->id;
        $object_type = "item";
    }


    if(strpos($order, 'hierarchy')!==false)
    {
        // list comments keeping hierarchy
        // MySQL (still) does not have recursive queries, meanwhile we apply the following procedure:
        // find all comments of 0-depth (root level) and calculate if they have any reply
        // then, in PHP, parse the results and load (recursively) all replies and subreplies
        // in the result array, INSERT the additional results in the position where they must be respecting the order requested (oldest/newest)
        // note 1: this procedure allows optimization, for now we've made it work
        // note 2: the only drawback is that offset/limit it's only taken into account for the root level comments, so the
        //         number of results is variable on each request; we found that an acceptable drawback

        $DB->query('
            SELECT SQL_CALC_FOUND_ROWS nvc.*, nvwu.username, nvwu.avatar,
                   (SELECT COUNT(nvcr.id) 
                      FROM nv_comments nvcr 
                     WHERE nvcr.reply_to = nvc.id 
                       AND nvcr.status = 0
                   ) AS replies
              FROM nv_comments nvc
             LEFT OUTER JOIN nv_webusers nvwu
                          ON nvwu.id = nvc.user
             WHERE nvc.website = :wid 
               AND nvc.object_type = :object_type
               AND nvc.object_id = :object_id 
               AND nvc.status = 0
               AND nvc.reply_to = 0
            ORDER BY ' . $orderby . '
            LIMIT ' . $limit . '
           OFFSET ' . $offset,
            'object',
            array(
                ':wid' => $website->id,
                ':object_type' => $object_type,
                ':object_id' => $object_id
            )
        );

        $rs = $DB->result();

        $out = array();

        for($r=0; $r < count($rs); $r++)
        {
            $rows_to_add = array();
            if($rs[$r]->replies > 0)
            {
                $c = new comment();
                $c->load_from_resultset(array($rs[$r]));
                $rows_to_add = $c->get_replies();
            }

            $out[] = $rs[$r];
            if(!empty($rows_to_add))
            {
                foreach($rows_to_add as $rta)
                    $out[] = $rta;
            }
        }

        $rs = $out;
        $total = count($rs);
    }
    else // plain list
    {
        $DB->query('
            SELECT SQL_CALC_FOUND_ROWS nvc.*, nvwu.username, nvwu.avatar
              FROM nv_comments nvc
             LEFT OUTER JOIN nv_webusers nvwu
                          ON nvwu.id = nvc.user
             WHERE nvc.website = :wid
               AND nvc.object_type = :object_type
               AND nvc.object_id = :object_id 
               AND nvc.status = 0
            ORDER BY ' . $orderby . '
            LIMIT ' . $limit . '
           OFFSET ' . $offset,
            'object',
            array(
                ':wid' => $website->id,
                ':object_type' => $object_type,
                ':object_id' => $object_id
            )
        );

        $rs = $DB->result();
        $total = $DB->foundRows();
    }

	return array($rs, $total);
}

function nvweb_website_comments_list($offset=0, $limit=2147483647, $permission=NULL, $order='oldest')
{
    global $DB;
    global $website;
    global $current;

    if($order=='newest')
        $orderby = "nvc.date_created DESC";
    else
        $orderby = "nvc.date_created ASC";

    $DB->query('SELECT SQL_CALC_FOUND_ROWS nvc.*, nvwu.username, nvwu.avatar, nvwd.text as item_title
				  FROM nv_comments nvc
				 LEFT OUTER JOIN nv_webusers nvwu
							  ON nvwu.id = nvc.user
				 LEFT OUTER JOIN nv_webdictionary nvwd
				              ON nvwd.node_id = nvc.object_id AND
                                 nvwd.website = nvc.website AND
                                 nvwd.node_type = nvc.object_type AND
                                 nvwd.subtype = "title" AND
                                 nvwd.lang = :lang
				 WHERE nvc.website = :wid
				   AND status = 0
				ORDER BY '.$orderby.'
				LIMIT '.$limit.'
			   OFFSET '.$offset,
        array(
            ':wid' => $website->id,
            ':lang' => $current['lang']
        )
    );

    $rs = $DB->result();
    $total = $DB->foundRows();

    return array($rs, $total);
}

?>