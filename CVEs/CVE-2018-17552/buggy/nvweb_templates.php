<?php
/**
 * Return the contents of the physical template file from the website private folder or from theme folder.
 *
 * The file is determined by the URL requested and the information in the database.
 *
 * @return string $template
 */
function nvweb_template_load($template_id=null)
{
	global $current;
	global $DB;
	global $website;
	
	$template = '';

	if(empty($template_id))
        $template_id = $current['template'];

    if(!empty($template_id))
	{
		$template = new template();
		$template->load($template_id);

		if(!$template->enabled)
			nvweb_clean_exit();

		if($template->permission == 2)
			nvweb_clean_exit();
		else if($template->permission == 1 && empty($_SESSION['APP_USER#'.APP_UNIQUE]))
			nvweb_clean_exit();

		if(file_exists($template->file))
			$template->file_contents = @file_get_contents($template->file);	// from theme
		else if(file_exists(NAVIGATE_PRIVATE.'/'.$website->id.'/templates/'.$template->file))
			$template->file_contents = @file_get_contents(NAVIGATE_PRIVATE.'/'.$website->id.'/templates/'.$template->file);
        else
            $template->file_contents = 'NV error: template file not found! ('.$template->file.')';
	}
	
	return $template;
}

/**
 * Loads the webdictionary for the current website
 *
 * The function takes the current language for the source
 *
 * @return array $dictionary
 */
function nvweb_dictionary_load()
{
	global $DB;
	global $session;
	global $website;
	global $theme;
	
	$dictionary = array();	
	// the dictionary is an array merged from the following sources (- to + preference)
	// theme dictionary (json)
	// theme dictionary on database
	// webdictionary custom entries
	
	// theme dictionary
	if(!empty($theme))
	{
		$theme->dictionary = array(); // clear previous loaded dictionary
		$theme->t(); // force theme dictionary load
	}
	
	if(!empty($theme->dictionary))
		$dictionary = $theme->dictionary;

	// webdictionary custom entries
	$DB->query('SELECT node_id, text
				  FROM nv_webdictionary 
				 WHERE node_type = "global"
				   AND lang = '.protect($session['lang']).'
				   AND website = '.$website->id.'
				 UNION
				 SELECT subtype AS node_id, text
				 FROM nv_webdictionary
				 WHERE node_type = "theme"
				   AND theme = '.protect($website->theme).' 
				   AND lang = '.protect($session['lang']).'
				   AND website = '.$website->id
			   );		
						
	$data = $DB->result();
	
	if(!is_array($data)) $data = array();
	
	foreach($data as $item)
	{
		$dictionary[$item->node_id] = $item->text;
	}
	
	return $dictionary;
}

/**
 * Sets or prints a certain code that will be placed just before closing the </body> tag
 *
 * @param string $type "js", "css" or "html", depending on the code type to return / append
 * @param string $code actual source code that will be appended, if empty all source code saved will be returned
 * @return string the source code previously appended or empty
 */
function nvweb_after_body($type="js", $code="")
{
	global $current;

	if(empty($code))
	{
	    $out = array();
		if(!empty($current[$type.'_after_body']))
		{
            $out = $current[$type.'_after_body'];

			switch($type)
			{
				case 'js':
                    array_unshift($out, '<script language="javascript" type="text/javascript">');
                    $out[] = '</script>';
					break;

                case 'css':
                    array_unshift($out, '<style>');
                    $out[] = '</style>';
					break;

				case 'php':
                    foreach($current[$type . '_after_body'] as $code)
                    {
                        call_user_func($code);
                    }
                    $out = array();
					break;

				case 'html':
				default:
					break;
			}

			return implode("\n", $out);
		}
	}
	else
		$current[$type.'_after_body'][] = $code;

	return "";
}

/**
 * Parses a template looking for nv tags and replacing them with the generated HTML code
 *
 * @param $template string HTML code to parse
 * @return string HTML page generated
 */
