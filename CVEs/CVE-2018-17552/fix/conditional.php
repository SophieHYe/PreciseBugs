<?php
require_once(NAVIGATE_PATH.'/lib/webgets/menu.php');
require_once(NAVIGATE_PATH.'/lib/webgets/properties.php');
require_once(NAVIGATE_PATH.'/lib/webgets/content.php');
require_once(NAVIGATE_PATH.'/lib/webgets/gallery.php');
require_once(NAVIGATE_PATH.'/lib/webgets/votes.php');
require_once(NAVIGATE_PATH.'/lib/webgets/list.php');
require_once(NAVIGATE_PATH.'/lib/packages/products/product.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/feeds/feed_parser.class.php');

function nvweb_conditional($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $cache;
	global $structure;
	global $webgets;
    global $webuser;

	$out = array();

	$webget = 'conditional';

    $categories = array();

    $item = new item();

    if($current['type']=='item')
    {
        $item->load($current['object']->id);
        $item_type = 'element';
    }
    else if($current['type']=='product')
    {
        $item = new product();
        $item->load($current['object']->id);
        $item_type = 'product';
    }
    else
    {
        $item_type = 'structure';

        if(isset($vars['scope']) && $vars['scope'] == 'element')
        {
            // the current path belongs to a structure category, but the template is asking for an element value,
            // so we try to find the first element assigned to the current category
            $categories = array();
            if(!empty($current['object']->id))
                $categories = array($current['object']->id);

            if(isset($vars['categories']))
            {
                $categories = explode(',', $vars['categories']);
                $categories = array_filter($categories); // remove empty elements
            }

            $permission = (!empty($_SESSION['APP_USER#'.APP_UNIQUE])? 1 : 0);

            // public access / webuser based / webuser groups based
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
                        $access_groups[] = 's.groups LIKE "%g'.$wg.'%"';
                    }
                    if(!empty($access_groups))
                        $access_extra = ' OR (s.access = 3 AND ('.implode(' OR ', $access_groups).'))';
                }
            }

            // get order type: PARAMETER > NV TAG PROPERTY > DEFAULT (priority given in CMS)
            $order      = @$_REQUEST['order'];
            if(empty($order))
                $order  = @$vars['order'];
            if(empty($order))   // default order: latest
                $order = 'latest';

            $orderby = nvweb_list_get_orderby($order);

            $rs = NULL;

            $access_extra_items = str_replace('s.', 'i.', $access_extra);

            if(empty($categories))
            {
                // force executing the query; search in all categories
                $categories = nvweb_menu_get_children(array(0));
            }

            // default source for retrieving items
            $DB->query('
                SELECT SQL_CALC_FOUND_ROWS i.id, i.permission, i.date_published, i.date_unpublish,
                       i.date_to_display, COALESCE(NULLIF(i.date_to_display, 0), i.date_created) as pdate,
                       d.text as title, i.position as position
                  FROM nv_items i, nv_structure s, nv_webdictionary d
                 WHERE i.category IN(:categories)
                   AND i.website = :wid
                   AND i.permission <= '.$permission.'
                   AND (i.date_published = 0 OR i.date_published < :time)
                   AND (i.date_unpublish = 0 OR i.date_unpublish > :time)
                   AND s.id = i.category
                   AND (s.date_published = 0 OR s.date_published < :time)
                   AND (s.date_unpublish = 0 OR s.date_unpublish > :time)
                   AND s.permission <= '.$permission.'
                   AND (s.access = 0 OR s.access = '.$access.$access_extra.')
                   AND (i.access = 0 OR i.access = '.$access.$access_extra_items.')
                   AND d.website = i.website
                   AND d.node_type = "item"
                   AND d.subtype = "title"
                   AND d.node_id = i.id
                   AND d.lang = :lang
                 '.$orderby.'
                 LIMIT 1
                 OFFSET 0',
            'object',
                array(
                    ':wid' => $website->id,
                    ':lang' => $current['lang'],
                    ':categories' => implode(",", $categories),
                    ':time' => core_time()
                )
            );

            $rs = $DB->result();

            // now we have the element against which the condition will be checked
            $i = 0;

            $item->load($rs[$i]->id);
            $item_type = 'element';
        }
        else if(!isset($vars['scope']) || $vars['scope'] == 'structure')
        {
            $item = $current['object'];
            $item_type = 'structure';
        }
    }

    // get the template
    $item_html = $vars['_template'];

    // now, parse the conditional tags (with html source code inside)
    switch($vars['by'])
    {
        case 'property':
            $property_value = NULL;
            $property_name = $vars['property_name'];
            if(empty($vars['property_name']))
                $property_name = $vars['property_id'];

            if(in_array($vars['property_scope'], array("element", "product")))
            {
                $property_value = $item->property($property_name);
            }
            else if($vars['property_scope'] == "structure")
            {
                $property = nvweb_properties(array('mode' => 'structure', 'property' => $property_name, 'return' => 'object'));
                if(!empty($property))
                    $property_value = $property->value;
            }
            else if($vars['property_scope'] == "website")
            {
                $property_value = $website->theme_options->{$property_name};
            }
            else
            {
                // no scope defined, so we have to check  PRODUCT / ELEMENT > STRUCTURE > WEBSITE (the first with a property with the given name)
                // element
                $property_value = $item->property($property_name);

                if(!$item->property_exists($property_name) && $item_type == 'structure')
                {
                    // get the first embedded element and check find the property
                    $ci = nvweb_content_items(array($item->id), true, 1, true, 'priority');

                    $item = new item();
                    if(isset($ci[0]))
                    {
                        $item->load($ci[0]->id);
                        $property_value = $item->property($property_name);
                    }
                }

                if(!$item->property_exists($property_name))
                {
                    // structure
                    $property = nvweb_properties(array('mode' => 'structure', 'property' => $property_name, 'return' => 'object'));
                    if(!empty($property))
                        $property_value = $property->value;
                    else
                    {
                        // website
                        if(isset($website->theme_options->{$property_name}))
                            $property_value = $website->theme_options->{$property_name};
                        else
                            $property_value = '';
                    }
                }
            }

            // if the property is multilanguage, get the value for the current language
            if(is_array($property_value))
                $property_value = $property_value[$current['lang']];

            // check the given condition
            if(isset($vars['empty']) || isset($vars['property_empty']))
            {
                if(@$vars['empty']=='true' || @$vars['property_empty']=='true')
                {
                    if(empty($property_value))
                        $out = $item_html;
                    else
                        $out = '';
                }
                else if(@$vars['empty']=='false' || @$vars['property_empty']=='false')
                {
                    if(!empty($property_value))
                        $out = $item_html;
                    else
                        $out = '';
                }
            }
            else if(isset($vars['property_value']))
            {
                $condition_value = $vars['property_value'];

                switch($vars['property_compare'])
                {
                    case '>':
                    case 'gt':
                        $condition = ($property_value > $condition_value);
                        break;

                    case '<':
                    case 'lt':
                        $condition = ($property_value < $condition_value);
                        break;

                    case '>=':
                    case '=>':
                    case 'gte':
                        $condition = ($property_value >= $condition_value);
                        break;

                    case '<=':
                    case '=<':
                    case 'lte':
                        $condition = ($property_value <= $condition_value);
                        break;

                    case 'in':
                        $condition_values = explode(",", $condition_value);
                        $condition = in_array($property_value, $condition_values);
                        break;

                    case 'nin':
                        $condition_values = explode(",", $condition_value);
                        $condition = !in_array($property_value, $condition_values);
                        break;

                    case '!=':
                    case 'neq':
                        if(is_numeric($property_value))
                        {
                            if($condition_value == 'true' || $condition_value===true)
                                $condition_value = '1';
                            else if($condition_value == 'false' || $condition_value===false)
                                $condition_value = '0';
                        }

                        $condition = ($property_value != $condition_value);
                        break;

                    case '=':
                    case '==':
                    case 'eq':
                    default:
                        if(is_numeric($property_value))
                        {
                            if($condition_value == 'true' || $condition_value===true)
                                $condition_value = '1';
                            else if($condition_value == 'false' || $condition_value===false)
                                $condition_value = '0';
                        }

                        $condition = ($property_value == $condition_value);
                        break;
                }

                if($condition)
                    $out = $item_html;
                else
                    $out = '';
            }
            break;

        case 'template':
        case 'templates':
            $templates = array();
            if(isset($vars['templates']))
                $templates = explode(",", $vars['templates']);
            else if(isset($vars['template']))
                $templates = array($vars['template']);

            if(in_array($item->template, $templates))
                $out = $item_html;
            else
                $out = '';
            break;

        case 'section':
            $section_empty = empty($item->dictionary[$current['lang']]['section-'.$vars['section']]);
            if(
                $vars['empty']=='true' && $section_empty    ||
                $vars['empty']=='false' && !$section_empty
            )
            {
                $out = $item_html;
            }
            else
            {
                $out = '';
            }
            break;

        case 'access':
            $access = 0;

            switch($vars['access'])
            {
                case 'navigate_user':
                    if(!empty($_SESSION['APP_USER#'.APP_UNIQUE]))
                    {
                        $access = 0; // everybody
                        // only for a certain user?
                        if(isset($vars['user']) && $vars['user'] != $_SESSION['APP_USER#'.APP_UNIQUE])
                            $access = -1;
                    }
                    else
                    {
                        $access = -1; // nobody!
                    }
                    break;

                case 3:
                case 'webuser_groups':
                    $access = 3;
                    break;

                case 2:
                case 'not_signed_in':
                    $access = 2;
                    break;

                case 1:
                case 'signed_in':
                    $access = 1;
                    break;

                case 0:
                case 'everyone':
                default:
                    $access = 0;
                    break;
            }

            if($item->access == $access)
                $out = $item_html;
            else
                $out = '';

            break;

        case 'webuser':
            if($vars['signed_in']=='true' && !empty($webuser->id))
                $out = $item_html;
            else if($vars['signed_in']=='false' && empty($webuser->id))
                $out = $item_html;
            else
                $out = '';

            break;

        case 'languages':
            if(count($website->languages_published) >= $vars['min'])
            {
                $out = $item_html;
            }
            else if(count($website->languages_published) <= $vars['max'])
            {
                $out = $item_html;
            }
            break;

        case 'language':
            if($current['lang'] == $vars['lang'])
            {
                $out = $item_html;
            }
            break;

        case 'gallery':
            if($vars['empty']=='true')
            {
                if(empty($item->galleries[0]))
                    $out = $item_html;
            }
            else if($vars['empty']=='false')
            {
                if(!empty($item->galleries[0]))
                    $out = $item_html;
            }
            else if(isset($vars['min']) && (count($item->galleries[0]) >= intval($vars['min'])))
            {
                $out = $item_html;
            }
            else if(isset($vars['max']) && (count($item->galleries[0]) <= intval($vars['max'])))
            {
                $out = $item_html;
            }
            break;

        case 'tags':
            if($vars['empty']=='true')
            {
                if(empty($item->dictionary[$current['lang']]['tags']))
                    $out = $item_html;
            }
            else if($vars['empty']=='false')
            {
                if(!empty($item->dictionary[$current['lang']]['tags']))
                    $out = $item_html;
            }
            break;

        case 'comments':
            $DB->query('
                SELECT COUNT(*) as total
				  FROM nv_comments
				 WHERE website = :wid
				   AND object_id = :item_id
				   AND object_type = "item"
				   AND status = 0',
                'object',
                array(
                    ':wid' => $website->id,
                    ':item_id' => $item->id
                )
            );
            $rs = $DB->result();
            $comments_count = intval($rs[0]->total) + 0;

            if(isset($vars['allowed']))
            {
                if($vars['allowed']=='true' || $vars['allowed']=='1' || empty($vars['allowed']))
                {
                    // comments allowed to everybody (2) or to registered users only (1)
                    if( $item->comments_enabled_to == 2 ||
                        ( $item->comments_enabled_to == 1 && !empty($webuser->id)))
                        $out = $item_html;
                }
                else if($vars['allowed']=='false')
                {
                    // comments not allowed for anyone or for webusers but there is no webuser active right now
                    if( $item->comments_enabled_to == 0 ||
                        ( $item->comments_enabled_to == 1 && empty($webuser->id)))
                        $out = $item_html;
                }
            }
            else if(isset($vars['min']) && ($comments_count >= intval($vars['min'])))
            {
                $out = $item_html;
            }
            else if(isset($vars['max']) && ($comments_count <= intval($vars['max'])))
            {
                $out = $item_html;
            }
            break;

        case 'product':
            if(isset($vars['offer']))
            {
                $on_offer = $item->on_offer();
                if(($vars['offer']=='true' || $vars['offer']=='1') && $on_offer)
                    $out = $item_html;
                else if(($vars['offer']=='false' || $vars['offer']=='0') && !$on_offer)
                    $out = $item_html;
                else
                    $out = '';
            }

            if(isset($vars['top']))
            {
                $is_top = $item->is_top(@$vars['top_limit']);
                if(($vars['top']=='true' || $vars['top']=='1') && $is_top)
                    $out = $item_html;
                else if(($vars['top']=='false' || $vars['top']=='0') && !$is_top)
                    $out = $item_html;
                else
                    $out = '';
            }

            if(isset($vars['new']))
            {
                $is_new = $item->is_new(@$vars['since']);
                if(($vars['new']=='true' || $vars['new']=='1') && $is_new)
                    $out = $item_html;
                else if(($vars['new']=='false' || $vars['new']=='0') && !$is_new)
                    $out = $item_html;
                else
                    $out = '';
            }
            break;

        default:
            // unknown nvlist_conditional, discard
            $out = '';
    }

    // return the new html code after applying the condition
	return $out;
}

?>