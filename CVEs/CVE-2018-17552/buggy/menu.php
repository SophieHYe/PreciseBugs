<?php
nvweb_webget_load('properties');

function nvweb_menu($vars=array())
{
	global $website;
	global $DB;
	global $structure;
	global $current;

	$out = '';

	nvweb_menu_load_dictionary();
	nvweb_menu_load_routes();	
	nvweb_menu_load_structure();
	nvweb_menu_load_actions();
	
	$parent = intval(@$vars['parent']) + 0;
	$from = intval(@$vars['from']) + 0;
	$of	= intval(@$vars['of']) + 0;

    if(isset($vars['parent']) && !is_numeric($vars['parent']))
    {
        // assume parent attribute contains a property_id which has the category value
        $parent_property = nvweb_properties(array(
            'property' => $vars['parent']
        ));
        if(!empty($parent_property))
            $parent = $parent_property;
    }

	if($of > 0)
	{
		// get options of the parent x in the order of the structure
		// example:
		//	Home [5]	Products [6]	Contact [7]
		//					|
		//					-- Computers [8]	Mobile Phones [9]
		//							|
		//							-- Apple [10]	Dell [11]
		//
		//	we want the categories under Products [6]: "Computers" [8] and "Mobile Phones" [9]
		//	of: 2 (second item in the main structure)
		//  <nv object="nvweb" name="menu" of="2" />
		$parent = $structure['cat-0'][intval($of)-1]->id;
	}

    if(empty($current['hierarchy']))	// calculate
    {
        $inverse_hierarchy = array();
        // discover the parent from which get the menu
        if(!empty($current['category']))
        {
            $inverse_hierarchy[] = $current['category'];
            $last = $current['category'];
        }
        else
        {
            $inverse_hierarchy[] = $current['object']->category;
            $last = $current['object']->category;
        }

        // get category parents until root (to know how many levels count from)
        while($last > 0)
        {
            $last = $DB->query_single('parent', 'nv_structure', ' id = '.protect($last));
            $inverse_hierarchy[] = $last;
        }
        $current['hierarchy'] = array_reverse($inverse_hierarchy);
    }

	if($from > 0)
	{
		// get a certain level of the menu based on the path to current item category with offset 
		// example:
		//	Home [5]	Products [6]	Contact [7]
		//					|
		//					-- Computers [8]	Mobile Phones [9]
		//							|
		//							-- Apple [10]	Dell [11]
		//
		//	current item is a Dell computer (category = 11)
		//  we want the menu from level 1
		//	from: 1	--> 8, 9		
		$parent = $current['hierarchy'][$from];

		if(is_null($parent)) return '';	// the requested level of menu does not exist under the current category
	}

	$option = -1;	
	if(isset($vars['option']))
		$option = intval($vars['option']);

    if($vars['mode']=='next' || $vars['mode']=='previous')
        $out = nvweb_menu_render_arrow($vars);
    else
    {
        $out = nvweb_menu_generate($vars['mode'], $vars['levels'], $parent, 0, $option, $vars['class']);

        if($vars['mode'] == 'select')
        {
            nvweb_after_body('js', '
                // jQuery required
                $("select.menu_level_0").off("change").on("change", function()
                {
                    var option = $(this).find("option[value=" + $(this).val() + "]");
                    if($(option).attr("target") == "_blank")
                        window.open($(option).attr("href"));
                    else
                    {
                        if($(option).attr("href")=="#")
                            window.location.replace($(option).attr("href") + "sid_" + $(option).attr("value"));
                        else
                            window.location.replace($(option).attr("href"));
                    }
                });
            ');
        }
    }

	return $out;
}

function nvweb_menu_generate($mode='ul', $levels=0, $parent=0, $level=0, $option=-1, $class='')
{
	global $structure;
	global $current;

	$out = "";

	if($level >= $levels && $levels > 0)
        return '';
	
	nvweb_menu_load_structure($parent);

	if(!empty($structure['cat-'.$parent]))
	{	
		switch($mode)
		{
            case 'category_title':
			case 'current_title':
                if($current['type']=='structure')
				    $out = $structure['dictionary'][$current['category']];
                else if($current['type']=='item')
                    $out = $structure['dictionary'][$current['object']->category];
				break;

            case 'category_link':
            case 'category_url':
                if($current['type']=='structure')
                    $out = nvweb_source_url('structure', $current['category']);
                else if($current['type']=='item')
                    $out = nvweb_source_url('structure', $current['object']->category);
                else
                    $out = '#';
                break;
			
			case 'a':
				$out[] = '<div class="menu_level_'.$level.' '.$class.'">';	
				for($m=0; $m < count($structure['cat-'.$parent]); $m++)
				{
					if(!nvweb_object_enabled($structure['cat-'.$parent][$m]))
                        continue;

					if($structure['cat-'.$parent][$m]->visible == 0)
                        continue;

					$mid = $structure['cat-'.$parent][$m]->id;

                    // hide menu items without a title
                    if(empty($structure['dictionary'][$mid]))
                        continue;

					$aclass = '';
                    if(in_array($mid, $current['hierarchy']))
						$aclass = ' class="menu_option_active"';
					$out[] = '<a'.$aclass.' '.nvweb_menu_action($mid).'>'.$structure['dictionary'][$mid].'</a>';
					if($option==$m)
						return array_pop($out);

                    $out[] = nvweb_menu_generate($mode, $levels, $mid, $level+1);
				}
				$out[] = '</div>';		
				$out = implode("\n", $out);	
				break;

            case 'select':
                $out[] = '<select class="menu_level_'.$level.' '.$class.'">';
                for($m=0; $m < count($structure['cat-'.$parent]); $m++)
                {
                    if(!nvweb_object_enabled($structure['cat-'.$parent][$m]))
                        continue;

                    if($structure['cat-'.$parent][$m]->visible == 0)
                        continue;

                    $mid = $structure['cat-'.$parent][$m]->id;

                    // hide menu items without a title
                    if(empty($structure['dictionary'][$mid]))
                        continue;

                    $aclass = '';
                    if(in_array($mid, $current['hierarchy']))
                        $aclass = ' class="menu_option_active" selected="selected"';

                    $target = '';
                    $act = nvweb_menu_action($mid, NULL, false, false);
                    if(strpos($act, 'target="_blank"')!==false)
                        $target = 'target="_blank"';
                    if(strpos($act, 'onclick')!==false)
                        $act = '#';

                    $act = str_replace('target="_blank"', '', $act);
                    $act = str_replace('data-sid', 'data_sid', $act);
                    $act = str_replace('href="', '', $act);
                    $act = str_replace('"', '', $act);
                    $act = trim($act);

                    $out[] = '<option'.$aclass.' value="'.$mid.'" href="'.$act.'" '.$target.'>'
                             .$structure['dictionary'][$mid]
                             .'</option>';

                    if($option==$m)
                        return array_pop($out);

                    $submenu = nvweb_menu_generate($mode, $levels, $mid, $level+1);
                    $submenu = strip_tags($submenu, '<option>');

                    $parts = explode('>', $submenu);
                    $submenu = '';
                    for($p=0; $p < count($parts); $p++)
                    {
                        if(strpos($parts[$p], '</option')!==false)
                            $parts[$p] = '&ndash;&nbsp;'.$parts[$p];
                    }
                    $submenu = implode('>', $parts);

                    $out[] = $submenu;
                }
                $out[] = '</select>';
                $out = implode("\n", $out);
                break;
	
			default:
			case 'ul':
                $ul_items = 0;
                $out = array();
				$out[] = '<ul class="menu_level_'.$level.' '.$class.'">';

				for($m=0; $m < count($structure['cat-'.$parent]); $m++)
				{
					if(!nvweb_object_enabled($structure['cat-'.$parent][$m]))
                        continue;

                    if($structure['cat-'.$parent][$m]->visible == 0)
                        continue;

					$mid = $structure['cat-'.$parent][$m]->id;

                    // hide menu items without a title
                    if(empty($structure['dictionary'][$mid]))
                        continue;

                    $aclass = '';
					if(in_array($mid, $current['hierarchy']))
						$aclass = ' class="menu_option_active"';

					$out[] = '<li'.$aclass.'>';
					$out[] = '<a'.$aclass.' '.nvweb_menu_action($mid).'>'.$structure['dictionary'][$mid].'</a>';
					if($option==$m)
						return array_pop($out);
					$out[] = nvweb_menu_generate($mode, $levels, $mid, $level+1);
					$out[] = '</li>';
                    $ul_items++;
				}
                $out[] = '</ul>';

                if($ul_items==0) // no option found, remove the last two lines (<ul> and </ul>)
                {
                    array_pop($out);
                    array_pop($out);
                }

				$out = implode("\n", $out);			
				break;
		}
	}

	return $out;
	
}

function nvweb_menu_action($id, $force_type=NULL, $use_javascript=true, $include_datasid=true)
{
	global $structure; 
	global $current;
	
	$type = $structure['actions'][$id]['action-type'];
		
	if(!empty($force_type))
		$type = $force_type;
	
	switch($type)
	{
		case 'url':
			$url = $structure['routes'][$id];
			if(empty($url))
            {
                if($use_javascript)
				    $url = 'javascript: return false;';
                else
                    $url = '#';
            }
            else
                $url = nvweb_prepare_link($url);

			$action = ' href="'.$url.'" ';
            if($include_datasid)
                $action.= ' data-sid="'.$id.'" ';
			break;
			
		case 'jump-branch':
			// we force only one jump to avoid infinite loops (caused by user mistake)
			$action = nvweb_menu_action($structure['actions'][$id]['action-jump-branch'], 'url');
			break;
			
		case 'jump-item':
			$url = nvweb_source_url('item', $structure['actions'][$id]['action-jump-item'], $current['lang']);
			if(empty($url))
            {
                if($use_javascript)
                    $url = 'javascript: return false;';
                else
                    $url = '#';
            }
			else
                $url = nvweb_prepare_link($url);
			$action = ' href="'.$url.'" ';
            if($include_datasid)
                $action.= ' data-sid="'.$id.'" ';
			break;
			
		case 'do-nothing':
			$action = ' href="#" onclick="javascript: return false;" ';
            if($include_datasid)
                $action.= ' data-sid="'.$id.'" ';
			break;
			
		default:
			// Navigate CMS < 1.6.5 compatibility [deprecated]
            // will be removed by 1.7
			$url = $structure['routes'][$id];
			if(substr($url, 0, 7)=='http://' || substr($url, 0, 7)=='https://')
            {
                $action = ' href="'.$url.'" target="_blank" ';
                if($include_datasid)
                    $action.= ' data-sid="'.$id.'" ';
				return $action; // ;)
            }
			else if(empty($url))
            {
                $action = ' href="#" onclick="return false;" ';
                if($include_datasid)
                    $action.= ' data-sid="'.$id.'" ';
				return $action;
            }
			else
            {
                $action = ' href="'.NVWEB_ABSOLUTE.$url.'"';
                if($include_datasid)
                    $action.= ' data-sid="'.$id.'" ';
				return $action;
            }
			break;	
	}
	
	if($structure['actions'][$id]['action-new-window']=='1' && $type!='do-nothing')
		$action .= ' target="_blank"';
	
	return $action;	
}

function nvweb_menu_load_dictionary()
{
	global $DB;	
	global $structure;
	global $current;
	global $website;
			
	if(empty($structure['dictionary']))
	{
		$structure['dictionary'] = array();

		$DB->query('SELECT node_id, text
					  FROM nv_webdictionary 
					 WHERE node_type = "structure"
					   AND subtype = "title" 
					   AND lang = '.protect($current['lang']).'
					   AND website = '.$website->id);		
					
		$data = $DB->result();
		
		if(!is_array($data)) $data = array();
		$dictionary = array();
		
		foreach($data as $item)
		{
			$structure['dictionary'][$item->node_id] = $item->text;
		}
	}
}

function nvweb_menu_load_routes()
{
	global $DB;	
	global $structure;
	global $current;
	global $website;
			
	if(empty($structure['routes']))
	{
		$structure['routes'] = array();

		$DB->query('SELECT object_id, path
					  FROM nv_paths 
					 WHERE type = "structure"
					   AND lang = '.protect($current['lang']).'
					   AND website = '.$website->id);		
					
		$data = $DB->result();
		
		if(!is_array($data)) $data = array();
		$dictionary = array();
		
		foreach($data as $item)
		{
			$structure['routes'][$item->object_id] = $item->path;
		}			
	}
}

function nvweb_menu_load_actions()
{
	global $DB;	
	global $structure;
	global $current;
	global $website;
			
	if(empty($structure['actions']))
	{
		$structure['actions'] = array();

		$DB->query('
            SELECT node_id, subtype, text
			  FROM nv_webdictionary 
			 WHERE node_type = "structure"
			   AND lang = '.protect($current['lang']).'
			   AND subtype IN("action-type", "action-jump-item", "action-jump-branch", "action-new-window")
			   AND website = '.$website->id
        );
					
		$data = $DB->result();
		
		if(!is_array($data))
		    $data = array();

		foreach($data as $row)
		{
			$structure['actions'][$row->node_id][$row->subtype] = $row->text;
		}
	}
}

function nvweb_menu_load_structure($parent=0)
{
	global $DB;	
	global $structure;
	global $website;

	if(!isset($structure['cat-'.$parent]))
	{
		$structure['cat-'.$parent] = array();
		
		$DB->query('SELECT * 
					  FROM nv_structure
					 WHERE parent = '.protect($parent).' 
					   AND website = '.$website->id.' 
					  ORDER BY position ASC');
				  
		$structure['cat-'.$parent] = $DB->result();

        // parse some result values
        foreach($structure['cat-'.$parent] as $key => $value)
        {
            $value->groups = str_replace('g', '', $value->groups);
            $value->groups = array_filter(explode(',', $value->groups));
            $structure[$key] = clone $value;
        }
	}
}

function nvweb_menu_get_children($categories=array(), $sublevels=NULL)
{
	global $structure;

	// get all leafs from all categories that are child of the selected ones
	$categories_count = count($categories);

    $depth = array();

	for($c=0; $c < $categories_count; $c++)
	{
		$categories[$c] = trim($categories[$c]);
		if(empty($categories[$c]) && $categories[$c]!='0') 
            continue;
        
        if(!isset($depth[$categories[$c]]))
            $depth[$categories[$c]] = 0;

		nvweb_menu_load_structure($categories[$c]);

		for($s=0; $s < count($structure['cat-'.$categories[$c]]); $s++)
		{
            $depth[$structure['cat-'.$categories[$c]][$s]->id] = $depth[$categories[$c]] + 1;

            // the current category is beyond the allowed number of sublevels on hierarchy?
            if(isset($sublevels) && $depth[$structure['cat-'.$categories[$c]][$s]->id] > $sublevels)
                continue;

			array_push($categories, $structure['cat-'.$categories[$c]][$s]->id);
		}

		$categories = array_unique($categories); // remove duplicates
		$categories_count = count($categories);	 // recount elements
	}

	return $categories;		
}

function nvweb_menu_render_arrow($vars=array())
{
    global $DB;
    global $structure;
    global $current;
    global $website;

    $out = '';
    $char = $vars['character'];
    $link = '';
    $title = '';
    $previous = null;
    $next = null;
    $parent = null;

    // look for the category before and after the current one

    if($current['type']=='structure')
        $parent = $current['object']->parent;
    else if($current['category'] > 0)
        $parent = $current['hierarchy'][count($current['category'])-1];

    // if we have found the parent
    // AND
    // if the "from" option is not empty AND the number of levels until the current category is greater than "from"
    if( $parent >= 0 &&
        (!empty($vars['from']) && count($current['hierarchy']) >= $vars['from']))
    {
        nvweb_menu_load_structure($parent);

        for($c=0; $c < count($structure['cat-'.$parent]); $c++)
        {
            if($structure['cat-'.$parent][$c]->id == $current['category'])
            {
                if(isset($structure['cat-'.$parent][$c-1]))
                    $previous = $structure['cat-'.$parent][$c-1]->id;

                if(isset($structure['cat-'.$parent][$c+1]))
                    $next = $structure['cat-'.$parent][$c+1]->id;

                break;
            }
        }

        // TO DO: look for previous and next categories from the parent's brothers
        /*
        if(empty($previous))
        {
            // we have not found a PREVIOUS structure entry in the same level of the current category
            // we may look for the last child of the parent brother... example
            /*
             *  ROOT
             *      1
             *      2
             *        2.1
             *          2.1.1
             *          2.1.2
             *        2.2
             *          2.2.1 <- current category
             *          2.2.2
             *        2.3
             *          2.3.1
             *      3
             *
             *  in this example, the previous category of 2.2.1 is 2.1.2
             *  if the current category is 2.2.2, the next one will be the children of the next parent, so, 2.3.1
             */
        /*
        }
        /*
        if(empty($next))
        {

        }
        */

        if($vars['mode']=='next' && !empty($next))
        {
            if(empty($char))
                $char = '&gt;';

            $link = $structure['routes'][$next];
            $title = $structure['dictionary'][$next];
        }
        else if($vars['mode']=='previous' && !empty($previous))
        {
            if(empty($char))
                $char = '&lt;';

            $link = $structure['routes'][$previous];
            $title = $structure['dictionary'][$previous];
        }

        if(!empty($link))
            $out = '<a href="'.$link.'" title="'.$title.'">'.$char.'</a>';
    }

    return $out;
}

?>