function nvweb_template_parse($template)
{
	global $dictionary;
	global $DB;
	global $current;
	global $website;
    global $structure;
    global $theme;
    global $idn;
	global $session;
	
	$html = $template;
	
	// now parse autoclosing tags
	$tags = nvweb_tags_extract($html, 'nv', true, true, 'UTF-8');

	foreach($tags as $tag)
	{
		$content = '';
		
		switch($tag['attributes']['object'])
		{
			// MAIN OBJECT TYPES
			case 'nvweb':
			case 'widget':
			case 'webget':
			case '':
                debugger::timer('nvweb-templates-nvweb-'.$tag['attributes']['name']);

				// webgets on lib/webgets have priority over private/webgets
				nvweb_webget_load($tag['attributes']['name']);
				
				$fname = 'nvweb_'.$tag['attributes']['name'];

				$tag['attributes']['nvweb_html'] = $html;	// always pass the current buffered output to the webget
				
				if(function_exists($fname))
					$content = $fname($tag['attributes']);

                debugger::stop_timer('nvweb-templates-webget-'.$tag['attributes']['name'].'[mode="'.$tag['attributes']['mode'].'"]');
				break;
				
			case 'root':
				$content = NVWEB_ABSOLUTE;
				break;

            case 'nvajax':
                $content = NVWEB_AJAX;
                break;

			case 'url':
                $content = '';
                if(!empty($tag['attributes']['lang']))
                    $lang = $tag['attributes']['lang'];
                else
                    $lang = $current['lang'];

				if(!empty($tag['attributes']['type']) && !empty($tag['attributes']['id']))
				{
					$url = nvweb_source_url($tag['attributes']['type'], $tag['attributes']['id'], $lang);
					if(!empty($url)) $content .= $url;
				}
				else if(!empty($tag['attributes']['type']) && !empty($tag['attributes']['property']))
				{
					$tag['attributes']['id'] = nvweb_properties(array('property' => $tag['attributes']['property']));
					$url = nvweb_source_url($tag['attributes']['type'], $tag['attributes']['id'], $lang);
					if(!empty($url)) $content .= $url;
				}
				else if(!empty($tag['attributes']['type']) && empty($tag['attributes']['id']))
                {
                    // get structure parent for this element and return its path
                    if($current['type']=='structure')
                    {
                        $category = $current['object']->parent;
                        if(empty($category))
                            $category = $current['object']->id;
                    }
                    else
                        $category = $current['object']->category;

                    $url = nvweb_source_url($tag['attributes']['type'], $category, $lang);

                    if(!empty($url)) $content .= $url;
                }
				else
				{
					$content .= '/'.$current['route'];	
				}

                $content = nvweb_prepare_link($content);
				break;

            case 'd':
			case 'dict':
			case 'dictionary':
                if(!empty($tag['attributes']['type']))
                {
                    if($tag['attributes']['type']=='structure' || $tag['attributes']['type']=='category')
                    {
                        // force loading dictionary for all elements in structure (for the current language)
                        nvweb_menu_load_dictionary();
	                    if(!is_numeric($tag['attributes']['id']))
	                    {
		                    // maybe it's a property name instead of a category id
		                    $tag['attributes']['id'] = nvweb_properties(array('property' => $tag['attributes']['property']));
	                    }
                        $content = $structure['dictionary'][$tag['attributes']['id']];
                    }
                    else if($tag['attributes']['type']=='item')
                    {
                        $tmp = webdictionary::load_element_strings('item', $tag['attributes']['id']);
                        $content = $tmp[$current['lang']]['title'];
                    }
                }
                else
                    $content = $dictionary[$tag['attributes']['id']];

                if(empty($content))
                    $content = $tag['attributes']['label'];
                if(empty($content))
                    $content = $tag['attributes']['default'];
				break;
				
			case 'request':
                if(!empty($tag['attributes']['name']))
				    $content = $_REQUEST[$tag['attributes']['name']];
                else // deprecated: use "request" as attribute [will be removed on navigate cms 2.0]
                    $content = $_REQUEST[$tag['attributes']['request']];

                if(is_array($content))
                    $content = implode(',', $content);

                if(!isset($tag['attributes']['raw']) || $tag['attributes']['raw']!='true')
                {
                    // prepare string to be included in a webpage
                    $content = htmlentities($content);
                }
				break;				
				
			case 'constant':
			case 'variable':
				switch($tag['attributes']['name'])
				{
                    case "structure":
					case "category":
						// retrieve the category ID from current session
						$tmp = NULL;
						if($current['type']=='structure')
							$tmp = $current['id'];
						else if(!empty($current['category']))
							$tmp = $current['category'];
						else if(!empty($current['object']->category))
							$tmp = $current['object']->category;
														
						if(empty($tmp))
							$content = '';
						else
						{
							$content = $DB->query_single(
								'text',
								'nv_webdictionary', '
									   node_type = "structure"
								   AND subtype = "title"
								   AND node_id = '.$tmp.'
								   AND lang = '.protect($current['lang']).'
								   AND website = '.$website->id
							);
						}
						break;
						
					case "year":
						$content = date('Y');
						break;

                    case "website_name":
                        $content = $website->name;
                        break;

                    case "website_description":
                        $content = $website->metatag_description[$current['lang']];
                        break;
						
					case "lang_code":
						$content = $current['lang'];
						break;
												
					default:
						break;
				}
				break;
				
			case 'php':
                if(!empty($tag['attributes']['code']))
				    eval('$content = '.$tag['attributes']['code'].';');
				break;

            case 'theme':
				// compatibility with Navigate < 1.8.9
				// deprecated! code will be removed in Navigate 3.0
				if($tag['attributes']['name']=='url')
				{
					$tag['attributes']['mode'] = 'url';
				}
	            else if($tag['attributes']['name']=='style')
	            {
		            $tag['attributes']['name'] = $tag['attributes']['mode'];
		            $tag['attributes']['mode'] = 'style';
	            }

				// new syntax ("mode" first)
	            switch($tag['attributes']['mode'])
	            {
		            case "style":
			            $content = $website->theme_options->style;

						if(!empty($tag['attributes']['name']))
						{
				            switch($tag['attributes']['name'])
				            {
					            case 'name':
						            $content = $website->theme_options->style;
						            break;

					            case 'color':
					            default:
						            // return theme definition file location for the requested substyle
						            if(!empty($website->theme_options->style))
							            $content = $theme->styles->{$website->theme_options->style}->{$tag['attributes']['name']};
						            if(empty($content))
						            {
							            // return first available
							            $theme_styles = array_keys(get_object_vars($theme->styles));
							            $content = $theme->styles->{$theme_styles[0]}->{$tag['attributes']['name']};
						            }
						            break;
				            }
						}
			            break;

		            case "url":
                        $content = $website->absolute_path();
                        try
                        {
                            $content = $idn->encodeUri($content);
                        }
                        catch (\InvalidArgumentException $e)
                        {
                            // do nothing, the domain is already in punycode
                        }
			            $content.= NAVIGATE_FOLDER.'/themes/'.$theme->name.'/';
			            break;

	            }
				break;

			default: 
				//var_dump($tag['attributes']['object']);
				break; 
		}

		$html = str_replace($tag['full_tag'], $content, $html);
	}

    return $html;
}

/**
 * Parse special Navigate CMS tags like:
 * <ul>
 * <li>&lt;nv object="include" file="" id="" /&gt;</li>
 * <li>curly bracket tags {{nv object=""}}</li>
 * </ul>
 *
 * Generate the final HTML code for these special tags or convert them
 * to a simpler nv tags.
 *
 * @param $html
 * @return mixed
 */
