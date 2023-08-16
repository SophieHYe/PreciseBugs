<?php
/**
 * 
 * Navigate CMS common core functions
 * 
 * @copyright Copyright (C) 2010-2013 Naviwebs. All rights reserved.
 * @author Naviwebs (http://www.naviwebs.com/) 
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 License
 * @version 1.7.7 2013-09-07
 *
 */

/**
 * Returns the translated version of an internal CMS string
 *
 * Given a certain ID, function t returns the associated string
 * in the current active language.
 *
 * @param integer $id The ID of the string on Navigate CMS dictionary
 * @param string $default The default string to be returned if no translation found
 * @param array $replace Array of substitutions; example: "Your name is {your_name}" [ 'your_name' => 'Navigate' ]
 * @param boolean $encodeChars Encode some special characters as HTML entities
 * @return string
 */
function t($id, $default='', $replace=array(), $encodeChars=false)
{
	global $lang;
	global $session;

	if(!method_exists($lang, 't'))
    {
        $lang = new language();
        $lang->load($session['lang']);
    }
	
	$out = $lang->t($id, $default, $replace, $encodeChars);

	if(empty($out))
	    $out = $default;

    return $out;
}

/**
 * Protects a string before inserting it into the database
 *
 * @param string $text
 * @param string $wrapped_by Surround the input string with "double" or 'single' quotes (default is "double")
 * @return string
 */
function protect($text, $wrapped_by="", $keep_numeric=false)
{
	global $DB;

    if($keep_numeric && is_numeric($text))
        return $text;

	return $DB->protect($text, $wrapped_by);
}

/**
 * Encodes " character to &quot; (HTML char)
 *
 * @param string $text
 * @return string
 */
function pquotes($text)
{
	return str_replace('"', '&quot;', $text);	
}

/**
 * Executes a Navigate CMS function taking the 'fid' url parameter
 * fid can be the name of the package (p.e. "dashboard") or its numeric assignment (p.e. "6")
 * note: if no "fid" is found, then loads the first available menu function
 *
 * @return mixed Navigate CMS package output
 */
function core_run()
{
	global $layout;
    global $menu_layout;

	$content = "";
	$fid = 'dashboard'; // default function

	if(isset($_REQUEST['fid']))
		$fid = $_REQUEST['fid'];

	$f = core_load_function($fid);

    if(empty($f) && ($fid=="dashboard" || empty($fid)))
    {
        // load first function available
        $fid = $menu_layout->menus[0]->items[0]->codename;
        if(empty($fid))
            $fid = "unknown";
        else
        {
            header('location: '.NAVIGATE_MAIN.'?fid='.$fid);
            core_terminate();
        }
    }

	if(file_exists('lib/packages/'.$f->codename.'/'.$f->codename.'.php'))
	{
		include('lib/packages/'.$f->codename.'/'.$f->codename.'.php');
		$content = run();
	}
	else
		$content = 'function '.$fid.': <strong>'.$f->codename.'</strong> has not been found!';
		
	return $content;	
}

/**
 * Finish Navigate CMS execution sending a flush, writing the session and disconnecting the database
 *
 * @param string $redirect_to send a HTTP header redirecting the browser to another URL after terminating the execution
 *
 */
function core_terminate($redirect_to="")
{
	global $DB;
	global $website;
	global $session;

    @$_SESSION['nvweb.' . $website->id] = $session;

	session_write_close();	
	if($DB)
		$DB->disconnect();

    if(!empty($redirect_to))
        header('Location: '.$redirect_to);

    flush();
    exit;
}

/**
 * Loads the metadata of a function giving its name or numeric code
 *
 * @param mixed $fid
 * @return object $function
 */
