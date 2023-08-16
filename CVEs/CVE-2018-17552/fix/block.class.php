<?php
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');

class block
{
	public $id;
	public $type;   // assigned block type name (f.e. sidebar_poll)
    public $class;  // block class (block, theme, poll...)
	public $date_published;
	public $date_unpublish;
    public $date_modified;
	public $access;
    public $groups;
	public $enabled;
	public $trigger;
	public $action;
	public $notes;
	public $dictionary;
	public $position;
    public $fixed;
    public $categories;
    public $exclusions;
    public $elements; // selection or exclusions in a JSON object

    public $uid;
    public $properties;

    static $nv_fontawesome_classes;

    public function __clone()
    {
        foreach($this as $key => $val)
        {
            if(is_object($val))
                $this->{$key} = clone $val;
            else if(is_array($val))
                $this->{$key} = mb_unserialize(serialize($val));
        }
    }

	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('
		    SELECT * FROM nv_blocks
			 WHERE id = '.intval($id).'
			   AND website = '.$website->id)
        )
		{
			$data = $DB->result();
			$this->load_from_resultset($data);
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id				= $main->id;
		$this->website			= $main->website;
		$this->type  			= $main->type;
		$this->date_published	= (empty($main->date_published)? '' : $main->date_published);
		$this->date_unpublish	= (empty($main->date_unpublish)? '' : $main->date_unpublish);
		$this->date_modified	= (empty($main->date_modified)? '' : $main->date_modified);
		$this->access			= $main->access;
		$this->enabled			= $main->enabled;

		$this->trigger			= mb_unserialize($main->trigger);
				
		if(is_array($this->trigger['trigger-html']))
		{
			foreach($this->trigger['trigger-html'] as $language => $code)
			{
				$this->trigger['trigger-html'][$language] = htmlspecialchars_decode($code);
			}
		}
		
		if(is_array($this->trigger['trigger-content']))
		{
			foreach($this->trigger['trigger-content'] as $language => $code)
			{
				$this->trigger['trigger-content'][$language] = stripslashes($code);
			}
		}

		$this->action			= mb_unserialize($main->action);				
		$this->notes			= $main->notes;		
		$this->dictionary		= webdictionary::load_element_strings('block', $this->id);	// title

        $this->position			= $main->position;
        $this->fixed	        = $main->fixed;
        $this->categories		= array_filter(explode(',', $main->categories));
        $this->exclusions		= array_filter(explode(',', $main->exclusions));
        $this->elements         = json_decode($main->elements, true);

        // to get the array of groups first we remove the "g" character
        $groups = str_replace('g', '', $main->groups);
        $this->groups = explode(',', $groups);
        if(!is_array($this->groups))  $this->groups = array($groups);

        $block_classes = block::types();
        foreach($block_classes as $bc)
        {
            if($bc['code']==$this->type)
                $this->class = $bc['type'];
        }
	}

    public function load_from_block_group($block_group_id, $block_type, $block_uid=null)
    {
        $this->id = $block_type;
        $this->type = $block_type;
        $this->class = 'block_group_block';
        $this->_block_group_id = $block_group_id;
        $this->date_modified = time();
        $this->access = 0;
        $this->enabled = 1;
        $this->uid = $block_uid;
    }
	