function nvweb_template_parse_special($html)
{
	global $website;
	global $current;

	// find <pre> and <code> tags, save its contents and leave a placeholder to restore the content later
	$tags_pre = nvweb_tags_extract($html, 'pre', false, true, 'UTF-8');
	for($t=count($tags_pre); $t--; $t >= 0) // need to process the tags upwards, to keep the offsets found
	{
		$tag = $tags_pre[$t];
		if(empty($tag)) continue;
		$tag_uid = uniqid('nv-tags-pre-');
		$current['delayed_tags_pre'][$tag_uid] = $tag['full_tag'];
		$html = substr_replace($html, '<!--#'.$tag_uid.'#-->', $tag['offset'], strlen($tag['full_tag']));
	}

	$tags_code = nvweb_tags_extract($html, 'code', false, true, 'UTF-8');
	for($t=count($tags_code); $t--; $t >= 0) // need to process the tags upwards, to keep the offsets found
	{
		$tag = $tags_code[$t];
		if(empty($tag)) continue;
		$tag_uid = uniqid('nv-tags-code-');
		$current['delayed_tags_code'][$tag_uid] = $tag['full_tag'];
		$html = substr_replace($html, '<!--#'.$tag_uid.'#-->', $tag['offset'], strlen($tag['full_tag']));
	}

    $changed = false;

	// translate "{{nv object='list' " tags to "<nv object='list' " version
	preg_match_all("/{{nv\s object=[\"']list[\"'] ([^}]+)}}/ixsm", $html, $curly_tags);
	for($c=0; $c < count($curly_tags[0]); $c++)
	{
		if(stripos($curly_tags[0], 'object="list"'))
		{
			$tmp = str_ireplace(array('{{nv object="list" ', '}}'), array('<nv object="list" ', '>'), $curly_tags[0][$c]);
			$html = str_ireplace($curly_tags[0][$c], $tmp, $html);
		}
		else
		{
			$tmp = str_ireplace(array("{{nv object='list' ", '}}'), array('<nv object="list" ', '>'), $curly_tags[0][$c]);
			$html = str_ireplace($curly_tags[0][$c], $tmp, $html);
		}

		$changed = true;
	}

	// translate "{{/nv}}" tags to "</nv>" version
	$html = str_ireplace('{{/nv}}', '</nv>', $html);

	// translate "{{nvlist_conditional }}" tags to "<nvlist_conditional >" version
	preg_match_all("/{{nvlist_conditional \s([^}]+)}}/ixsm", $html, $curly_tags);
	for($c=0; $c < count($curly_tags[0]); $c++)
	{
		$tmp = str_replace(array('{{nvlist_conditional ', '}}'), array('<nvlist_conditional ', '>'), $curly_tags[0][$c]);
		$html = str_ireplace($curly_tags[0][$c], $tmp, $html);
		$changed = true;
	}

	// translate "{{/nvlist_conditional}}" tags to "</nvlist_conditional>" version
	$html = str_ireplace('{{/nvlist_conditional}}', '</nvlist_conditional>', $html);

    // translate "{{nv }}" tags to "<nv />" version
    preg_match_all("/{{nv\s([^}]+)}}/ixsm", $html, $curly_tags);
    for($c=0; $c < count($curly_tags[0]); $c++)
    {
        $tmp = str_replace(array('{{nv ', '}}'), array('<nv ', ' />'), $curly_tags[0][$c]);
        $html = str_ireplace($curly_tags[0][$c], $tmp, $html);
        $changed = true;
    }

    // translate "{{nvlist }}" tags to "<nvlist />" version
    preg_match_all("/{{nvlist\s([^}]+)}}/ixsm", $html, $curly_tags);
    for($c=0; $c < count($curly_tags[0]); $c++)
    {
        $tmp = str_replace(array('{{nvlist ', '}}'), array('<nvlist ', ' />'), $curly_tags[0][$c]);
        $html = str_ireplace($curly_tags[0][$c], $tmp, $html);
        $changed = true;
    }

    if($changed)
        return nvweb_template_parse_special($html);

    // parse includes (we must do it before parsing list or search)
    $tags = nvweb_tags_extract($html, 'nv', true, true, 'UTF-8');
    foreach($tags as $tag)
	{
        $content = '';
        $changed = false;
        $tag['length'] = strlen($tag['full_tag']);

        if($tag['attributes']['object']=='include')
        {
            $tid = $tag['attributes']['id'];
            $file = $tag['attributes']['file'];

            if(!empty($tid))
            {
                $template = new template();
                $template->load($tid);
                if($template->website == $website->id) // cross-website security
                {
                    $content = file_get_contents(NAVIGATE_PRIVATE.'/'.$website->id.'/templates/'.$template->file);
                }
            }
            else if(!empty($file))
            {
                $content = file_get_contents(NAVIGATE_PATH.'/themes/'.$website->theme.'/'.$file);
            }

            $html = substr_replace($html, $content, $tag['offset'], $tag['length']);
            $changed = true;
        }

        // if an object="include" has been found, we need to restart the parse_special tags function
        // as it may contain other "includes" or "{{nv" tags that need transformation
        if($changed)
        {
            $html = nvweb_template_parse_special($html);
            break;
        }
    }

    return $html;
}

function nvweb_template_restore_special($html)
{
	global $current;

	foreach($current['delayed_tags_code'] as $tag_uid => $tag_code)
	{
		$html = str_replace('<!--#'.$tag_uid.'#-->', $current['delayed_tags_code'][$tag_uid], $html);
		$current['delayed_tags_code'][$tag_uid] = NULL;
	}

	foreach($current['delayed_tags_pre'] as $tag_uid => $tag_code)
	{
		$html = str_replace('<!--#'.$tag_uid.'#-->', $current['delayed_tags_pre'][$tag_uid], $html);
		$current['delayed_tags_pre'][$tag_uid] = NULL;
	}

	return $html;
}

/**
 * Parse Navigate CMS tags like:
 * <ul>
 * <li>&lt;nv object="list"&gt;&lt;/nv&gt;</li>
 * <li>&lt;nv object="search"&gt;&lt;/nv&gt;</li>
 * <li>&lt;nv object="conditional"&gt;&lt;/nv&gt;</li>
 * </ul>
 *
 * Generate the final HTML code for these special tags or convert them
 * to a simpler nv tags.
 *
 * @param $html
 * @return mixed
 */
