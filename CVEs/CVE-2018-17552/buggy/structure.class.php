<?php
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/paths/path.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webuser_votes/webuser_vote.class.php');

class structure
{
	public $id;
	public $website;
	public $parent;
	public $position;
	public $access; // 0 => everyone, 1 => logged in, 2 => not logged in, 3 => selected webuser groups
    public $groups; // webuser groups
	public $permission;
	public $icon;
	public $metatags;
	public $template;
	public $date_published;
	public $date_unpublish;
	public $views;
	public $votes;
	public $score;	
	public $visible; // in menus
	
	public $dictionary;
	public $paths;
	
	public function load($id)
	{
		global $DB;

		if($DB->query('SELECT * FROM nv_structure WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function reload()
	{
		$item = new structure();
		$item->load($this->id);
		return $item;
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id			= $main->id;
		$this->website		= $main->website;
		$this->parent  		= $main->parent;
		$this->position		= $main->position;
		$this->template  	= $main->template;
		$this->access		= $main->access;			
		$this->permission  	= $main->permission;
		$this->icon			= $main->icon;
		$this->metatags  	= $main->metatags;
		$this->date_published	= (empty($main->date_published)? '' : $main->date_published);
		$this->date_unpublish	= (empty($main->date_unpublish)? '' : $main->date_unpublish);
		
		$this->votes		= $main->votes;
		$this->score		= $main->score;
		$this->views		= $main->views;			

		$this->dictionary	= webdictionary::load_element_strings('structure', $this->id);
		$this->paths		= path::loadElementPaths('structure', $this->id, $this->website);
		$this->visible		= $main->visible;

        $this->groups       = $main->groups;
        if(!is_array($this->groups))
        {
            // to get the array of groups first we remove the "g" character
            $groups = str_replace('g', '', $this->groups);
            $this->groups = explode(',', $groups);
        }

        if(!is_array($this->groups))
            $this->groups = array($this->groups);
    }
	
	public function load_from_post()
	{
		if(intval($_REQUEST['parent'])!=$this->id)	// protection against selecting this same category as parent of itself
			$this->parent 		= intval($_REQUEST['parent']);
			
		$this->template 	= $_REQUEST['template'];
		$this->access		= intval($_REQUEST['access']);

        $this->groups	    = $_REQUEST['groups'];
        if($this->access < 3)
            $this->groups = array();

		$this->permission	= intval($_REQUEST['permission']);		
		$this->visible		= intval($_REQUEST['visible']);		
		
		$this->date_published	= (empty($_REQUEST['date_published'])? '' : core_date2ts($_REQUEST['date_published']));	
		$this->date_unpublish	= (empty($_REQUEST['date_unpublish'])? '' : core_date2ts($_REQUEST['date_unpublish']));	
		
		// language strings and options
		$this->dictionary = array();
		$this->paths = array();

		$fields = array('title', 'action-type', 'action-jump-item', 'action-jump-branch', 'action-new-window', 'action-masked-redirect'); //, 'path', 'visible');
		foreach($_REQUEST as $key => $value)
		{
			if(empty($value)) continue;
			
			foreach($fields as $field)
			{
				if(substr($key, 0, strlen($field.'-'))==$field.'-')
					$this->dictionary[substr($key, strlen($field.'-'))][$field] = $value;
			}
		
			if(substr($key, 0, strlen('path-'))=='path-')
				$this->paths[substr($key, strlen('path-'))] = $value;
		}		
	}

	public function save()
	{
		if(!empty($this->id))
			return $this->update();
		else
			return $this->insert();
	}
	
	public function delete()
	{
		global $DB;
		global $events;

		$affected_rows = 0;

		if(!empty($this->id))
		{
			// remove dictionary entries
			webdictionary::save_element_strings('structure', $this->id, array());

            // remove paths
			path::saveElementPaths('structure', $this->id, array(), $this->website);

            // remove all votes assigned
			webuser_vote::remove_object_votes('structure', $this->id);

            // remove the properties
            property::remove_properties('structure', $this->id);

            // remove the structure entry
			$DB->execute('
				DELETE FROM nv_structure
					WHERE id = '.intval($this->id).'
					  AND website = '.$this->website.'
					LIMIT 1'
			);

            $affected_rows = $DB->get_affected_rows();

            if(method_exists($events, 'trigger'))
            {
                $events->trigger(
                    'structure',
                    'delete',
                    array(
                        'structure' => $this
                    )
                );
            }

        }
		return $affected_rows;
	}
	
	public function insert()
	{
		global $DB;
		global $website;
		global $events;

		if(empty($this->website))
			$this->website = $website->id;

        if(empty($this->position))
        {
            // no position given, so find the first position free in the same parent (after all existing children)
            $DB->query('
				SELECT MAX(position) as max_position
                  FROM nv_structure
                 WHERE parent = '.protect($this->parent).'
                   AND website = '.protect($this->website)
            );

            $max = $DB->result('max_position');
            $this->position = intval($max[0]) + 1;
        }

        $groups = '';
        if(is_array($this->groups))
        {
            $this->groups = array_unique($this->groups); // remove duplicates
            $this->groups = array_filter($this->groups); // remove empty
            if(!empty($this->groups))
                $groups = 'g'.implode(',g', $this->groups);
        }

        if($groups == 'g')
            $groups = '';

        $ok = $DB->execute('
			INSERT INTO nv_structure
				(	id, website, parent, position, access, groups, permission,
					icon, metatags, template, date_published, date_unpublish,
					visible, views, votes, score
				)
				VALUES
				(	0, :website, :parent, :position, :access, :groups, :permission,
					:icon, :metatags, :template, :date_published, :date_unpublish,
					:visible, :views, :votes, :score
				)
			',
            array(
	            ":website" => value_or_default($this->website, $website->id),
	            ":parent" => value_or_default($this->parent, 0),
	            ":position" => value_or_default($this->position, 0),
	            ":access" => value_or_default($this->access, 0),
	            ":groups" => $groups,
	            ":permission" => value_or_default($this->permission, 0),
				":icon" => value_or_default($this->icon, 0),
				":metatags" => value_or_default($this->metatags, ''),
				":template" =>  value_or_default($this->template, ''),
				":date_published" => value_or_default($this->date_published, 0),
				":date_unpublish" => value_or_default($this->date_unpublish, 0),
				":visible" => value_or_default($this->visible, 0),
				":views" => 0,
				":votes" => 0,
				":score" => 0
            )
        );

		if(!$ok)
			throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();

		webdictionary::save_element_strings('structure', $this->id, $this->dictionary, $this->website);
   		path::saveElementPaths('structure', $this->id, $this->paths, $this->website);

        if(method_exists($events, 'trigger'))
        {
            $events->trigger(
                'structure',
                'save',
                array(
                    'structure' => $this
                )
            );
        }
		
		return true;
	}
	
	public function update()
	{
		global $DB;
		global $events;

        $groups = '';
        if(is_array($this->groups))
        {
            $this->groups = array_unique($this->groups); // remove duplicates
            $this->groups = array_filter($this->groups); // remove empty
            if(!empty($this->groups))
                $groups = 'g'.implode(',g', $this->groups);
        }

        if($groups == 'g')
            $groups = '';

		$ok = $DB->execute('
 			UPDATE nv_structure
			   SET  parent = :parent, position = :position, access = :access, groups = :groups,
			   		permission = :permission, icon = :icon, metatags = :metatags,
					date_published = :date_published, date_unpublish = :date_unpublish,
					template = :template, visible = :visible, views = :views, votes = :votes, score = :score
			 WHERE id = :id
			   AND website = :website
		   ',
			array(
				":id" => $this->id,
				":website" => $this->website,
	            ":parent" => value_or_default($this->parent, 0),
	            ":position" => $this->position,
	            ":access" => $this->access,
	            ":groups" => $groups,
	            ":permission" => $this->permission,
				":icon" => value_or_default($this->icon, ''),
				":metatags" => value_or_default($this->metatags, ''),
				":template" => $this->template,
				":date_published" => value_or_default($this->date_published, 0),
				":date_unpublish" => value_or_default($this->date_unpublish, 0),
				":visible" => $this->visible,
				":views" => $this->views,
				":votes" => $this->votes,
				":score" => $this->score
			)
		);
			      
		if(!$ok)
		    throw new Exception($DB->get_last_error());
		
		webdictionary::save_element_strings('structure', $this->id, $this->dictionary, $this->website);
		path::saveElementPaths('structure', $this->id, $this->paths, $this->website);

        if(method_exists($events, 'trigger'))
        {
            $events->trigger(
                'structure',
                'save',
                array(
                    'structure' => $this
                )
            );
        }

		return true;
	}

    // retrieve all elements associated with this structure entry
    public function elements($position=NULL)
    {
        global $DB;

        $elements = array();

        if(!empty($this->id))
        {
            $DB->query('
                SELECT id
                  FROM nv_items
                 WHERE category = '.$this->id.'
              ORDER BY position ASC, id ASC
            ');
            $ids = $DB->result('id');

            if(!empty($position))
            {
                $element = new item();
                $element->load($ids[$position]);
                return $element;
            }
            else
            {
                for($i = 0; $i < count($ids); $i++)
                {
                    $elements[$i] = new item();
                    $elements[$i]->load($ids[$i]);
                }
            }
        }

        return $elements;
    }

    public function elements_count()
    {
        global $DB;
        global $webuser;
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
            ' category = '.protect($this->id).' AND
              website = '.protect($this->website).' AND
              permission <= '.$permission.' AND 
              (date_published = 0 OR date_published < '.core_time().') AND 
              (date_unpublish = 0 OR date_unpublish > '.core_time().') AND 
              (access = 0 OR access = '.$access.$access_extra.')
            ');

        return $out;
    }
	
	public static function loadTree($id_parent=0, $ws_id=null)
	{
		global $DB;	
		global $website;

		if(empty($ws_id))
			$ws_id = $website->id;

		$ws = new website();
		$ws->load($ws_id);

        // TODO: consider implementing a cache to avoid extra database queries
		$DB->query('
            SELECT *
              FROM nv_structure
			 WHERE parent = '.intval($id_parent).' AND
			       website = '.$ws->id.'
		  ORDER BY position ASC, id DESC
	    ');

		$result = $DB->result();

		for($i=0; $i < count($result); $i++)
		{
			if(empty($result[$i]->date_published)) 
				$result[$i]->date_published = '&infin;';
			else
				$result[$i]->date_published = core_ts2date($result[$i]->date_published, false);
				
			if(empty($result[$i]->date_unpublish)) 
				$result[$i]->date_unpublish = '&infin;';	
			else
				$result[$i]->date_unpublish = core_ts2date($result[$i]->date_unpublish, false);		
				
			$result[$i]->dates = $result[$i]->date_published.' - '.$result[$i]->date_unpublish;
		}
		
		return $result;
	}
	
	public static function hierarchy($id_parent=0, $ws_id=null)
	{
		global $website;
        global $theme;

		if(empty($ws_id))
			$ws_id = $website->id;

		$ws = new website();
		$ws->load($ws_id);

		$flang = $ws->languages_list[0];
		if(empty($flang))
            return array();
		
		$tree = array();
		
		if($id_parent==-1)
		{
            // create the virtual root structure entry (the website)
			$obj = new structure();
			$obj->id = 0;
			$obj->label = $ws->name;
            $obj->_multilanguage_label = $ws->name;
			$obj->parent = -1;
			$obj->children = structure::hierarchy(0, $ws_id);

			$tree[] = $obj;
		}
		else
		{
			$tree = structure::loadTree($id_parent, $ws_id);

            $templates = template::elements('structure');
            if(empty($templates))
                $templates = array();

			for($i=0; $i < count($tree); $i++)
            {
				$tree[$i]->dictionary = webdictionary::load_element_strings('structure', $tree[$i]->id);
                $tree[$i]->label = $tree[$i]->dictionary[$ws->languages_list[0]]['title'];

                $tree[$i]->template_title = $tree[$i]->template;

                foreach($templates as $template_def)
                {
                    if($template_def->type == $tree[$i]->template)
                    {
                        $tree[$i]->template_title = $template_def->title;
                        break;
                    }
                }

                if(method_exists($theme, "t"))
                    $tree[$i]->template_title = $theme->t($tree[$i]->template_title);

                for($wl=0; $wl < count($ws->languages_list); $wl++)
                {
                    $lang = $ws->languages_list[$wl];

                    if(empty($tree[$i]->dictionary[$lang]['title']))
                        $tree[$i]->dictionary[$lang]['title'] = '[ ? ]';

                    $style = '';
                    if($lang != $flang)
                        $style = 'display: none';

                    $label[] = '<span class="structure-label" lang="'.$lang.'" style="'.$style.'">'
                              .$tree[$i]->dictionary[$lang]['title']
                              .'</span>';

                    $bc[$tree[$i]->id][$lang] = $tree[$i]->dictionary[$lang]['title'];
                }

                $children = structure::hierarchy($tree[$i]->id, $ws_id);
                $tree[$i]->children = $children;
            }
		}
		
		return $tree;
	}
	
	public static function hierarchyList($hierarchy, $selected=0, $lang="", $ignore_permissions=false)
	{
		$html = array();
				
		if(!is_array($hierarchy))
            $hierarchy = array();
		
		if(!is_array($selected))
			$selected = array($selected);

		foreach($hierarchy as $node)
		{	
			$li_class = '';

			$post_html = structure::hierarchyList($node->children, $selected, $lang, $ignore_permissions);
            $has_children = !empty($post_html);

			if(strpos($post_html, 'class="active"')!==false)
				$li_class = ' class="open" ';

			// disable option if not allowed AND all of its children are not allowed either
			if(!$ignore_permissions && !structure::category_allowed($node->id) && strpos($post_html, "ui-state-disabled") > 0)
				$li_class = ' class="ui-state-disabled" ';

			if(empty($html)) $html[] = '<ul>';

            if(empty($lang))
                $title = $node->label;
            else
                $title = $node->dictionary[$lang]['title'];

			if(empty($title))
			{
				foreach($node->dictionary as $lkey => $lval)
				{
					if(!empty($lval['title']) && $lval['title'] != '[ ? ]')
					{
						$title  = '<span style="opacity: 0.8;">'.$lval['title'].' <img align="absmiddle" src="img/icons/silk/comment.png" class="silk-sprite" /><i>'.$lkey.'</i></span>';
						break;
					}
				}
				if(empty($title)) // no translation for ANY language, so just add a placeholder
					$title = '<span style="opacity: 0.75;"><i class="fa fa-fw fa-language"></i> #'.$node->id.'</span>';
			}

			if(!$ignore_permissions && !structure::category_allowed($node->id))
				$title = '<div class="ui-state-disabled">'.$title.'</div>';

            $node_type = 'folder';
            if(!$has_children)
                $node_type = 'leaf';

			if(in_array($node->id, $selected))
				$html[] = '<li '.$li_class.' value="'.$node->id.'" data-node-id="'.$node->id.'" data-selected="true" data-jstree=\'{"selected": true, "type": "'.$node_type.'"}\'><span class="active">'.$title.'</span>';
			else
				$html[] = '<li '.$li_class.' value="'.$node->id.'" data-node-id="'.$node->id.'" data-selected="false" data-jstree=\'{"selected": false, "type": "'.$node_type.'"}\'><span>'.$title.'</span>';

			$html[] = $post_html;
			$html[] = '</li>';
		}
		if(!empty($html)) $html[] = '</ul>';		
		
		return implode("\n", $html);
	}

	public static function category_allowed($id)
	{
		global $user;

		$allowed = true;

		if(!empty($user->id))
		{
			$categories_allowed = $user->permission("structure.categories.allowed");

			if(!empty($categories_allowed))
			{
				if(!in_array($id, $categories_allowed))
					$allowed = false;
			}

			$categories_excluded = $user->permission("structure.categories.excluded");
			if(!empty($categories_excluded))
			{
				if(in_array($id, $categories_excluded))
					$allowed = false;
			}
		}

		return $allowed;
	}

    public static function hierarchyPath($hierarchy, $category)
    {
        if(is_array($hierarchy))
        {
            foreach($hierarchy as $node)
            {
                if(!empty($node->children))
                    $val = structure::hierarchyPath($node->children, $category);

                if($node->id == $category || (!empty($val)) )
                {
                    if(empty($val))
                        return array($node->label);

                    return array_merge(array($node->label), $val);
                }
            }
        }
        return;
    }

    public static function hierarchyListClasses($hierarchy, $level=1)
    {
        $html = array();

        if(!is_array($hierarchy))
            $hierarchy = array();

        foreach($hierarchy as $node)
        {
            $post_html = structure::hierarchyListClasses($node->children, $level+1);

            if(empty($html) && $level==1) $html[] = '<ul>';

            $extra = '';
            if(!empty($post_html))
                $extra = 'group';

            $html[] = '<li class="level'.$level.' '.$extra.'" data-value="'.$node->id.'"><span>'.$node->label.'</span>';

            $html[] = $post_html;
            $html[] = '</li>';
        }
        if(!empty($html) && $level==1) $html[] = '</ul>';

        return implode("\n", $html);
    }
	
	public static function reorder($parent, $children)
	{
		global $DB;
		global $website;
		
		$children = explode("#", $children);
				
		for($i=0; $i < count($children); $i++)
		{		
			if(empty($children[$i])) continue;
			$ok =	$DB->execute('UPDATE nv_structure 
									 SET position = '.($i+1).'
								   WHERE id = '.$children[$i].' 
									 AND parent = '.intval($parent).'
									 AND website = '.$website->id);
							 
			if(!$ok) return array("error" => $DB->get_last_error()); 
		}
			
		return true;	
	}

	public function property($property_name, $raw=false)
	{
		// load properties if not already done
		if(empty($this->properties))
			$this->properties = property::load_properties('structure', $this->template, 'structure', $this->id);

		for($p=0; $p < count($this->properties); $p++)
		{
			if($this->properties[$p]->name==$property_name || $this->properties[$p]->id==$property_name)
			{
				if($raw)
					$out = $this->properties[$p]->value;
				else
					$out = $this->properties[$p]->value;

				break;
			}
		}

		return $out;
	}

	public function property_definition($property_name)
	{
		// load properties if not already done
		if(empty($this->properties))
			$this->properties = property::load_properties('structure', $this->template, 'structure', $this->id);

		for($p=0; $p < count($this->properties); $p++)
		{
			if($this->properties[$p]->name==$property_name || $this->properties[$p]->id==$property_name)
			{
				$out = $this->properties[$p];
				break;
			}
		}

		return $out;
	}

    public function property_exists($property_name)
    {
        // load properties if not already done
        if(empty($this->properties))
            $this->properties = property::load_properties('structure', $this->template, 'structure', $this->id);

        for($p=0; $p < count($this->properties); $p++)
        {
            if($this->properties[$p]->name==$property_name || $this->properties[$p]->id==$property_name)
                return true;
        }
        return false;
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_structure WHERE website = '.protect($website->id), 'object');

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }

	public static function __set_state(array $obj)
	{
		$tmp = new structure();
		foreach($obj as $key => $val)
			$tmp->$key = $val;

		return $tmp;
	}
}

?>