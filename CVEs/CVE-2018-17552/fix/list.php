<?php
require_once(NAVIGATE_PATH.'/lib/webgets/menu.php');
require_once(NAVIGATE_PATH.'/lib/webgets/properties.php');
require_once(NAVIGATE_PATH.'/lib/webgets/content.php');
require_once(NAVIGATE_PATH.'/lib/webgets/gallery.php');
require_once(NAVIGATE_PATH.'/lib/webgets/votes.php');
require_once(NAVIGATE_PATH.'/lib/webgets/blocks.php');
require_once(NAVIGATE_PATH.'/lib/webgets/product.php');
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/feeds/feed_parser.class.php');

function nvweb_list($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $cache;
	global $structure;
	global $webgets;
    global $theme;
    global $webuser;
    global $session;

	$out = array();

	$webget = 'list';
    $categories = array();
    $exclude = '';

    if(in_array($current['type'], array('item', 'product')))
	    $categories = array($current['object']->category);
    else if($current['type'] == 'structure')
        $categories = array($current['object']->id);

	if(isset($vars['categories']))
	{
        if($vars['categories']=='all')
        {
            $categories = array(0);
            $vars['children'] = 'true';
        }
        else if($vars['categories']=='parent')
        {
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
            // if not, then maybe we want to get the categories from a request parameter or from a specific property of the current page
            if(strpos($vars['categories'], '$')===0)
            {
                $categories = explode(",", $_REQUEST[substr($vars['categories'], 1)]);

                // if categories parameter is empty, then default to the root category
                if(empty($categories) || empty($categories[0]))
                    $categories = array(0);
            }
            else if(strpos($vars['categories'], ',')===false)
            {
                $categories = nvweb_properties(array(
                    'property'	=> 	$vars['categories']
                ));
            }

            if(empty($categories) && (@$vars['nvlist_parent_vars']['source'] == 'block_group'))
            {
                $categories = nvweb_properties(array(
                    'mode'     => 'block_group_block',
                    'property' => $vars['categories'],
                    'id'       => $vars['nvlist_parent_item']->id,
                    'uid'      => $vars['nvlist_parent_item']->uid
                ));
            }

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

	if(empty($vars['items']) || $vars['items']=='0')
	{
        $vars['items'] = 5000; //2147483647; // maximum integer
        // NOTE: having >5000 items on a page without a paginator is probably a bad idea... disagree? Contact Navigate CMS team!
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

    if(!empty($vars['exclude']))
    {
        $exclude = str_replace('current', $current['object']->id, $vars['exclude']);
        $exclude = explode(',', $exclude);
        $exclude = array_filter($exclude);

        if(!empty($exclude))
        {
            if($vars['source']=='structure' || $vars['source']=='category')
                $exclude = 'AND s.id NOT IN('.implode(',', $exclude).')';
            else if($vars['source']=='product')
                $exclude = 'AND p.id NOT IN('.implode(',', $exclude).')';
            else // item
                $exclude = 'AND i.id NOT IN('.implode(',', $exclude).')';
        }
        else
            $exclude = '';
    }

    // search parameter (for now only ELEMENTS and PRODUCTS!)
    $search = '';
    if(!empty($vars['search']))
    {
        // prepare and execute the search
        $search_what = $vars['search'];

        if(substr($vars['search'], 0, 1)=='$')
            $search_what = $_REQUEST[substr($vars['search'], 1)];

        if(!empty($search_what) && !isset($_SESSION['APP_USER#'.APP_UNIQUE]))
        {
            // ignore searches requested by a navigate cms user or explicitly ignored by the template
            if(@!($vars['log']=='false'))
            {
                $DB->execute('
                    INSERT INTO nv_search_log
                      (id, website, date, webuser, origin, text, request)
                    VALUES
                      (0, :website, :date, :webuser, :origin, :text, :request)
                  ',
                    array(
                        'website' => $website->id,
                        'date'    => time(),
                        'webuser' => value_or_default($webuser->id, 0),
                        'origin'  => (empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER']),
                        'text'    => $search_what,
                        'request' => json_encode(array_merge($_GET, $_POST))
                    )
                );
            }
        }

        $search_what = explode(' ', $search_what);
        $search_what = array_filter($search_what);

        if(empty($search_what))
            $search_what = array();

        $search = array();
        $search[] = ' 1=1 ';

        $object_alias = "i";
        $object_type = "item";

        if($vars['source'] == "product")
        {
            $object_alias = "p";
            $object_type = "product";
        }

        foreach ($search_what as $what)
        {
            if (substr($what, 0, 1) == '-')
            {
                $search[] = '(
                                '.$object_alias.'.id NOT IN (
                                    SELECT search_nwdi.node_id
                                    FROM nv_webdictionary search_nwdi
                                    WHERE search_nwdi.node_type IN ("'.$object_type.'", "property-'.$object_type.'") AND
                                          search_nwdi.website = ' . intval($website->id) . ' AND
                                          search_nwdi.text LIKE ' . protect('%' . substr($what, 1) . '%') .
                            '    ) 
                                 AND '.$object_alias.'.id NOT IN( 
                                    SELECT search_npi.node_id
                                     FROM   nv_properties_items search_npi 
                                     WHERE  search_npi.element = "'.$object_type.'" AND 
                                            search_npi.website = ' . intval($website->id) . ' AND
                                            search_npi.value LIKE ' . protect('%' . substr($what, 1) . '%') . '
                                 )
                            )';
            }
            else
            {
                $search[] = '(
                                '.$object_alias.'.id IN (
                                    SELECT search_nwdi.node_id
                                    FROM nv_webdictionary search_nwdi
                                    WHERE search_nwdi.node_type IN ("'.$object_type.'", "property-'.$object_type.'") AND
                                          search_nwdi.website = ' . intval($website->id) . ' AND
                                          search_nwdi.text LIKE ' . protect('%' . $what . '%') .
                            '    )
                                 OR '.$object_alias.'.id IN( 
                                    SELECT  search_npi.node_id
                                     FROM   nv_properties_items search_npi
                                     WHERE  search_npi.element = "'.$object_type.'" AND 
                                            search_npi.website = ' . intval($website->id) . ' AND
                                            search_npi.value LIKE ' . protect('%' . $what . '%') . '
                                )
                            )';
            }
        }

        $search = ' AND ( ' . implode(' AND ', $search) . ' ) ';
    }

	// RETRIEVE OBJECTS

    // calculate the offset of the first element to retrieve
    // Warning: the paginator applies on all paginated lists on a page (so right now there can only be one in a page)
	if(empty($_GET['page']))
        $_GET['page'] = 1;
	$offset = intval($_GET['page'] - 1) * $vars['items'];

    // this list does not use paginator, so offset must be always zero
    if(!isset($vars['paginator']) || $vars['paginator']=='false')
        $offset = 0;

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

    // get order type: REQUEST PARAMETER > NV TAG PROPERTY > DEFAULT (priority given in CMS)
    $order = @$_REQUEST['order'];
    if(empty($order))
        $order  = @$vars['order'];
    if(empty($order))   // default order: latest
        $order = 'latest';

    $orderby = nvweb_list_get_orderby($order);

    $rs = NULL;

    if(($vars['source']=='structure' || $vars['source']=='category') && !empty($categories))
	{
        $orderby = str_replace(array('i.', 'p.'), 's.', $orderby);

        $visible = '';
        if($vars['filter']=='menu')
            $visible = ' AND s.visible = 1 ';

		$templates = "";
        if(!empty($vars['templates']))
        {
            if(strpos($vars['templates'], '$')===0)
                $templates = explode(",", $_REQUEST[substr($vars['templates'], 1)]);
            else
	            $templates = explode(",", $vars['templates']);

	        $templates = array_filter($templates);
			$templates = ' AND s.template IN ("'.implode('","', $templates).'")';
        }

		$DB->query('
			SELECT SQL_CALC_FOUND_ROWS s.id, s.permission,
			            s.date_published, s.date_unpublish, s.date_published as pdate,
			            d.text as title, s.position as position
			  FROM nv_structure s, nv_webdictionary d
			 WHERE s.id IN(:categories)
			   AND s.website = :wid
			   AND s.permission <= '.$permission.'
			   AND (s.date_published = 0 OR s.date_published < :time)
			   AND (s.date_unpublish = 0 OR s.date_unpublish > :time)
			   AND (s.access = 0 OR s.access = '.$access.$access_extra.')
			   AND d.website = s.website
			   AND d.node_type = "structure"
			   AND d.subtype = "title"
			   AND d.node_id = s.id
			   AND d.lang = :lang
		     '.$templates.'
			 '.$visible.'
			 '.$exclude.'
			 '.$orderby.'
			 LIMIT '.$vars['items'].'
			OFFSET '.$offset,
            'object',
            array(
                ':wid' => $website->id,
                ':lang' => $current['lang'],
                ':time' => core_time(),
                ':categories' => implode(",", $categories)
            )
		);

		$rs = $DB->result();
		$total = $DB->foundRows();
	}
    else if($vars['source']=='block')
    {
        list($rs, $total) = nvweb_blocks(array(
            'type' => $vars['type'],
            'number' => $vars['items'],
            'mode' => ($order=='random'? 'random' : 'ordered'), // blocks webget has two sorting methods only
            'zone' => 'object'
        ));
    }
    else if($vars['source']=='block_link')
    {
        // only useful if this nvlist is inside another nv list of source="block"
        $block_links = $vars['nvlist_parent_item']->trigger['trigger-links'][$current['lang']];
        $rs = block::block_links_list_parse($block_links);
        $total = count($rs);
    }
    else if($vars['source']=='block_group')
    {
        $bg = new block_group();
        if(!empty($vars['type']))
            $bg->load_by_code($vars['type']);

        if(!empty($bg) && !empty($bg->blocks))
        {
            $rs = array();
            foreach($bg->blocks as $bgb)
            {
                unset($bgbo);

                switch($bgb['type'])
                {
                    case 'block':
                        $bgbo = new block();
                        $bgbo->load($bgb['id']);

                        if(empty($bgbo) || empty($bgbo->type))
                            continue;

                        // check if we can display this block
                        if(nvweb_object_enabled($bgbo))
                        {
                            // check categories / exclusions
                            if(!empty($bgbo->categories))
                            {
                                $bgbo_cat_found = false;
                                foreach($categories as $list_cat)
                                {
                                    if(in_array($list_cat, $bgbo->categories))
                                        $bgbo_cat_found = true;
                                }
                                if(!$bgbo_cat_found) // block categories don't match the current list categories, skip this block
                                    continue;
                            }

                            if(!empty($bgbo->exclusions))
                            {
                                $bgbo_cat_found = false;
                                foreach($categories as $list_cat)
                                {
                                    if(in_array($list_cat, $bgbo->exclusions))
                                        $bgbo_cat_found = true;
                                }

                                if($bgbo_cat_found) // block excluded categories match the current list categories, skip this block
                                    continue;
                            }

                            // inclusion/exclusion by specific elements
                            if(!empty($bgbo->elements))
                            {
                                if($current['type']=='item')
                                {
                                    if(isset($bgbo->elements['exclusions']) && in_array($current['id'], $bgbo->elements['exclusions']))
                                    {
                                        // do not include this block in this element's page!
                                        continue;
                                    }

                                    if(isset($bgbo->elements['selection']) && !in_array($current['id'], $bgbo->elements['selection']))
                                    {
                                        // block not associated with the current item, ignore!
                                        continue;
                                    }
                                }
                            }
                            $rs[] = $bgbo;
                        }
                        break;

                    case 'block_group_block':
                        $bgba = $theme->block_group_blocks($vars['type']);

                        if(!empty($bgba[$bgb['id']])) // get the definition for that "block group block" type
                        {
                            $bgbo = clone $bgba[$bgb['id']];
                            $bgbo->uid = $bgb['uid'];
                            $rs[] = clone $bgbo;
                        }
                        break;

                    case 'block_type':
                        // a collection of blocks of the same type
                        list($bgbos, $foo) = nvweb_blocks(array(
                            'type' => $bgb['id'],
                            'mode' => ($order=='random'? 'random' : 'ordered'),
                            'zone' => 'object'
                        ));

                        // add the block type definition, with its title
                        if(count($bgbos) > 0 && isset($bgb['title']) && !empty($bgb['title']))
                        {
                            $bgb['_object_type'] = 'block_group_block_type';
                            $rs[] = (object)$bgb;
                        }

                        for($i=0; $i < count($bgbos); $i++)
                            $rs[] = $bgbos[$i];

                        break;

                    case 'extension':
                        $rs[] = (object)($bgb);
                        break;
                }
            }
            $total = count($rs);
        }
        else    // block group block empty, just return without content
            return "";
    }
    else if($vars['source']=='product')
    {
        $filters = '';
        if(!empty($vars['filter']))
            $filters = nvweb_list_parse_filters($vars['filter'], 'product');

        // reuse structure.access permission
        $access_extra_items = str_replace('s.', 'p.', $access_extra);

        $embedded = ($vars['embedded']=='true'? '1' : '0');

        $templates = "";
        if(!empty($vars['templates']))
        {
            if(strpos($vars['templates'], '$')===0)
                $templates = explode(",", $_REQUEST[substr($vars['templates'], 1)]);
            else
                $templates = explode(",", $vars['templates']);

            $templates = array_filter($templates);
            if($embedded=='1')
                $templates = ' AND s.template IN ("'.implode('","', $templates).'")';
            else
                $templates = ' AND p.template IN ("'.implode('","', $templates).'")';
        }

        $columns_extra = '';
        if($vars['order'] == 'comments')
        {
            // we need to retrieve the number of comments to apply the order by clause
            $columns_extra = ', ( SELECT COUNT(p.id) 
                                    FROM nv_comments c 
                                    WHERE   c.object_type = "product" AND 
                                            p.id = c.object_id AND 
                                            c.website = p.website AND 
                                            c.status = 0
                                ) AS comments_published';
        }

        if($vars['order'] == 'sales')
        {
            // retrieve the number of sales to apply the order requested
            $columns_extra = ', (
                    SELECT COUNT(*) FROM nv_orders_lines WHERE website = p.website AND product = p.id
                ) AS sales';
        }

        if($vars['order'] == 'price_asc' || $vars['order'] == 'price_desc')
        {
            // we need to calculate the offer price and get the lowest price for the product
            $columns_extra = ', ( 
                    IF( 
                        (   p.offer_price > 0 
                            AND (p.offer_begin_date = 0 OR '.core_time().' >= p.offer_begin_date)
                            AND (p.offer_end_date = 0 OR '.core_time().' <= p.offer_end_date)
                        ), 
                        p.offer_price, 
                        p.base_price
                    ) 
                ) AS sale_price';
        }

        // default source for retrieving items
        $query = '
			SELECT SQL_CALC_FOUND_ROWS p.id, p.permission, p.date_published, p.date_unpublish,
                    p.date_to_display, COALESCE(NULLIF(p.date_to_display, 0), p.date_created) as pdate,
                    d.text as title, p.position as position, s.position '.$columns_extra.'
			  FROM nv_products p, nv_structure s, nv_webdictionary d			          
			 WHERE p.category IN(:categories)
			   AND p.website = :wid
			   AND p.permission <= '.$permission.'
			   AND (p.date_published = 0 OR p.date_published < '.core_time().')
			   AND (p.date_unpublish = 0 OR p.date_unpublish > '.core_time().')
			   AND s.id = p.category
			   AND (s.date_published = 0 OR s.date_published < :time)
			   AND (s.date_unpublish = 0 OR s.date_unpublish > :time)
			   AND s.permission <= '.$permission.'
			   AND (s.access = 0 OR s.access = '.$access.$access_extra.')
			   AND (p.access = 0 OR p.access = '.$access.$access_extra_items.')
               AND d.website = p.website
			   AND d.node_type = "product"
			   AND d.subtype = "title"
			   AND d.node_id = p.id
			   AND d.lang = :lang
             '.$filters.'
             '.$search.'
		     '.$templates.'
			 '.$exclude.'
			 '.$orderby.'
			 LIMIT '.$vars['items'].'
			OFFSET '.$offset;

        $DB->query(
            $query,
            'object',
            array(
                ':wid' => $website->id,
                ':lang' => $current['lang'],
                ':time' => core_time(),
                ':categories' => implode(",", $categories)
            )
        );

        $rs = $DB->result();
        $total = $DB->foundRows();
    }
    else if($vars['source']=='cart')
    {
        $cart = json_decode(json_encode($session['cart']));
        $rs = $cart->lines;
        $total = count($cart->lines);
    }
    else if($vars['source']=='gallery')
    {
        if(!isset($vars['nvlist_parent_type']))
        {
            // get gallery of the current item
            if($current['type']=='item')
            {
                $galleries = $current['object']->galleries;
                if(!is_array($galleries))
                    $galleries = mb_unserialize($galleries);
                $rs = $galleries[0];
                $total = count($rs);
            }
            else if($current['type']=='structure')
            {
                // we need the first item assigned to the structure
                $access_extra_items = str_replace('s.', 'i.', $access_extra);

	            $templates = "";
		        if(!empty($vars['templates']))
		        {
                    if(strpos($vars['templates'], '$')===0)
                        $templates = explode(",", $_REQUEST[substr($vars['templates'], 1)]);
                    else
                        $templates = explode(",", $vars['templates']);

			        $templates = array_filter($templates);
					$templates = ' AND i.template IN ("'.implode('","', $templates).'")';
		        }

		        if(empty($categories))
		            $categories = array(0);

                // default source for retrieving items (embedded or not)
                $DB->query('
                    SELECT SQL_CALC_FOUND_ROWS i.id
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
                     '.$templates.'
                     '.$exclude.'
                     ORDER BY i.position ASC
                     LIMIT 1',
                    'object',
                    array(
                        ':wid' => $website->id,
                        ':lang' => $current['lang'],
                        ':time' => core_time(),
                        ':categories' => implode(",", $categories)
                    ));

                $rs = $DB->result();
                $tmp = new item();
                $tmp->load($rs[0]->id);

                $rs = $tmp->galleries[0];
                $total = count($rs);
            }
        }
        else if($vars['nvlist_parent_type'] == 'item')
        {
            $pitem = $vars['nvlist_parent_item'];
            $rs = $pitem->galleries[0];
            $total = count($rs);
        }

        if($total > 0)
        {
            $order = 'priority'; // display images using the assigned priority
            if(!empty($vars['order']))
                $order = $vars['order'];

            $rs = nvweb_gallery_reorder($rs, $order);

            // prepare format to be parsed by nv list iterator
            $rs = array_map(
                function($k, $v)
                {
                    $v['file'] = $k;
                    return $v;
                },
                array_keys($rs),
                array_values($rs)
            );
        }
    }
    else if($vars['source']=='rss')
    {
        // url may be a property
        $rss_url = $vars['url'];
        if(strpos($vars['url'], "http")!==0)
        {
            $rss_url = nvweb_properties(array(
                'property'	=> 	$vars['url']
            ));
        }
        list($rs, $total) = nvweb_list_get_from_rss($rss_url, @$vars['cache'], $offset, $vars['items'], $permission, $order);
    }
    else if($vars['source']=='twitter')
    {
        list($rs, $total) = nvweb_list_get_from_twitter($vars['username'], @$vars['cache'], $offset, $vars['items'], $permission, $order);
    }
	else if(!empty($vars['source']) && !in_array($vars['source'], array('item', 'element')))
	{
		// CUSTOM data source
        if($vars['source']=='comment')
            $vars['source'] = 'comments';

		$fname = 'nvweb_'.$vars['source'].'_list';

        if($vars['source']=='website_comments')
            $vars['source'] = 'comments';

		@nvweb_webget_load($vars['source']);

		if(function_exists($fname))
			list($rs, $total) = $fname($offset, $vars['items'], $permission, $order, $vars);
    }
    else if(!in_array($vars['source'], array('item', 'element')))
    {
        return ''; // ignore this list definition, return empty content
    }

    $categories = array_filter($categories);

	// DATA SOURCE not given or ERROR ===> items
	if((empty($vars['source']) || !is_numeric($total)) && !empty($categories))
    {
        /*
         * TO DO: design decision ... lists should show items from published categories which has unpublished parent?
         * Navigate CMS 1.6.7: NO

        // we have to check all website UNPUBLISHED categories to keep the list query efficient
        // there are some cases:
        //  a) Permission is beyond user's level [0=>public, 1=>private, 2=>hidden]
        //  b) Date published is set and the value is before the current time (not yet published)
        //  c) Date unpublish is set and the value is before the current time (no more published)
        //  d) User account level not allowed [0=>everyone, 1=>signed in users, 2=>users NOT signed in]
        $DB->query('
            SELECT id
              FROM nv_structure
             WHERE website = '.intval($website->id).'
               AND (    permission > '.$permission.'
                     OR (date_published > 0 AND '.$website->current_time().' > date_published)
                     OR (date_unpublish > 0 AND '.$website->current_time().' > date_unpublish)
                     OR (access <> 0 AND access <> '.$access.')
               )
        ');

        $hidden_categories = $DB->result('id');

        // now we would have to mark the children categories also as unpublished

        */
        $filters = '';
        if(!empty($vars['filter']))
            $filters = nvweb_list_parse_filters($vars['filter'], 'items');

        // reuse structure.access permission
        $access_extra_items = str_replace('s.', 'i.', $access_extra);

        $embedded = ($vars['embedded']=='true'? '1' : '0');

		$templates = "";
        if(!empty($vars['templates']))
        {
            if(strpos($vars['templates'], '$')===0)
                $templates = explode(",", $_REQUEST[substr($vars['templates'], 1)]);
            else
                $templates = explode(",", $vars['templates']);

	        $templates = array_filter($templates);
	        if($embedded=='1')
				$templates = ' AND s.template IN ("'.implode('","', $templates).'")';
			else
	            $templates = ' AND i.template IN ("'.implode('","', $templates).'")';
        }

        $columns_extra = '';
        if($vars['order'] == 'comments')
        {
            // we need to retrieve the number of comments to apply the order by clause
            $columns_extra = ', (   SELECT COUNT(c.id) 
                                    FROM nv_comments c 
                                    WHERE c.object_type = "item" AND 
                                          i.id = c.object_id AND 
                                          c.website = i.website AND 
                                          c.status = 0
                                 ) AS comments_published';
        }

		// default source for retrieving items
        $query = '
			SELECT SQL_CALC_FOUND_ROWS i.id, i.permission, i.date_published, i.date_unpublish,
                    i.date_to_display, COALESCE(NULLIF(i.date_to_display, 0), i.date_created) as pdate,
                    d.text as title, i.position as position, s.position '.$columns_extra.'
			  FROM nv_items i, nv_structure s, nv_webdictionary d			          
			 WHERE i.category IN(:categories)
			   AND i.website = :wid
			   AND i.permission <= '.$permission.'
			   AND i.embedding = '.$embedded.'
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
             '.$filters.'
             '.$search.'
		     '.$templates.'
			 '.$exclude.'
			 '.$orderby.'
			 LIMIT '.$vars['items'].'
			OFFSET '.$offset;

		$DB->query(
		    $query,
            'object',
            array(
                ':wid' => $website->id,
                ':lang' => $current['lang'],
                ':time' => core_time(),
                ':categories' => implode(",", $categories)
            )
        );

		$rs = $DB->result();

		$total = $DB->foundRows();
	}

	// preprocess list html template, conditionals and nested lists

    // get the nv list template
    $item_html = $vars['template'];

    // first we need to isolate the nested nv lists/searches
    unset($nested_lists_fragments);
    list($item_html, $nested_lists_fragments) = nvweb_list_isolate_lists($item_html);

    // now, parse the nvlist_conditional tags (with html source code inside (and maybe other nvlist tags))
    unset($nested_condition_fragments);
    list($item_html, $nested_conditional_fragments) = nvweb_list_isolate_conditionals($item_html);

    $item_html_preprocessed = $item_html;
    $nested_lists_fragments_preprocessed = $nested_lists_fragments;
    $nested_conditional_fragments_preprocessed = $nested_conditional_fragments;
    $conditional_placeholder_tags_preprocessed = nvweb_tags_extract($item_html, 'nvlist_conditional_placeholder', true, true, 'UTF-8'); // selfclosing = true

    // now we have all elements that will be shown in the list (if any)
    // let's process the nvlist template for each one
    if(empty($rs))
    {
        $rs = array();
    }

	for($i = 0; $i < count($rs); $i++)
	{
        // ignore empty objects, except in custom_source or cart modes
        if( (!isset($vars['custom_source']) || $vars['custom_source']!='true') )
        {
            if(
                ($vars['source']!='gallery' && empty($rs[$i]->id))  ||
                ($vars['source']=='gallery' && empty($rs[$i]['file']))
            )
            {
                continue;
            }
        }

        // prepare a standard-object called  $item  with the current element
		switch($vars['source'])
        {
            case 'comment':
            case 'comments':
                $item = new comment();
                $item->load_from_resultset(array($rs[$i]));
                break;

            case 'structure':
            case 'category':
                $item = new structure();
                $item->load($rs[$i]->id);
                $item->date_to_display = $rs[$i]->pdate;
                break;

            case 'rss':
            case 'twitter':
            case 'block_link':
                // item is virtually created
                $item = $rs[$i];
                break;

            case 'block':
            case 'block_group':
                if(get_class($rs[$i])=='block')
                {
                    // standard block
                    $item = $rs[$i];
                }
                else if(isset($rs[$i]->_object_type) && ($rs[$i]->_object_type == "block_group_block_type"))
                {
                    // block type definition (mainly used to add a title before a list of blocks of the same type)
                    $item = $rs[$i];
                }
                else if(isset($rs[$i]->extension))
                {
                    // extension block
                    $item = block::extension_block($rs[$i]->extension, $rs[$i]->id);
                    if(empty($item)) // empty or inexistant block, ignore
                        continue;
                    $item->type = "extension";
                    $item->extension = $rs[$i]->extension;
                    $item->uid = $rs[$i]->uid;
                }
                else
                {
                    // block from block group
                    $item = new block();
                    $item->load_from_block_group($vars['type'], $rs[$i]->id, $rs[$i]->uid);
                }
                break;

            case 'gallery':
                $item = $rs[$i];
                break;

            case 'product':
                $item = new product();
                if(!empty($rs[$i]->id)) // custom_source mode may return empty IDs
                {
                    $item->load($rs[$i]->id);
                    // if the product comes from a custom source, save the original query result
                    // this allows getting a special field without extra work ;)
                    $item->_query = $rs[$i];
                }
                else
                {
                    // for custom_source mode without product->id information,
                    // just return the whole object returned (to be used with "query" source, for example)
                    $item = $rs[$i];
                }
                break;

            case 'cart':
                $item = new product();
                $item->load($rs[$i]->id);
                $item->_cart = $rs[$i];
                break;

            case 'element':
            case 'item':
            default:
                $item = new item();
                if(!empty($rs[$i]->id)) // custom_source mode may return empty IDs
                {
                    $item->load($rs[$i]->id);
                    // if the item comes from a custom source, save the original query result
                    // this allows getting a special field without extra work ;)
                    $item->_query = $rs[$i];
                }
                else
                {
                    // for custom_source mode without element->id information,
                    // just return the whole object returned (to be used with "query" source, for example)
                    $item = $rs[$i];
                }
                break;
        }

        // get the preprocessed template
        $item_html = $item_html_preprocessed;
        $nested_lists_fragments = $nested_lists_fragments_preprocessed;
        $nested_conditional_fragments = $nested_conditional_fragments_preprocessed;
        $conditional_placeholder_tags = $conditional_placeholder_tags_preprocessed;

        // start processing tags, <nvlist_conditional />
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

            // we need to check for new conditionals after the previous replace
            $conditional_placeholder_tags = nvweb_tags_extract($item_html, 'nvlist_conditional_placeholder', true, true, 'UTF-8'); // selfclosing = true
        }

        // now, parse the (remaining) common nvlist tags (selfclosing tags)
        $template_tags_processed = 0;
        $template_tags = nvweb_tags_extract($item_html, 'nvlist', true, true, 'UTF-8'); // selfclosing = true
        while(!empty($template_tags))
		{
            $tag = $template_tags[0];

            // protect the "while" loop, maximum 500 nvlist tags parsed!
            $template_tags_processed++;
            if($template_tags_processed > 500)
                break;

            // TODO: check if it is really needed to parse the tag based on offsets
            $content = nvweb_list_parse_tag($tag, $item, $vars['source'], $i, ($i+$offset), $total);
            $item_html = str_replace($tag['full_tag'], $content, $item_html);

            // html template has changed, the nvlist tags may have changed its positions
            $template_tags = nvweb_tags_extract($item_html, 'nvlist', true, true, 'UTF-8');
		}

        // restore & process nested lists (if any)
        foreach($nested_lists_fragments as $nested_list_uid => $nested_list_vars)
        {
            if(strpos($item_html, '<!--#'.$nested_list_uid.'#-->')!==false)
            {
                $nested_list_vars['nvlist_parent_vars'] = $vars;
                $nested_list_vars['nvlist_parent_type'] = $vars['source'];
                $nested_list_vars['nvlist_parent_item'] = clone $item;
                $content = nvweb_list($nested_list_vars);
                $item_html = str_replace('<!--#' . $nested_list_uid . '#-->', $content, $item_html);
            }
        }

		$out[] = $item_html;

	}

    if(count($rs)==0)
    {
        // special case, no results found
        // get the nv list template and parse only the following conditional: <nvlist_conditional by="count" value="empty"> (or value=0)
        $item_html = $vars['template'];

        // now, parse the nvlist_conditional tags (with html source code inside (and other nvlist tags))
        unset($nested_condition_fragments);
        list($item_html, $nested_conditional_fragments) = nvweb_list_isolate_conditionals($item_html, array('count'));

        // if we can't find a suitable conditional, let's assume the list content will be empty
        $item_html = "";

        // check every nvlist_conditional found, looking for count = [0 or empty]
        // we only process the first found!
        foreach($nested_conditional_fragments as $ncf)
        {
            if( $ncf['attributes']['value'] == '0' ||
                $ncf['attributes']['value'] == 'empty')
            {
                $item_html = $ncf['nvlist_conditional_template'];
                break;
            }
        }

        $out[] = $item_html;
    }

	if(isset($vars['paginator']) && $vars['paginator']!='false')
		$out[] = nvweb_list_paginator($vars['paginator'], $_GET['page'], $total, $vars['items'], $vars);

	return implode("\n", $out);
}