function nvweb_template_parse_lists($html, $process_delayed=false)
{
    global $current;

    if($process_delayed)
    {
        // time to process delayed nvlists and nvsearches
        foreach($current['delayed_nvlists'] as $uid => $vars)
        {
            debugger::timer('nvweb-templates-list-[source="'.$vars['source'].'"]');
            $content = nvweb_list($vars);
            $html = str_replace('<!--#'.$uid.'#-->', $content, $html);
            debugger::stop_timer('nvweb-templates-list-[source="'.$vars['source'].'"]');
        }

        foreach($current['delayed_nvsearches'] as $uid => $vars)
        {
            debugger::timer('nvweb-templates-search-[source="'.$vars['source'].'"]');
            $content = nvweb_search($vars);
            $html = str_replace('<!--#'.$uid.'#-->', $content, $html);
            debugger::stop_timer('nvweb-templates-search-[source="'.$vars['source'].'"]');
        }

        return $html;
    }

	// parse special navigate tags (includes, lists, searches...)
	$tags = nvweb_tags_extract($html, 'nv', true, true, 'UTF-8');

	foreach($tags as $tag)
	{
		$content = '';
		$changed = false;

		switch($tag['attributes']['object'])
		{
			case 'list':
                $template_end = nvweb_templates_find_closing_list_tag($html, $tag['offset']);
				$tag['length'] = $template_end - $tag['offset'] + strlen('</nv>'); // remove tag characters
				$list = substr($html, ($tag['offset'] + strlen($tag['full_tag'])), ($tag['length'] - strlen('</nv>') - strlen($tag['full_tag'])));

				$vars = array_merge($tag['attributes'], array('template' => $list));

				// save lists which need to be processed later (after other simpler nv tags)
                // "cart" lists are always delayed
                if($tag['attributes']['delayed']=='true' || $tag['attributes']['source']=='cart')
                {
                    $list_uid = uniqid('nvlist-');
                    $current['delayed_nvlists'][$list_uid] = $vars;
                    $html = substr_replace($html, '<!--#'.$list_uid.'#-->', $tag['offset'], $tag['length']);
                    $changed = true;
                    continue;
                }

                debugger::timer('nvweb-templates-list-[source="'.$vars['source'].'"]');
				$content = nvweb_list($vars);
                debugger::stop_timer('nvweb-templates-list-[source="'.$vars['source'].'"]');
				
				$html = substr_replace($html, $content, $tag['offset'], $tag['length']);
				$changed = true;
				break;	

			case 'search':
                $template_end = nvweb_templates_find_closing_list_tag($html, $tag['offset']);
				$tag['length'] = $template_end - $tag['offset'] + strlen('</nv>'); // remove tag characters
				$search = substr($html, ($tag['offset'] + strlen($tag['full_tag'])), ($tag['length'] - strlen('</nv>') - strlen($tag['full_tag'])));
								
				@include_once(NAVIGATE_PATH.'/lib/webgets/search.php');				
				$vars = array_merge($tag['attributes'], array('template' => $search));

                if($tag['attributes']['delayed']=='true')
                {
                    $search_uid = uniqid('nvsearch-');
                    $current['delayed_nvsearches'][$search_uid] = $vars;
                    $html = substr_replace($html, '<!--#'.$search_uid.'#-->', $tag['offset'], $tag['length']);
                    $changed = true;
                    continue;
                }

                debugger::timer('nvweb-templates-search-[source="'.$vars['source'].'"]');
                $content = nvweb_search($vars);
                debugger::stop_timer('nvweb-templates-search-[source="'.$vars['source'].'"]');
				
				$html = substr_replace($html, $content, $tag['offset'], $tag['length']);
				$changed = true;
				break;

            case 'conditional':
                $template_end = nvweb_templates_find_closing_list_tag($html, $tag['offset']);
                $tag['length'] = $template_end - $tag['offset'] + strlen('</nv>'); // remove tag characters
                $conditional = substr($html, ($tag['offset'] + strlen($tag['full_tag'])), ($tag['length'] - strlen('</nv>') - strlen($tag['full_tag'])));

                @include_once(NAVIGATE_PATH.'/lib/webgets/conditional.php');
                $vars = array_merge($tag['attributes'], array('_template' => $conditional));

                $content = nvweb_conditional($vars);

                $html = substr_replace($html, $content, $tag['offset'], $tag['length']);
                $changed = true;
                break;
		}
		
		if($changed)
		{
			// ok, we've found and processed ONE special tag
			// now the HTML has changed, so the original positions of the special <nv> tags have also changed
			// we must finish the current loop and start a new one
			$html = nvweb_template_parse_lists($html);
			break;
		}
	}

	return $html;
}

function nvweb_templates_find_closing_list_tag($html, $offset)
{
    $found = false;
    $level = 0;
    $closing_tag_position = 0;
    $loops = 0;

    // find next nv object="" tag (opening or closing)
    // if it is an opening tag --> level + 1
    // if it is a closing tag --> level - 1
    //      if level = 0, that's the closing tag we were looking for
    //      else repeat from current offset
    while(!$found && $loops < 2000)
    {
        // check if there is a special '<nv>' opening tag (list, search, conditional) before the next closing found tag
        $next_opening = stripos_array(
            $html,
            array(
                '<nv object="list" ',
                '<nv object="search" ',
                '<nv object="conditional" '
            ),
            $offset
        );

        // find next '</nv>' occurrence from offset
        $next_closing = stripos($html, '</nv>', $offset);

        if($next_opening!==false && $next_opening < $next_closing)
        {
            // there is an opening tag before a closing tag, so there is an inner nvlist_conditional
            // move the offset to the opening tag found
            $offset = $next_opening + strlen('<nv object="');
            $level++;
        }
        else
        {
            // found a closing tag without an inner nvlist_conditional opening tag
            $level--;
            if($level > 0)
            {
                $offset = $next_closing + strlen('</nv>');
            }
            else
            {
                $closing_tag_position = $next_closing;
                $found = true;
            }
        }

        $loops++;
    }

    if(!$found)
        $closing_tag_position = false;

    return $closing_tag_position;


    /*


    // no support for nested lists
    // $template_end = strpos($html, '</nv>', $tag['offset']);
    // return $template_end;

    // supporting nested lists
    $loops = 0;
    $found = false;

    while(!$found)
    {
        // find next '</nv>' occurrence from offset
        $next_closing = stripos($html, '</nv>', $offset);

        // check if there is a special '<nv>' opening tag (list, search, conditional) before the next closing found tag
        $next_opening = stripos_array(
            $html,
            array(
                '<nv object="list" ',
                '<nv object="search" ',
                '<nv object="conditional" '
            ),
            $offset
        );

        if(!$next_opening)
        {
            $found = true;
        }
        else
        {
            $found = $next_opening > $next_closing;

            if(!$found)
            {
                $offset = $next_closing + strlen('</nv>');
                $loops++;
            }
        }

        if(!$found && ($offset > strlen($html) || $loops > 1000))
            break;
    }

    if(!$found)
        $next_closing = false;

    return $next_closing;
    */
}

function nvweb_replace_tag_contents($tag_id, $content, $html_source_code)
{
	brasofilo_suSetHtmlById( $html_source_code, $tag_id, $content );
	return $html_source_code;
}


/**
 * Apply current website theme settings
 *
 * Example: <HORIZON_LOGO />    -->   Theme logo URL
 *
 * @param $template string HTML of the current page
 * @return string $template HTML of the current page with the theme settings applied
 */
function nvweb_theme_settings($template)
{
    global $website;

    if(!empty($website->theme))
    {
        nvweb_webget_load($website->theme);

        if(function_exists('nvweb_'.$website->theme))
        {
            $out = call_user_func(
                'nvweb_'.$website->theme,
                array(
                    'mode' => 'theme',
                    'html' => $template
                )
            );

            if(!empty($out))
               $template = $out;
        }
    }

    return $template;
}

/**
 * Returns the current visitor real IP or false if couldn't be located
 *
 * @return mixed|mixed Real IP of the current visitor or false
 */
function nvweb_real_ip()
{
     $ip = false;
     if(!empty($_SERVER['HTTP_CLIENT_IP']))
          $ip = $_SERVER['HTTP_CLIENT_IP'];

     if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
     {
          $ips = explode(", ", $_SERVER['HTTP_X_FORWARDED_FOR']);
          if($ip)
          {
               array_unshift($ips, $ip);
               $ip = false;
          }
          for($i = 0; $i < count($ips); $i++)
          {
               if(!preg_match("/^(10|172\.16|192\.168)\./i", $ips[$i]))
               {
                    if(version_compare(phpversion(), "5.0.0", ">="))
                    {
                         if(ip2long($ips[$i]) != false)
                         {
                              $ip = $ips[$i];
                              break;
                         }
                    }
                    else
                    {
                         if(ip2long($ips[$i]) != - 1)
                         {
                              $ip = $ips[$i];
                              break;
                         }
                    }
               }
          }
     }
     return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
}