function core_load_function($fid)
{
	global $DB;
    global $menu_layout;

    // check if fid is an internal function
    // or we need to retrieve its information from the database
    switch($fid)
    {
        case 'utils':
            $func = new stdClass();
            $func->id = 'utils';
            $func->codename = 'utils';
            $func->category = 'internal';
            $func->icon = '';
            $func->lid = '';
            $func->enabled = 1;
            break;

        case 'grid_notes':
            $func = new stdClass();
            $func->id = 'grid_notes';
            $func->codename = 'grid_notes';
            $func->category = 'content';
            $func->icon = '';
            $func->lid = '';
            $func->enabled = 1;
            break;

        case 'permissions':
            $func = new stdClass();
            $func->id = 'permissions';
            $func->codename = 'permissions';
            $func->category = 'config';
            $func->icon = '';
            $func->lid = '';
            $func->enabled = 1;
            break;

        default:
            $query_params = NULL;
            if(is_numeric($fid))
            {
                $where = 'id = '.intval($fid);
            }
            else
            {
                $where = 'codename = :codename';
                $query_params = array(':codename' => $fid);
            }

            $DB->query(
                'SELECT * FROM nv_functions WHERE '.$where.' AND enabled = 1',
                'object',
                $query_params
            );

            $func = $DB->first();

            if(!$menu_layout->function_is_displayed($func->id))
                $func = false;
    }

    return $func;
}


/**
 * Converts a user formatted date to a unix timestamp
 *
 * @param string $date
 * @return integer
 */
function core_date2ts($date)
{
	global $user;
    global $website;
	
	$ts = 0;
	
	$aDate = explode(" ", $date); // hour is always the last part
    $aDate = array_values(array_filter($aDate));
    list($date, $time) = $aDate;

	if(!empty($time))
        list($hour, $minute) = explode(":", $time);
	else			  
	{
		$hour = 0;
        $minute = 0;
	}

    if(empty($user->timezone))
        $user->timezone = 'UTC';
	
	switch($user->date_format)
	{
		case "Y-m-d H:i":
			list($year, $month, $day) = explode("-", $date);
			break;
			
		case "d-m-Y H:i":
			list($day, $month, $year) = explode("-", $date);
			break;

		case "m-d-Y H:i":
			list($month, $day, $year) = explode("-", $date);		
			break;

		case "Y/m/d H:i":
			list($year, $month, $day) = explode("/", $date);		
			break;

		case "d/m/Y H:i":
			list($day, $month, $year) = explode("/", $date);		
			break;

		case "m/d/Y H:i":
			list($month, $day, $year) = explode("/", $date);				
			break;
	}

    // works on PHP 5.2+
    $userTimezone = new DateTimeZone($user->timezone);
    $utcTimezone = new DateTimeZone('UTC');
    $date = new DateTime($year.'-'.$month.'-'.$day.' '.$hour.':'.$minute, $userTimezone);
    $offset = $utcTimezone->getOffset($date);
    $ts = $date->format('U') + $offset;

	return $ts;	
}

/**
 * Converts a UNIX timestamp to a user formatted date
 *
 * @param integer $timestamp
 * @param boolean $time Set true to add the time after the date
 * @return string
 */
function core_ts2date($timestamp, $time=false)
{
	global $user;
	
	$format = $user->date_format;

    if(empty($format))
        $format = "Y-m-d H:i";

	if(!$time) $format = str_replace('H:i', '', $format);

    $user_timezone = 'UTC';

    if(!empty($user->timezone))
        $user_timezone = $user->timezone;

	$date = new DateTime();		
	if(version_compare(PHP_VERSION, '5.3.0') < 0)
	{
		$datets = getdate( ( int ) $timestamp );
		$date->setDate( $datets['year'] , $datets['mon'] , $datets['mday'] );
		$date->setTime( $datets['hours'] , $datets['minutes'] , $datets['seconds'] );
	}
	else
	{
		$date->setTimestamp(intval($timestamp));
		$date->setTimezone(new DateTimeZone($user_timezone));
	}
	
	return $date->format($format);
}