function nvweb_list_parse_tag($tag, $item, $source='item', $item_relative_position, $item_absolute_position, $total)
{
	global $current;
	global $website;
	global $structure;
	global $DB;
    global $webuser;

	$out = '';

	switch($tag['attributes']['source'])
	{
        // special condition, return direct query result values
		case 'query':
            $out = $item->_query->{$tag['attributes']['value']};
            break;

		// special: return element position in list
		case 'position':

			$position = $item_relative_position;
			if($tag['attributes']['absolute']=='true')
				$position = $item_absolute_position;

			switch($tag['attributes']['type'])
			{
				case 'alphabetic':
					$out = number2alphabet($position);
					break;

				case 'numeric':
				default:
					$out = $position + 1; // first element is 1, but in list is zero
					break;
			}

			break;

		// NOTE: the following refers to structure information of an ITEM, useless if the source are categories!
		case 'structure':
		case 'category':
			nvweb_menu_load_dictionary(); // load menu translations if not already done
			nvweb_menu_load_routes(); // load menu paths if not already done
			switch($tag['attributes']['value'])
			{
				case 'title':
                    if($source=='structure' || $source=='category')
					    $out = $structure['dictionary'][$item->id];
                    else
                        $out = $structure['dictionary'][$item->category];

                    if(!empty($tag['attributes']['length']))
                        $out = core_string_cut($out, $tag['attributes']['length'], '&hellip;');
					break;


                case 'slug':
                    if($source=='structure' || $source=='category')
					    $out = $structure['dictionary'][$item->id];
                    else
                        $out = $structure['dictionary'][$item->category];

                    // remove spaces, special chars, etc.
                    $out = core_string_clean($out);
                    $out = slug($out);
                    break;

				case 'property':
                    $id = $item->id;
                    if($source!='structure' && $source!='category')
                        $id = $item->category;

                    $nvweb_properties_parameters = array_replace(
                        $tag['attributes'],
                        array(
                            'mode'		=>	(!isset($tag['attributes']['mode'])? 'structure' : $tag['attributes']['mode']),
                            'id'		=>	$id,
                            'property'	=> 	(!empty($tag['attributes']['property'])? $tag['attributes']['property'] : $tag['attributes']['name'])
                        )
                    );

                    $out = nvweb_properties($nvweb_properties_parameters);
					break;

				case 'url':
				case 'path':
                    if($source=='structure' || $source=='category')
                        $out = $structure['routes'][$item->id];
                    else
                        $out = $structure['routes'][$item->category];

                    $out = nvweb_prepare_link($out);
					break;

                case 'id':
                    if($source=='structure' || $source=='category')
                        $out = $item->id;
                    else // source = 'item'?
                        $out = $item->category;
                    break;

                case 'count':
                    // how many elements have been assigned to this category? (and can be seen by the current webuser)

                    $permission = (!empty($_SESSION['APP_USER#'.APP_UNIQUE])? 1 : 0);

                    // public access / webuser based / webuser groups based
                    $access = 2;
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
                                $access_groups[] = 'groups LIKE "%g'.$wg.'%"';
                            }
                            if(!empty($access_groups))
                                $access_extra = ' OR (access = 3 AND ('.implode(' OR ', $access_groups).'))';
                        }
                    }

                    $out = $DB->query_single(
                        'COUNT(id)',
                        'nv_items',
                        ' category = '.intval($item->id).' AND
                          website = '.intval($item->website).' AND
                          permission <= '.$permission.' AND 
                          (date_published = 0 OR date_published < '.core_time().') AND 
                          (date_unpublish = 0 OR date_unpublish > '.core_time().') AND 
                          (access = 0 OR access = '.$access.$access_extra.')
                    ');
                    break;

				default:
					break;
			}
			break;

		// ITEM comments
		case 'comment':
        case 'comments':
            switch($tag['attributes']['value'])
			{
                case 'id':
                    $out = $item->id;
                    break;

				case 'avatar':
					$size = '48';
                    $extra = '';
					if(!empty($tag['attributes']['size']))
						$size = intval($tag['attributes']['size']);

                    if(!empty($tag['attributes']['border']))
						$extra .= '&border='.$tag['attributes']['border'];

                    if(!empty($tag['attributes']['opacity']))
						$extra .= '&opacity='.$tag['attributes']['opacity'];

                    $avatar = $item->author_avatar();
					if(!empty($avatar))
					{
						$out = '<img class="'.$tag['attributes']['class'].'" src="'.NVWEB_OBJECT.'?type=image'.$extra.'&id='.$avatar.'" width="'.$size.'px" height="'.$size.'px"/>';
					}
					else if(!empty($tag['attributes']['default']))
                    {
                        // the comment creator has not an avatar, but the template wants to show a default one
                        // 3 cases:
                        //  numerical   ->  ID of the avatar image file in Navigate CMS
                        //  absolute path (http://www...)
                        //  relative path (/img/avatar.png) -> path to the avatar file included in the THEME used
                        if(is_numeric($tag['attributes']['default']))
                            $out = '<img class="'.$tag['attributes']['class'].'" src="'.NVWEB_OBJECT.'?type=image'.$extra.'&id='.$tag['attributes']['default'].'" width="'.$size.'px" height="'.$size.'px"/>';
                        else if(strpos($tag['attributes']['default'], 'http://')===0)
                            $out = '<img class="'.$tag['attributes']['class'].'" src="'.$tag['attributes']['default'].'" width="'.$size.'px" height="'.$size.'px"/>';
                        else if($tag['attributes']['default']=='none')
                            $out = ''; // no image
                        else
                            $out = '<img class="'.$tag['attributes']['class'].'"src="'.NAVIGATE_URL.'/themes/'.$website->theme.'/'.$tag['attributes']['default'].'" width="'.$size.'px" height="'.$size.'px"/>';
                    }
                    else // empty avatar, try to get a libravatar/gravatar or show a blank avatar
                    {
	                    $gravatar_hash = "";
	                    $gravatar_default = 'blank';
	                    if(!empty($tag['attributes']['gravatar_default']))
		                    $gravatar_default = $tag['attributes']['gravatar_default'];

	                    if(!empty($item->email))
	                    {
		                    $gravatar_hash = md5( strtolower( trim( $item->email ) ) );
	                    }
	                    else if(!empty($item->user))
	                    {
		                    $email = $DB->query_single('email', 'nv_webusers', 'id = '.intval($item->user));
		                    if(!empty($email))
                            {
                                $gravatar_hash = md5( strtolower( trim( $item->email ) ) );
                            }
	                    }

	                    if(!empty($gravatar_hash) && $gravatar_default != 'none')
	                    {
		                    // gravatar real url: https://www.gravatar.com/avatar/
		                    // we use libravatar to get more userbase
		                    $gravatar_url = 'https://seccdn.libravatar.org/avatar/' . $gravatar_hash . '?s='.$size.'&d='.$gravatar_default;
		                    $out = '<img class="'.$tag['attributes']['class'].'" src="'.$gravatar_url.'" width="'.$size.'px" height="'.$size.'px"/>';
	                    }
						else
							$out = '<img class="'.$tag['attributes']['class'].'" src="data:image/gif;base64,R0lGODlhAQABAPAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" width="'.$size.'px" height="'.$size.'px"/>';
                    }

					if($tag['attributes']['linked']=='true' && !empty($out))
					{
						if(!empty($item->url))
	                    {
	                        $comment_link = $item->url;
	                    }
	                    else if(!empty($item->user))
	                    {
	                        $wu = new webuser();
	                        $wu->load($item->user);
	                        $comment_link = $wu->social_website;
	                    }

						if(!empty($comment_link))
                            $out = '<a href="'.$comment_link.'" target="_blank">'.$out.'</a>';
					}
					break;

				case 'username':
					$out = $item->author_name();
					if($tag['attributes']['linked']=='true' && !empty($out))
					{
						if(!empty($item->url))
	                    {
	                        $comment_link = $item->url;
	                    }
	                    else if(!empty($item->user))
	                    {
	                        $wu = new webuser();
	                        $wu->load($item->user);
	                        $comment_link = $wu->social_website;
	                    }

						if(!empty($comment_link))
                            $out = '<a href="'.$comment_link.'" target="_blank">'.$out.'</a>';
					}
					break;

                case 'webuser_comments':
                    if(!empty($item->user))
                        $out = comment::webuser_comments_count($item->user);
                    else
                        $out = "";
                    break;

                case 'website':
                    if(!empty($item->url))
                    {
                        $out = nvweb_prepare_link($item->url);
                    }
                    else if(!empty($item->user))
                    {
                        $wu = new webuser();
                        $wu->load($item->user);
                        $out = nvweb_prepare_link($wu->social_website);
                    }

                    if(empty($out))
                    {
                        $out = '#';
                    }
                    break;

				case 'message':
                    if(!empty($tag['attributes']['length']))
                        $out = core_string_cut($item->message, $tag['attributes']['length'], '&hellip;');
                    else
					    $out = nl2br($item->message);
					break;

				case 'date':
                    // Navigate CMS 1.6.6 compatibility
                    if(empty($tag['attributes']['format']) && !empty($tag['attributes']['date_format']))
                        $tag['attributes']['format'] = $tag['attributes']['date_format'];

                    if(!empty($tag['attributes']['format'])) // custom date format
                        $out = nvweb_content_date_format($tag['attributes']['format'], $item->date_created);
                    else
                        $out = date($website->date_format.' H:i', $item->date_created);
					break;

                case 'item_url':
                    $out = nvweb_source_url($item->object_type, $item->object_id, $current['lang']);
                    break;

                case 'item_title':
                    $out = $item->item_title;
                    break;

                case 'reply_to':
                    $out = $item->reply_to;
                    break;

                case 'depth':
                    $c = new comment();
                    $c->load_from_resultset(array($item));
                    $out = $c->depth();
                    if(isset($tag['max']) && $out > $tag['max'])
                    {
                        $out = $tag['max'];
                    }
                    if(isset($tag['min']) && $out < $tag['min'])
                    {
                        $out = $tag['min'];
                    }
                    break;

                case 'property':
                    $c = new comment();
                    $c->load_from_resultset(array($item));

                    // pass all nvlist tag parameters to properties nvweb, but some attribute/values take preference
                    $nvweb_properties_parameters = array_replace(
                        $tag['attributes'],
                        array(
                            'mode'		=>	'comment',
                            'id'		=>	$c->id,
                            'template'	=>	$c->element_template(),
                            'property'	=> 	(!empty($tag['attributes']['property'])? $tag['attributes']['property'] : $tag['attributes']['name'])
                        )
                    );

                    $out = nvweb_properties($nvweb_properties_parameters);
                    break;
			}
			break;

        case 'block':
            switch($tag['attributes']['value'])
            {
                case 'id':
                    $out = $item->id;
                    break;

                // only for blocks in a block group!
                case 'uid':
                    $out = $item->uid;
                    break;

                case 'block':
                    // generate the full block code
                    if($item->type == "extension")
                    {
                        if(function_exists('nvweb_'.$item->extension.'_'.$item->id))
                        {
                            // load extension block property values
                            $item->properties = property::load_properties(NULL, $item->id, "extension_block", NULL, $item->uid);
                            $out = call_user_func('nvweb_'.$item->extension.'_'.$item->id, $item);
                        }
                    }
                    else
                        $out = nvweb_blocks_render($item->type, $item->trigger, $item->action, NULL, NULL, $tag['attributes']);
                    break;

                // not for extension_blocks
                case 'title':
                    $out = $item->dictionary[$current['lang']]['title'];
                    if(!empty($tag['attributes']['length']))
                        $out = core_string_cut($out, $tag['attributes']['length'], '&hellip;');
                    break;

                case 'content':
                    if($item->type == "extension")
                    {
                        if(function_exists('nvweb_'.$item->extension.'_'.$item->id))
                        {
                            // load extension block property values
                            $item->properties = property::load_properties(NULL, $item->id, "extension_block", NULL, $item->uid);
                            $out = call_user_func('nvweb_'.$item->extension.'_'.$item->id, $item);
                        }
                    }
                    else
                        $out = nvweb_blocks_render($item->type, $item->trigger, $item->action, 'content', $item, $tag['attributes']);
                    break;

                // not for extension_blocks
                case 'url':
                case 'path':
                    $out = nvweb_blocks_render_action($item->action, '', $current['lang'], true);
                    if(empty($out))
                        $out = '#';
                    else
                        $out = nvweb_prepare_link($out);
                    break;

                // not for extension_blocks
                case 'target':
                    if($item->action['action-type'][$current['lang']]=='web-n')
                        $out = '_blank';
                    else
                        $out = '_self';
                    break;

                // not for extension_blocks (only for standard blocks and block group blocks)
                case 'property':
                    $properties_mode = 'block';

                    if(!is_numeric($item->id))
                        $properties_mode = 'block_group_block';

                    $nvweb_properties_parameters = array_replace(
                        $tag['attributes'],
                        array(
                            'mode'		=>	(!isset($tag['attributes']['mode'])? $properties_mode : $tag['attributes']['mode']),
                            'id'		=>	$item->id,
                            'property'	=> 	(!empty($tag['attributes']['property'])? $tag['attributes']['property'] : $tag['attributes']['name']),
                            'uid'       =>  @$item->uid
                        )
                    );

                    $out = nvweb_properties($nvweb_properties_parameters);
                    break;

                // not for extension_blocks
                case 'poll_answers':
                    $out = nvweb_blocks_render_poll($item);
                    break;

                default:
                    break;
            }
            break;

        case 'block_link':
            switch($tag['attributes']['value'])
            {
                case 'id':
                    $out = $item->id;
                    break;

                case 'title':
                    $out = $item->title;
                    if(!empty($tag['attributes']['length']))
                        $out = core_string_cut($out, $tag['attributes']['length'], '&hellip;');
                    break;

                case 'url':
                case 'path':
                    $out = $item->link;
                    if(empty($out))
                        $out = '#';
                    else
                        $out = nvweb_prepare_link($out);
                    break;

                case 'target':
                    if($item->new_window == 1)
                        $out = '_blank';
                    else
                        $out = '_self';
                    break;

                case 'icon':
                    $out = @$item->icon;
                    break;

                default:
                    break;
            }
            break;

        case 'block_type':
            switch($tag['attributes']['value'])
            {
                case 'title':
                    $title_obj = json_decode($item->title, true);
                    if(empty($title_obj)) // not json
                        $out = $item->title;
                    else
                        $out = $title_obj[$current['lang']];
                    break;
            }
            break;

        case 'gallery':
            switch($tag['attributes']['value'])
            {
                case 'url':
                case 'path':
                    $out = NVWEB_OBJECT.'?wid='.$website->id.'&id='.$item['file'].'&amp;disposition=inline';
                    break;

                case 'thumbnail':
				case 'thumbnail_url':
                    $thumbnail_url = NVWEB_OBJECT.'?wid='.$website->id.'&id='.$item['file'].'&amp;disposition=inline&amp;width='.$tag['attributes']['width'].'&amp;height='.$tag['attributes']['height'].'&amp;border='.$tag['attributes']['border'].'&amp;opacity='.$tag['attributes']['opacity'];
                    if($tag['attributes']['value']=='thumbnail_url' || @$tag['attributes']['return']=='url')
                        $out = $thumbnail_url;
                    else
                        $out = '<img src="'.$thumbnail_url.'" alt="'.$item[$current['lang']].'" title="'.$item[$current['lang']].'" />';
                    break;

                case 'title':
                    $f = new file();
                    $f->load($item['file']);
                    $out = $f->title[$current['lang']];
                    break;

                case 'alt':
                case 'description':
                    $f = new file();
                    $f->load($item['file']);
                    $out = $f->description[$current['lang']];
                    break;

                default:
                    $out = '<a href="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$item['file'].'&amp;disposition=inline">
                                <img src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$item['file'].'&amp;disposition=inline&amp;width='.$tag['attributes']['width'].'&amp;height='.$tag['attributes']['height'].'&amp;border='.$tag['attributes']['border'].'&amp;opacity='.$tag['attributes']['opacity'].'"
									 alt="'.$item[$current['lang']].'" title="'.$item[$current['lang']].'" />
                            </a>';
                    break;
            }
            break;

        case 'product':

            switch($tag['attributes']['value'])
            {
                case 'id':
                    $out = $item->id;
                    break;

                case 'slug':
                    $lang = $current['lang'];

                    if(!empty($tag['attributes']['lang']))
                        $lang = $tag['attributes']['lang'];

                    $out = $item->dictionary[$lang]['title'];

                    // remove spaces, special chars, etc.
                    $out = core_string_clean($out);
                    $out = slug($out);
                    break;

                case 'title':
                    $lang = $current['lang'];

                    if(!empty($tag['attributes']['lang']))
                        $lang = $tag['attributes']['lang'];

                    $out = $item->dictionary[$lang]['title'];

                    if(!empty($tag['attributes']['length']))
                        $out = core_string_cut($out, $tag['attributes']['length'], '&hellip;', $tag['attributes']['length']);
                    break;

                case 'author':
                    if(!empty($item->author))
                    {
                        $nu = new user();
                        $nu->load($item->author);
                        $out = $nu->username;
                        unset($nu);
                    }

                    if(empty($out))
                        $out = $website->name;
                    break;

                case 'date':
                    if(!empty($tag['attributes']['format'])) // custom date format
                        $out = nvweb_content_date_format($tag['attributes']['format'], $item->date_to_display);
                    else
                        $out = date($website->date_format, $item->date_to_display);
                    break;

                case 'date_created':
                    if(!empty($tag['attributes']['format'])) // custom date format
                        $out = nvweb_content_date_format($tag['attributes']['format'], $item->date_created);
                    else
                        $out = date($website->date_format, $item->date_created);
                    break;

                case 'content':
                case 'section':
                    $section = $tag['attributes']['section'];
                    if(empty($section)) $section = 'main';
                    $out = $item->dictionary[$current['lang']]['section-'.$section];

                    if(!empty($tag['attributes']['length']))
                    {
                        $allowed_tags = '';
                        if(!empty($tag['attributes']['allowed_tags']))
                            $allowed_tags = explode(',', $tag['attributes']['allowed_tags']);
                        $out = core_string_cut($out, $tag['attributes']['length'], '&hellip;', $allowed_tags);
                    }
                    break;

                case 'comments':
                    if(method_exists($item, 'comments_count'))
                        $out = $item->comments_count();
                    else
                        $out = nvweb_content_comments_count($item->id, "product");
                    break;

                case 'gallery':
                    $params = array('product' => $item->id);
                    $params = array_merge($params, $tag['attributes']);
                    $out = nvweb_gallery($params);
                    break;

                case 'image':
                case 'photo':
                    $photo = @array_shift(array_keys($item->galleries[0]));
                    if(empty($photo))
                        $out = NVWEB_OBJECT . '?type=transparent';
                    else
                    {
                        $out = NVWEB_OBJECT . '?wid='.$website->id.'&id='.$photo.'&amp;disposition=inline&amp;width='.$tag['attributes']['width'].'&amp;height='.$tag['attributes']['height'].'&amp;border='.$tag['attributes']['border'].'&amp;opacity='.$tag['attributes']['opacity'];
                    }
                    break;

                case 'url':
                case 'path':
                    $path = $item->paths[$current['lang']];
                    if(empty($path))
                        $path = '/node/'.$item->id;
                    $out = nvweb_prepare_link($path);
                    break;

                case 'tags':
                    // pass all nvlist tag parameters to the content nvweb, but some attribute/values take preference
                    $nvweb_parameters = array_replace(
                        $tag['attributes'],
                        array(
                            'mode' => 'tags',
                            'id' => $item->id,
                            'object_type' => 'product'
                        )
                    );
                    $out = nvweb_content($nvweb_parameters);
                    break;

                case 'score':
                    $out = nvweb_votes_calc($item, $tag['attributes']['round'], $tag['attributes']['half'], $tag['attributes']['min'], $tag['attributes']['max']);
                    break;

                case 'votes':
                    $out = intval($item->votes);
                    break;

                case 'views':
                    $out = intval($item->views);
                    break;

                case 'property':
                    // pass all nvlist tag parameters to properties nvweb, but some attribute/values take preference
                    $nvweb_properties_parameters = array_replace(
                        $tag['attributes'],
                        array(
                            'mode'		=>	"product",
                            'id'		=>	$item->id,
                            'template'	=>	$item->template,
                            'property'	=> 	(!empty($tag['attributes']['property'])? $tag['attributes']['property'] : $tag['attributes']['name'])
                        )
                    );

                    $out = nvweb_properties($nvweb_properties_parameters);
                    break;

                case 'function':
                    $function = @$tag['attributes']['function'];
                    if(!empty($function) && function_exists($function))
                        $out = call_user_func($function, array('product' => $item, 'vars' => $tag['attributes']));
                    break;

                // specific "product" nvlist values
                case 'sku':
                    $out = $item->sku;
                    break;

                case 'barcode':
                    $out = $item->barcode;
                    break;

                case 'size':
                    $out = nvweb_product(array_merge($tag['attributes'], array('mode' => 'size', 'pid' => $item->id)));
                    break;

                case 'weight':
                    $out = nvweb_product(array_merge($tag['attributes'], array('mode' => 'weight', 'pid' => $item->id)));
                    break;

                case 'stock':
                    $out = nvweb_product(array_merge($tag['attributes'], array('mode' => 'stock', 'pid' => $item->id)));
                    break;

                case 'price':
                    $out = nvweb_product(array_merge($tag['attributes'], array('mode' => 'price', 'pid' => $item->id)));
                    break;

                case 'old_price':
                    $out = nvweb_product(array_merge($tag['attributes'], array('mode' => 'old_price', 'pid' => $item->id)));
                    break;

                case 'tax':
                    $out = nvweb_product(array_merge($tag['attributes'], array('mode' => 'tax', 'pid' => $item->id)));
                    break;

                case 'add_to_cart':
                    $out = nvweb_product(array_merge($tag['attributes'], array('mode' => 'add_to_cart', 'pid' => $item->id)));
                    break;

                default:
                    // maybe a special tag not related to a source? (unimplemented)
            }
            break;

        case 'cart':
            switch($tag['attributes']['value'])
            {
                case 'quantity':
                    $out = core_decimal2string($item->_cart->quantity);
                    break;

                case 'price':
                    $base_price_wtax = $item->_cart->base_price + $item->_cart->base_price_tax_amount;
                    if($item->_cart->price != $base_price_wtax && ($tag['attributes']['original']=='true'))
                    {
                        $out = '<span class="line_product_price_before">'.core_price2string($item->_cart->price, $item->base_price_currency, @$tag['attributes']['return']).'</span>';
                        $out.= ' ';
                        $out.= '<span class="line_product_price_after">'.core_price2string($base_price_wtax, $item->base_price_currency, @$tag['attributes']['return']).'</span>';
                    }
                    else
                    {
                        $out = core_price2string($item->_cart->price, $item->base_price_currency, @$tag['attributes']['return']);
                    }
                    break;

                case 'coupon_amount':
                    if(!empty($item->_cart->coupon_amount))
                    {
                        $out = core_price2string($item->_cart->coupon_amount, $item->base_price_currency, @$tag['attributes']['return']);
                    }
                    break;

                case 'coupon_unit':
                    if(!empty($item->_cart->coupon_unit))
                    {
                        $out = core_price2string($item->_cart->coupon_unit, $item->base_price_currency, @$tag['attributes']['return']);
                    }
                    break;

                case 'subtotal':
                    $out = core_price2string($item->_cart->subtotal, $item->base_price_currency, @$tag['attributes']['return']);
                    break;

                case 'subtotal_with_taxes_without_coupon':
                    $out = core_price2string($item->_cart->subtotal_with_taxes_without_coupon, $item->base_price_currency, @$tag['attributes']['return']);
                    break;

                case 'remove':
                    $cart_link = nvweb_source_url('theme', 'cart');
                    $out = $cart_link . '?action=remove_product&product='.$item->id.'&ta='.$item->_cart->timestamp_added;
                    break;

                case 'add_one':
                    $cart_link = nvweb_source_url('theme', 'cart');
                    $out = $cart_link . '?action=add_one&product='.$item->id.'&ta='.$item->_cart->timestamp_added;
                    break;

                case 'remove_one':
                    $cart_link = nvweb_source_url('theme', 'cart');
                    $out = $cart_link . '?action=remove_one&product='.$item->id.'&ta='.$item->_cart->timestamp_added;
                    break;

                case 'update_quantity':
                    $cart_link = nvweb_source_url('theme', 'cart');
                    $out = $cart_link . '?action=update_qty&product='.$item->id.'&ta='.$item->_cart->timestamp_added.'&qty=';
                    break;

                default:
                    // run as if it is a source='product' nvlist tag
                    $tag['attributes']['source'] = 'product';
                    $out = nvweb_list_parse_tag(
                        $tag,
                        $item,
                        'product',
                        $item_relative_position,
                        $item_absolute_position,
                        $total
                    );
                    break;
            }
            break;

        case 'element': // useful also for source="structure" (but some are nonsense: title, comments, etc)
        case 'item':
		default:
			switch($tag['attributes']['value'])
			{
                case 'id':
                    $out = $item->id;
                    break;

                case 'slug':
                    $lang = $current['lang'];

                    if(!empty($tag['attributes']['lang']))
                        $lang = $tag['attributes']['lang'];

                    $out = $item->dictionary[$lang]['title'];

                    // remove spaces, special chars, etc.
                    $out = core_string_clean($out);
                    $out = slug($out);
                    break;

				case 'title':
                    $lang = $current['lang'];

                    if(!empty($tag['attributes']['lang']))
                        $lang = $tag['attributes']['lang'];

					$out = $item->dictionary[$lang]['title'];

                    if(!empty($tag['attributes']['length']))
                        $out = core_string_cut($out, $tag['attributes']['length'], '&hellip;', $tag['attributes']['length']);
					break;

                case 'author':
                    if(!empty($item->author))
                    {
                        $nu = new user();
                        $nu->load($item->author);
                        $out = $nu->username;
                        unset($nu);
                    }

                    if(empty($out))
                        $out = $website->name;
                    break;

				case 'date':
                case 'date_post':
					if(!empty($tag['attributes']['format'])) // custom date format
						$out = nvweb_content_date_format($tag['attributes']['format'], $item->date_to_display);
					else
						$out = date($website->date_format, $item->date_to_display);
					break;

				case 'content':
				case 'section':
                    if($source=='structure' && $tag['attributes']['source'] == 'item')
                    {
	                    $items = nvweb_content_items($item->id, true, 1, false, 'priority'); // we force finding the first non-embedded item ordered by priority
	                    if(empty($items))
	                        $items = nvweb_content_items($item->id, true, 1, true, 'priority'); // find the first embedded item ordered by priority
	                    $item = $items[0];
                    }

					$section = $tag['attributes']['section'];
					if(empty($section)) $section = 'main';
					$out = $item->dictionary[$current['lang']]['section-'.$section];

					if(!empty($tag['attributes']['length']))
                    {
                        $allowed_tags = '';
                        if(!empty($tag['attributes']['allowed_tags']))
                            $allowed_tags = explode(',', $tag['attributes']['allowed_tags']);
						$out = core_string_cut($out, $tag['attributes']['length'], '&hellip;', $allowed_tags);
                    }
					break;

				case 'comments':
				    if(method_exists($item, 'comments_count'))
    					$out = $item->comments_count();
                    else
                        $out = nvweb_content_comments_count($item->id);
					break;

				case 'gallery':
					$params = array('item' => $item->id);
					$params = array_merge($params, $tag['attributes']);
					$out = nvweb_gallery($params);
					break;

				case 'image':
				case 'photo':
					$photo = @array_shift(array_keys($item->galleries[0]));
					if(empty($photo))
						$out = NVWEB_OBJECT . '?type=transparent';
					else
					{
						$out = NVWEB_OBJECT . '?wid='.$website->id.'&id='.$photo.'&amp;disposition=inline&amp;width='.$tag['attributes']['width'].'&amp;height='.$tag['attributes']['height'].'&amp;border='.$tag['attributes']['border'].'&amp;opacity='.$tag['attributes']['opacity'];
					}
					break;

				case 'url':
				case 'path':
                    // rss -> full url
                    // item -> relative url
                    // embedded item -> category url
                    if($item->embedding==1 && $item->association=='category')
                    {
                        nvweb_menu_load_routes(); // load menu paths if not already done
                        $out = nvweb_prepare_link($structure['routes'][$item->category]);
                    }
                    else
                    {
                        $path = $item->paths[$current['lang']];
                        if(empty($path))
                            $path = '/node/'.$item->id;
                        $out = nvweb_prepare_link($path);
                    }
					break;

                case 'tags':
                    // pass all nvlist tag parameters to the content nvweb, but some attribute/values take preference
                    $nvweb_parameters = array_replace(
                        $tag['attributes'],
                        array(
                            'mode' => 'tags',
                            'id' => $item->id
                        )
                    );
                    $out = nvweb_content($nvweb_parameters);
                    break;

				case 'score':
                    $out = nvweb_votes_calc($item, $tag['attributes']['round'], $tag['attributes']['half'], $tag['attributes']['min'], $tag['attributes']['max']);
					break;

				case 'votes':
					$out = intval($item->votes);
					break;

                case 'views':
                    $out = intval($item->views);
                    break;

				case 'property':
					if($source=='structure' && $tag['attributes']['source'] == 'item')
                    {
	                    $items = nvweb_content_items($item->id, true, 1, false, 'priority'); // we force finding the first non-embedded item ordered by priority
	                    if(empty($items))
	                        $items = nvweb_content_items($item->id, true, 1, true, 'priority'); // find the first embedded item ordered by priority
	                    $item = $items[0];
	                    $source = "item";
                    }
					// pass all nvlist tag parameters to properties nvweb, but some attribute/values take preference
					$nvweb_properties_parameters = array_replace(
						$tag['attributes'],
						array(
							'mode'		=>	(($source=='structure' || $source=='category')? 'structure' : 'item'),
							'id'		=>	$item->id,
							'template'	=>	$item->template,
							'property'	=> 	(!empty($tag['attributes']['property'])? $tag['attributes']['property'] : $tag['attributes']['name'])
						)
					);

					$out = nvweb_properties($nvweb_properties_parameters);
					break;

                case 'function':
                    $function = @$tag['attributes']['function'];
                    if(!empty($function) && function_exists($function))
                        $out = call_user_func($function, array('item' => $item, 'vars' => $tag['attributes']));
                    break;

				default:
                    // maybe a special tag not related to a source? (unimplemented)
			}
			break;
	}

	return $out;
}