	public function load_from_post()
	{
		global $website;

		$this->type  			= $_REQUEST['type'];
		$this->date_published	= (empty($_REQUEST['date_published'])? '' : core_date2ts($_REQUEST['date_published']));	
		$this->date_unpublish	= (empty($_REQUEST['date_unpublish'])? '' : core_date2ts($_REQUEST['date_unpublish']));	
		$this->access			= intval($_REQUEST['access']);

        $this->groups	        = $_REQUEST['groups'];
        if($this->access < 3)
            $this->groups = array();

		$this->enabled			= intval($_REQUEST['enabled']);	
		$this->notes  			= pquotes($_REQUEST['notes']);

        $this->categories 	= '';
        if(!empty($_REQUEST['categories']))
            $this->categories	= explode(',', $_REQUEST['categories']);

        $this->exclusions 	= '';
        if(!empty($_REQUEST['exclusions']))
            $this->exclusions	= explode(',', $_REQUEST['exclusions']);

        if($_REQUEST['all_categories']=='1' || (is_array($_REQUEST['all_categories']) && $_REQUEST['all_categories'][0] == '1'))
        {
            $this->categories 	= array();
            $this->exclusions 	= array();
        }

        $this->elements = array();
        if(!empty($_REQUEST['elements_selection']) && $_REQUEST['elements_display'][0] != 'all')
        $this->elements = array(
            $_REQUEST['elements_display'][0] => $_REQUEST['elements_selection']
        );

		$this->dictionary		= array();	// for titles
		$this->trigger			= array();
		$this->action			= array();

        if(empty($this->class))
            $this->class = 'block';

        switch($this->class)
        {
            case 'poll':
                foreach($website->languages_list as $lang)
                {
                    $this->dictionary[$lang]['title'] = $_REQUEST['title-'.$lang];
                    $this->trigger[$lang] = array();

                    $answers_order = explode("#", $_REQUEST['poll-answers-table-order-'.$lang]);

                    foreach($_REQUEST['poll-answers-table-title-'.$lang] as $fcode => $fval)
                    {
                        $pos = array_search("poll-answers-table-row-".$fcode, $answers_order);

                        if($pos===false)
                            $pos = $fcode;

                        $fval = trim($fval);
                        if(empty($fval))
                            continue;

                        $this->trigger[$lang][$pos] = array(
                            'title' => $fval,
                            'code' => $fcode,
                            'votes' => intval($_REQUEST['poll-answers-table-votes-'.$lang][$fcode])
                        );
                    }

                    ksort($this->trigger[$lang]);
                }
                break;

            case 'block':
            case 'theme':
            default:
                $fields_title 	= array( 'title' );
                $fields_trigger = array(
                    'trigger-type',
                    'trigger-title',
                    'trigger-image',
                    'trigger-rollover',
                    'trigger-rollover-active',
                    'trigger-video',
                    'trigger-flash',
                    'trigger-html',
                    'trigger-links',
                    'trigger-content'
                );
                $fields_action	= array(
                    'action-type',
                    'action-web',
                    'action-javascript',
                    'action-file',
                    'action-image'
                );

                foreach($_REQUEST as $key => $value)
                {
                    if(empty($value)) continue;

                    foreach($fields_title as $field)
                    {
                        if(substr($key, 0, strlen($field.'-'))==$field.'-')
                            $this->dictionary[substr($key, strlen($field.'-'))]['title'] = $value;
                    }

                    foreach($fields_trigger as $field)
                    {
                        // f.e., Does this REQUEST field begins with "trigger-content-"?
                        if(substr($key, 0, strlen($field.'-'))==$field.'-')
                        {
                            switch($field)
                            {
                                case 'trigger-html':
                                    $this->trigger[$field][substr($key, strlen($field.'-'))] = htmlspecialchars($value);
                                    break;

                                case 'trigger-content':
                                    $this->trigger[$field][substr($key, strlen($field.'-'))] = $value;
                                    break;

                                case 'trigger-links':
                                    $key_parts = explode("-", $key);
                                    $key_lang = array_pop($key_parts);
                                    $key_name = array_pop($key_parts);

                                    if(!is_array($value))
										$value = array($value);
                                    $value = array_filter($value);

                                    if(empty($key_name))
                                        continue;

                                    if($key_name=="link")
                                    {
                                        // trim & clean the links
                                        foreach($value as $vkey => $vval)
                                        {
                                            $vval = trim($vval);
                                            $vval = preg_replace("/\xE2\x80\x8B/", "", $vval); // remove "zero width space" &#8203;
                                            $value[$vkey] = $vval;
                                        }
                                    }

                                    $this->trigger[$field][$key_lang][$key_name] = $value;
                                    break;

                                default:
                                    $this->trigger[$field][substr($key, strlen($field.'-'))] = pquotes($value);
                                    break;
                            }
                        }
                    }

                    foreach($fields_action as $field)
                    {
                        if(substr($key, 0, strlen($field.'-'))==$field.'-')
                            $this->action[$field][substr($key, strlen($field.'-'))] = $value;
                    }
                }
            // end default case
        }
	}
	