/**
 * Determine the default language to show to the user reading its browser preferences
 *
 * Note: if the language is already setted in a cookie or in the session this function is never called
 *
 * @return string $lang 2-letter code of the language
 */
function nvweb_country_language()
{
    global $website;

    $lang = '';

    if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
    {
        preg_match_all( '/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'],
            $lang_parse);

        if(count($lang_parse[1]))
        {
            $langs = array_combine($lang_parse[1], $lang_parse[4]);
            foreach ($langs as $lang => $val)
                if($val === '') $langs[$lang] = 1;
            arsort($langs, SORT_NUMERIC);
        }

        $found = false;

		if(is_array($langs))
		{
			foreach($langs as $language_browser => $val)
			{
				foreach($website->languages_published as $language_available)
				{
					if($language_available == $language_browser)
					{
						$lang = $language_browser;
						$found = true;
						break;
					}
				}
				if($found)
					break;
			}
		}
    }

    if(empty($lang))
    {
        // no user defined language matches the website available languages, so take the website's default one
        $lang = $website->languages_list[0];
    }

    return $lang;
}

/**
 * Replace "navigate_download.php" paths for base_domain/object uris
 *
 * @param $html string HTML content
 * @return string HTML content without "navigate_download.php" requests
 */
function nvweb_template_fix_download_paths($in)
{
    $regex = '/https?\:\/\/[^\" ]+/i';
    preg_match_all($regex, $in, $data);

    $out = $in;
    $nv_download = basename(NAVIGATE_DOWNLOAD);

    if(is_array($data[0]))
    {
        foreach($data[0] as $url)
        {
            $url_decoded = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
            $url_parsed = parse_url($url_decoded);

            if(strpos($url_parsed['path'], $nv_download) !== false)
            {
                $new_url = NVWEB_OBJECT.'?'.$url_parsed['query'];
                $out = str_replace($url, $new_url, $out, $c);
            }
        }
    }

    return $out;
}

/**
 * Apply some template tweaks to improve Navigate CMS theme developing experience like:
 *
 * <ul>
 * <li>Guess absolute paths to images, stylesheets, videos and scripts (even on urls without http, "//")</li>
 * <li>Convert &lt;a rel="video"&gt; and &lt;a rel="audio"&gt;  to &lt;video&gt; and &lt;audio&gt; tags</li>
 * <li>Process &lt;img&gt; tags to generate optimized images</li>
 * <li>Add Navigate CMS content default styles</li>
 * </ul>
 *
 * @param $html string Original HTML template content
 * @return string HTML template tweaked
 */
