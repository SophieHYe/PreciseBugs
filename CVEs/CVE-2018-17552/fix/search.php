<?php
require_once(NAVIGATE_PATH.'/lib/webgets/list.php');
require_once(NAVIGATE_PATH.'/lib/webgets/menu.php');

// DEPRECATED webget, ** please use nv list instead **

function nvweb_search($vars=array())
{
	global $website;
    global $webuser;
	global $DB;
	global $current;
	global $cache;
	global $structure;
    global $theme;

	$out = array();

	$search_what = $_REQUEST[$vars['request']];
    $search_archive = array();

    // COMPATIBILITY LAYER (to run nv object="search" on old themes)
    if(empty($_REQUEST['archive']) && empty($vars['no_results_found']))
    {
        // redirect this query to nv list webget, as it does not use any nv object="search" special attributes
        $options = array_merge(
            $vars,
            array(
                'source' => 'item',
                'search' => '$'.$vars['request'],
                'request' => ''
            )
        );
        $out = nvweb_list($options);
        return $out;
    }

    if(!empty($_REQUEST['archive']))
        $search_archive = explode("-", $_REQUEST['archive']);  // YEAR, MONTH, CATEGORIES (separated by commas)

	if(isset($_REQUEST[$vars['request']]) || (!empty($search_archive[0]) && !empty($search_archive[1])))
	{
        // ignore searches requested by a navigate cms user or explicitly ignored by the template
        if(!isset($_SESSION['APP_USER#'.APP_UNIQUE]) && @!($vars['log']=='false'))
        {
            // LOG search request
            $DB->execute('
                INSERT INTO nv_search_log
                  (id, website, date, webuser, origin, text, request)
                VALUES
                  (0, :website, :date, :webuser, :origin, :text, :request)
            ', array(
                'website' => $website->id,
                'date'    => time(),
                'webuser' => value_or_default($webuser->id, 0),
                'origin'  => (empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER']),
                'text'    => $search_what,
                'request' => json_encode(array_merge($_GET, $_POST))
            ));
        }

        // prepare and execute the search
		$search_what = explode(' ', $search_what);
		$search_what = array_filter($search_what);

        if(empty($search_what))
           $search_what = array();

		$likes = array();
        $likes[] = ' 1=1 ';

		foreach($search_what as $what)
        {
            if(substr($what, 0, 1)=='-')
            {
                $likes[] = 'd.text NOT LIKE '.protect('%'.substr($what, 1).'%').
                            '    AND i.id NOT IN( 
                                            SELECT p.node_id
                                             FROM   nv_properties_items p 
                                             WHERE  p.element = "item" AND 
                                                    p.website = '.intval($website->id).' AND
                                                    p.value LIKE '.protect('%'.substr($what, 1).'%').'
                                        )';
            }
            else
            {
			    $likes[] = 'd.text LIKE '.protect('%'.$what.'%').
			               '    OR i.id IN( 
			                        SELECT  p.node_id
			                         FROM   nv_properties_items p 
			                         WHERE  p.element = "item" AND 
			                                p.website = '.intval($website->id).' AND
			                                p.value LIKE '.protect('%'.$what.'%').'
                                )';
            }
        }

        if(!empty($search_archive)) // add the conditions
        {
            $start_date = gmmktime(0, 0, 0, $search_archive[1], 1, $search_archive[0]);
            $end_date   = gmmktime(0, 0, 0, $search_archive[1]+1, 1, $search_archive[0]);

            $likes[] = ' (i.date_to_display >= '.$start_date.')';
            $likes[] = ' (i.date_to_display <= '.$end_date.')';
        }

        if(!empty($search_archive[2]))
            $vars['categories'] = $search_archive[2];

        $categories = NULL;

		if(isset($vars['categories']))
		{
	        if($vars['categories']=='all')
	        {
	            $categories = array(0);
	            $vars['children'] = 'true';
	        }
	        else if($vars['categories']=='parent')
	        {
				$categories = array($current['object']->id);
	            $parent = $DB->query_single('parent', 'nv_structure', 'id = '.intval($categories[0]));
	            $categories = array($parent);
	        }
	        else if($vars['categories']=='nvlist_parent')
	        {
	            if($vars['nvlist_parent_type'] === 'structure')
	            {
	                $categories = array($vars['nvlist_parent_item']->id);
	            }
	        }
	        else if(!is_numeric($vars['categories']))
	        {
	            // if "categories" attribute has a comma, then we suppose it is a list of comma separated values
	            // if not, then maybe we want to get the categories from a specific property of the current page
	            if(strpos($vars['categories'], ',')===false)
	            {
	                $categories = nvweb_properties(array(
	                    'property'	=> 	$vars['categories']
	                ));
	            }

	            if(empty($categories) && (@$vars['nvlist_parent_vars']['source'] == 'block_group'))
	            {
	                $categories = nvweb_properties(array(
	                    'mode'	=>	'block_group_block',
	                    'property' => $vars['categories']
	                ));
	            }

	            if(empty($categories))
	                $categories = $vars['categories'];

	            if(!is_array($categories))
	            {
	                $categories = explode(',', $categories);
	                $categories = array_filter($categories); // remove empty elements
	            }
	        }
	        else
	        {
	            $categories = explode(',', $vars['categories']);
	            $categories = array_filter($categories); // remove empty elements
	        }
		}

		if($vars['children']=='true')
			$categories = nvweb_menu_get_children($categories);

		// if we have categories="x" children="true" [to get the children of a category, but not itself]
		if($vars['children']=='only')
		{
			$children = nvweb_menu_get_children($categories);
			for($c=0; $c < count($categories); $c++)
				array_shift($children);
			$categories = $children;
		}

		if(!empty($vars['children']) && intval($vars['children']) > 0)
		{
			$children = nvweb_menu_get_children($categories, intval($vars['children']));

			for($c=0; $c < count($categories); $c++)
				array_shift($children);
			$categories = $children;
		}

		// apply a filter on categories, if given
		// example: request_categories="c" ... in the url &q=text&c=23,35
		if(!empty($vars['request_categories']))
		{
			$categories_filter = explode(",", $_REQUEST[$vars['request_categories']]);
			if(empty($categories))
			{
				// note: categories may be empty by the rules applies on categories + children;
				// in this case we give preference to the request_categories filter
				$categories = array_values($categories_filter);
			}
			else
			{
				for($cf=0; $cf < count($categories_filter); $cf++)
				{
					if(!in_array($categories_filter[$cf], $categories))
					{
						unset($categories_filter[$cf]);
					}
					$categories_filter = array_filter($categories_filter);
				}
				$categories = $categories_filter;
			}
		}
		
		// retrieve entries
		$permission = (!empty($_SESSION['APP_USER#'.APP_UNIQUE])? 1 : 0);
        $access     = (!empty($current['webuser'])? 1 : 2);

		if(empty($_GET['page'])) $_GET['page'] = 1;
		$offset = intval($_GET['page'] - 1) * $vars['items'];

        // get order type: PARAMETER > NV TAG PROPERTY > DEFAULT (priority given in CMS)
        $order      = @$_REQUEST['order'];
        if(empty($order))
            $order  = @$vars['order'];
        if(empty($order))   // default order: latest
            $order = 'latest';

        $orderby = nvweb_list_get_orderby($order);
        // we can't use the title alias in search; in fact it does not make sense, as the search
        // finds text in content, tags, titles, etc.
        $orderby = str_replace(" title ", " wd.text ", $orderby);

        if(empty($vars['items']) || $vars['items']=='0')
        {
            $vars['items'] = 500; //2147483647; // maximum integer
            // NOTE: having >500 items on a page without a paginator is probably a bad idea... disagree? Contact Navigate CMS team!
        }
        else if(!is_numeric($vars['items']))
        {
            $max_items = "";

            // the number of items is defined by a property
            $max_items = nvweb_properties(array(
                'property'	=> 	$vars['items']
            ));

            if(empty($max_items) && (@$vars['nvlist_parent_vars']['source'] == 'block_group'))
            {
                $max_items = nvweb_properties(array(
                    'mode'	    =>	'block_group_block',
                    'property'  => $vars['items'],
                    'id'        =>	$vars['nvlist_parent_item']->id,
                    'uid'       => $vars['nvlist_parent_item']->uid
                ));
            }

            if(!empty($max_items))
                $vars['items'] = $max_items;
            else
                $vars['items'] = 500; // default maximum
        }

        // this query is not reliable and will be removed in the future,
        // please use nv list with the attribute search="$url_parameter_name"
        $query = '
            SELECT SQL_CALC_FOUND_ROWS rs.id
            FROM (
                SELECT DISTINCT(i.id) AS id, COALESCE(NULLIF(i.date_to_display, 0), i.date_created) as pdate
                  FROM nv_items i, nv_webdictionary d
                  LEFT JOIN nv_webdictionary wd
                    ON wd.node_id = d.node_id
                   AND wd.lang =  ' . protect($current['lang']) . '
                   AND wd.node_type = "item"
                   AND wd.website = ' . intval($website->id) . '			  
                 WHERE i.website = ' . $website->id . '
                   AND i.permission <= ' . $permission . '
                   AND (i.date_published = 0 OR i.date_published < ' . core_time() . ')
                   AND (i.date_unpublish = 0 OR i.date_unpublish > ' . core_time() . ')
                   AND (i.access = 0 OR i.access = ' . $access . ')
                   AND d.website = ' . intval($website->id) . '
                   AND d.node_id = i.id
                   AND d.lang =  ' . protect($current['lang']) . '
                   AND (d.node_type = "item" OR d.node_type = "tags")
                   AND (
                    ' . implode(' AND ', $likes) . '
                   )
                   ' . (empty($categories) ? '' : 'AND category IN(' . implode(",", $categories) . ')') . '
                 ' . $orderby . '
             ) rs
			 LIMIT '.$vars['items'].'
			OFFSET '.$offset;

		$DB->query($query);

		$rs = $DB->result();
		$total = $DB->foundRows();

		for($i = 0; $i < count($rs); $i++)
		{
			if(empty($rs[$i]->id)) break;
			$item = new item();
			$item->load($rs[$i]->id);

            // get the nv list template
            $item_html = $vars['template'];

            // now, parse the nvlist_conditional tags (with html source code inside (and other nvlist tags))
            unset($nested_condition_fragments);
            list($item_html, $nested_conditional_fragments) = nvweb_list_isolate_conditionals($item_html);

            $conditional_placeholder_tags = nvweb_tags_extract($item_html, 'nvlist_conditional_placeholder', true, true, 'UTF-8'); // selfclosing = true

            while(!empty($conditional_placeholder_tags))
            {
                $tag = $conditional_placeholder_tags[0];
                $conditional = $nested_conditional_fragments[$tag["attributes"]["id"]];

                $conditional_html_output = nvweb_list_parse_conditional(
                    $conditional,
                    $item,
                    $conditional['nvlist_conditional_template'],
                    $i,
                    count($rs)
                );


                $item_html = str_replace(
                    $tag["full_tag"],
                    $conditional_html_output,
                    $item_html
                );

                $conditional_placeholder_tags = nvweb_tags_extract($item_html, 'nvlist_conditional_placeholder', true, true, 'UTF-8'); // selfclosing = true
            }

            // now parse the (remaining) common nvlist tags
            $template_tags = nvweb_tags_extract($item_html, 'nvlist', true, true, 'UTF-8'); // selfclosing = true
		
			if(empty($item_html)) // apply a default template if no one is defined
			{		
				$item_html = array();
				$item_html[] = '<div class="search-result-item">';
				$item_html[] = '	<div class="search-result-title"><a href="'.$website->absolute_path().$item->paths[$current['lang']].'">'.$item->dictionary[$current['lang']]['title'].'</a></div>';	
				$item_html[] = '	<div class="search-result-summary">'.core_string_cut($item->dictionary[$current['lang']]['section-main'], 300, '&hellip;').'</div>';
				$item_html[] = '</div>';
				
				$item_html = implode("\n", $item_html);
				
				$out[] = $item_html;					
			}
			else
			{
				// parse special template tags
				foreach($template_tags as $tag)
				{
					$content = nvweb_list_parse_tag($tag, $item, $vars['source'], $i, ($i+$offset), $total);
					$item_html = str_replace($tag['full_tag'], $content, $item_html);	
				}
				
				$out[] = $item_html;
			}
		}

		if($total==0)
        {
            $search_results_empty_text = $theme->t("no_results_found");

            if(isset($vars['no_results_found']))
                $search_results_empty_text = $theme->t($vars["no_results_found"]);

            if(empty($search_results_empty_text) || $search_results_empty_text == 'no_results_found')
                $search_results_empty_text = t(645, "No results found");

            // display the error message only if
            //  1) it's not empty
            //  2) the template is preventing the display of any error message in the search ( no_results_found="" )
            if( !empty($search_results_empty_text) &&
                (   !isset($vars['no_results_found']) || ( isset($vars['no_results_found']) && !empty($vars['no_results_found'])) )
            )
            {
                $out[] = '<div class="search-results-empty">';
                $out[] =    $search_results_empty_text;
                $out[] = '</div>';
            }
        }

        $archive = $_REQUEST['archive'];
        if(!empty($archive))
           $archive = 'archive='.$archive.'&';

		if(isset($vars['paginator']) && $vars['paginator']!='false')
		{
			$search_url = '?'.$archive.$vars['request'].'='.$_REQUEST[$vars['request']].'&page=';
			$out[] = nvweb_list_paginator($vars['paginator'], $_GET['page'], $total, $vars['items'], $vars, $search_url);
		}
	}
	
	return implode("\n", $out);
}

?>