	public static function reorder($type, $order, $fixed)
	{
		global $DB;
		global $website;

		$item = explode("#", $order);
							
		for($i=0; $i < count($item); $i++)
		{		
			if(empty($item[$i])) continue;

            $block_is_fixed = ($fixed[$item[$i]]=='1'? '1' : '0');

			$ok = $DB->execute('
                UPDATE nv_blocks
				SET position = '.($i+1).',
				    fixed = '.$block_is_fixed.'
                WHERE id = '.$item[$i].'
				  AND website = '.$website->id
            );
			
			if(!$ok) return array("error" => $DB->get_last_error()); 
		}
			
		return true;	
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
		global $user;
		global $events;

        $affected_rows = 0;

		if(!empty($user->id))
		{
			if($user->permission("blocks.delete") == 'false')
				throw new Exception(t(610, "Sorry, you are not allowed to execute this function."));
		}

		if(!empty($this->id))
		{
			webdictionary::save_element_strings('block', $this->id, array(), $this->website);

            // remove grid notes
            grid_notes::remove_all('block', $this->id);
			
			$DB->execute('
              DELETE FROM nv_blocks
			   WHERE id = '.intval($this->id).' AND
                     website = '.$this->website
            );

			$affected_rows = $DB->get_affected_rows();

            if(method_exists($events, 'trigger'))
            {
                $events->trigger(
                    'block',
                    'delete',
                    array(
                        'block' => $this
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
		global $user;
		global $events;

		if(!empty($user->id))
		{
			if( $user->permission("blocks.create") == 'false' )
				throw new Exception(t(610, "Sorry, you are not allowed to execute this function."));
		}

        if(empty($this->website))
            $this->website = $website->id;

        if(!is_array($this->categories))
            $this->categories = array();

        if(!is_array($this->exclusions))
            $this->exclusions = array();

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

        $ok = $DB->execute(
            'INSERT INTO nv_blocks
                (id, website, type, date_published, date_unpublish,
                 position, fixed, categories, exclusions, elements,
                 access, groups, enabled, `trigger`, action, notes,
                 date_modified)
                VALUES
                ( 0,
                  :website,
                  :type,
                  :date_published,
                  :date_unpublish,
                  :position,
                  :fixed,
                  :categories,
                  :exclusions,
                  :elements,
                  :access,
                  :groups,
                  :enabled,
                  :trigger,
                  :action,
                  :notes,
                  :date_modified
                )
            ',
            array(
                ':website'          =>  $this->website,
                ':type'             =>  value_or_default($this->type, ""),
                ':date_published'   =>  value_or_default($this->date_published, 0),
                ':date_unpublish'   =>  value_or_default($this->date_unpublish, 0),
                ':position'         =>  value_or_default($this->position, 0),
                ':fixed'            =>  value_or_default($this->fixed, 0),
                ':categories'       =>  implode(',', $this->categories),
                ':exclusions'       =>  implode(',', $this->exclusions),
                ':elements'         =>  json_encode($this->elements),
                ':access'           =>  value_or_default($this->access, 0),
                ':groups'           =>  $groups,
                ':enabled'          =>  value_or_default($this->enabled, 0),
                ':trigger'          =>  serialize($this->trigger),
                ':action'           =>  serialize($this->action),
                ':notes'            =>  value_or_default($this->notes, ''),
                ':date_modified'    =>  time()
            )
        );

		if(!$ok) throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
		
		webdictionary::save_element_strings('block', $this->id, $this->dictionary, $this->website);

        if(method_exists($events, 'trigger'))
        {
            $events->trigger(
                'block',
                'save',
                array(
                    'block' => $this
                )
            );
        }
		
		return true;
	}
	
	public function update()
	{
		global $DB;
		global $user;
		global $events;

        if(!is_array($this->categories))
            $this->categories = array();

        if(!is_array($this->exclusions))
            $this->exclusions = array();

		if(!empty($user->id))
		{
			if($user->permission("blocks.edit") == 'false')
				throw new Exception(t(610, "Sorry, you are not allowed to execute this function."));
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

        $ok = $DB->execute(
            'UPDATE nv_blocks
             SET
                `type`			= :type,
                date_published 	= :date_published,
                date_unpublish  = :date_unpublish,
                `position` 		= :position,
                fixed	        = :fixed,
                categories		= :categories,
                exclusions		= :exclusions,
                elements        = :elements,
                `trigger` 		= :trigger,
                `action` 		= :action,
                access 			= :access,
                groups          = :groups,
                enabled 		= :enabled,
                notes	 		= :notes,
                date_modified	= :date_modified
             WHERE id = :id
               AND website = :website
            ',
            array(
                ':id'               =>  $this->id,
                ':website'          =>  $this->website,
                ':type'             =>  $this->type,
                ':date_published'   =>  value_or_default($this->date_published, 0),
                ':date_unpublish'   =>  value_or_default($this->date_unpublish, 0),
                ':position'         =>  value_or_default($this->position, 0),
                ':fixed'            =>  value_or_default($this->fixed, 0),
                ':categories'       =>  implode(',', $this->categories),
                ':exclusions'       =>  implode(',', $this->exclusions),
                ':elements'         =>  json_encode($this->elements),
                ':access'           =>  value_or_default($this->access, 0),
                ':groups'           =>  $groups,
                ':enabled'          =>  value_or_default($this->enabled, 0),
                ':trigger'          =>  serialize($this->trigger),
                ':action'           =>  serialize($this->action),
                ':notes'            =>  value_or_default($this->notes, ""),
                ':date_modified'    =>  time()
            )
        );
		
		if(!$ok)
            throw new Exception($DB->get_last_error());

		webdictionary::save_element_strings('block', $this->id, $this->dictionary, $this->website);

        if(method_exists($events, 'trigger'))
        {
            $events->trigger(
                'block',
                'save',
                array(
                    'block' => $this
                )
            );
        }
		
		return true;
	}

    // TODO: add more block types (modes)
    public static function modes()
    {
        $modes = array(
            'block' => t(437, 'Block'),
            'theme' => t(368, 'Theme'),
            'poll' => t(557, 'Poll')
            /*
             * 'google_map' => 'Google Map',
             * 'bing_map' => 'Bing Map',
             * 'google_adsense' => 'Google Adsense'
             * etc.
             */
        );

        return $modes;
    }

    public static function custom_types()
    {
        global $DB;
        global $website;

        $data = $DB->query_single('block_types', 'nv_websites', ' id = '.$website->id);
        $data = mb_unserialize($data);

        return $data;
    }
	
	public static function types($orderby='id', $asc='asc')
	{
		global $theme;
        global $DB;
        global $website;

        $data = block::custom_types();

        // retrieve block types from theme
        $theme_blocks = json_decode(json_encode($theme->blocks), true);

        if(!is_array($theme_blocks))
            $theme_blocks = array();
        else
        {
            // retrieve more info for each block (title translation and block count)
            for($b=0; $b < count($theme_blocks); $b++)
            {
                $theme_blocks[$b]['title'] = $theme->t($theme_blocks[$b]['title']);
                $theme_blocks[$b]['count'] = $DB->query_single(
                    'COUNT(*) AS total',
                    'nv_blocks',
                    ' website = :wid AND type = :type',
                    NULL,
                     array(
                        ':wid' => $website->id,
                        ':type' => $theme_blocks[$b]['id']
                     )
                );
            }
        }

        if(!is_array($data))
            $data = array();

        $data = array_merge($data, $theme_blocks);

        // Navigate 1.6.6 compatibility (before title/code separation)
        for($d=0; $d < count($data); $d++)
        {
        	if(function_exists($theme->t))
            	$data[$d]['title'] = $theme->t($data[$d]['title']);

            if(empty($data[$d]['code']))
                $data[$d]['code'] = $data[$d]['title'];

            if(empty($data[$d]['type']))
                $data[$d]['type'] = 'block';
        }

		// Obtain a list of columns
		if(!is_array($data)) $data = array();
		$order = array();
				
		foreach($data as $key => $row)
			$order[$key]  = $row[$orderby];

		// Sort the data with volume descending, edition ascending
		// $data as the last parameter, to sort by the common key
		array_multisort($order, (($asc=='asc')? SORT_ASC : SORT_DESC), $data);

        /*
            $x[] = array( 'id'		=> 1,
                          'title'	=> 'test',
                          'code'    => 'codename',
                          'width'	=> 200,
                          'height'	=> 50,
                          'order'	=> 'theme',
                          'maximum' => 3
                        );

            $x = serialize($x);
            var_dump($x);
        */
		return $data;
	}

    // DEPRECATED; may be removed in navigate cms 3.0
	public static function types_update($array)
	{
		global $DB;
		global $website;

        $array = array_filter($array);
        sort($array);

		$array = serialize($array);

		$ok = $DB->execute('
		    UPDATE nv_websites
               SET block_types = :block_types
			 WHERE id = :wid',
            array(
                ':wid' => $website->id,
                ':block_types' => $array
            )
        );
					
		if(!$ok)
			throw new Exception($DB->last_error());
							
		return true;
	}

    public function property($property_name, $raw=false)
    {
        // load properties if not already done
        if(empty($this->properties))
        {
            if($this->class == 'block_group_block')
            {
                $this->properties = property::load_properties('block_group_block', $this->_block_group_id, 'block_group_block', $this->id);
            }
            else
            {
                $this->properties = property::load_properties('block', $this->type, 'block', $this->id);
            }
        }

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
        {
            if($this->class == 'block_group_block')
            {
                $this->properties = property::load_properties('block_group_block', $this->_block_group_id, 'block_group_block', $this->id);
            }
            else
            {
                $this->properties = property::load_properties('block', $this->type, 'block', $this->id);
            }
        }

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

    /* translates a links list from:
        array (5)
            icon => array (2)
                58e8beafe762d => "fa-twitter-square" (17)
                58e8beafe76cf => "fa-facebook-square" (18)
            title => array ()
            link => array (2)
                58e8beafe762d => "http://twitter.com/navigatecms" (30)
                58e8beafe76cf => "http://facebook.com/navigatecms" (31)
            new_window => array (2)
                58e8beafe762d => "1"
                58e8beafe76cf => "1"
            access => array (1)
                58e8beafe76cf => "1"

        to:

        array(2)
            0 => stdClass()
                icon ->
                title ->
                link ->
                new_window ->
                access ->
            1 => ...
    */
    public static function block_links_list_parse($block_links=array(), $filter_hidden=true)
    {
        $rs = array();
        if(!is_array($block_links))
            $block_links = array();

        foreach($block_links as $link_key => $links_data)
        {
            foreach($links_data as $link_reference => $link_value)
            {
                if(!isset($rs[$link_reference]))
                {
                    $rs[$link_reference] = new stdClass();
                    $rs[$link_reference]->id = $link_reference;
                }

                $rs[$link_reference]->$link_key = $link_value;
            }
        }

        $rs = array_filter(
            $rs,
            function($v) use ($filter_hidden)
            {
                if($filter_hidden && @$v->access > 0)
                    return false;
                else
                    return true;
            }
        );

        $rs = array_values($rs);

        return $rs;
    }

    public static function block_group_block($block_group, $block_code)
    {
        global $theme;

        $block = null;

        if(is_array($theme->block_groups))
        {
            foreach($theme->block_groups as $key => $bg)
            {
                // block_group matches?
                // if we don't have a block_group, find the first block_group block with the code requested
                if($bg->id == $block_group || empty($block_group))
                {
                    for($i=0; $i < count($bg->blocks); $i++)
                    {
                        if($bg->blocks[$i]->id == $block_code)
                        {
                            $block = $bg->blocks[$i];
                            $block->_block_group_id = $bg->id;
                            break;
                        }
                    }
                }
                if(!empty($block))
                    break;
            }
        }

        return $block;
    }

    public static function block_group_block_by_property($property)
    {
        global $theme;

        $block = null;
        foreach($theme->block_groups as $key => $bg)
        {
            for($i=0; $i < count($bg->blocks); $i++)
            {
                if(empty($bg->blocks[$i]->properties))
                    continue;

                foreach($bg->blocks[$i]->properties as $bgbp)
                {
                    if($bgbp->id == $property)
                    {
                        $block = $bg->blocks[$i];
                        $block->_block_group_id = $bg->id;
                        break;
                    }
                }
                if(!empty($block))
                    break;
            }
            if(!empty($block))
                break;
        }

        return $block;
    }

    // extension may be the extension codename or the extension object
    public static function extension_block($extension, $block_type)
    {
        if(is_string($extension))
        {
            $extension_name = $extension;
            $extension = new extension();
            $extension->load($extension_name);
        }

        $extension_blocks = $extension->definition->blocks;

        $block = null;

        // the block origin is not found, maybe was part of a missing extension
        if(empty($extension_blocks))
            return false;

        for($eb=0; $eb < count($extension_blocks); $eb++)
        {
            if($extension_blocks[$eb]->id == $block_type)
            {
                $block = $extension_blocks[$eb];
                break;
            }
        }

        return $block;
    }
	
	public function quicksearch($text)
	{
		global $DB;
		global $website;
		
		$like = ' LIKE '.protect('%'.$text.'%');
		
		// we search for the IDs at the dictionary NOW (to avoid inefficient requests)
		$DB->query('
            SELECT DISTINCT (nvw.node_id)
              FROM nv_webdictionary nvw
             WHERE nvw.node_type = "block" AND
                   nvw.text '.$like.' AND
                   nvw.website = '.$website->id,
            'array'
        );
						   
		$dict_ids = $DB->result("node_id");
		
		// all columns to look for	
		$cols[] = 'b.id' . $like;
		$cols[] = 'b.type' . $like;
		$cols[] = 'b.notes' . $like;		

		if(!empty($dict_ids))
			$cols[] = 'b.id IN ('.implode(',', $dict_ids).')';
			
		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $DB->query('
            SELECT *
            FROM nv_blocks
            WHERE website = '.intval($website->id),
            'object'
        );

        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

    public static function fontawesome_list()
    {
        if(empty($nv_fontawesome_classes))
        {
            $facss = file_get_contents(NAVIGATE_PATH.'/css/font-awesome/css/font-awesome.css');
            $facss = explode("\n", $facss);
            $facss = array_map(function($k)
            {
                if(strpos($k, '.')===0 && strpos($k, ':before')!==false)
                    return substr($k, 1, strpos($k, ':before')-1);
                else
                    return NULL;
            }, $facss);
            $facss = array_filter($facss);
            $nv_fontawesome_classes = array_values($facss);
            sort($nv_fontawesome_classes);
        }

        return $nv_fontawesome_classes;
    }

	// TODO: add other font icon libraries (ionicons, etc.)

    public static function __set_state(array $obj)
	{
		$tmp = new block();
		foreach($obj as $key => $val)
			$tmp->$key = $val;

		return $tmp;
	}

}

?>