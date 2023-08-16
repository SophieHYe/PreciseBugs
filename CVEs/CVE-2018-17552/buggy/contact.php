<?php
function nvweb_contact($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $webgets;
	global $dictionary;
	global $webuser;
	global $theme;
    global $events;
	
	$webget = 'contact';

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
            'name' => t(159, 'Name'),
            'email' => t(44, 'E-Mail'),
            'message' => t(380, 'Message'),
            'fields_blank' => t(444, 'You left some required fields blank.'),
            'contact_request_sent' => t(445, 'Your contact request has been sent. We will contact you shortly.'),
            'contact_request_failed' => t(446, 'We\'re sorry. Your contact request could not be sent. Please try again or find another way to contact us.'),
            'receipt_confirmation' => t(650, 'Receipt confirmation')
		);

		// theme translations 
		// if the web theme has custom translations for this string subtypes, use it (for the user selected language)
		/* just add the following translations to your json theme dictionary:

			"name": "Name",
			"email": "E-Mail",
			"message": "Message",
		    "fields_blank": "You left some required fields blank.",
		    "contact_request_sent": "Your contact request has been sent. We will contact you shortly.",
		    "contact_request_failed": "We're sorry. Your contact request could not be sent. Please try again or find another way to contact us.",
		    "receipt_confirmation": "Receipt confirmation"

		*/
		if(!empty($website->theme) && method_exists($theme, 't'))
		{
			foreach($webgets[$webget]['translations'] as $code => $text)
			{
				$theme_translation = $theme->t($code);
				if(!empty($theme_translation) && $code!=$theme_translation)
					$webgets[$webget]['translations'][$code] = $theme_translation;
			}
		}
	}

	if(empty($vars['notify']))
        $vars['notify'] = 'alert';

	$out = '';

	switch(@$vars['mode'])
	{	
		case 'send':
            if(!empty($_POST))  // form sent
            {
                // a page may have several forms, which one do we have to check?
                if(!empty($vars['form']))
                {
                    list($field_name, $field_value) = explode('=', $vars['form']);
                    $field_name = trim($field_name);
                    $field_value = trim($field_value);
                    if($_POST[$field_name]!=$field_value)
                        return;
                }

                // try to check if this send request really comes from the website and not from a spambot
                if( parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->subdomain.'.'.$website->domain &&
                    parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->domain )
                    return;

                // prepare fields and labels
                $fields = explode(',', @$vars['fields']);
                $labels = explode(',', @$vars['labels']);
                if(empty($labels))
                    $labels = $fields;

                $labels = array_map(
                    function($key)
                    {
                        global $webgets;
                        global $theme;

                        $key = trim($key);

                        $tmp = $theme->t($key);

                        if(!empty($tmp))
                            return $tmp;
                        else
                            return $webgets['contact']['translations'][$key];
                    },
                    $labels
                );
                $fields = array_combine($fields, $labels);
	            
                // $fields = array( 'field_name' => 'field_label', ... )

                // check required fields
                $errors = array();
                $required = array();

                if(!empty($vars['required']))
                    $required = explode(',', $vars['required']);

                if(!empty($required))
                {
                    foreach($required as $field)
                    {
                        $field = trim($field);
                        $value = trim($_POST[$field]);
                        if(empty($value))
                            $errors[] = $fields[$field];
                    }

                    if(!empty($errors))
                        return nvweb_contact_notify($vars, true, $webgets[$webget]['translations']['fields_blank'].' ('.implode(", ", $errors).')');
                }

                // create e-mail message and send it
                $message = nvweb_contact_generate($fields);

	            // prepare any attachment to be sent
	            $attachments = array();
	            foreach($fields as $field => $label)
	            {
		            if(isset($_FILES[$field]) && $_FILES[$field]['error']==0)
		            {
			            $attachments[] = array(
				            'file' => $_FILES[$field]['tmp_name'],
				            'name' => $_FILES[$field]['name']
			            );
		            }
	            }

                $subject = $vars['subject'];
                if(!empty($subject))
                    $subject = ' | '.$theme->t($subject);
                $subject = $website->name.$subject;

                $recipients = $website->contact_emails;
                if(!empty($vars['recipients']))
                    $recipients = $vars['recipients'];

                $event_messages = $events->trigger(
                    'contact',
                    'before_sending',
                    array(
                        'subject' => &$subject,
                        'message' => &$message,
                        'vars' => &$vars,
                        'emails' => &$recipients,
                        'files' => &$attachments
                    )
                );

                if(!empty($event_messages))
                {
                    $out = array();
                    foreach($event_messages as $module => $result)
                    {
                        if(isset($result['error']) && !empty($result['error']))
                            $out[] = $result['error'];
                    }

                    if(!empty($out))
                    {
                        $out = nvweb_contact_notify($vars, true, implode('<br />', $out));
                        return $out;
                    }
                }

                $sent = nvweb_send_email($subject, $message, $recipients, $attachments);

                if($sent)
                {
                    if(!empty($vars['receipt_confirmation_email']))
                    {
                        $confirmation_email = '';
                        if($vars['receipt_confirmation_email'] == 'webuser')
                            $confirmation_email = $webuser->email;
                        else
                            $confirmation_email = $_POST[$vars['receipt_confirmation_email']];

                        if(!empty($confirmation_email))
                        {
                            nvweb_send_email(
                                $subject.' ('.$webgets[$webget]['translations']['receipt_confirmation'].')',
                                $message,
                                $confirmation_email,
                                $attachments
                            );
                        }
                    }

                    $events->trigger(
                        'contact',
                        'sent',
                        array(
                            'subject' => $subject,
                            'message' => $message,
                            'form' => @$vars['form'],
                            'emails' => $recipients,
                            'files' => $attachments
                        )
                    );
                    $out = nvweb_contact_notify($vars, false, $webgets[$webget]['translations']['contact_request_sent']);
                }
                else
                    $out = nvweb_contact_notify($vars, true, $webgets[$webget]['translations']['contact_request_failed']);
            }

    }
	
	return $out;
}