function core_ts2elapsed_time($timestamp)
{
    $time_elapsed 	= time() - $timestamp;
    $seconds 	= $time_elapsed ;
    $minutes 	= round($time_elapsed / 60 );
    $hours 		= round($time_elapsed / 3600);
    $days 		= round($time_elapsed / 86400 );
    $weeks 		= round($time_elapsed / 604800);
    $months 	= round($time_elapsed / 2600640 );
    $years 		= round($time_elapsed / 31207680 );

    // Seconds
    if($seconds <= 60)
    {
        $out = t(564, "%s seconds ago", array('%s' => $seconds));
    }
    //Minutes
    else if($minutes <= 60)
    {
        if($minutes==1)
            $out = t(565, "one minute ago");
        else
            $out = t(566, "%m minutes ago", array('%m' => $minutes));
    }
    //Hours
    else if($hours <=24)
    {
        if($hours==1)
            $out = t(567, "an hour ago");
        else
            $out = t(568, "%h hours ago", array('%h' => $hours));
    }
    //Days
    else if($days <= 7)
    {
        if($days==1)
            $out = t(569, "yesterday");
        else
            $out = t(570, "%d days ago", array('%d' => $days));
    }
    //Weeks
    else if($weeks <= 4.3)
    {
        if($weeks==1)
            $out = t(571, "a week ago");
        else
            $out = t(572, "%w weeks ago", array('%w' => $weeks));
    }
    //Months
    else if($months <=12)
    {
        if($months==1)
            $out = t(573, "a month ago");
        else
            $out = t(574, "%m months ago", array('%m' => $months));
    }
    //Years
    else
    {
        if($years==1)
            $out = t(575, "one year ago");
        else
            $out = t(576, "%y years ago", array('%y' => $years));
    }

    return $out;
}

/**
 * Returns the current UNIX timestamp (UTC)
 *
 * @return integer
 */
function core_time()
{
    if(class_exists("DateTime"))
    {
        $ts = new DateTime();
	    return $ts->format("U");
    }
    else
        return time();
}

/**
 * Converts numeric value from string to its decimal representation
 *  the format depends on the current signed in user and his preferences (decimal_separator, thousands_separator)
 *
 * @param string $value
 * @return string internal PHP representation of a decimal number (dot notation)
 */
function core_string2decimal($value)
{
    global $user;

    if(empty($user) || !isset($user->decimal_separator))
    {
        $user = new user();
        $user->decimal_separator = '¿'; // if no user preference, set a random character to represent the decimal separator
    }

    // remove all characters except numbers, the negative symbol and the decimal character (defined by the current user)
    $value = preg_replace('/[^0-9\-\\'.$user->decimal_separator.']/', '', $value);

    // replace the user decimal character for the internal PHP symbol: a dot .
    $value = str_replace($user->decimal_separator, ".", $value);

    return $value;
}

/**
 * Converts an internal decimal value to its string representation
 *  the format depends on the current signed in user and his preferences (decimal_separator, thousands_separator)
 *  NOTE: this only formats the number to be used in the Navigate CMS interface, not in the website!
 *
 * @param decimal $value
 * @return string number formatted using user's defined preferences
 */
function core_decimal2string($value, $decimals = 2)
{
    global $user;
    global $website;

    // if the decimal part is 0, remove it for cleaner presentation
    $value = sprintf("%F", $value); // was %G

    if( $value - intval($value) === 0 ||
        $value - intval($value) === 0.0
    )
    {
        $decimals = 0;
    }

    if(!empty($user) && isset($user->decimal_separator))
        $value = number_format($value, $decimals, $user->decimal_separator, $user->thousands_separator);
    else  // no user defined, use the website defaults
        $value = number_format($value, $decimals, $website->decimal_separator, $website->thousands_separator);

    return $value;
}

/**
 * Sends an e-mail using the account details entered in the website settings form
 * Note: if this function is called when the url has the parameter "debug", a log of the process is dumped
 *
 * @param string $subject
 * @param string $body
 * @param mixed $recipients An e-mail address string or an array of recipients [name => address, name => ...]
 * @param array $attachments Files or data to be attached. [0][file => "/path/to/file", name => "name_of_the_file_in_the_email"]... [1][data => "binary data", name => "name_of_the_file_in_the_email"]
 * @param boolean $quiet  hide any possible exception message
 * @return boolean True if the mail has been sent, false otherwise
 *
 */
