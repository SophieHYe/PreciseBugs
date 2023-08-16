<?php
function nvweb_self_url()
{ 
	if(!isset($_SERVER['REQUEST_URI']))
		$serverrequri = $_SERVER['PHP_SELF']; 
	else
		$serverrequri = $_SERVER['REQUEST_URI'];

	$s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : ""; 
	$s1 = strtolower($_SERVER["SERVER_PROTOCOL"]);
	
	$protocol = substr($s1, 0, strpos($s1, "/")).$s; 

	$port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]); 

    // some shared hostings need $_SERVER['HTTP_HOST']
    // but $_SERVER['SERVER_NAME'] should work if the server is configured properly
	$url = $protocol."://".$_SERVER['HTTP_HOST'].$port.$serverrequri;
	
	// decode %chars
	$url = urldecode($url);

    // remove last '?' in url if exists
    if(substr($url, -1)=='?')
        $url = substr($url, 0, -1);

	// remove last '/' in url if exists
    if(substr($url, -1)=='/')
        $url = substr($url, 0, -1);

	return $url;
}

function nvweb_load_website_by_url($url, $exit=true)
{
	global $DB;
    global $idn;

	$website = new website();
	
	$parsed = parse_url($url);
    $scheme = $parsed['scheme']; // http, https...
	$host = $parsed['host']; // subdomain.domain.tld
	$path = $parsed['path']; // [/folder]page

    if(filter_var($host, FILTER_VALIDATE_IP) !== false)
    {
        // is an ip, do nothing
    }
    else
    {
        // is not an ip
        $host = $idn->decode($host);
    }

    // look for website aliases
    $DB->query('SELECT aliases FROM nv_websites', 'array');
    $ars = $DB->result('aliases');

    $aliases = array();
    foreach($ars as $ajson)
    {
        if(!is_array($aliases))
            $aliases = array();

        $ajson = json_decode($ajson, true);
        if(!is_array($ajson))
            continue;

        $aliases = array_merge($aliases, $ajson);
    }

    if(!is_array($aliases))
        $aliases = array();


    foreach($aliases as $alias => $real)
    {
        $alias_parsed = parse_url($alias);

        if( $alias_parsed['host'] == $host )
        {
			if(!isset($alias_parsed['path']))
				$alias_parsed['path'] = "";

            $rud_path = rawurldecode($alias_parsed['path']);

	        // check the path section
			if(	($path == $alias_parsed['path']) ||
				($path == '/nvweb.home' && empty($alias_parsed['path'])) ||
				(!empty($path) && !empty($rud_path) && strpos($path, $rud_path, 0) !== false)
			)
			{
				// alias path is included in the requested path
				// identify the extra part
				// EXAMPLE
				//
				//    ALIAS           http://themes.navigatecms.com
				//    REQUEST         http://themes.navigatecms.com/en/introduction
				//        EXTRA           /en/introduction
				//
				//    REAL PATH       http://www.navigatecms.com/en/documentation/themes
				//    REAL + EXTRA    http://www.navigatecms.com/en/documentation/themes/introduction
				//
				// note that the language part "en" is placed in different order
				// so our approach is to IGNORE the path sections already existing in the real path

				$extra = substr($path, strlen($alias_parsed['path']));

				$real_parsed = parse_url($real);
				$real_path = explode('/', $real_parsed['path']);
				$extra_path = explode('/', $extra);

				if (!is_array($extra_path))
					$extra_path = array();

				$add_to_real = '';
				foreach ($extra_path as $part) {
					if ($part == 'nvweb.home')
						continue;

					if (in_array($part, $real_path))
						continue;

					$add_to_real .= '/' . $part;
				}

				// TO DO: maybe in a later version full ALIAS support could be implemented
				//        right now we only redirect to the real path
				$url = $real . $add_to_real;

				header('location: ' . $idn->encodeUri($url));
				nvweb_clean_exit();
			}
        }
    }

	// the host is an IP address or a full domain?
	$isIP = filter_var($host, FILTER_VALIDATE_IP);
	if($isIP)
	{
		$domain = $host;
		$subdomain = "";
	}
	else
	{
		// do we have a subdomain in the url?
		preg_match('/(?:http[s]*\:\/\/)*(.*?)\.(?=[^\/]*\..{2,5})/i', $url, $parts);
		$subdomain = $parts[1];
		$domain = $host;

		if(empty($subdomain)) // may be NULL
			$subdomain = "";
		else
			$domain = substr($host, strlen($subdomain)+1);
	}

    $DB->query('
		SELECT id, folder
		  FROM nv_websites
		 WHERE subdomain = '.protect($subdomain).'
		   AND ( domain = '.protect($domain).' OR domain = '.protect($idn->encode($domain)).' ) 
		 ORDER BY folder DESC
	 ');
	$websites = $DB->result();

	if(empty($websites))
	{
        // no 'real' website found using this address
		if($subdomain == 'nv')
		{
            /*
			$website->load(); // first available, it doesn't matter
			$nvweb_absolute = (empty($website->protocol)? 'http://' : $website->protocol);
			if(!empty($website->subdomain))
				$nvweb_absolute .= $website->subdomain.'.';
			$nvweb_absolute .= $website->domain.$website->folder;
            */
			$nvweb_absolute = NAVIGATE_PARENT.NAVIGATE_FOLDER;
			header('location: '.$nvweb_absolute);
			nvweb_clean_exit();
		}
		else
		{		
			header("HTTP/1.1 404 Not Found");
			if($exit)
			{
				nvweb_clean_exit();
			}
			else
			{
				return false;
			}
		}
	}

    // choose which website based on folder name
	foreach($websites as $web)
	{
        // there can only be one subdomain.domain.tld without folder
		if(empty($web->folder))
		{
			$website->load($web->id);
            break;
		}
        else
        {
            $path_segments = explode('/', $path);
            $folder_segments = explode('/', $web->folder);

            $folder_coincidence = true;
            for($fs=0; $fs < count($folder_segments); $fs++)
                $folder_coincidence = $folder_coincidence && ($folder_segments[$fs]==$path_segments[$fs]);

            if($folder_coincidence)
            {
                $website->load($web->id);
                break;
            }
        }
	}

	// website could not be identified, just load the first available
	if(empty($website->id))
		$website->load();

	return $website;
}

function nvweb_prepare_link($path="")
{
	$path = trim($path);

    if(	substr(strtolower($path), 0, 7)=='http://' ||
		substr(strtolower($path), 0, 8)=='https://' ||
		substr(strtolower($path), 0, 5)=='nv://' ||
		substr(strtolower($path), 0, 1)=='#'
	)
    {
        $url = $path;
    }
    else if(substr(strtolower($path), 0, 4)=='www.')
    {
        $url = 'http://' . $path;
    }
    else
    {
        $url = NVWEB_ABSOLUTE . $path;
    }

    return $url;
}

function nvweb_route_parse($route="")
{
	global $website;
	global $DB;
	global $current;
	global $session;
    global $theme;
	global $events;
	global $dictionary;

	// node route types
	if(substr($route, 0, 5)=='node/')
	{
		$node  =  substr($route, 5);
		$route = 'node';		
	}

	// product route types
	if(substr($route, 0, 8)=='product/')
	{
		$product  =  substr($route, 8);
		$route = 'product';
	}

	switch($route)
	{			
		case 'object':
			nvweb_object();
			nvweb_clean_exit();
			break;

        case 'nvajax':
            nvweb_ajax();
            nvweb_clean_exit();
            break;

        case 'nvtags':
        case 'nvsearch':
            $current['template'] = 'search';
            break;

		case 'nv.webuser/verify':
			$hash = $_REQUEST['hash'];
			$email = filter_var($_REQUEST['email'], FILTER_VALIDATE_EMAIL);
			if(!empty($hash) && !empty($email))
			{
				$ok = webuser::email_verification($email, $hash);
				if($ok)
					$session['nv.webuser/verify:email_confirmed'] = time();
                else
                    $session['nv.webuser/verify:invalid_confirmation'] = time();
			}
			nvweb_clean_exit(NVWEB_ABSOLUTE.$website->homepage().'?_s='.time());
			break;

        case 'nv.webuser/confirm':
			$hash = $_REQUEST['hash'];
			$email = filter_var($_REQUEST['email'], FILTER_VALIDATE_EMAIL);
			$redirect = NVWEB_ABSOLUTE.$website->homepage().'?_s='.time();
			if(!empty($hash) && !empty($email))
			{
				$wu = webuser::account_verification($email, $hash);
				if(!empty($wu))
                {
                    $webuser = new webuser();
                    $webuser->load($wu);

                    $session['nv.webuser/verify:email_confirmed'] = time();

                    // autologin after callback
                    $webuser->set_cookie();

                    if(!empty($_REQUEST['callback']))
                        $redirect = base64_decode($_REQUEST['callback']);
                }
                else
                    $session['nv.webuser/verify:invalid_confirmation'] = time();
			}

			nvweb_clean_exit($redirect);
			break;

        case 'nv.comments/unsubscribe':
			$cid = $_REQUEST['cid'];
			$hash = $_REQUEST['hash'];
			if(!empty($hash) && !empty($cid))
			{
				$ok = comment::notifications_unsubscribe($cid, $hash);
				if($ok)
					$session['nv.comments/unsubscribe'] = time();
			}
			nvweb_clean_exit(NVWEB_ABSOLUTE.$website->homepage());
			break;
			
		case 'node':
			if($node > 0)
			{
				$current['id'] = $node;
				
				$DB->query('SELECT * FROM nv_items 
							 WHERE id = '.protect($current['id']).'
							   AND website = '.$website->id);
				$current['object'] = $DB->first();
				
				// let's count a hit (except admin)
				if($current['navigate_session']!=1 && !nvweb_is_bot())
				{
					$DB->execute(' UPDATE nv_items SET views = views + 1 
								   WHERE id = '.$current['id'].' 
									 AND website = '.$website->id);
				}

				$current['type'] = 'item';
				$current['template'] = $current['object']->template;

				if($current['navigate_session']==1 && !empty($_REQUEST['template']))
					$current['template'] = $_REQUEST['template'];
			}
			break;

        case 'product':
			if($product > 0)
			{
				$current['id'] = $product;

				$DB->query('SELECT * FROM nv_products 
							 WHERE id = '.protect($current['id']).'
							   AND website = '.$website->id);
				$current['object'] = $DB->first();

				// let's count a hit (except admin)
				if($current['navigate_session']!=1 && !nvweb_is_bot())
				{
					$DB->execute(' UPDATE nv_products SET views = views + 1 
								   WHERE id = '.$current['id'].' 
									 AND website = '.$website->id);
				}

				$current['type'] = 'product';
				$current['template'] = $current['object']->template;

				if($current['navigate_session']==1 && !empty($_REQUEST['template']))
					$current['template'] = $_REQUEST['template'];
			}
			break;

        case 'sitemap.xml':
            nvweb_webget_load('sitemap');
            echo nvweb_sitemap(array('mode' => 'xml'));
            nvweb_clean_exit();
            break;
			
		// redirect to home page of the current website
		case 'nvweb.home':
		case 'nv.home':
			header('location: '.NVWEB_ABSOLUTE.$website->homepage());
			nvweb_clean_exit();
			break;

		// webservice endpoint via XML-RPC calls
		case 'xmlrpc':
			$events->trigger(
				'nvweb',
				'xmlrpc',
				array(
					'route' => '/'.$route
				)
			);
			// if no extension processes the call, use the integrated XML-RPC parser
			nvweb_xmlrpc();
			nvweb_clean_exit();
			break;

        // empty path
        case '':
		case '/':
		case 'nv.empty':
			if($website->empty_path_action == 'homepage_noredirect')
			{
				$route = $website->homepage();
				if(strpos($route, '/')===0)
					$route = substr($route, 1);
			}
			else // other empty path cases simply are processed like a wrong path action
			{
				$route = "";
				$website->wrong_path_action = $website->empty_path_action;
			}
			// do NOT break this case, continue processing as wrong_path action

        // no special route (or already processed), look for the path on navigate routing table
		default:
			$DB->query('
                SELECT * FROM nv_paths 
				WHERE path = '.protect('/'.$route).' AND 
				      website = '.$website->id.'
				ORDER BY id DESC'
            );
			$rs = $DB->result();

			if(empty($rs))
            {
                // route not found in navigate cms core
                // check if any extension knows of this route
                $ext_results = $events->trigger(
                    'nvweb',
                    'find_route',
                    array(
                        'route' => $route
                    )
                );

                foreach($ext_results as $ext_result)
                {
                    if(!empty($ext_result) && is_array($ext_result))
                    {
                        // get the first valid result and return
                        // must simulate a row from nv_paths, with id NULL or 0
                        // example:
                        // $rs = array((object)array('id' => 0,'website' => 1,'path' => '/en/contact','type' => 'structure','object_id' => 5,'lang' => 'en'));
                        $rs = $ext_result;
                        break;
                    }
                }
            }

    		if(empty($rs))
			{
                // no one knows about this route,
                // so we have to take it as a wrong path;
                // apply the default setting
                switch($website->wrong_path_action)
                {
                    case 'homepage':
	                case 'homepage_redirect':
                        header('location: '.NVWEB_ABSOLUTE.$website->homepage());
                        nvweb_clean_exit();
                        break;

                    case 'http_404':
                        header("HTTP/1.0 404 Not Found");
                        nvweb_clean_exit();
                        break;

                    case 'theme_404':
                        $current['template'] = 'not_found';
                        $current['type']	 = 'structure';
                        $current['id'] 		 = 0;
                        $current['object']   = new structure();
                        return;
                        break;

                    case 'website_path':
                        $redirect_url = nvweb_template_convert_nv_paths($website->wrong_path_redirect);
                        header('location: '.$redirect_url);
                        nvweb_clean_exit();
                        break;

                    case 'blank':
                    default:
                        nvweb_clean_exit();
                        break;
                }
			}
			else
			{
				// route found!
				// let's count a hit (except admin)
				if($current['navigate_session']!=1 && !nvweb_is_bot() && !empty($rs[0]->id))
				{
					$DB->execute(' 
                        UPDATE nv_paths SET views = views + 1 
						WHERE id = '.$rs[0]->id.' AND 
						      website = '.$website->id
                    );
				}
				
				// set the default language for this route
				if(!isset($_REQUEST['lang']))
				{
					$current['lang'] 	 = $rs[0]->lang;
					$session['lang']	 = $rs[0]->lang;

					// force reloading the dictionary
					$dictionary = nvweb_dictionary_load();
				}
					
				$current['type']	 = $rs[0]->type;
				$current['id'] 		 = $rs[0]->object_id;
				
				// look for the template associated with this item
				switch($current['type'])
				{
                    case 'structure':
                        $obj = new structure();
                        $obj->load($current['id']);

                        // check if it is a direct access to a "jump to another branch" path
                        if($obj->dictionary[$current['lang']]['action-type']=='jump-branch')
                        {
                            $current['id'] = $obj->dictionary[$current['lang']]['action-jump-branch'];
                            $obj = new structure();
                            $obj->load($current['id']);
                            header('location: '.NVWEB_ABSOLUTE.$obj->paths[$current['lang']]);
                            nvweb_clean_exit();
                        }
                        else if($obj->dictionary[$current['lang']]['action-type']=='jump-item')
                        {
                            $current['id'] = $obj->dictionary[$current['lang']]['action-jump-item'];
                            $obj = new item();
                            $obj->load($current['id']);
                            header('location: '.NVWEB_ABSOLUTE.$obj->paths[$current['lang']]);
                            nvweb_clean_exit();
                        }
                        else if($obj->dictionary[$current['lang']]['action-type']=='masked-redirect')
                        {
                            $masked_path = $obj->dictionary[$current['lang']]['action-masked-redirect'];
                            if(strpos($masked_path, "/")===0)
                                $masked_path = substr($masked_path, 1);

                            // decompose masked_path in route / url parameters
                            $masked_path_parsed = parse_url($masked_path);

                            $request = $_REQUEST;
                            $request['route'] = $masked_path_parsed['path'];
                            $request['wid'] = $website->id;

                            $masked_path_params = array();
                            if(!empty($masked_path_parsed['query']))
                            {
                                parse_str($masked_path_parsed['query'], $masked_path_params);
                                foreach($masked_path_params as $key => $val)
                                {
                                    if($key == 'route')
                                        continue;
                                    $request[$key] = $val;
                                }
                            }

                            $_REQUEST = $request;

                            nvweb_parse($request);
                            nvweb_clean_exit();
                        }

                        $current['object'] = $obj;
                        $current['category'] = $current['id'];

                        if($current['navigate_session']!=1 && !nvweb_is_bot())
                        {
                            $DB->execute(' UPDATE nv_structure SET views = views + 1 
                                            WHERE id = '.protect($current['id']).' 
                                              AND website = '.$website->id);
                        }
                        break;

                    case 'item':
                        $DB->query('SELECT * FROM nv_items 
                                     WHERE id = '.protect($current['id']).'
                                       AND website = '.$website->id);

                        $current['object'] = $DB->first();

                        // let's count a hit (except admin)
                        if($current['navigate_session']!=1 && !nvweb_is_bot())
                        {
                            $DB->execute(' UPDATE nv_items SET views = views + 1 
                                           WHERE id = '.$current['id'].' 
                                             AND website = '.$website->id);
                        }
                        break;

                    case 'product':
                        $DB->query('SELECT * FROM nv_products 
                                     WHERE id = '.protect($current['id']).'
                                       AND website = '.$website->id);

                        $current['object'] = $DB->first();

                        // let's count a hit (except admin)
                        if($current['navigate_session']!=1 && !nvweb_is_bot())
                        {
                            $DB->execute(' UPDATE nv_products SET views = views + 1 
                                           WHERE id = '.$current['id'].' 
                                             AND website = '.$website->id);
                        }
                        break;

                    case 'feed':
                        $out = feed::generate_feed($current['id']);
                        if($current['navigate_session']!=1 && !nvweb_is_bot())
                        {
                            $DB->execute(' UPDATE nv_feeds SET views = views + 1
                                               WHERE id = '.$current['id'].'
                                                 AND website = '.$website->id);
                        }

                        if(strpos($out,'<rss')!==false)
                            header('Content-Type: application/rss+xml');
                        else if(strpos($out,'<atom')!==false)
                            header('Content-Type: application/atom+xml');
                        else if(strpos($out,'<xml')!==false)
                            header('Content-Type: application/xml');

                        echo $out;
                        nvweb_clean_exit();
                        break;

                    default:
                        // path exists, but the object type is unknown
                        // maybe the path belongs to an extension?
                        $events->trigger(
                            'nvweb',
                            'routes',
                            array(
                                'path' => $rs[0]
                            )
                        );
				}

				$current['template'] = $current['object']->template;
			}
			break;			
	}
}


function nvweb_check_permission()
{
	global $current;
    global $webuser;
	
	$permission = true;
	
	switch($current['object']->permission)
	{
		case 2:	// hidden to ANYONE
			$permission = false;
			break;
			
		case 1:	// hidden to ANYBODY except NAVIGATE users
			$permission = (!empty($_SESSION['APP_USER#'.APP_UNIQUE]));
			break;
			
		case 0:	// visible to EVERYBODY if publishing dates allow it
		default:
			$permission = (empty($current['object']->date_published) || ($current['object']->date_published < core_time()));
			$permission = $permission && (empty($current['object']->date_unpublish) || ($current['object']->date_unpublish > core_time()));		
	}
	
	// check access
	if(isset($current['object']->access))
	{
		$access = true;
		
		switch($current['object']->access)
		{
            case 3: // accessible to SELECTED WEB USER GROUPS only
                $access = false;
                $groups = $current['object']->groups;
                if( !empty($current['webuser']) )
                {
                    $groups = array_intersect($webuser->groups, $groups);
                    if(count($groups) > 0)
                        $access = true;
                }
                break;

            case 2:	// accessible to NOT SIGNED IN visitors
				$access = empty($current['webuser']);
				break;
			
			case 1: // accessible to WEB USERS ONLY
				$access = !empty($current['webuser']);
				break;
			
			case 0:	// accessible to EVERYBODY 
			default:
				$access = true;
		}
		
		$permission = $permission && $access;
	}
		
	return $permission;
}

function nvweb_object_enabled($object)
{
	global $current;
    global $webuser;
	
	$enabled = true;

	switch($object->permission)
	{
		case 2:
			$enabled = false;
			break;
			
		case 1:
			$enabled = (!empty($_SESSION['APP_USER#'.APP_UNIQUE]));
			break;
			
		case 0:
		default:
			$enabled = true;
	}

	// the following check is mainly used for blocks
	if(property_exists($object, 'enabled') && $enabled)
        $enabled = ($object->enabled=='1');

	$enabled = $enabled && (empty($object->date_published) || ($object->date_published < core_time()));
	$enabled = $enabled && (empty($object->date_unpublish) || ($object->date_unpublish > core_time()));

	// check access
	if(isset($object->access))
	{
		$access = true;
		
		switch($object->access)
		{
            case 3: // accessible to SELECTED WEB USER GROUPS only
                $access = false;
                $groups = $object->groups;

                if( !empty($current['webuser']) )
                {
                    $groups = array_intersect($webuser->groups, $groups);
                    if(count($groups) > 0)
                        $access = true;
                }
                break;

            case 2:	// accessible to NOT SIGNED IN visitors ONLY
				$access = empty($current['webuser']);
				break;
			
			case 1: // accessible to WEB USERS ONLY
				$access = !empty($current['webuser']);
				break;
			
			case 0:	// accessible to EVERYBODY 
			default:
				$access = true;
		}
		
		$enabled = $enabled && $access;
	}

	return $enabled;
}

// type: theme, item, structure, (product)
function nvweb_source_url($type, $id, $lang='')
{
	global $DB;
	global $website;
	global $current;
    global $theme;
	
	if(empty($lang)) 
		$lang = $current['lang'];

    if($type=='theme')
    {
        // find the first PUBLIC & PUBLISHED article / element / structure category
        // that is using the template type given in $id
        $template_type = $id;
        $id = '';

        //TODO: a) search products
        if(empty($id))
        {
            // $DB->query_single('id', 'nv_products')
            if(!empty($id))
                $type = 'product';
        }

        // b) search items
        if(empty($id))
        {
            $id = $DB->query_single(
                'id',
                'nv_items',
                'website = '.protect($website->id).'
                 AND template = '.protect($template_type).'
                 AND permission = 0
                 AND access = 0
                 AND (date_published = 0 OR date_published < '.core_time().')
                 AND (date_unpublish = 0 OR date_unpublish > '.core_time().')'
            );
            if(!empty($id))
                $type = 'item';
        }

        // c) search structure elements
        if(empty($id))
        {
            $id = $DB->query_single(
                'id',
                'nv_structure',
                'website = '.protect($website->id).'
                 AND template = '.protect($template_type).'
                 AND permission = 0
                 AND access = 0
                 AND (date_published = 0 OR date_published < '.core_time().')
                 AND (date_unpublish = 0 OR date_unpublish > '.core_time().')'
            );
            if(!empty($id))
                $type = 'structure';
        }

        if(empty($id))
            return "";
    }

    if($type=='element')
        $type = 'item';

    $url = $DB->query_single(
		'path',
		'nv_paths',
		' type = '.protect($type).'
		   AND object_id = '.protect($id).'
		   AND lang = '.protect($lang).'
		   AND website = '.$website->id
	);

	if(empty($url))
	{
		if($type=='item')
			$url = '/node/' . $id;
	}
	
    $url = nvweb_prepare_link($url);

	return $url;										   
}

function nvweb_ajax()
{
    global $website;
    global $theme;

    nvweb_webget_load($theme->name);
    $fname = 'nvweb_'.$theme->name.'_nvajax';

    if(function_exists($fname))
        $content = $fname();
}

// the following function checks if a request comes from a search bot
// author: Pavan Gudhe
// http://www.phpclasses.org/package/7026-PHP-Determine-if-the-current-user-is-a-bot.html
function nvweb_is_bot()
{
    $arrstrBotMatches = array();
    
    $arrstrBots = array (
         'googlebot'        => '/Googlebot/',
         'msnbot'           => '/MSNBot/',
         'slurp'            => '/Inktomi/',
         'yahoo'            => '/Yahoo/',
         'askjeeves'        => '/AskJeeves/',
         'fastcrawler'      => '/FastCrawler/',
         'infoseek'         => '/InfoSeek/',
         'lycos'            => '/Lycos/',
         'yandex'           => '/YandexBot/',
         'geohasher'        => '/GeoHasher/',
         'gigablast'        => '/Gigabot/',
         'baidu'            => '/Baiduspider/',
         'spinn3r'          => '/Spinn3r/'
    );

    //check if bot request
    if( true == isset( $_SERVER['HTTP_USER_AGENT'] ))
    {
        $arrstrBotMatches = preg_filter( $arrstrBots, array_fill( 1, count( $arrstrBots ), '$0' ), array( trim( $_SERVER['HTTP_USER_AGENT'] )));
    }

    //isBot() can be used to check if the request is bot request before incrementing the visit count.
    //check if bot request.
    return ( true == is_array( $arrstrBotMatches ) && 0 < count( $arrstrBotMatches )) ? 1 : 0;
}

function nvweb_clean_exit($url='')
{
	global $session;
	global $DB;
	global $website;

    if(!empty($website->id))
	    $_SESSION['nvweb.'.$website->id] = $session;
	
	session_write_close();
	$DB->disconnect();

    if(!empty($url))
        header('Location: '.$url);

    flush();
	exit;
}

?>