function nvweb_contact_notify($vars, $is_error, $message)
{
    global $events;

    $out = '';

    switch($vars['notify'])
    {
        case 'inline':
            if($is_error)
                $out = '<div class="nvweb-contact-form-error">'.$message.'</div>';
            else
                $out = '<div class="nvweb-contact-form-success">'.$message.'</div>';
            break;

        case 'alert':
            nvweb_after_body('js', 'alert("'.$message.'");');
            break;

        default:
            // if empty, default is alert
            if(empty($vars['notify']))
            {
                nvweb_after_body('js', 'alert("'.$message.'");');
            }
            else
            {
                // if not empty, it's a javascript function call
                if($is_error && !empty($vars['error_callback']))
                    nvweb_after_body('js', $vars['error_callback'].'("'.$message.'");');
                else
                    nvweb_after_body('js', $vars['notify'].'("'.$message.'");');
            }
            break;
    }

    $events->trigger(
        'contact',
        'after_sending',
        array(
            'sent' => !$is_error,
            'message' => $message
        )
    );

    return $out;
}

function nvweb_contact_generate($fields)
{
    global $website;
    global $DB;

    $out = array();

    // default colors
    $background_color = '#E5F1FF';
    $text_color = '#595959';
    $title_color = '#595959';

    $background_color_db = $DB->query_single('value', 'nv_permissions', 'name = '.protect("nvweb.contact.background_color").' AND website = '.protect($website->id), 'id DESC');
    $text_color_db = $DB->query_single('value', 'nv_permissions', 'name = '.protect("nvweb.contact.text_color").' AND website = '.protect($website->id), 'id DESC');
    $title_color_db = $DB->query_single('value', 'nv_permissions', 'name = '.protect("nvweb.contact.titles_color").' AND website = '.protect($website->id), 'id DESC');

    if(!empty($background_color_db))    $background_color = str_replace('"', '', $background_color_db);
    if(!empty($text_color_db))          $text_color = str_replace('"', '', $text_color_db);
    if(!empty($title_color_db))         $title_color = str_replace('"', '', $title_color_db);

    $out[] = '<div style=" background: '.$background_color.'; width: 600px; border-radius: 6px; margin: 10px auto; padding: 1px 20px 20px 20px;">';

    if(is_array($fields))
    {
        foreach($fields as $field => $label)
        {
            $field = trim($field); // remove unwanted spaces

           if(substr($field, -2, 2)=='[]')
               $field = substr($field, 0, -2);

            if(is_array($_REQUEST[$field]))
            {
                $value = print_r($_REQUEST[$field], true);
                $value = str_replace("Array\n", '', $value);
                $value = nl2br($value);
            }
            else
                $value = nl2br($_REQUEST[$field]);

	        if(empty($value) && isset($_FILES[$field]))
		        $value = $_FILES[$field]['name'];

            $out[] = '<div style="margin: 25px 0px 10px 0px;">';
            $out[] = '    <div style="color: '.$title_color.'; font-size: 17px; font-weight: bold; font-family: Verdana;">'.$label.'</div>';
            $out[] = '</div>';
            $out[] = '<div style=" background: #fff; border-radius: 6px; padding: 10px; margin-top: 5px; line-height: 25px; text-align: justify; ">';
            $out[] = '    <div class="text" style="color: '.$text_color.'; font-size: 16px; font-style: italic; font-family: Verdana;">'.$value.'</div>';
            $out[] = '</div>';
        }
    }
    else
        $out[] = $fields;

    $out[] = '</div>';

    return implode("\n", $out);
}

?>