function navigate_send_email($subject, $body, $recipients=array(), $attachments=array(), $quiet=false)
{
	global $website;

    $mail = new PHPMailer(true); // the true param means it will throw exceptions on errors, which we need to catch
    $mail->CharSet = 'UTF-8';

    if($website->mail_mailer=='sendmail')
        $mail->IsSendmail(); // telling the class to use Sendmail
    else if($website->mail_mailer=='mail')
        $mail->IsMail(); // telling the class to use PHP Mail
    else
        $mail->IsSMTP(); // telling the class to use SMTP

    try
    {
        $mail->Host       = $website->mail_server;
        $mail->SMTPAuth   = true;
        $mail->Port       = $website->mail_port;

        if($website->mail_security=='1')    // SSL/TLS
            $mail->SMTPSecure = "ssl";
        if($website->mail_security=='2')    // STARTTLS
        {
            $mail->SMTPSecure = "tls";
        }

        if($website->mail_ignore_ssl_security)
        {
            // some servers have incorrect security settings, missing certificates, etc.
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
        }

        $mail->Username   = $website->mail_user;
        $mail->Password   = $website->mail_password;

        //if(APP_DEBUG)
        //    $mail->SMTPDebug = 1;

        if(empty($recipients))
        {
            // if no recipients given, assign the website contacts
            $recipients = $website->contact_emails;
        }

        if(!is_array($recipients))	// single recipient or several emails (multiline)
        {
            if(strpos($recipients, "\n")!=false)
                $recipients = explode("\n", $recipients);
            else
                $recipients = array($recipients);
        }

        $from_email_address = $website->mail_address;
        if(empty($from_email_address))
            $from_email_address = 'no-reply@website.com';

        $mail->SetFrom($from_email_address, $website->name);

        $mail->IsHTML(true);

        $mail->Subject = $subject;
        $mail->MsgHTML($body);
        $mail->AltBody = strip_tags($body);

        if(is_array($attachments))
        {
            for($i=0; $i< count($attachments); $i++)
            {
                if(!empty($attachments[$i]['file']))
                {
                    $mail->AddAttachment($attachments[$i]['file'], $attachments[$i]['name']);
                }
                else
                {
                    $mail->AddStringAttachment($attachments[$i]['data'], $attachments[$i]['name']);
                }
            }
        }

        $already_sent = array();

        foreach($recipients as $name => $email)
        {
            // avoid sending someone the same email two times
            if(in_array($email, $already_sent))
                continue;

            if(empty($email) && !empty($name))
                $email = $name;

            $mail->ClearAddresses();
            $mail->AddAddress($email, $name);

            $mail->Send();

            array_push($already_sent, $email);
        }

        $ok = true; // no exceptions => mail sent
    }
    catch (phpmailerException $e)
    {
        if(!$quiet)
            echo $e->errorMessage(); //Pretty error messages from PHPMailer
        $ok = false;
    }
    catch (Exception $e)
    {
        if(!$quiet)
            echo $e->getMessage(); //Boring error messages from anything else!
        $ok = false;
    }

	return $ok;
}


/**
 * Checks if a string is not empty after a trim
 *
 * @param string $text
 * @return boolean
 */
function is_not_empty($text) 
{ 
	$text = trim($text);
	return !empty($text); 
}

/**
 * Cleans a string of: tags, new lines and duplicated spaces
 *
 * @param string $text
 * @return string
 */
function core_string_clean($text="")
{
	$text = strip_tags($text);
	$text = str_replace("\n", " ", $text);
	$text = str_replace("\r", " ", $text);	
	$text = preg_replace('/(\s+)/', " ", $text);
	return $text;	
}

/**
 * Cleans a string of any short nv tags: <nv /> or {{nv}}
 * TODO: add option to also remove nvlist, nvconditional and other similar tags
 *
 * @param string $text
 * @return string
 */
function core_remove_nvtags($text)
{
    $text = preg_replace("/<nv[^>]+\>/i", "", $text);
    $text = preg_replace("/{{nv[^>]+}}/i", "", $text);
    return $text;
}

/**
 * Cuts a text string to a certain length; any HTML tag is removed and text is cutted without breaking words
 *
 * @param string $text
 * @param string $maxlen Maximum character length
 * @param string $morechar Append string if the original text is cutted somewhere
 * @param array $allowedtags List of tags which have to be kept (for example 'a')
 * @return string
 */