function nvweb_template_tweaks($html)
{
	global $website;
	// apply some tweaks to the generated html code

	// tweak 1: try to make absolute all image, css and script paths not starting by http	
	if(!empty($website->theme))
		$website_absolute_path = NAVIGATE_URL.'/themes/'.$website->theme;
	else
		$website_absolute_path = $website->absolute_path(false);

    // stylesheets
    $replacements = array();
    $tags = nvweb_tags_extract($html, 'link', NULL, true, 'UTF-8');
	foreach($tags as $tag)
	{		
		if(!isset($tag['attributes']['href'])) continue;	
		if(substr($tag['attributes']['href'], 0, 7)!='http://' &&
		   substr($tag['attributes']['href'], 0, 8)!='https://')
		{
            // treat "//" paths (without http or https)
            if(substr($tag['attributes']['href'], 0, 2)=='//')
                $src = $website->protocol.substr($tag['attributes']['href'], 2);
            else
			    $src = $website_absolute_path.'/'.$tag['attributes']['href'];

			$tag['new'] = '<link href="'.$src.'" ';
			foreach($tag['attributes'] as $name => $value)
			{
				if($name!='href') $tag['new'] .= $name.'="'.$value.'" ';
			}
			$tag['new'] .= '/>';
			
			//$html = str_replace($tag['full_tag'], $tag['new'], $html);
            $replacements[$tag['full_tag']] = $tag['new'];
		}
	}
	
	// scripts
	$tags = nvweb_tags_extract($html, 'script', NULL, true, 'UTF-8');
	foreach($tags as $tag)
	{
		if(!isset($tag['attributes']['src'])) continue;
		if(substr($tag['attributes']['src'], 0, 7)!='http://' && 
		   substr($tag['attributes']['src'], 0, 8)!='https://')
		{
            if(substr($tag['attributes']['src'], 0, 2)=='//')
                $src = $website->protocol.substr($tag['attributes']['src'], 2);
            else
                $src = $website_absolute_path.'/'.$tag['attributes']['src'];

			$tag['new'] = '<script src="'.$src.'" ';
			foreach($tag['attributes'] as $name => $value)
			{
				if($name!='src') $tag['new'] .= $name.'="'.$value.'" ';
			}
			$tag['new'] .= '></script>';
			
			//$html = str_replace($tag['full_tag'], $tag['new'], $html);
            $replacements[$tag['full_tag']] = $tag['new'];
		}
	}

    $html = str_replace(
        array_keys($replacements),
        array_values($replacements),
        $html
    );

	// poster attribute (<video>)
    $replacements = array();
    $tags = nvweb_tags_extract($html, array('video'), false, true, 'UTF-8');
	foreach($tags as $tag)
	{
		if(!isset($tag['attributes']['poster'])) continue;
		if(substr($tag['attributes']['poster'], 0, 7)!='http://' &&
		   substr($tag['attributes']['poster'], 0, 8)!='https://')
		{
            if(substr($tag['attributes']['poster'], 0, 2)=='//')
                $src = $website->protocol.substr($tag['attributes']['poster'], 2);
            else
                $src = $website_absolute_path.'/'.$tag['attributes']['poster'];

			$tag['new'] = '<video poster="'.$src.'" ';
			foreach($tag['attributes'] as $name => $value)
			{
				if($name!='poster')
					$tag['new'] .= $name.'="'.$value.'" ';
			}
			$tag['new'] .= '>'.$tag['contents'].'</video>';

			//$html = str_replace($tag['full_tag'], $tag['new'], $html);
            $replacements[$tag['full_tag']] = $tag['new'];
		}
	}

    $html = str_replace(
        array_keys($replacements),
        array_values($replacements),
        $html
    );


	// sources (video, audio)
    $replacements = array();
	$tags = nvweb_tags_extract($html, array('video', 'audio'), false, true, 'UTF-8');
	foreach($tags as $tag)
	{
		$tag_sources = nvweb_tags_extract($tag['contents'], 'source', true, true, 'UTF-8');

		foreach($tag_sources as $source)
		{
			if(!isset($source['attributes']['src'])) continue;
			if(substr($source['attributes']['src'], 0, 7)!='http://' &&
			   substr($source['attributes']['src'], 0, 8)!='https://')
			{
	            if(substr($source['attributes']['src'], 0, 2)=='//')
	                $src = $website->protocol.substr($source['attributes']['src'], 2);
	            else
	                $src = $website_absolute_path.'/'.$source['attributes']['src'];

				$source['new'] = '<source src="'.$src.'" ';
				foreach($source['attributes'] as $name => $value)
				{
					if($name!='poster')
						$source['new'] .= $name.'="'.$value.'" ';
				}
				$source['new'] .= '>'.$source['contents'].'</source>';

				//$html = str_replace($source['full_tag'], $source['new'], $html);
                $replacements[$tag['full_tag']] = $tag['new'];
			}
		}
	}

    $html = str_replace(
        array_keys($replacements),
        array_values($replacements),
        $html
    );

	// images
    $replacements = array();
    $tags = nvweb_tags_extract($html, 'img', NULL, true, 'UTF-8');

	foreach($tags as $tag)
	{
		if(isset($tag['attributes']['srcset']))
		{
			$srcset_old = explode(",", $tag['attributes']['srcset']);
			$srcset_new = array();

			foreach($srcset_old as $src)
			{
				$src = trim($src);
				$src = explode(" ", $src);

				if( substr($src[0], 0, 7)!='http://' &&
		            substr($src[0], 0, 8)!='https://' &&
                    substr($src[0], 0, 2)!='//')
				{
	                $src[0] = $website_absolute_path.'/'.$src[0];
				}

                if(substr($src[0], 0, 2)=='//')
                    $src[0] = $website->protocol.substr($src[0], 2);

                $srcset_new[] = implode(" ", $src);

                $tag['attributes']['srcset'] = implode(", ", $srcset_new);
			}

			$tag['new'] = '<img ';
			foreach($tag['attributes'] as $name => $value)
				$tag['new'] .= $name.'="'.$value.'" ';
			$tag['new'] .= '/>';

			//$html = str_replace($tag['full_tag'], $tag['new'], $html);
            $replacements[$tag['full_tag']] = $tag['new'];
		}

		if(!isset($tag['attributes']['src'])) continue;

		if(substr($tag['attributes']['src'], 0, 7)!='http://' && 
		   substr($tag['attributes']['src'], 0, 8)!='https://' &&
		   substr($tag['attributes']['src'], 0, 5)!='data:')
		{
            if(substr($tag['attributes']['src'], 0, 2)=='//')
                $src = $website->protocol.substr($tag['attributes']['src'], 2);
            else
			    $src = $website_absolute_path.'/'.$tag['attributes']['src'];
			
			$tag['new'] = '<img src="'.$src.'" ';
			foreach($tag['attributes'] as $name => $value)
			{
				if($name!='src') $tag['new'] .= $name.'="'.$value.'" ';
			}			
			$tag['new'] .= '/>';
			
			//$html = str_replace($tag['full_tag'], $tag['new'], $html);
            $replacements[$tag['full_tag']] = $tag['new'];
		}
	}

    $html = str_replace(
        array_keys($replacements),
        array_values($replacements),
        $html
    );

    // replace any "navigate_download.php" request for "/object" request
    $html = nvweb_template_fix_download_paths($html);
	
	// tweak 2: convert <a rel="video"> to <video> and <a rel="audio"> to <audio> tags
    $replacements = array();
    $tags = nvweb_tags_extract($html, 'a', NULL, true, 'UTF-8');
	foreach($tags as $tag)
	{
		if($tag['attributes']['rel']=='video' && $tag['attributes']['navigate']=='navigate')
		{
            $preload = 'metadata';
            if(!empty($tag['attributes']['preload']))
                $preload = $tag['attributes']['preload'];

			$content = array();
			$content[] = '<video controls="controls" preload="'.$preload.'">';
			$content[] = '	<source src="'.$tag['attributes']['href'].'" />';
			$content[] = '	'.$tag['full_tag'];
			$content[] = '</video>';
				
			//$html = str_replace($tag['full_tag'], implode("\n", $content), $html);
            $replacements[$tag['full_tag']] = implode("\n", $content);
		}
		
		if($tag['attributes']['rel']=='audio' && $tag['attributes']['navigate']=='navigate')
		{
            $preload = 'metadata';
            if(!empty($tag['attributes']['preload']))
                $preload = $tag['attributes']['preload'];

			$content = array();
			$content[] = '<audio controls="controls" preload="'.$preload.'">';
			$content[] = '	<source src="'.$tag['attributes']['href'].'" type="'.$tag['attributes']['type'].'" />';
			$content[] = '	'.$tag['full_tag'];
			$content[] = '</audio>';			
			
			//$html = str_replace($tag['full_tag'], implode("\n", $content), $html);
            $replacements[$tag['full_tag']] = implode("\n", $content);
		}		
	}

    $html = str_replace(
        array_keys($replacements),
        array_values($replacements),
        $html
    );


	// tweak 3: let navigate generate a resized image/thumbnail if width/height is given in the img tag
	$tags = nvweb_tags_extract($html, 'img', NULL, true, 'UTF-8');
    $replacements = array();
	foreach($tags as $tag)
	{
		if(!isset($tag['attributes']['src'])) continue;
		$src = $tag['attributes']['src'];
		
		$tag['new'] = '';

		foreach($tag['attributes'] as $name => $value)
		{
			if($name!='src')
                $tag['new'] .= $name.'="'.$value.'" ';

			// width attribute, the image is retrieved from a dynamic source, the width is not expressed in percentage, the width is not already given in the url parameters
			if($name=='width' && strpos($src, '?')!==false && strpos($value, "%")===false && strpos($src, "&width=")===false)
				$src .= '&width='.$value;
				
			if($name=='height' && strpos($src, '?')!==false && strpos($value, "%")===false && strpos($src, "&height=")===false)
				$src .= '&height='.$value;	
		}

		$tag['new'] = '<img src="'.$src.'" '.$tag['new'].'/>';

        $replacements[$tag['full_tag']] = $tag['new'];
		//$html = str_replace($tag['full_tag'], $tag['new'], $html);
	}

	// more efficient than replacing one by one
	$html = str_replace(
	    array_keys($replacements),
        array_values($replacements),
        $html
    );


    // tweak 4: add Navigate CMS content default styles
    $default_css = file_get_contents(NAVIGATE_PATH.'/css/tools/tinymce.defaults.css');
    $default_css = str_replace(array("\n", "\r", "\s\s", "  "), " ", $default_css);
    $default_css = substr($default_css, strpos($default_css, '/* nvweb */')+11);
    $default_css = '<style type="text/css">'.$default_css.'</style>';
    $html = str_replace('</title>', "</title>\n\t".$default_css."\n", $html);

	return $html;
}

