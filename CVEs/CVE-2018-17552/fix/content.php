<?php
require_once(NAVIGATE_PATH.'/lib/external/force-utf8/Encoding.php');

nvweb_webget_load("menu");
nvweb_webget_load("list");

function nvweb_content($vars=array())
{
	global $website;
	global $current;
	global $template;
	global $structure;
	
	$out = '';	
	switch(@$vars['mode'])
	{
        case 'id':
            $out = $current['object']->id;
            break;

		case 'title':
			if($current['type']=='structure')
			{
                $rs = nvweb_content_items($current['object']->id, true, 1);
				$texts = webdictionary::load_element_strings('item', $rs[0]->id);
				$out = $texts[$current['lang']]['title'];				
			}
			else
			{				
				$texts = webdictionary::load_element_strings($current['type'], $current['object']->id);				
				$out = $texts[$current['lang']]['title'];
			}
				
			if(!empty($vars['function']))	eval('$out = '.$vars['function'].'("'.$out.'");');
			break;

        case 'date':
		case 'date_post':
            $ts = $current['object']->date_to_display;
            // if no date, return nothing
            if(!empty($ts))
    			$out = nvweb_content_date_format(@$vars['format'], $ts);
			break;
			
		case 'date_created':
			$ts = $current['object']->date_created;
			$out = $vars['format'];
			$out = nvweb_content_date_format($out, $ts);			
			break;
			
		case 'comments':
			// display published comments number for the current item
			$out = nvweb_content_comments_count();
			break;

        case 'views':
            $out = $current['object']->views;
            break;
			
		case 'summary':
            $length = 300;
            $allowed_tags = array();
            if(!empty($vars['length']))
                $length = intval($vars['length']);
			$texts = webdictionary::load_element_strings('item', $current['object']->id);				
			$text = $texts[$current['lang']]['main'];
            if(!empty($vars['allowed_tags']))
                $allowed_tags = explode(',', $vars['allowed_tags']);
			$out = core_string_cut($text, $length, '&hellip;', $allowed_tags);
			break;

        case 'author':
            if(!empty($current['object']->author))
            {
                $nu = new user();
                $nu->load($current['object']->author);
                $out = $nu->username;
                unset($nu);
            }

            if(empty($out))
                $out = $website->name;
            break;
			
		case 'structure':
			// force loading structure data
			nvweb_menu();

            $structure_id = 0;
            if($current['type']=='item')
                $structure_id = $current['object']->category;
            else if($current['type']=='structure')
                $structure_id = $current['object']->id;

			switch($vars['return'])
			{
				case 'path':
					$out = $structure['routes'][$structure_id];
					break;
					
				case 'title':
					$out = $structure['dictionary'][$structure_id];
					break;
					
				case 'action':
					$out = nvweb_menu_action($structure_id);
					break;
					
				default:
			}
			break;

        case 'tags':
            $tags = array();

            $search_url = nvweb_source_url('theme', 'search');
            if(!empty($search_url))
                $search_url .= '?q=';
            else
                $search_url = NVWEB_ABSOLUTE.'/nvtags?q=';

            $ids = array();
            if(empty($vars['separator']))
                $vars['separator'] = ' ';

            $class = 'item-tag';
            if(!empty($vars['class']))
                $class = $vars['class'];

            if(!empty($vars['id']))
            {
                $object_type = value_or_default($vars['object_type'], "item");
                if($object_type == "product")
                    $itm = new product();
                else
                    $itm = new item();

                $itm->load($vars['id']);
                $enabled = nvweb_object_enabled($itm);
                if($enabled)
                {
                    $texts = webdictionary::load_element_strings($object_type, $itm->id);
                    $itags = explode(',', $texts[$current['lang']]['tags']);
                    if(!empty($itags))
                    {
                        for($i=0; $i < count($itags); $i++)
                        {
                            if(empty($itags[$i])) continue;
                            $tags[$i] = '<a class="'.$class.'" href="'.$search_url.$itags[$i].'">'.$itags[$i].'</a>';
                        }
                    }
                }
            }
            else if($current['type']=='item')
            {
                // check publishing is enabled
                $enabled = nvweb_object_enabled($current['object']);

                if($enabled)
                {
                    $texts = webdictionary::load_element_strings('item', $current['object']->id);
                    $itags = explode(',', $texts[$current['lang']]['tags']);
                    if(!empty($itags))
                    {
                        for($i=0; $i < count($itags); $i++)
                        {
                            if(empty($itags[$i])) continue;
                            $tags[$i] = '<a class="'.$class.'" href="'.$search_url.$itags[$i].'">'.$itags[$i].'</a>';
                        }
                    }
                }
            }
            else if($current['type']=='product')
            {
                // check publishing is enabled
                $enabled = nvweb_object_enabled($current['object']);

                if($enabled)
                {
                    $texts = webdictionary::load_element_strings('product', $current['object']->id);
                    $itags = explode(',', $texts[$current['lang']]['tags']);
                    if(!empty($itags))
                    {
                        for($i=0; $i < count($itags); $i++)
                        {
                            if(empty($itags[$i])) continue;
                            $tags[$i] = '<a class="'.$class.'" href="'.$search_url.$itags[$i].'">'.$itags[$i].'</a>';
                        }
                    }
                }
            }
            else if($current['type']=='structure')
            {
                $rs = nvweb_content_items($current['object']->id);

                foreach($rs as $category_item)
                {
                    $enabled = nvweb_object_enabled($category_item);

                    if($enabled)
                    {
                        $texts = webdictionary::load_element_strings('item', $current['object']->id);
                        $itags = explode(',', $texts[$current['lang']]['tags']);
                        if(!empty($itags))
                        {
                            for($i=0; $i < count($itags); $i++)
                            {
                                $tags[$i] = '<a class="'.$class.'" href="'.$search_url.$itags[$i].'">'.$itags[$i].'</a>';
                            }
                        }
                    }
                }
            }
            $out = implode($vars['separator'], $tags);
            break;
		
		case 'section':
		case 'body':
		default:
			if(empty($vars['section'])) $vars['section'] = 'main';
			$section = "section-".$vars['section'];

			if($current['type']=='item')
			{
				// check publishing is enabled
				$enabled = nvweb_object_enabled($current['object']);
                $texts = NULL;

                // retrieve last saved text (is a preview request from navigate)
				if($_REQUEST['preview']=='true' && $current['navigate_session']==1)
					$texts = webdictionary_history::load_element_strings('item', $current['object']->id, 'latest');
                // or last approved/saved text
				else if($enabled)
					$texts = webdictionary::load_element_strings('item', $current['object']->id);

                // have we found any content?
                if(!empty($texts) && !empty($template->sections))
                {
                    foreach($template->sections as $tsection)
                    {
                        if($tsection['id'] == $vars['section'] || $tsection['code'] == $vars['section'])
                        {
                            switch($tsection['editor'])
                            {
                                case 'raw':
                                    $out = nl2br($texts[$current['lang']][$section]);
                                    break;

                                case 'html':
                                case 'tinymce':
                                default:
                                    $out = $texts[$current['lang']][$section];
                                    break;
                            }
                            break;
                        }
                    }
                }
			}
			else if($current['type']=='structure')
			{
                $rs = nvweb_content_items($current['object']->id);

				foreach($rs as $category_item)
				{
					$enabled = nvweb_object_enabled($category_item);

					if(!$enabled)
						continue;
					else
					{
						$texts = webdictionary::load_element_strings('item', $category_item->id);

                        foreach($template->sections as $tsection)
                        {
                            if($tsection['id'] == $vars['section'] || $tsection['code'] == $vars['section'])
                            {
                                switch($tsection['editor'])
                                {
                                    case 'raw':
                                        $texts[$current['lang']][$section] = nl2br($texts[$current['lang']][$section]);
                                        break;
                                    case 'html':
                                    case 'tinymce':
                                    default:
                                        // we don't need to change a thing
                                        // $texts[$current['lang']][$section] = $texts[$current['lang']][$section];
                                        break;
                                }
                                break;
                            }
                        }

						$out .= '<div id="navigate-content-'.$category_item->id.'-'.$section.'">'.$texts[$current['lang']][$section].'</div>';
					}
				}
			}

			break;
	}

	return $out;
}