function core_string_cut($text, $maxlen, $morechar='&hellip;', $allowedtags=array())
{
    if(!empty($allowedtags))
    {
	    if(!is_array($allowedtags))
		    $allowedtags = array($allowedtags);

        $text = strip_tags($text, '<'.implode('><', $allowedtags).'>');
        $text = core_truncate_html($text, $maxlen, $morechar);
    }
    else
    {
        // truncate by plain text
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $olen = strlen($text);

        if($olen < $maxlen) return $text;

        $pos = strrpos( substr( $text , 0 , $maxlen), ' ') ;
        $text = substr( $text , 0 , $pos );
        if($olen > $maxlen) $text.= $morechar;
    }
	return $text;
}

/**
 *  function to truncate and then clean up end of the HTML,
 *  truncates by counting characters outside of HTML tags
 *
 *  @author Alex Lockwood, alex dot lockwood at websightdesign
 *  @updated by Marc Lobato [try not to truncate words]
 *
 *  @param string $str the string to truncate
 *  @param int $len the number of characters
 *  @param string $end the end string for truncation
 *  @return string $truncated_html
 *
 *  **/
function core_truncate_html($str, $len, $end = '&hellip;')
{
    $closeTagString = '';
    //find all tags
    $tagPattern = '/(<\/?)([\w]*)(\s*[^>]*)>?|&[\w#]+;/i';  //match html tags and entities
    preg_match_all($tagPattern, $str, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER );

    $i =0;
    //loop through each found tag that is within the $len, add those characters to the len,
    //also track open and closed tags
    // $matches[$i][0] = the whole tag string  --the only applicable field for html enitities
    // IF its not matching an &htmlentity; the following apply
    // $matches[$i][1] = the start of the tag either '<' or '</'
    // $matches[$i][2] = the tag name
    // $matches[$i][3] = the end of the tag
    //$matces[$i][$j][0] = the string
    //$matces[$i][$j][1] = the str offest

    while($matches[$i][0][1] < $len && !empty($matches[$i]))
    {
        $len = $len + strlen($matches[$i][0][0]);
        if(substr($matches[$i][0][0],0,1) == '&' )
            $len = $len-1;

        //if $matches[$i][2] is undefined then its an html entity, want to ignore those for tag counting
        //ignore empty/singleton tags for tag counting
        if(!empty($matches[$i][2][0]) && !in_array($matches[$i][2][0],array('br','img','hr', 'input', 'param', 'link')))
        {
            //double check
            if(substr($matches[$i][3][0],-1) !='/' && substr($matches[$i][1][0],-1) !='/')
                $openTags[] = $matches[$i][2][0];
            elseif(end($openTags) == $matches[$i][2][0])
                array_pop($openTags);
            else
                $warnings[] = "html has some tags mismatched in it:  $str";
        }
        $i++;
    }

    $closeTags = '';

    if (!empty($openTags))
    {
        $openTags = array_reverse($openTags);
        foreach ($openTags as $t)
        {
            $closeTagString .="</".$t . ">";
        }
    }

    if(strlen($str) > $len)
    {
        // look for the first space character after the required length
        // then, truncate with new len
        $slen = core_strpos_array($str, array(' ', ',', '.', ';', "\n"), $len);
        if(!$slen)
            $truncated_html = substr($str, 0, $len);
        else
            $truncated_html = substr($str, 0, $slen);

        //add the end text
        $truncated_html .= $end ;
        //restore any open tags
        $truncated_html .= $closeTagString;
    }
    else
        $truncated_html = $str;

    return $truncated_html;
}


function core_price2string($price, $base_currency, $part=NULL)
{
    switch($part)
    {
        case 'value':
            $out = core_decimal2string($price);
            break;

        case 'internal':
            $out = $price;
            break;

        case 'currency':
            $out = product::currencies($base_currency);
            break;

        default:
            $currency = product::currencies($base_currency, false);
            if($currency['placement'] == 'after')
                $out = core_decimal2string($price, $currency['decimals']).' '.$currency['symbol'];
            else
                $out = $currency['symbol'].' '.core_decimal2string($price, $currency['decimals']);
    }

    return $out;
}