function nvweb_list_get_orderby($order)
{
    global $website;

    // convert order type to "order by" clause
    switch($order)
    {
        case 'random':
            $orderby = 'ORDER BY RAND()';
            break;

        case 'oldest':
            $orderby = 'ORDER BY pdate ASC';
            break;

        case 'alphabetical':
        case 'abc':
            $orderby = 'ORDER BY title ASC';
            break;

        case 'reverse_alphabetical':
        case 'zyx':
            $orderby = 'ORDER BY title DESC';
            break;

        case 'future':
        case 'from_today':
            $orderby = ' AND i.date_to_display > '.gmmktime(0,0,0,gmdate('m',$website->current_time()),gmdate('d',$website->current_time()),gmdate('Y',$website->current_time())).'
                         ORDER BY pdate ASC ';
            break;

        case 'priority':
            $orderby = ' ORDER BY IFNULL(i.position, 0) ASC, IFNULL(s.position, 0) ASC ';
            break;

        case 'rating':
            $orderby = ' ORDER BY i.score DESC ';
            break;

        case 'votes':
            $orderby = ' ORDER BY i.votes DESC ';
            break;

        case 'comments':
            $orderby = ' ORDER BY IFNULL(comments_published, 0) DESC ';
            break;

        case 'views':
            $orderby = ' ORDER BY i.views DESC ';
            break;

        case 'newest':
        case 'latest':
            $orderby = ' ORDER BY pdate DESC';
            break;

        // product special order types
        case 'sales':
            $orderby = ' ORDER BY sales DESC';
            break;

        case 'price_asc':
            $orderby = ' ORDER BY sale_price ASC';
            break;

        case 'price_desc':
            $orderby = ' ORDER BY sale_price DESC';
            break;


        default:

    }

    return $orderby;
}