function nvweb_content_comments_count($object_id = NULL, $object_type = "item")
{
	global $DB;
	global $website;
	global $current;

    $element = $current['object'];
    if($current['type']=='structure' && $object_type == "item")
        $element = $element->elements(0); // item = structure->elements(first)

	if(empty($object_id))
		$object_id = $element->id;

	$DB->query('SELECT COUNT(*) as total
				  FROM nv_comments
				 WHERE website = '.intval($website->id).'
				   AND object_type = "'.$object_type.'"
				   AND object_id = '.intval($object_id).'
				   AND status = 0'
				);
													
	$out = $DB->result('total');
	
	return $out[0];
}

function nvweb_content_date_format($format="", $ts)
{
    global $website;
    global $session;

    $out = '';

    setlocale(LC_ALL, $website->languages[$session['lang']]['system_locale']);

    if(empty($format))
        $out = date($website->date_format, $ts);
    else if(strpos($format, '%day')!==false || strpos($format, '%month')!==false || strpos($format, '%year4'))
    {
        // deprecated: used until Navigate CMS 1.6.7; to be removed in Navigate CMS 2.0
        $out = str_replace('%br', '<br />', $format);
        $out = str_replace('%day', date("d", $ts), $out);
        $out = str_replace('%month_abr', Encoding::toUTF8(strtoupper(strftime("%b", $ts))), $out);
        $out = str_replace('%month', date("m", $ts), $out);
        $out = str_replace('%year4', date("Y", $ts), $out);
    }
    else
    {
        if(!empty($ts))
            $out = Encoding::toUTF8(strftime($format, intval($ts)));
    }

	return $out;
}