function core_strpos_array($haystack, $needles, $offset)
{
    if ( is_array($needles) )
    {
        foreach ($needles as $str)
        {
            if ( is_array($str) )
            {
                $pos = core_strpos_array($haystack, $str, $offset);
            }
            else
            {
                $pos = strpos($haystack, $str, $offset);
            }

            if ($pos !== FALSE)
                return $pos;
        }
    }
    else
    {
        return strpos($haystack, $needles, $offset);
    }
}

/**
 * Translate a number of bytes to a human readable format (from Bytes to PetaBytes)
 *
 * @param integer $bytes
 * @return string
 */
function core_bytes($bytes) 
{
    $unim = array("Bytes", "KB", "MB", "GB", "TB", "PB");
    $c = 0;
    while ($bytes >= 1024) 
    {
        $c++;
        $bytes = $bytes / 1024;
    }
    return number_format($bytes, ($c ? 2 : 0),",",".")." ".$unim[$c];
}


/**
 * Executes a simple GET HTTP request using CURL if available, file_get_contents otherwise
 *
 * @param string $url
 * @param integer $timeout
 * @return string Body of the response
 */
function core_http_request($url, $timeout=8) 
{	
	if(function_exists('curl_init'))
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); 
		$data = curl_exec($ch);
		curl_close($ch);
	}
	else
	{
		$data = file_get_contents($url);	
	}
	
	return $data;
}

/**
 * Execute a CURL HTTP POST request with parameters
 * Author: Mahesh Chari
 * Website: http://www.maheshchari.com/simple-curl-function-to-send-data-remote-server/
 *
 * @param string $url
 * @param mixed $postdata Array of parameter => value or POST string
 * @param string $header HTTP header
 * @param mixed $timeout Number of seconds to wait for a HTTP response
 * @return string Body of the response
 */