/*
	convert nv:// paths to real links
	right now there are two possibilities (ID is a numeric value)
	nv://element/ID (or elements, or item)
	nv://structure/ID   (or category)
*/
function nvweb_template_convert_nv_paths($html)
{
    // find all urls
	// attempt to retrieve urls including ?parameters
    // preg_match_all("/nv:\/\/(element|elements|structure|category)\/([0-9]+)([?]*)(.*)[\/\"\/']+/i", $html, $matches);
	preg_match_all("/nv:\/\/(element|elements|structure|category)\/([0-9]+)+/i", $html, $matches);

	if(!empty($matches) && !empty($matches[0]))
	{
		$matches = $matches[0];
		foreach($matches as $match)
		{
			$parts = explode('/', str_replace('nv://', '', $match));

			$url = "";
			switch($parts[0])
			{
				case 'element':
				case 'item':
				case 'elements':
					$url = nvweb_source_url("element", $parts[1]);
					break;
				
				case 'structure':
				case 'category':
					$url = nvweb_source_url("structure", $parts[1]);
					break;
				
				default:
					// ignore this url
			}

			if(!empty($url))
            {
                if(strpos($html, 'nv://')===0)
                {
                    $html = $url;
                }
                else
                {
                    $html = str_replace(
                        array('"' . $match . '"', "'" . $match . "'"),
                        array('"' . $url . '"', "'" . $url . "'"),
                        $html
                    );
                }
            }
		}
	}

	return $html;
}

function nvweb_template_oembed_parse($html)
{
    $reg_exUrl = '/[-a-zA-Z0-9@:%_\+.~#?&\/\/=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~#?&\/\/=]*)?/i';

    foreach(array('div', 'p', 'span', 'strong') as $tag_name)
    {
        $tags = nvweb_tags_extract($html, $tag_name, false, true);

        foreach($tags as $tag)
        {
            $text = $tag['contents'];

            if(strpos(@$tag['attributes']['class'], 'nv_oembedded')!==false)
                continue;

            // find all urls in content as PLAIN TEXT urls
            if(preg_match_all($reg_exUrl, strip_tags($text), $url))
            {
                $matches = array_unique($url[0]);
                foreach($matches as $match)
                {
                    $replacement = nvweb_template_oembed_url($match);
                    if($replacement!=$match)
                        $text = str_replace($match, '<div class="nv_oembedded">'.$replacement.'</div>', $text);
                }
            }

            if($text!=$tag['contents'])
            {
                $fragment = str_replace($tag['contents'], $text, $tag['full_tag']);
                $html = str_replace($tag['full_tag'], $fragment, $html);
            }
        }
    }

    return $html;
}

function nvweb_template_oembed_url($url)
{
    global $current;

    // TODO: implement more oembed services
    $out = $url;

    // Twitter: https://twitter.com/username/status/status_id
    if(strpos($url, 'twitter.com/')!==false && strpos($url, '/status')!==false)
    {
        $oembed_url = 'https://api.twitter.com/1/statuses/oembed.json?lang='.$current['lang'].'&url='.urlencode($url); // &omit_script=true
        $response = nvweb_template_oembed_cache('twitter', $oembed_url);
        if(!empty($response->html))
            $out = $response->html;
    }
    // Youtube: http://www.youtube.com?watch=3MteSlpxCpo
    else if(strpos($url, 'www.youtube.com/watch'))
    {
        $oembed_url = 'https://www.youtube.com/oembed?url='.urlencode($url).'&format=json';
        $response = nvweb_template_oembed_cache('youtube', $oembed_url);
        if(!empty($response->html))
            $out = $response->html;
    }
    // Vimeo: http://vimeo.com/channels/staffpicks/113397445
    else if(strpos($url, 'www.vimeo.com/') || strpos($url, 'vimeo.com/'))
    {
        $oembed_url = 'https://vimeo.com/api/oembed.json?url='.urlencode($url);
        $response = nvweb_template_oembed_cache('vimeo', $oembed_url);
        if(!empty($response->html))
            $out = $response->html;
    }

    // Instagram: https://www.instagram.com/p/BInLvYQDSHe/
    else if( strpos($url, 'www.instagram.com/p/') ||
             strpos($url, 'instagram.com/p/') ||
             strpos($url, 'instagr.am/p/')
    )
    {
        $oembed_url = 'https://api.instagram.com/oembed?url='.urlencode($url);
        $response = nvweb_template_oembed_cache('instagram', $oembed_url);
        if(!empty($response->html))
            $out = $response->html;
    }
    // Flickr: http://www.flickr.com/photos/bees/2362225867
    else if(strpos($url, 'www.flickr.com/photos/'))
    {
        $oembed_url = 'https://www.flickr.com/services/oembed.json?url='.urlencode($url);
        $response = nvweb_template_oembed_cache('flickr', $oembed_url);
        if(!empty($response->html))
            $out = $response->html;
    }
    // DailyMotion: http://www.dailymotion.com/video/x40gjsb_stock-video-category-nature-landscapes-corsican-nature-island-of-beauty-sea-beach_shortfilms
    else if(strpos($url, 'www.dailymotion.com/video/'))
    {
        $oembed_url = 'https://www.dailymotion.com/services/oembed?format=json&url='.urlencode($url);
        $response = nvweb_template_oembed_cache('dailymotion', $oembed_url);
        if(!empty($response->html))
            $out = $response->html;
    }
    // Scribd: https://www.scribd.com/doc/110799637
    else if(strpos($url, 'www.scribd.com/doc/'))
    {
        $oembed_url = 'https://www.scribd.com/services/oembed?format=json&url='.urlencode($url);
        $response = nvweb_template_oembed_cache('scribd', $oembed_url);
        if(!empty($response->html))
            $out = $response->html;
    }
    // Soundcloud: https://soundcloud.com/elvenlied/ivan-torrent-icarus-feat-julie
    else if(strpos($url, 'soundcloud.com/'))
    {
        $oembed_url = 'https://soundcloud.com/oembed?format=json&url='.urlencode($url);
        $response = nvweb_template_oembed_cache('soundcloud', $oembed_url);
        if(!empty($response->html))
            $out = $response->html;
    }

    return $out;
}