function nvweb_list_isolate_lists($item_html)
{
    $nested_lists_fragments = array();
    $nested_lists_tags = nvweb_tags_extract($item_html, 'nv', true, true, 'UTF-8');

    foreach($nested_lists_tags as $tag)
    {
        $changed = false;

        switch($tag['attributes']['object'])
        {
            case 'list':
            case 'search':
                $template_end = nvweb_templates_find_closing_list_tag($item_html, $tag['offset']);
                $tag['length'] = $template_end - $tag['offset'] + strlen('</nv>'); // remove tag characters
                $list_template = substr($item_html, ($tag['offset'] + strlen($tag['full_tag'])), ($tag['length'] - strlen('</nv>') - strlen($tag['full_tag'])));

                $nested_list_uid = uniqid('nvlist-');
                $nested_lists_fragments[$nested_list_uid] = array_merge(
                    $tag['attributes'],
                    array('template' => $list_template)
                );

                $item_html = substr_replace($item_html, '<!--#'.$nested_list_uid.'#-->', $tag['offset'], $tag['length']);
                $changed = true;
                break;
        }

        // offsets may change due the replace
        if($changed)
        {
            list($item_html, $nested_sub_lists_fragments) = nvweb_list_isolate_lists($item_html);
            $nested_lists_fragments = array_merge($nested_lists_fragments, $nested_sub_lists_fragments);
            break;
        }
    }

    return array($item_html, $nested_lists_fragments);
}