function core_curl_post($url, $postdata = NULL, $header = NULL, $timeout = 60, $method="post")
{
	$s = curl_init();
	// initialize curl handler 
	
	curl_setopt($s, CURLOPT_URL, $url);
	//set option URL of the location 
	if ($header) 
		curl_setopt($s, CURLOPT_HTTPHEADER, $header);
		
	//set headers if presents
	curl_setopt($s, CURLOPT_TIMEOUT, $timeout);
	//time out of the curl handler  		
	curl_setopt($s, CURLOPT_CONNECTTIMEOUT, $timeout);
	//time out of the curl socket connection closing 
	curl_setopt($s, CURLOPT_MAXREDIRS, 3);
	//set maximum URL redirections to 3 
	curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
	// set option curl to return as string, don't output directly
    // on some configurations: CURLOPT_FOLLOWLOCATION cannot be activated when safe_mode is enabled or an open_basedir is set
	@curl_setopt($s, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($s, CURLOPT_COOKIEJAR, 'cache/cookie.curl.txt');
	curl_setopt($s, CURLOPT_COOKIEFILE, 'cache/cookie.curl.txt');
	//set a cookie text file, make sure it is writable chmod 777 permission to cookie.txt
	
	if(strtolower($method) == 'post')
	{
		curl_setopt($s,CURLOPT_POST, true);
		//set curl option to post method
		curl_setopt($s,CURLOPT_POSTFIELDS, $postdata);	// can be a string or an associative array
		//if post data present send them.
	}
	else if(strtolower($method) == 'delete')
	{
		curl_setopt($s,CURLOPT_CUSTOMREQUEST, 'DELETE');
		//file transfer time delete
	}
	else if(strtolower($method) == 'put')
	{
		curl_setopt($s,CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($s,CURLOPT_POSTFIELDS, $postdata);
		//file transfer to post ,put method and set data
	}
	
	curl_setopt($s,CURLOPT_HEADER, 0);			 
	// curl send header 
	curl_setopt($s,CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1');
	//proxy as Mozilla browser 
	curl_setopt($s, CURLOPT_SSL_VERIFYPEER, false);
	// don't need to SSL verify ,if present it need openSSL PHP extension
	
	$html = curl_exec($s);
	//run handler
	
	$status = curl_getinfo($s, CURLINFO_HTTP_CODE);
	// get the response status
	
	curl_close($s);
	//close handler

    @unlink('cache/cookie.curl.txt');
	
	return $html;
	//return output	
}

/**
 * Retrieves the size of a remote file doing a CURL request
 *
 * @param string $file URL of the file
 * @return integer|boolean Size of the file in bytes or false
 */
function core_filesize_curl($file)
{
    $ch = curl_init($file);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    // on some configurations: CURLOPT_FOLLOWLOCATION cannot be activated when safe_mode is enabled or an open_basedir is set
    @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    curl_close($ch);

    if ($data === false)
      return false;

    if (preg_match('/Content-Length: (\d+)/', $data, $matches))
      return (float)$matches[1];
}

/**
 * Retrieves a remote file doing a CURL request
 *
 * @param string $url URL of the file
 * @param string $file Absolute system path where the file will be saved
 * @return string $contents File contents
 */
function core_file_curl($url, $file)
{
    // prepare URL
    $userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
	curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    // on some configurations: CURLOPT_FOLLOWLOCATION cannot be activated when safe_mode is enabled or an open_basedir is set
    @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cache/cookie.curl.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cache/cookie.curl.txt');

    $header = curl_exec($ch);
    curl_close($ch);

    $header = str_replace(array("\n", "\r"), ' ', $header);
    $redirect = strpos($header, 'Location:');
    if($redirect!==false)
        $url = substr($header, $redirect + strlen('Location:') + 1, strpos($header, " ", $redirect)+1);

    $ch = curl_init($url);
    $fp = fopen($file, 'w');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    clearstatcache();
    if(filesize($file) == 0)
    {
        // cURL method didn't work
        // try with file_get_contents
        $data = file_get_contents($url);
        file_put_contents($file, $data);
        unset($data);
    }

    clearstatcache();
}

/**
 * Removes a folder on the disk and its subfolders
 *
 * @param string $dir Path of the folder to remove
 */
function core_remove_folder($dir) 
{
   if(is_dir($dir)) 
   {
     $objects = scandir($dir);
     foreach ($objects as $object) 
	 {
       if($object != "." && $object != "..") 
	   {
         if(filetype($dir."/".$object) == "dir") 
         	core_remove_folder($dir."/".$object); 
		 else 
		 	unlink($dir."/".$object);
       }
     }
     reset($objects);
     rmdir($dir);
   }
} 

/**
 * Changes the UNIX permissions of a file or folder and its contents
 *
 * @param string $path Absolute path to the file or folder to change
 * @param string $filemode UNIX permissions code, p.e. 0755
 * @return boolean TRUE if no problem found, FALSE otherwise. Note: on Windows systems this function is always FALSE.
 */
function core_chmodr($path, $filemode) 
{
    if (!is_dir($path))
        return chmod($path, $filemode);

    $dh = opendir($path);

    while (($file = readdir($dh)) !== false) 
    {
        if($file != '.' && $file != '..') 
        {
            $fullpath = $path.'/'.$file;
            if(is_link($fullpath))
                return FALSE;
            else if(!is_dir($fullpath) && !chmod($fullpath, $filemode))
                return FALSE;
            else if(!core_chmodr($fullpath, $filemode))
                return FALSE;
        }
    }

    closedir($dh);

    if(chmod($path, $filemode))
        return TRUE;
    else
        return FALSE;
}

/**
 * Decodes a JSON string removing new lines, tabs, spaces...
 *
 * @param string $json
 * @return object
 */
function core_javascript_json($json)
{
	$json = trim(substr($json, strpos($json, '{'), -1));
	$json = str_replace("\n", "", $json);
	$json = str_replace("\r", "", $json);
	$json = str_replace("\t", "", $json);			
	$json = str_replace('"+"', "", $json);
	$json = str_replace('" +"', "", $json);			
	$json = str_replace('"+ "', "", $json);			
	$json = str_replace('" + "', "", $json);			
	
	return json_decode($json);	
}

/**
 * Completely removes the current session, even the created cookie
 *
 */
function core_session_remove()
{
	if(ini_get("session.use_cookies"))
	{
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params["path"], $params["domain"],
			$params["secure"], $params["httponly"]
		);
	}
	@session_destroy();
}

/**
 * Shows the last PHP JSON decoding error.
 * This function needs APP_DEBUG enabled or the url parameter "debug".
 * The error is sent to the Firebug FirePHP plugin within Mozilla Firefox.
 *
 * @param string $prepend String to prepend before the error (if exists)
 */
function debug_json_error($prepend='')
{
    $error = '';
    if(!empty($prepend))
        $prepend .= ' - ';

    if(function_exists('json_last_error'))
    {
        switch (json_last_error())
        {
            case JSON_ERROR_NONE:
                $error = '';
                break;

            case JSON_ERROR_DEPTH:
                $error = 'Maximum stack depth exceeded';
                break;

            case JSON_ERROR_STATE_MISMATCH:
                $error = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $error = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $error = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $error = 'Unknown error';
                break;
        }
    }

    if(!empty($error) && (APP_DEBUG || isset($_REQUEST['debug'])))
        debugger::console($prepend.$error);
}

/*
  EXAMPLE
    $body = navigate_compose_email(
        array(
            array(
                'title'   => "Website",
                'content' => '<a href="' . $website->absolute_path() . $website->homepage() . '">' . $website->name . '</a>'
            ),
            array(
                'title'   => "Content",
                'content' => $content
            ),
            array(
                'footer' => '<a href="' . $unsubscribe_link . '">Unsubscribe text</a>'
            )
        )
    );
*/

function navigate_compose_email($data, $style=array())
{
    global $DB;
    global $website;

    $body = array();

    if(empty($style))
    {
        // default colors
        $background_color = '#E5F1FF';
        $title_color = '#595959';
        $text_color = '#595959';

        $background_color_db = $DB->query_single('value', 'nv_permissions', 'name = "nvweb.comments.background_color" AND website = ' . intval($website->id), 'id DESC');
        $text_color_db = $DB->query_single('value', 'nv_permissions', 'name = "nvweb.comments.text_color" AND website = ' . intval($website->id), 'id DESC');
        $title_color_db = $DB->query_single('value', 'nv_permissions', 'name = "nvweb.comments.titles_color" AND website = ' . intval($website->id), 'id DESC');

        if (!empty($background_color_db))
            $background_color = str_replace('"', '', $background_color_db);

        if (!empty($text_color_db))
            $text_color = str_replace('"', '', $text_color_db);

        if (!empty($title_color_db))
            $title_color = str_replace('"', '', $title_color_db);

        $style = array(
            'background' => $background_color,
            'title-color' => $title_color,
            'content-color' => $text_color
        );
    }

    $body[] = '<div style=" background: '.$style['background'].'; width: 86%; max-width: 600px; border-radius: 6px; margin: 10px auto; padding: 1px 20px 20px 20px;">';

    foreach($data as $section)
    {
        if(!empty($section['title']))
        {
            $body[] = '<div style="margin: 25px 0px 10px 0px;">';
            $body[] = '    <div style="color: '.$style['title-color'].'; font-size: 17px; font-weight: bold; font-family: Verdana;">'.$section['title'].'</div>';
            $body[] = '</div>';
        }

        if(!empty($section['content']))
        {
            $body[] = '<div style=" background: #fff; border-radius: 6px; padding: 10px; margin-top: 5px; line-height: 25px; text-align: justify; ">';
            $body[] = '    <div class="text" style="color: '.$style['content-color'].'; font-size: 16px; font-style: italic; font-family: Verdana;">'.$section['content'].'</div>';
            $body[] = '</div>';
        }

        if(!empty($section['footer']))
        {
            $body[] = '<br /><br />';
            $body[] = '<div style="color: '.$style['title-color'].';">'.$section['footer'].'</div>';
        }
    }

    $body[] = '</div>';

    $body = implode("\n", $body);

    return $body;
}

// get current active language code or the default one
function core_get_language($default=null)
{
    global $user;
    global $webuser;

    $lang = $default;

    // static function can be called from navigate or from a webget (user then is not a navigate user)
    if(empty($lang) && !empty($webuser->id))
        $lang = $webuser->language;

    if(empty($lang) && !empty($user->id))
        $lang = $user->language;

    // default to english
    if(empty($lang))
        $lang = 'en';

    return $lang;
}

function core_version()
{
	global $config;

	if(!isset($config['version']))
	{
		$data = update::latest_installed();
		$config['version'] = $data->version;
		$config['revision'] = $data->revision;
	}

	return $config['version'];
}

?>