// default cache 43200 minutes (30 days)
function nvweb_template_oembed_cache($provider, $oembed_url, $minutes=43200)
{
    $file = NAVIGATE_PRIVATE.'/oembed/'.$provider.'.'.md5($oembed_url).'.json';

    if(file_exists($file) && filemtime($file) > (time() - ($minutes * 60)))
    {
        // a previous request has already been posted in the last xx minutes
        $response = file_get_contents($file);
    }
    else
    {
        // request has not been cached or it has expired
        $response = core_curl_post($oembed_url, NULL, NULL, 60, "get");

	    if($response=='Not found')
		    $response = '';

        if(!empty($response))
            file_put_contents($file, $response);
    }

    if(!empty($response))
        $response = json_decode($response);

    return $response;
}


function nvweb_template_processes($html)
{
	global $session;
	global $theme;

	if(isset($session['nv.webuser/verify:email_confirmed']))
	{
		unset($session['nv.webuser/verify:email_confirmed']);

		$text = $theme->t("subscribed_ok");
		if(empty($text) || $text=="subscribed_ok")
			$text = t(37, "E-Mail confirmed");

		nvweb_after_body(
			"html",
			'<div id="nv_webuser_verify_email_confirmed" style=" transition: all 1s; text-align: center; width: 40%; padding: 12px; margin: -48px 30% 0 30%; top: 50%; color: #333; position: fixed; z-index: 1000000; background: rgba(240, 255, 240, 0.8); box-shadow: 0 0 7px -2px #777;  ">
				<span style="vertical-align: middle; font-size: 200%; ">&#10003;</span>
				&nbsp;&nbsp;
				<span style="font-size: 125%; vertical-align: middle; ">'.$text.'</span>
			</div>'
		);

		nvweb_after_body(
			"js",
			'setTimeout(function() {
				document.getElementById("nv_webuser_verify_email_confirmed").style.opacity = 0;
				setTimeout(function()
				{
					document.getElementById("nv_webuser_verify_email_confirmed").style.display = "none";
				},
				1000);
			}, 8000);'
		);
	}

	if(isset($session['nv.webuser/verify:invalid_confirmation']))
	{
		unset($session['nv.webuser/verify:invalid_confirmation']);

		$text = $theme->t("invalid_confirmation");
		if(empty($text) || $text=="invalid_confirmation")
			$text = t(777, "Sorry, confirmation link is invalid or has expired.");

		nvweb_after_body(
			"html",
			'<div id="nv_webuser_verify_invalid_confirmation" style=" transition: all 1s; text-align: center; width: 40%; padding: 12px; margin: -48px 30% 0 30%; top: 50%; color: #333; position: fixed; z-index: 1000000; background: rgba(255, 240, 240, 0.8); box-shadow: 0 0 7px -2px #777;  ">
				<span style="vertical-align: middle; font-size: 200%; ">&#9888;</span>
				&nbsp;&nbsp;
				<span style="font-size: 125%; vertical-align: middle; ">'.$text.'</span>
			</div>'
		);

		nvweb_after_body(
			"js",
			'setTimeout(function() {
				document.getElementById("nv_webuser_verify_invalid_confirmation").style.opacity = 0;
				setTimeout(function()
				{
					document.getElementById("nv_webuser_verify_invalid_confirmation").style.display = "none";
				},
				1000);
			}, 8000);'
		);
	}

    if(isset($session['nv.comments/unsubscribe']))
    {
        unset($session['nv.comments/unsubscribe']);

        $text = $theme->t("unsubscribed_ok");
        if(empty($text) || $text=="unsubscribed_ok")
            $text = t(654, "Cancelled subscription");

        nvweb_after_body(
            "html",
            '<div id="nv_comments_subscription_cancelled_notice" style=" transition: all 1s; text-align: center; width: 40%; padding: 12px; margin: -48px 30% 0 30%; top: 50%; color: #333; position: fixed; z-index: 1000000; background: rgba(239, 228, 176, 0.8); box-shadow: 0 0 7px -2px #777;  ">
				<span style="vertical-align: middle; font-size: 200%; ">&#10003;</span>
				&nbsp;&nbsp;
				<span style="font-size: 125%; vertical-align: middle; ">'.$text.'</span>
			</div>'
        );

        nvweb_after_body(
            "js",
            'setTimeout(function() {
				document.getElementById("nv_comments_subscription_cancelled_notice").style.opacity = 0;
				setTimeout(function()
				{
					document.getElementById("nv_comments_subscription_cancelled_notice").style.display = "none";
				},
				1000);
			}, 8000);'
        );
    }

	return $html;
}


/**
 * Autoload a webget when needed, the source can be:
 * <ul>
 *  <li>navigate cms default webgets</li>
 *  <li>website private folder</li>
 *  <li>navigate cms plugins folder</li>
 *  <li>website theme folder</li>
 * </ul>
 * @param $webget_name string
 */
function nvweb_webget_load($webget_name)
{				
	global $website;

	$fname = 'nvweb_'.$webget_name;
	if(!function_exists($fname))
	{
		if(file_exists(NAVIGATE_PATH.'/lib/webgets/'.$webget_name.'.php'))
			@include_once(NAVIGATE_PATH.'/lib/webgets/'.$webget_name.'.php');
		else if(file_exists(NAVIGATE_PRIVATE.'/'.$website->id.'/webgets/'.$webget_name.'.php'))
			@include_once(NAVIGATE_PRIVATE.'/'.$website->id.'/webgets/'.$webget_name.'.php');
		else if(file_exists(NAVIGATE_PATH.'/plugins/'.$webget_name.'/'.$webget_name.'.php'))
			@include_once(NAVIGATE_PATH.'/plugins/'.$webget_name.'/'.$webget_name.'.php');
        else if(file_exists(NAVIGATE_PATH.'/themes/'.$website->theme.'/'.$website->theme.'.nvweb.php'))
            @include_once(NAVIGATE_PATH.'/themes/'.$website->theme.'/'.$website->theme.'.nvweb.php');
	}
}

/**
 * Alias of navigate_send_email
 *
 * @param $subject
 * @param $message
 * @param string|array $recipients e-mail address of the recipient or array of e-mail addresses
 * @param array $attachments
 * @return bool
 */
function nvweb_send_email($subject, $message, $recipients=array(), $attachments=array(), $quiet=false)
{	
	return navigate_send_email($subject, $message, $recipients, $attachments, $quiet);
}

?>