function nvweb_list_isolate_conditionals($item_html, $only_by=array())
{
    $nested_conditionals_fragments = array();
    $conditional_tags = nvweb_tags_extract($item_html, 'nvlist_conditional', true, true, 'UTF-8');

    if(!empty($conditional_tags))
    {
        $tag = $conditional_tags[0];

        $template_end = nvweb_list_find_closing_conditional_tag($item_html, $tag['offset']); // + strlen($tag['full_tag']));

        $tag['length'] = $template_end - $tag['offset'] + strlen('</nvlist_conditional>'); // remove tag characters
        $conditional_template = substr(
            $item_html,
            ($tag['offset'] + strlen($tag['full_tag'])), // start
            ($tag['length'] - strlen('</nvlist_conditional>') - strlen($tag['full_tag'])) // length
        );

        // if this conditional is NOT of one of the allowed "conditional by" types, then all its content must be cleared
        if(!empty($only_by) && !in_array($tag['attributes']['by'], $only_by))
            $conditional_template = '';

        // find inner conditionals before replacing the conditional found
        list($conditional_template, $nested_sub_conditionals_fragments) = nvweb_list_isolate_conditionals($conditional_template);
        $nested_conditionals_fragments = array_merge($nested_sub_conditionals_fragments, $nested_conditionals_fragments);

        $nested_conditional_vars = array_merge($tag, array('nvlist_conditional_template' => $conditional_template));
        $nested_conditional_uid = uniqid('nvlist_conditional-');
        $nested_conditionals_fragments[$nested_conditional_uid] = $nested_conditional_vars;
        $item_html = substr_replace($item_html, '<nvlist_conditional_placeholder id="'.$nested_conditional_uid.'" />', $tag['offset'], $tag['length']);

        // process other conditionals
        list($item_html, $nested_sub_conditionals_fragments) = nvweb_list_isolate_conditionals($item_html);
        $nested_conditionals_fragments = array_merge($nested_sub_conditionals_fragments, $nested_conditionals_fragments);
    }

    return array($item_html, $nested_conditionals_fragments);
}