function nvweb_content_items($categories=array(), $only_published=false, $max=NULL, $embedding=true, $order='date')
{
    global $website;
    global $DB;
    global $current;
    global $webuser;

    if(!is_array($categories))
        $categories = array(intval($categories));

    if($categories[0] == NULL)
        $categories = array(0);

    $where = ' i.website = '.$website->id.'
               AND i.category IN ('.implode(",", $categories).')
               AND i.embedding = '.($embedding? '1' : '0');

    if($only_published)
        $where .= ' AND (i.date_published = 0 OR i.date_published < '.core_time().')
                    AND (i.date_unpublish = 0 OR i.date_unpublish > '.core_time().')';

    // status (0 public, 1 private (navigate cms users), 2 hidden)
    $permission = (!empty($_SESSION['APP_USER#'.APP_UNIQUE])? 1 : 0);
    $where .= ' AND i.permission <= '.$permission;

    // access permission (0 public, 1 web users only, 2 unidentified users, 3 selected web user groups)
    $access = 2;
    $access_extra = '';
    if(!empty($current['webuser']))
    {
        $access = 1;
        if(!empty($webuser->groups))
        {
            $access_groups = array();
            foreach($webuser->groups as $wg)
            {
                if(empty($wg))
                    continue;
                $access_groups[] = 'i.groups LIKE "%g'.$wg.'%"';
            }
            if(!empty($access_groups))
                $access_extra = ' OR (i.access = 3 AND ('.implode(' OR ', $access_groups).'))';
        }
    }

    $where .= ' AND (i.access = 0 OR i.access = '.$access.$access_extra.')';

    if(!empty($max))
        $limit = 'LIMIT '.$max;

    $orderby = nvweb_list_get_orderby($order);
	$orderby = str_replace(", IFNULL(s.position, 0) ASC", "", $orderby); // remove s. order used exclusively at nvweb_list

    $DB->query('
        SELECT i.*, COALESCE(NULLIF(i.date_to_display, 0), i.date_created) as pdate, d.text as title
        FROM nv_items i
         LEFT JOIN nv_webdictionary d ON
         	   d.website = i.website
			   AND d.node_type = "item"
			   AND d.subtype = "title"
			   AND d.node_id = i.id
			   AND d.lang = :lang
        WHERE '.$where.'
        '.$orderby.'
        '.$limit,
        'object',
        array(
            ':lang' => $current['lang']
        )
    );

    $rs = $DB->result();

    return $rs;
}

?>