function nvweb_list_find_closing_conditional_tag($html, $offset)
{
    $found = false;
    $level = 0;
    $closing_tag_position = 0;
    $loops = 0;

    // find next nvlist_conditional tag (opening or closing)
    // if it is an opening tag --> level + 1
    // if it is a closing tag --> level - 1
    //      if level = 0, that's the closing tag we were looking for
    //      else repeat from current offset

    while(!$found && $loops < 1000)
    {
        $next_opening = stripos($html, '<nvlist_conditional ', $offset);
        $next_closing = stripos($html, '</nvlist_conditional>', $offset);

        if($next_opening!==false && $next_opening < $next_closing)
        {
            // there is an opening tag before a closing tag, so there is an inner nvlist_conditional
            // move the offset to the opening tag found
            $offset = $next_opening + strlen('<nvlist_conditional ');
            $level++;
        }
        else
        {
            // found a closing tag without an inner nvlist_conditional opening tag
            $level--;
            if($level > 0)
            {
                $offset = $next_closing + strlen('</nvlist_conditional>');
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
}


function nvweb_list_parse_conditional($tag, $item, $item_html, $position, $total)
{
    global $current;

    $out = '';

    switch($tag['attributes']['by'])
    {
        case 'property':
            if(empty($item)) return ''; // can't parse values of empty objects

            $property_name = $tag['attributes']['property_id'];
            if(empty($property_name))
                $property_name = $tag['attributes']['property_name'];

            if(!method_exists($item, 'property'))
                return "";

            $property_value = $item->property($property_name);
            $property_definition = $item->property_definition($property_name);

            $condition_value = $tag['attributes']['property_value'];

            if(in_array($property_definition->type, array('image', "file")))
            {
                if($property_value == '0')
                    $property_value = "";
            }

            // process special comparing values
            switch($property_definition->type)
            {
                case 'date':
                    if($condition_value == 'today')
                    {
                        $now = getdate(core_time());
                        $condition_value = mktime(0, 0, 0, $now['mon'], $now['mday'], $now['year']);
                    }
                    else if($condition_value == 'now')
                    {
                        $condition_value = core_time();
                    }
                    break;

                case 'boolean':
                    if($property_value=="" && isset($property_definition->dvalue))
                        $property_value = $property_definition->dvalue;

                    break;
            }

            $condition = false;
            if(isset($tag['attributes']['property_empty']))
            {
                // special case: for multilanguage properties check the active language
                if($property_definition->type == 'text' && is_array($property_value))
                {
                    $property_value = $property_value[$current['lang']];
                }

                if( $tag['attributes']['property_empty']=='true' && empty($property_value) ||
                    $tag['attributes']['property_empty']=='false' && !empty($property_value)
                )
                {
                    $condition = true;
                }
            }
            else
            {
                switch($tag['attributes']['property_compare'])
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
            }

            if($condition)
            {
                // parse the contents of this condition on this round
                $out = $item_html;
            }
            else
            {
                // remove this conditional html code on this round
                $out = '';
            }
            break;

        case 'product':
            if(isset($tag['attributes']['offer']))
            {
                $on_offer = $item->on_offer();
                if(($tag['attributes']['offer']=='true' || $tag['attributes']['offer']=='1') && $on_offer)
                    $out = $item_html;
                else if(($tag['attributes']['offer']=='false' || $tag['attributes']['offer']=='0') && !$on_offer)
                    $out = $item_html;
                else
                    $out = '';
            }

            if(isset($tag['attributes']['top']))
            {
                $is_top = $item->is_top(@$tag['attributes']['top_limit']);
                if(($tag['attributes']['top']=='true' || $tag['attributes']['top']=='1') && $is_top)
                    $out = $item_html;
                else if(($tag['attributes']['top']=='false' || $tag['attributes']['top']=='0') && !$is_top)
                    $out = $item_html;
                else
                    $out = '';
            }

            if(isset($tag['attributes']['new']))
            {
                $is_new = $item->is_new(@$tag['attributes']['since']);
                if(($tag['attributes']['new']=='true' || $tag['attributes']['new']=='1') && $is_new)
                    $out = $item_html;
                else if(($tag['attributes']['new']=='false' || $tag['attributes']['new']=='0') && !$is_new)
                    $out = $item_html;
                else
                    $out = '';
            }
            break;

        case 'template':
        case 'templates':
            if(empty($item)) return ''; // can't parse values of empty objects

            $templates = array();
            if(isset($tag['attributes']['templates']))
                $templates = explode(",", $tag['attributes']['templates']);
            else if(isset($tag['attributes']['template']))
                $templates = array($tag['attributes']['template']);

            if(empty($item->template))
            {
                // check if the item is embedded in a category, so we have to get the template from the category, not the item
                if(get_class($item) == 'item' && $item->association == 'category' && $item->embedding == 1)
                {
                    // assign template from its category
                    $item_category = new structure();
                    $item_category->load($item->category);
                    $item->template = $item_category->template;
                }
            }

            if(in_array($item->template, $templates))
            {
                // the template matches the condition, apply
                $out = $item_html;
            }
            else
            {
                // remove this conditional html code on this round
                $out = '';
            }
            break;

        case 'position':
            if(empty($item)) return ''; // can't parse values of empty objects

            if(isset($tag['attributes']['each']))
            {
                if($position % $tag['attributes']['each'] == 0) // condition applies
                    $out = $item_html;
                else // remove the full nvlist_conditional tag, doesn't apply here
                    $out = '';
            }
            else if(isset($tag['attributes']['range']))
            {
                list($pos_min, $pos_max) = explode('-', $tag['attributes']['range']);

                if(($position+1) >= $pos_min && ($position+1) <= $pos_max)
                    $out = $item_html;
                else
                    $out = '';
            }
            else if(isset($tag['attributes']['position']))
            {
                switch($tag['attributes']['position'])
                {
                    case 'first':
                        if($position == 0)
                            $out = $item_html;
                        else
                            $out = '';
                        break;

                    case 'not_first':
                        if($position > 0)
                            $out = $item_html;
                        else
                            $out = '';
                        break;

                    case 'last':
                        if($position == ($total-1))
                            $out = $item_html;
                        else
                            $out = '';
                        break;

                    case 'not_last':
                        if($position != ($total-1))
                            $out = $item_html;
                        else
                            $out = '';
                        break;

                    default:
                        // position "x"?
                        if($tag['attributes']['position']==='0')
                            $tag['attributes']['position'] = 1;
                        if(($position+1) == $tag['attributes']['position'])
                            $out = $item_html;
                        else
                            $out = '';
                        break;
                }
            }
            break;

        case 'block':
            if(empty($item)) return ''; // can't parse values of empty objects

            // $item may be a block object or a block group block type
            $output_condition = true;
            if(isset($tag['attributes']['type']))
            {
                if( !(  $tag['attributes']['type'] == $item->type ||
                        $tag['attributes']['type'] == $item->id
                    )
                )
                {
                    $output_condition = false;
                }
            }

            // conditional by block trigger type
            if(isset($tag['attributes']['trigger']))
            {
                // allow using "hidden" for internally set "(empty)" types
                if( $tag['attributes']['trigger'] == 'hidden' )
                    $tag['attributes']['trigger'] = "";

                if( $item->trigger['trigger-type'][$current['lang']] != $tag['attributes']['trigger'] )
                    $output_condition = false;
            }

            if($output_condition)
                $out = $item_html;
            else
                $out = "";

            // does the block have a link defined?
            if(isset($tag['attributes']['linked']))
            {
                $block_has_link = in_array(
                    $item->action['action-type'][$current['lang']],
                    array("web", "web-n", "file", "image", "javascript")
                );

                if( $tag['attributes']['linked'] == "true" && $block_has_link)
                {
                    $out = $item_html;
                }
                else if( $tag['attributes']['linked'] == "false" && !$block_has_link)
                {
                    $out = $item_html;
                }
                else
                {
                    // no match, discard this conditional
                    $out = '';
                }
            }
            break;

        case 'block_type':
            if(empty($item)) return ''; // can't parse values of empty objects

            // $item is a block type defined in a block group (to add a title before listing blocks of that kind)
            if(isset($tag['attributes']['type']) && $item->_object_type == "block_group_block_type")
            {
                if( $tag['attributes']['type'] == $item->type || $tag['attributes']['type'] == $item->id )
                {
                    $out = $item_html;
                }
                else
                {
                    // no match, discard this conditional
                    $out = '';
                }
            }
            else
            {
                $out = '';
            }
            break;

        case 'access':
            if(empty($item)) return ''; // can't parse values of empty objects

            $access = 0;
            switch($tag['attributes']['access'])
            {
                case 'navigate_user':
                    if(!empty($_SESSION['APP_USER#'.APP_UNIQUE]))
                    {
                        $access = 0; // everybody
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

        case 'gallery':
            if(empty($item)) return ''; // can't parse values of empty objects

            if($tag['attributes']['empty']=='true')
            {
                if(empty($item->galleries[0]))
                    $out = $item_html;
            }
            else if($tag['attributes']['empty']=='false')
            {
                if(!empty($item->galleries[0]))
                    $out = $item_html;
            }
            break;

        case 'tags':
            if(empty($item)) return ''; // can't parse values of empty objects

            if($tag['attributes']['empty']=='true')
            {
                if(empty($item->dictionary[$current['lang']]['tags']))
                    $out = $item_html;
            }
            else if($tag['attributes']['empty']=='false')
            {
                if(!empty($item->dictionary[$current['lang']]['tags']))
                    $out = $item_html;
            }
            break;

        case 'structure':
            if(empty($item)) return ''; // can't parse values of empty objects

            if( isset($tag['attributes']['show_in_menus']) && isset($item->visible) )
            {
                if($item->visible == 1 && in_array($tag['attributes']['show_in_menus'], array(1, true, "true")))
                    $out = $item_html;
                else if($item->visible != 1 && !in_array($tag['attributes']['show_in_menus'], array(1, true, "true")))
                    $out = $item_html;
                else
                    $out = "";
            }
            else
            {
                // no match, discard this conditional
                $out = '';
            }
            break;

        case 'count':
            // check the number of results found
            // note: this is called also WHEN resultset is empty
            if( $tag['attributes']['value'] == $total    ||
                ($tag['attributes']['value'] == "empty" && $total == 0)
            )
            {
                $out = $item_html;
            }
            else
            {
                $out = '';
            }
            break;

        case 'comment':

            switch($tag['attributes']['check'])
            {
                case 'website':
                    $has_website = false;

                    if(!empty($item->url))
                    {
                        $has_website = true;
                    }
                    else if(!empty($item->user))
                    {
                        $wu = new webuser();
                        $wu->load($item->user);
                        if(!empty($wu->social_website))
                            $has_website = true;
                    }

                    if($has_website && (!isset($tag['attributes']['empty']) || $tag['attributes']['empty'] == 'false'))
                        $out = $item_html;
                    else if(!$has_website && @$tag['attributes']['empty'] == 'true')
                        $out = $item_html;
                    else
                        $out = "";
                    break;

                default:
                    $out = "";
            }
            break;

        case 'query':
            switch($tag['attributes']['check'])
            {
                case 'field':
                    if( isset($item->_query->{$tag['attributes']['field']}) &&
                        !empty($item->_query->{$tag['attributes']['field']})
                    )
                    {
                        $out = $item_html;
                    }
                    break;

                case 'field_range':
                    if( isset($item->_query->{$tag['attributes']['field']}))
                    {
                        $min = value_or_default($tag['attributes']['min'], -PHP_INT_MAX);
                        $max = value_or_default($tag['attributes']['max'], PHP_INT_MAX);

                        if( ($min && $item->_query->{$tag['attributes']['field']} >= $min)  &&
                            ($max && $item->_query->{$tag['attributes']['field']} <= $max)
                        )
                        {
                            $out = $item_html;
                        }
                    }
                    break;

                default:
                    $out = '';
            }
            break;

        default:
            // unknown nvlist_conditional, discard
            $out = '';
    }

    return $out;
}

function nvweb_list_parse_filters($raw, $object='item')
{
    global $website;
    global $current;

    $alias = 'i';
    if($object=='product')
        $alias = 'p';

    $filters = array();

    if(!is_array($raw))
    {
        $raw = str_replace("'", '"', $raw);
        $aFilters = json_decode($raw, true);
    }
    else
    {
        $aFilters = $raw;
    }

    if(APP_DEBUG && json_last_error() > 0)
        debugger::console($raw, json_last_error_msg());

    $comparators = array(
        'eq' => '=',
        'neq' => '!=',
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<='
    );

    for($f=0; $f < count($aFilters); $f++)
    {
        $filter = $aFilters[$f];

        $key = array_keys($filter);
        $key = $key[0];
        $value = $filter[$key];

        if(substr($key, 0, 9) == 'property.')
        {
            // object property value
            // TODO: filters for values in DICTIONARY
            $key = substr($key, 9);

            if(!is_array($value))
            {
                if(substr($value, 0, 1)=='$')
                {
                    if(!isset($_REQUEST[substr($value, 1)]))
                        continue;   // ignore this filter

                    $value = $_REQUEST[substr($value, 1)];
                    if(empty($value)) // ignore empty values
                        continue;
                }
                else if(strpos($value, 'property.') === 0)
                {
                    // retrieve the property value
                    $value = nvweb_properties(
                        array(
                            'property' => str_replace("property.", "", $value)
                        )
                    );
                }

                $filters[] = ' AND '.$alias.'.id IN ( 
                                 SELECT node_id 
                                   FROM nv_properties_items
                                  WHERE website = '.$website->id.' AND
                                        property_id = '.protect($key).' AND
                                        element = "'.$object.'" AND
                                        value = '.protect($value).'
                               )';
            }
            else
            {
                foreach($value as $comp_type => $comp_value)
                {
                    if(!is_array($comp_value) && substr($comp_value, 0, 1)=='$')
                    {
                        if(!isset($_REQUEST[substr($comp_value, 1)]))
                            continue;   // ignore this filter

                        $comp_value = $_REQUEST[substr($comp_value, 1)];
                        if(empty($comp_value)) // ignore empty values
                            continue;
                    }
                    else if(!is_array($comp_value) && strpos($comp_value, 'property.') === 0)
                    {
                        // retrieve the property value
                        $comp_value = nvweb_properties(
                            array(
                                'property' => str_replace("property.", "", $comp_value)
                            )
                        );
                    }

                    if(isset($comparators[$comp_type]))
                    {
                        $filters[] = ' 
                            AND '.$alias.'.id IN ( 
                                 SELECT node_id 
                                   FROM nv_properties_items
                                  WHERE website = '.$website->id.' AND
                                        property_id = '.protect($key).' AND
                                        element = "'.$object.'" AND
                                        value '.$comparators[$comp_type].' '.protect($comp_value, null, true).'
                            )';
                    }
                    else if($comp_type == 'like' || $comp_type == 'not_like')
                    {
                        if(is_array($comp_value))
                        {
                            // multivalue, query with REGEXP: http://dev.mysql.com/doc/refman/5.7/en/string-functions.html#function_regexp
                            $filters[] = ' 
                                AND '.$alias.'.id IN ( 
                                     SELECT node_id 
                                       FROM nv_properties_items
                                      WHERE website = '.$website->id.' AND
                                            property_id = '.protect($key).' AND
                                            element = "'.$object.'" AND
                                            value '.($comp_type=='like'? 'REGEXP' : 'NOT REGEXP').' "'.implode('|', $comp_value).'"
                                )';
                        }
                        else
                        {
                            // single value, standard LIKE
                            $filters[] = ' 
                                AND '.$alias.'.id IN ( 
                                     SELECT node_id 
                                       FROM nv_properties_items
                                      WHERE website = '.$website->id.' AND
                                            property_id = '.protect($key).' AND
                                            element = "'.$object.'" AND
                                            value '.($comp_type=='like'? 'LIKE' : 'NOT LIKE').' '.protect('%'.$comp_value.'%', null, true).'
                                )';
                        }
                    }
                    else if($comp_type == 'in' || $comp_type == 'nin')
                    {
                        if($comp_type == 'nin')
                            $comp_type = 'NOT IN';
                        else
                            $comp_type = 'IN';

                        if(!is_array($comp_value))
                            $comp_value = explode(",", $comp_value);

                        if(empty($comp_value))
                            $comp_value = array(0); // avoid SQL query exception

                        $filters[] = ' 
                            AND '.$alias.'.id IN ( 
                                SELECT node_id 
                                  FROM nv_properties_items
                                 WHERE website = '.$website->id.' AND
                                        property_id = '.protect($key).' AND
                                        element = "'.$object.'" AND
                                        value '.$comp_type.'('.
                                            implode(
                                                ",",
                                                array_map(
                                                    function($v)
                                                    {
                                                        return protect($v);
                                                    },
                                                    array_values($comp_value)
                                                )
                                            ).')
                            )';
                    }
                    else if($comp_type == 'has' || $comp_type == 'hasnot')
                    {
                        if($comp_type == 'hasnot')
                            $comp_type = 'NOT FIND_IN_SET';
                        else
                            $comp_type = 'FIND_IN_SET';

                        if(!is_array($comp_value))
                            $comp_value = explode(",", $comp_value);

                        if(empty($comp_value))
                            $comp_value = array(0); // avoid SQL query exception

                        foreach($comp_value as $comp_value_part)
                        {
                            $filters[] = ' 
                                AND '.$alias.'.id IN ( 
                                    SELECT node_id 
                                      FROM nv_properties_items
                                     WHERE website = ' . $website->id . ' AND
                                            property_id = ' . protect($key) . ' AND
                                            element = "'.$object.'" AND
                                            ' . $comp_type . '(' . protect($comp_value_part) .', value)                               
                                )';
                        }
                    }
                }
            }
        }
        else
        {
            $direct_filter = false;

            // object value
            switch($key)
            {
                // item & product common values
                case 'id':
                    $field = $alias.'.id';
                    $direct_filter = true;
                    break;

                case 'author':
                    $field = $alias.'.author';
                    $direct_filter = true;
                    break;

                case 'date_to_display':
                    $field = $alias.'.date_to_display';
                    $direct_filter = true;
                    break;

                case 'score':
                    $field = $alias.'.score';
                    $direct_filter = true;
                    break;

                case 'votes':
                    $field = $alias.'.votes';
                    $direct_filter = true;
                    break;

                // product specific values or filters
                case 'offer':
                    if($value == 'true' || $value===true)
                    {
                        $filters[] = ' AND (
                            ' . $alias . '.offer_price > 0 
                            AND ( ' . $alias . '.offer_begin_date = 0 OR '.core_time().' >= '.$alias.'.offer_begin_date)
                            AND ( ' . $alias . '.offer_end_date = 0 OR '.core_time().' <= '.$alias.'.offer_end_date)
                        )';
                    }
                    else
                    {
                        $filters[] = ' AND (
                            ' . $alias . '.offer_price = 0 
                            OR ( ' . $alias . '.offer_begin_date > 0 AND '.core_time().' < '.$alias.'.offer_begin_date)
                            OR ( ' . $alias . '.offer_end_date > 0 AND '.core_time().' > '.$alias.'.offer_end_date)
                        )';
                    }
                    $direct_filter = false;
                    break;

                default:
                    continue;
                    break;
            }

            if($direct_filter)
            {
                if(!is_array($value))
                {
                    if(substr($value, 0, 1)=='$')
                    {
                        if(!isset($_REQUEST[substr($value, 1)]))
                            continue;   // ignore this filter
                        
                        $value = $_REQUEST[substr($value, 1)];
                        if(empty($value)) // ignore empty values
                            continue;
                    }
                    else if(strpos($value, 'property.') === 0)
                    {
                        // retrieve the property value
                        $value = nvweb_properties(
                            array(
                                'property' => str_replace("property.", "", $value)
                            )
                        );
                    }

                    $filters[] = ' AND '.$field.' = '.protect($value);
                }
                else
                {
                    foreach($value as $comp_type => $comp_value)
                    {
                        if(!is_array($comp_value) && substr($comp_value, 0, 1)=='$')
                        {
                            if(!isset($_REQUEST[substr($comp_value, 1)]))
                                continue;   // ignore this filter
                            $comp_value = $_REQUEST[substr($comp_value, 1)];
                            if(empty($comp_value)) // ignore empty values
                                continue;
                        }
                        else if(!is_array($comp_value) && strpos($comp_value, 'property.') === 0)
                        {
                            // retrieve the property value
                            $comp_value = nvweb_properties(
                                array(
                                    'property' => str_replace("property.", "", $comp_value)
                                )
                            );
                        }

                        if(isset($comparators[$comp_type]))
                        {
                            $filters[] = ' AND ' . $field . ' ' . $comparators[$comp_type] . ' ' . protect($comp_value, null, true);
                        }
                        else if($comp_type == 'like' || $comp_type == 'not_like')
                        {
                            if(is_array($comp_value))
                            {
                                // multivalue, query with REGEXP: http://dev.mysql.com/doc/refman/5.7/en/string-functions.html#function_regexp
                                $filters[] = ' AND ' . $field . ' ' . ($comp_type == 'like' ? 'REGEXP' : 'NOT REGEXP') . ' "' . implode('|' . $comp_value) . '"';
                            }
                            else
                            {
                                // single value, standard LIKE
                                $filters[] = ' AND ' . $field . ' ' . ($comp_type == 'like' ? 'LIKE' : 'NOT LIKE') . ' ' . protect('%' . $comp_value . '%', null, true);
                            }
                        }
                        else if($comp_type == 'in' || $comp_type == 'nin')
                        {
                            if($comp_type == 'nin')
                                $comp_type = 'NOT IN';
                            else
                                $comp_type = 'IN';

                            if(is_array($comp_value))
                            {
                                $comp_value = implode(
                                    ",",
                                    array_map(
                                        function ($v)
                                        {
                                            return protect($v);
                                        },
                                        array_values($comp_value)
                                    )
                                );
                            }
                            else if(empty($comp_value))
                            {
                                $comp_value = 0; // avoid SQL query exception
                            }

                            $filters[] = ' AND '.$field.' '.$comp_type.'('.$comp_value.')';
                        }
                        else if($comp_type == 'has' || $comp_type == 'hasnot')
                        {
                            if($comp_type == 'hasnot')
                                $comp_type = 'NOT FIND_IN_SET';
                            else
                                $comp_type = 'FIND_IN_SET';

                            if(!is_array($comp_value))
                                $comp_value = explode(",", $comp_value);

                            if(empty($comp_value))
                                $comp_value = array(); // avoid SQL query exception

                            foreach($comp_value as $comp_value_part)
                            {
                                $filters[] = ' 
                                    AND '.$alias.'.id IN ( 
                                        SELECT node_id 
                                          FROM nv_properties_items
                                         WHERE website = ' . $website->id . ' AND
                                                property_id = ' . protect($key) . ' AND
                                                element = "'.$object.'" AND
                                                ' . $comp_type . '(' . protect($comp_value_part) .', '.$field.')                               
                                    )';
                            }
                        }
                    }
                }
            }
        }
    }

    $filters = implode("\n", $filters);

    return $filters;
}

function nvweb_list_get_from_rss($url, $cache_time=3600, $offset=0, $items=null, $permission=null, $order=null)
{
    $feed = new feed_parser();

    $feed->set_cache($cache_time);
    $feed->load($url);
    list($channel, $articles, $count) = $feed->parse($offset, $items, $order);

    $items = item::convert_from_rss($articles);

    return array($items, $count);
}

function nvweb_list_get_from_twitter($username, $cache_time=3600, $offset, $items=10, $permission, $order)
{
    $url = 'https://api.twitter.com/1/statuses/user_timeline.rss?include_rts=true&contributor_details=false&screen_name='.$username.'&count='.$items;

    $feed = new feed_parser();

    $feed->set_cache($cache_time);
    $feed->load($url);
    list($channel, $articles) = $feed->parse('items', $offset, $order);

    $items = item::convert_from_rss($articles);

    return array($items, count($items));
}

function nvweb_list_paginator($type, $page, $total, $items_per_page, $params=array())
{
	global $theme;

	$out = array();

	$pages = ceil($total / $items_per_page);

    $paginator_text_prev = '&#10092;';
    $paginator_text_next = '&#10093;';

	$paginator_text_first = '&#10092;&#10072;';
    $paginator_text_last = '&#10072;&#10093;';
	$paginator_text_etc = '&hellip;';


	if(!empty($params['paginator_prev']))
		$paginator_text_prev = $theme->t($params['paginator_prev']);

	if(!empty($params['paginator_next']))
		$paginator_text_next = $theme->t($params['paginator_next']);

	if(!empty($params['paginator_first']))
		$paginator_text_first = $theme->t($params['paginator_first']);

	if(!empty($params['paginator_last']))
		$paginator_text_last = $theme->t($params['paginator_last']);

	if(!empty($params['paginator_etc']))
		$paginator_text_etc = $theme->t($params['paginator_etc']);

    // keep existing URL variables except "page" and "route" (route is an internal navigate variable)
    $url_suffix = '';
    if(!is_array($_GET)) $_GET = array();
    foreach($_GET as $key => $val)
    {
        if($key=='page' || $key=='route') continue;
        if(is_array($val))
        {
            foreach($val as $val_item)
            {
                $url_suffix .= '&' . $key . '[]=' . $val_item;
            }
        }
        else
        {
            $url_suffix .= '&' . $key . '=' . $val;
        }
    }

    if($pages > 1)
    {
	    switch($type)
	    {
		    case 'prev/next':
				$out[] = '<div class="paginator">';

		        if($page > 1)
			        $out[] = '<a href="?page='.($page - 1).$url_suffix.'" rel="prev">'.$paginator_text_prev.'</a>'; // <

			    if($page < $pages)
			        $out[] = '<a href="?page='.($page + 1).$url_suffix.'" rel="next">'.$paginator_text_next.'</a>'; // ❭

		        $out[] = '<div style=" clear: both; "></div>';

		        $out[] = '</div>';
			    break;

		    case 'first/prev/next/last':
				$out[] = '<div class="paginator">';

		        if($page > 1)
		        {
			        $out[] = '<a href="?page=1'.$url_suffix.'" rel="first">'.$paginator_text_first.'</a>'; // <|
			        $out[] = '<a href="?page='.($page - 1).$url_suffix.'" rel="prev">'.$paginator_text_prev.'</a>'; // <
		        }

			    if($page < $pages)
			    {
			        $out[] = '<a href="?page='.($page + 1).$url_suffix.'" rel="next">'.$paginator_text_next.'</a>'; // ❭
			        $out[] = '<a href="?page='.($pages - 1).$url_suffix.'" rel="last">'.$paginator_text_last.'</a>'; // |❭
			    }

		        $out[] = '<div style=" clear: both; "></div>';

		        $out[] = '</div>';

			    break;

		    case 'prev/central/next':
				$out[] = '<div class="paginator">';

		        if($page > 1)
			        $out[] = '<a href="?page='.($page - 1).$url_suffix.'" rel="prev">'.$paginator_text_prev.'</a>'; // <

		        for($p = $page - 2; $p < $page + 3; $p++)
		        {
		            if($p < 1) continue;

		            if($p > $pages) break;

		            if($p==$page)
		                $out[] = '<a href="?page='.$p.$url_suffix.'" class="paginator-current">'.$p.'</a>';
		            else
		                $out[] = '<a href="?page='.$p.$url_suffix.'">'.$p.'</a>';
		        }

		        if($page < $pages) $out[] = '<a href="?page='.($page + 1).$url_suffix.'" rel="next">'.$paginator_text_next.'</a>'; // ❭

		        $out[] = '<div style=" clear: both; "></div>';

		        $out[] = '</div>';
			    break;


		    case 'first/prev/central/next/last':
				$out[] = '<div class="paginator">';

				if($page > 1)
		        {
			        $out[] = '<a href="?page=1'.$url_suffix.'" rel="first">'.$paginator_text_first.'</a>'; // <|
			        $out[] = '<a href="?page='.($page - 1).$url_suffix.'" rel="prev">'.$paginator_text_prev.'</a>'; // <
		        }

		        for($p = $page - 2; $p < $page + 3; $p++)
		        {
		            if($p < 1) continue;

		            if($p > $pages) break;

		            if($p==$page)
		                $out[] = '<a href="?page='.$p.$url_suffix.'" class="paginator-current">'.$p.'</a>';
		            else
		                $out[] = '<a href="?page='.$p.$url_suffix.'">'.$p.'</a>';
		        }

                if($page < $pages)
			    {
			        $out[] = '<a href="?page='.($page + 1).$url_suffix.'" rel="next">'.$paginator_text_next.'</a>'; // ❭
			        $out[] = '<a href="?page='.($pages - 1).$url_suffix.'" rel="last">'.$paginator_text_last.'</a>'; // |❭
			    }

		        $out[] = '<div style=" clear: both; "></div>';

		        $out[] = '</div>';
		    break;


		    case 'all_pages':
				$out[] = '<div class="paginator">';

		        for($p = 1; $p <= $pages; $p++)
		        {
		            if($p==$page)
		                $out[] = '<a href="?page='.$p.$url_suffix.'" class="paginator-current">'.$p.'</a>';
		            else
		                $out[] = '<a href="?page='.$p.$url_suffix.'">'.$p.'</a>';
		        }

		        $out[] = '<div style=" clear: both; "></div>';

		        $out[] = '</div>';
			    break;

		    case 'true':
		    case 'classic':
			    $out[] = '<div class="paginator">';

		        if($page > 1) $out[] = '<a href="?page='.($page - 1).$url_suffix.'" rel="prev">'.$paginator_text_prev.'</a>'; // <

		        if($page == 4)
		            $out[] = '<a href="?page=1'.$url_suffix.'">1</a>';
		        else if($page > 3)
		            $out[] = '<a href="?page=1'.$url_suffix.'">1</a><span class="paginator-etc">'.$paginator_text_etc.'</span>';

		        for($p = $page - 2; $p < $page + 3; $p++)
		        {
		            if($p < 1) continue;

		            if($p > $pages) break;

		            if($p==$page)
		                $out[] = '<a href="?page='.$p.$url_suffix.'" class="paginator-current">'.$p.'</a>';
		            else
		                $out[] = '<a href="?page='.$p.$url_suffix.'">'.$p.'</a>';
		        }

		        if($page + 3 == $pages)
		            $out[] = '<a href="?page='.$pages.$url_suffix.'">'.$pages.'</a>';
		        else if($page + 3 < $pages)
		            $out[] = '<span class="paginator-etc">'.$paginator_text_etc.'</span><a href="?page='.$pages.$url_suffix.'">'.$pages.'</a>';

		        if($page < $pages) $out[] = '<a href="?page='.($page + 1).$url_suffix.'" rel="next">'.$paginator_text_next.'</a>'; // ❭

		        $out[] = '<div style=" clear: both; "></div>';

		        $out[] = '</div>';
			    break;

		    case 'false':
		    default:
			    // no paginator
	    }
    }

	if(!empty($params['paginator_tag_id']))
	{
		$paginator_html = implode("\n", $out);

		$paginator_func = function() use ($params, $paginator_html)
		{
			global $html;
			$html = nvweb_replace_tag_contents(
				$params['paginator_tag_id'],
				$paginator_html,
				$html
			);
		};

		nvweb_after_body('php', $paginator_func);
		$paginator_html = "";
	}
	else
	{
		$paginator_html = implode("\n", $out);
	}

	return $paginator_html;
}

?>