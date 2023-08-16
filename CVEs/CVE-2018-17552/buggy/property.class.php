<?php
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/templates/template.class.php');

class property
{
	public $id;
	public $website;
	public $element;
	public $template;
	public $name;
	public $type;
	public $options;
	public $dvalue;	// default value
    public $multilanguage; // "true", "false" or empty
    public $helper;
    public $width;
	public $position;
	public $enabled;

    // decimal properties extra fields
    public $precision;
    public $prefix;
    public $suffix;
	
		// value
        // decimal
		// option
		// multiple option
		// boolean
		// text (multilanguage)
		// textarea (default multilanguage)
		// date
		// date & time
		// link (default multilanguage)
		// image (optional multilanguage)
		// file
        // color
		// comment
		// rating
		// country
		// coordinates
        // video
        // source code (optional multilanguage)
        // rich text area (default multilanguage)
        // web user groups
        // category (Structure entry)
        // categories (Multiple structure entries)
        // element (a specific content element)
        // elements (a selection of content elements)
		// product (not yet!)
	
	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('
            SELECT * FROM nv_properties
			 WHERE id = '.intval($id).'
			   AND website = '.$website->id)
        )
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];

		$this->id			= $main->id;
		$this->element 		= $main->element;
		$this->template		= $main->template;		
		$this->name			= $main->name;
		$this->type			= $main->type;
		$this->options		= mb_unserialize($main->options);
		$this->dvalue		= $main->dvalue;		
		$this->multilanguage= $main->multilanguage;
		$this->helper       = $main->helper;
		$this->width        = $main->width;
		$this->position		= $main->position;
		$this->enabled		= $main->enabled;
        // decimal format extra fields
        $this->precision    = $main->precision;
        $this->prefix       = $main->prefix;
        $this->suffix       = $main->suffix;

		if($this->type == 'date')
			$this->dvalue = core_ts2date($this->dvalue, false);		
		else if($this->type == 'datetime')
			$this->dvalue	= 	core_ts2date($this->dvalue, true);

	}
	
	public function load_from_post()
	{
		$this->element  	= $_REQUEST['property-element'];
		$this->template  	= $_REQUEST['property-template'];		
		$this->name			= $_REQUEST['property-name'];
		$this->type			= $_REQUEST['property-type'];
		$this->dvalue		= $_REQUEST['property-dvalue'];
        $this->multilanguage= ($_REQUEST['property-multilanguage']=='1'? 'true' : '');
        $this->helper       = $_REQUEST['property-helper'];

		if(($this->type == 'date' || $this->type == 'datetime') && !empty($this->dvalue))
			$this->dvalue	= 	core_date2ts($this->dvalue);

        if(empty($this->type))
            $this->type = 'value';

        if(empty($this->element) || $this->element == 'element')
            $this->element = 'item';
		
		if(isset($_REQUEST['property-position']))
			$this->position		= $_REQUEST['property-position'];
		$this->enabled		= intval($_REQUEST['property-enabled']);	
		
		// parse property options
		$this->options = array();
		
		$options = $_REQUEST['property-options'];
		$options = explode("\n", $options);
		
		foreach($options as $option)
		{
			$option = explode('#', $option, 2);
			if(empty($option[1])) continue;
			$this->options[trim($option[0])] = trim($option[1]);
		}
	}

    public function load_from_theme($theme_option, $value=null, $source='website', $template='', $website_id=null)
    {
        global $website;
        global $theme;

        $ws = $website;
        $ws_theme = $theme;
        if(!empty($website_id) && $website_id!=$website->id)
        {
            $ws = new website();
            $ws->load($website_id);
            $ws_theme = new theme();
            $ws_theme->load($ws->theme);
        }

        if(is_string($theme_option))
        {
            // theme_option as ID, not object
            if($source=='website')
            {
                if(empty($ws_theme->options))
                    $ws_theme->options = array();

                foreach($ws_theme->options as $to)
                {
                    if($to->id==$theme_option || $to->name==$theme_option)
                    {
                        $theme_option = $to;
                        @$theme_option->element = 'website';
                        break;
                    }
                }
            }
            else if($source=='template')
            {
                if(empty($ws_theme->templates))
                    $ws_theme->templates = array();

                foreach($ws_theme->templates as $tt)
                {
                    if($tt->type != $template)
                        continue;

                    if(empty($tt->properties))
                        $tt->properties = array();

                    foreach($tt->properties as $tp)
                    {
                        if($tp->id==$theme_option)
                        {
                            $theme_option = $tp;
                            break;
                        }
                    }
                }

                if(empty($theme_option->element) || $theme_option->element == 'element')
                    $theme_option->element = 'item';
            }
        }

        $this->id = $theme_option->id;
        $this->website = $ws->id;
       	$this->element = $theme_option->element;
       	$this->template = '';
       	$this->name = $theme_option->name;
       	$this->type = $theme_option->type;
       	$this->options = (array)$theme_option->options;
       	$this->dvalue = $theme_option->dvalue;	// default value
        $this->width = $theme_option->width;
       	$this->multilanguage = $theme_option->multilanguage;
       	$this->helper = $theme_option->helper;
        $this->function = $theme_option->function;
        $this->conditional = $theme_option->conditional;
       	$this->position = 0;
       	$this->enabled = 1;
        // decimal format extra fields
        $this->precision    = $theme_option->precision;
        $this->prefix       = $theme_option->prefix;
        $this->suffix       = $theme_option->suffix;

        if(substr($this->name, 0, 1)=='@')  // get translation from theme dictionary
            $this->name = $ws_theme->t(substr($this->name, 1));

        if(substr($this->helper, 0, 1)=='@')
            $this->helper = $ws_theme->t(substr($this->helper, 1));

        $this->value = $value;

        if(!isset($value) && isset($ws->theme_options->{$this->id}))
            $this->value = $ws->theme_options->{$this->id};

        if(empty($this->value) && empty($this->id))
            $this->value = $this->dvalue;

        if(is_object($this->value))
            $this->value = (array)$this->value;
    }

    public function load_from_webuser($property_id, $webuser_id=null)
    {
        global $website;
        global $theme;
        global $webuser;

        $wu = $webuser;
        if(!empty($webuser_id))
        {
            $wu = new webuser();
            $wu->load($webuser_id);
        }

        $ws = $website;
        $ws_theme = $theme;
        if($wu->website != $website->id)
        {
            $ws = new website();
            $ws->load($wu->website);
            $ws_theme = new theme();
            $ws_theme->load($ws->theme);
        }

        if(empty($ws_theme->webusers['properties']))
            $ws_theme->webusers['properties'] = array();

        foreach($ws_theme->webusers['properties'] as $to)
        {
            if($to->id==$property_id || $to->name==$property_id)
            {
                $webuser_option = $to;
                $webuser_option->element = 'webuser';
                break;
            }
        }

        $this->id = $webuser_option->id;
        $this->website = $ws->id;
       	$this->element = $webuser_option->element;
       	$this->template = '';
       	$this->name = $webuser_option->name;
       	$this->type = $webuser_option->type;
       	$this->options = (array)$webuser_option->options;
       	$this->dvalue = $webuser_option->dvalue;	// default value
        $this->width = $webuser_option->width;
       	$this->multilanguage = $webuser_option->multilanguage;
       	$this->helper = $webuser_option->helper;
        $this->function = $webuser_option->function;
        $this->conditional = $webuser_option->conditional;
       	$this->position = 0;
       	$this->enabled = 1;
        // decimal format extra fields
        $this->precision    = $webuser_option->precision;
        $this->prefix       = $webuser_option->prefix;
        $this->suffix       = $webuser_option->suffix;

        if(substr($this->name, 0, 1)=='@')  // get translation from theme dictionary
            $this->name = $ws_theme->t(substr($this->name, 1));

        if(substr($this->helper, 0, 1)=='@')
            $this->helper = $ws_theme->t(substr($this->helper, 1));

        $values = property::load_properties_associative('webuser', '', 'webuser', $wu->id);

        $this->value = $values[$this->id];

        if(is_null($this->value) && !empty($this->dvalue))
        {
            $this->value = $this->dvalue;
        }

        if(is_object($this->value))
            $this->value = (array)$this->value;
    }

    public function load_from_object($object, $value=null, $dictionary=null)
    {
        global $website;

        $this->id = $object->id;
        $this->website = $website->id;
       	$this->element = $object->element;
       	$this->template = '';
       	$this->name = $object->name;
       	$this->type = $object->type;
       	$this->options = (array)$object->options;
       	$this->dvalue = $object->dvalue;	// default value
       	$this->multilanguage = $object->multilanguage;
       	$this->helper = $object->helper;
       	$this->width = $object->width;
        $this->function = $object->function;
       	$this->position = 0;
       	$this->enabled = 1;

        $this->precision    = $object->precision;
        $this->prefix       = $object->prefix;
        $this->suffix       = $object->suffix;

        if(!empty($dictionary))
            $this->name = $dictionary->t($this->name);

        $this->value = $value;

        if(empty($this->value) && empty($this->id))
            $this->value = $this->dvalue;

        if(is_object($this->value))
            $this->value = (array)$this->value;

        // translate option titles (when property type = option)
        if(!empty($this->options))
        {
            $options = array();
            foreach($this->options as $key => $value)
            {
                if(!empty($dictionary))
                    $value = $dictionary->t($value);

                $options[$key] = $value;
            }

            $this->options = json_decode(json_encode($options));
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
		global $website;

		// remove all old entries
		if(!empty($this->id))
		{
			$DB->execute('
				DELETE FROM nv_properties
				  WHERE id = '.intval($this->id).' AND 
				        website = '.$website->id
			);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;
				
		$ok = $DB->execute('
          INSERT INTO nv_properties
		    (id, website, element, template, name, type,
			    options, dvalue, multilanguage, helper, position, enabled)
            VALUES
            ( 0,
              :website,
              :element,
              :template,
              :name,
              :type,
              :options,
              :dvalue,
              :multilanguage,
              :helper,
              :position,
              :enabled
            )',
          array(
            ':website' => value_or_default($this->website, $website->id),
            ':element' => $this->element,
            ':template' => $this->template,
            ':name' => $this->name,
            ':type' => $this->type,
            ':options' => serialize($this->options),
            ':dvalue' => value_or_default($this->dvalue, ""),
            ':multilanguage' => $this->multilanguage,
            ':helper' => value_or_default($this->helper, ''),
            ':position' => value_or_default($this->position, 0),
            ':enabled' => value_or_default($this->enabled, 0)
          )
        );
			
		if(!$ok) throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
		
		return true;
	}
	
	public function update()
	{
		global $DB;
		global $website;
			
		$ok = $DB->execute('
		    UPDATE nv_properties
                SET
                    element	= :element,
                    template = :template,
                    name = :name,
                    type = :type,
                    options = :options,
                    dvalue = :dvalue,
                    multilanguage = :multilanguage,
                    helper = :helper,
                    position = :position,
                    enabled = :enabled
            WHERE id = :id
              AND website = :website',
            array(
                ':element' => $this->element,
                ':template' => $this->template,
                ':name' => $this->name,
                ':type' => $this->type,
                ':options' => serialize($this->options),
                ':dvalue' => $this->dvalue,
                ':multilanguage' => $this->multilanguage,
                ':helper' => $this->helper,
                ':position' => value_or_default($this->position, 0),
                ':enabled' => value_or_default($this->enabled, 0),
                ':id' => $this->id,
                ':website' => $this->website
            )
        );
		
		if(!$ok) throw new Exception($DB->get_last_error());
		
		return true;
	}	

	/* code: usually name or ID of a template
	*/
	public static function elements($code, $element="", $website_id=null)
	{
		global $DB;
		global $website;
        global $theme;

        $data = array();

        if(empty($website_id))
            $website_id = $website->id;

        if(is_numeric($code))
        {
            // properties attached to a custom template (not a theme template)
            if(!empty($element))
                $element = ' AND element = '.protect($element);
            else
                $element = ' AND element != "block"';

            if($DB->query('
                   SELECT *
                   FROM nv_properties
                   WHERE template = '.protect($code).'
                   '.$element.'
                     AND website = '.$website_id.'
                   ORDER BY position ASC, id ASC'
                )
            )
            {
                $data = $DB->result();
            }
        }
        else
        {
            switch($element)
            {
                case 'webuser':
                    // webuser properties (set in theme definition)
                    $data = $theme->webusers['properties'];
                    break;

                case 'block':
                    // block type properties
                    for($b=0; $b < count($theme->blocks); $b++)
                    {
                        if($theme->blocks[$b]->id == $code)
                        {
                            $data = $theme->blocks[$b]->properties;
                            break;
                        }
                    }
                    break;

                case 'block_group_block':
                    // block group blocks properties
                    for($b=0; $b < count($theme->block_groups); $b++)
                    {
                        if($theme->block_groups[$b]->id == $code)
                        {
                            $data = array();
                            foreach($theme->block_groups[$b]->blocks as $bgb)
                            {
                                // note: properties in block group blocks can't have the same name
                                if(isset($bgb->properties))
                                    $data = array_merge($data, $bgb->properties);
                            }
                            break;
                        }
                    }
                    break;

                case 'comment':
                    // properties of the comments of a certain template type
                    $theme_template = new template();
                    if(!empty($website_id))
                    {
                        // force loading website information
                        $ws = new website();
                        $ws->load($website_id);
                        $ws_theme = $ws->theme;
                    }

                    $theme_template->load_from_theme($code, $ws_theme);

                    $comments_properties = $theme_template->comments->properties;

                    if(empty($comments_properties))
                        $comments_properties = array();

                    $data = array();

                    for($p=0; $p < count($comments_properties); $p++)
                    {
                        $data[] = $comments_properties[$p];
                    }
                    break;

                case 'extension':
                    $extension = new extension();
                    $extension->load($code);
                    $data = $extension->definition->options;
                    break;

                default:
                    // items, products, etc. (using the properties of a particular template)
                    // properties of a theme template
                    $theme_template = new template();
                    if(!empty($website_id))
                    {
                        // force loading website information
                        $ws = new website();
                        $ws->load($website_id);
                        $ws_theme = $ws->theme;
                    }

                    $theme_template->load_from_theme($code, $ws_theme);

                    $template_properties = $theme_template->properties;

                    if(empty($template_properties))
                        $template_properties = array();

                    $data = array();

                    for($p=0; $p < count($template_properties); $p++)
                    {
                        // if we want all properties, no matter the element assigned or
                        // if the property is not assigned to an element, we assume "item", or
                        // if the property is assigned to an element, we check it
                        // note: in this case, "element" is an alias of "item"

                        if( empty($element) ||
                            ($element == 'item' && empty($template_properties[$p]->element)) ||
                            ($element == 'product' && empty($template_properties[$p]->element)) ||
                            ($element == 'item' && $template_properties[$p]->element=="element") ||
                            $template_properties[$p]->element == $element
                        )
                            $data[] = $template_properties[$p];
                    }
                    break;
            }
        }

		return $data;
	}
	
	public static function types()
	{
		$types = array(
            'value'			=>	t(193, 'Value'),
            'decimal'		=>	t(643, 'Decimal'),
            'boolean'		=>  t(206, 'Boolean'),
            'option' 		=>	t(194, 'Option'),
            'moption' 		=>	t(211, 'Multiple option'),
            'text'			=>	t(54, 'Text'),
            'textarea'		=>	t(195, 'Textarea'),
            'rich_textarea'	=>	t(488, 'Rich textarea'),
            'date'			=>	t(86, 'Date'),
            'datetime'		=>	t(196, 'Date & time'),
            'link'			=>	t(197, 'Link'),
            'image'			=>	t(157, 'Image'),
            'file'			=>	t(82, 'File'),
            'video'			=>	t(272, 'Video'),
            'color' 		=>  t(441, 'Color'),
            'comment'		=>  t(205, 'Comment'),
            'rating'		=>	t(222, 'Rating'),
            'country'		=>	t(224, 'Country'),
            'coordinates'	=>	t(297, 'Coordinates'),
            'product'		=>	t(198, 'Product'),
            'category'		=>	t(78, 'Category'),
            'categories'	=>	t(330, 'Categories'),
            'item'		    =>	t(180, 'Item'),
            'source_code'   =>  t(489, 'Source code'),
            'webuser_groups'=>  t(512, 'Selected web user groups')
        );
						
		return $types;		
	}

	public static function reorder($element, $template, $order, $enableds=NULL)
	{
		global $DB;
		global $website;
		
		$item = explode("#", $order);
							
		for($i=0; $i < count($item); $i++)
		{		
			if(empty($item[$i])) continue;

			$enabled = '';			
			if(is_array($enableds))
			{
				$enabled = ', enabled = 0 ';
				for($e=0; $e < count($enableds); $e++)
				{
					if($enableds[$e]==$item[$i]) $enabled = ', enabled = 1 ';
				}
			}
			
			$ok =	$DB->execute('UPDATE nv_properties
									 SET position = '.($i+1).' '.$enabled.' 
								   WHERE id = '.$item[$i].'
								     AND website = '.$website->id);
			
			if(!$ok) return array("error" => $DB->get_last_error()); 
		}
			
		return true;	
	}	
	
	public static function load_properties_associative($element, $template, $object_type, $object_id)
	{
		// maybe we have cache of the current website?
		global $properties;
		
		if(isset($properties[$object_type.'-'.$object_id]))
			$props = $properties[$object_type.'-'.$object_id];
		else
			$props = property::load_properties($element, $template, $object_type, $object_id);

		// now create the associative array by property name => value
		$associative_properties = array();
		
		if(!is_array($props)) $props = array();
		foreach($props as $property)
		{
            if(is_numeric($property->id))
                $associative_properties[$property->name] = $property->value;
            else
                $associative_properties[$property->id] = $property->value;
		}

		return $associative_properties;
	}	

    /*
     * element: type of the object where we need to read its properties definition (f.e., "template")
     * code: subtype of object (f.e., the template "blog_post")
     * object_type: type of the object where the properties values are saved (f.e. "item")
     * object_id: ID of the object where the properties values are saved (f.e. "25")
     * item_uid: used to differentiate between blocks in a block group (f.e. "64cc8f20-741d-11e6-9606-45d284f45e04")
     *
     * note: it is very common that element=object
     */

	public static function load_properties($element, $code, $object_type, $object_id, $item_uid=null)
	{
		global $DB;
		global $website;
        global $theme;

        if($element != $object_type)
        {
            if($element == 'extension')
            {
                $extension = new extension();
                $extension->load($code);
                $e_properties = $extension->definition->options;
            }
        }
        else if($object_type == 'block_group_block')
        {
            $block = block::block_group_block($code, $object_id);
            $e_properties = $block->properties;

            // we must find the block group ID to search the assigned property values
            // $object_id MUST BE the numeric ID of the block group
            if(!empty($code))
            {
                $block_group_id = $DB->query_single(
                    'MAX(id)',
                    'nv_block_groups',
                    ' code = '.protect($code).' AND website = '.$website->id
                );
                $object_id = $block_group_id;
                if(empty($block_group_id))
                    $object_id = 0;
            }
        }
        else if($object_type == 'extension_block')
        {
            // in this case, the parameters must match the following:
            //      $element => (not used)
            //      $code => type of the block in the extension definition
            //      $object_type => "extension_block"
            //      $object_id => type of the block_group (f.e. "sidebar" or the one defined in the theme definition);
            //                    if null, we have to find the right value
            //      $item_uid => the unique id assigned to the block in the block_group

            // find the extension block definition, to get the list of properties
            $extensions_blocks = extension::blocks();
            for($eb=0; $eb < count($extensions_blocks); $eb++)
            {
                if($extensions_blocks[$eb]->id == $code)
                {
                    $e_properties = $extensions_blocks[$eb]->properties;
                    break;
                }
            }

            if(empty($object_id))
            {
                // we need to find the block_group ID by checking the block uid
                $block_group_id = $DB->query_single('id', 'nv_block_groups', ' blocks LIKE '.protect('%'.$item_uid.'%').' AND website = '.$website->id);
                $object_id = $block_group_id;
                if(empty($block_group_id))
                    $object_id = 0;
            }
            // we must find the REAL numeric block group ID (based on its code) to get the assigned property values
            // at the end, $object_id MUST BE the numeric ID of the block group (we have only its codename, not the numeric ID)
            else if(!empty($code))
            {
                $block_group_id = $DB->query_single('MAX(id)', 'nv_block_groups', ' code = '.protect($object_id).' AND website = '.$website->id);
                $object_id = $block_group_id;
                if(empty($block_group_id))
                    $object_id = 0;
            }

            $object_type = "block_group-extension-block";
        }
        else if($object_type == 'webuser')
        {
            // the properties are set in the theme definition
            $e_properties = $theme->webusers['properties'];
        }
        else // item, structure, block, comment, product
        {
		    // load properties associated with the element type
		    $e_properties = property::elements($code, $object_type);
        }

		// load the values for multilanguage strings
		$dictionary = webdictionary::load_element_strings('property-'.$object_type, $object_id, $item_uid);

    	// load the assigned (simple) properties values
        // check node_uid empty or NULL to mantain compatibility with Navigate CMS < 2.2
		$DB->query('
		    SELECT * FROM nv_properties_items
 			 WHERE element = '.protect($object_type).'
			   AND node_id = '.protect($object_id).
			   (empty($item_uid)? '' : ' AND ( node_uid = '.protect($item_uid).' OR node_uid = "" OR node_uid IS NULL )').'
			   AND website = '.$website->id,
            'array'
        );

		$values = $DB->result();
        
		if(!is_array($values))
            $values = array();

        $o_properties = array();

        if(!is_array($e_properties))
            $e_properties = array();

        $p = 0;
		foreach($e_properties as $e_property)
		{
            if(is_object($e_property))
                $o_properties[$p] = clone $e_property;
            else
                $o_properties[$p] = $e_property;

            if(isset($o_properties[$p]->dvalue))
                $o_properties[$p]->value = $o_properties[$p]->dvalue;

			foreach($values as $value)
			{
    			if($value['property_id'] == $o_properties[$p]->id)
				{
    				$o_properties[$p]->value = $value['value'];

					if($value['value']=='[dictionary]')
					{
						$o_properties[$p]->value = array();
						foreach($website->languages_list as $lang)
						{
							$o_properties[$p]->value[$lang] = $dictionary[$lang]['property-'.$o_properties[$p]->id.'-'.$lang];
						}
					}
				}
			}

            if(substr($o_properties[$p]->name, 0, 1)=='@')  // get translation from theme dictionary
                $o_properties[$p]->name = $theme->t(substr($o_properties[$p]->name, 1));

            if(is_object($o_properties[$p]->value))
                $o_properties[$p]->value = (array)$o_properties[$p]->value;

            $p++;
		}

		return $o_properties;
	}

    // called when using navigate cms
	public static function save_properties_from_post($object_type, $object_id, $code=null, $element=null, $object_uid=null)
	{
		global $DB;
		global $website;
        global $theme;
		
		$dictionary = array();

		// load properties associated with the element type
        if($object_type=='block_group_block')
        {
            $block = block::block_group_block($code, $element);
            $properties = $block->properties;

            if(!is_numeric($object_id))
            {
                $block_group_id = $DB->query_single('MAX(id)', 'nv_block_groups', ' code = '.protect($code).' AND website = '.$website->id);
                $object_id = $block_group_id;
                if(empty($block_group_id))
                    $object_id = 0;
            }
        }
        else if($object_type=='extension_block')
        {
            // in this case, the parameters must match the following:
            //      $element => (not used)
            //      $code => type of the block in the extension definition
            //      $object_type => "extension_block"
            //      $object_id => type of the block_group (f.e. "sidebar" or the one defined in the theme definition)
            //      $object_uid => the unique id assigned to the block in the block_group

            // find the extension block definition, to get the list of properties
            $extensions_blocks = extension::blocks();
            for($eb=0; $eb < count($extensions_blocks); $eb++)
            {
                if($extensions_blocks[$eb]->id == $code)
                {
                    $properties = $extensions_blocks[$eb]->properties;
                    break;
                }
            }

            // we must find the REAL numeric block group ID (based on its code) to get the assigned property values
            // $object_id MUST BE the numeric ID of the block group (we have only its codename, not the numeric ID)
            if(!empty($code))
            {
                $block_group_id = $DB->query_single('MAX(id)', 'nv_block_groups', ' code = '.protect($object_id).' AND website = '.$website->id);
                $object_id = $block_group_id;
                if(empty($block_group_id))
                    $object_id = 0;
            }

            $object_type = "block_group-extension-block";
        }
        else if($object_type == 'webuser')
        {
            // the properties set in the theme definition
            $properties = $theme->webusers['properties'];
        }
        else
        {
            if(empty($code)) $code = $_REQUEST['property-template'];
            if(empty($element)) $element = $_REQUEST['property-element'];
            $properties = property::elements($code, $element);
        }

        if(!is_array($properties))
            $properties = array();

		foreach($properties as $property)
		{
			// ALWAYS SAVE the property value, even if it is empty

            $property_value = $_REQUEST['property-'.$property->id];

            // multilanguage property?
            if(in_array($property->type, array('text', 'textarea', 'link', 'rich_textarea')) || @$property->multilanguage=='true' || @$property->multilanguage===true)
                $property_value = '[dictionary]';

            // date/datetime property?
            if($property->type=='date' || $property->type=='datetime')
                $property_value = core_date2ts($_REQUEST['property-'.$property->id]);

            if($property->type=='moption' && !empty($_REQUEST['property-'.$property->id]))
                $property_value = implode(',', $_REQUEST['property-'.$property->id]);

            if($property->type=='coordinates')
                $property_value = $_REQUEST['property-'.$property->id.'-latitude'].'#'.$_REQUEST['property-'.$property->id.'-longitude'];

            if($property->type=='decimal')
                $property_value = core_string2decimal($_REQUEST['property-'.$property->id]);

            if($property->type=='webuser_groups' && !empty($_REQUEST['property-'.$property->id]))
                $property_value = 'g'.implode(',g', $_REQUEST['property-'.$property->id]);

            // boolean (checkbox): if not checked,  form does not send the value
            if($property->type=='boolean' && !isset($_REQUEST['property-'.$property->id]))
                $property_value = 0;

            // item (select2): if no selection, the form does not send a value (HTML);
            // if we don't set an empty value, Navigate would take that as non-existant field and would set the default value,
            // which is different as the user may really want to set "empty" as the value
            if(($property->type=='element' || $property->type=='item') && !isset($_REQUEST['property-'.$property->id]))
                $property_value = "";

            // remove the old property row
            $DB->execute('
                DELETE
                     FROM nv_properties_items
                    WHERE property_id = '.protect($property->id).'
                      AND element = '.protect($object_type).'
                      AND node_id = '.protect($object_id).
                      (empty($object_uid)? '' : ' AND node_uid = '.protect($object_uid)).'
                      AND website = '.$website->id
            );

            // now insert the new row
            $DB->execute('
                INSERT INTO nv_properties_items
                    (id, website, property_id, element, node_id, node_uid, name, value)
                VALUES
                    (   0,
                        :website,
                        :property_id,
                        :type,
                        :object_id,
                        :object_uid,
                        :name,
                        :value
                    )',
                array(
                    ':website' => $website->id,
                    ':property_id' => $property->id,
                    ':type' => $object_type,
                    ':object_id' => value_or_default($object_id, 0),
                    ':object_uid' => value_or_default($object_uid, ""),
                    ':name' => value_or_default($property->name, $property->id),
                    ':value' => value_or_default($property_value, "")
                )
            );

            // save in the dictionary the multilanguage properties
            $default_language = '';
            if($property->multilanguage === 'false' || $property->multilanguage === false)
                $default_language = $website->languages_list[0];

            if(in_array($property->type, array('text', 'textarea', 'rich_textarea')) || @$property->multilanguage=='true' || @$property->multilanguage===true)
            {
                foreach($website->languages_list as $lang)
                {
                    if(!empty($default_language))   // property is NOT multilanguage, use the first value for all languages
                        $_REQUEST['property-'.$property->id.'-'.$lang] = $_REQUEST['property-'.$property->id.'-'.$default_language];

                    $dictionary[$lang]['property-'.$property->id.'-'.$lang] = $_REQUEST['property-'.$property->id.'-'.$lang];
                }
            }
            else if($property->type == 'link')
            {
                foreach($website->languages_list as $lang)
                {
                    $link = $_REQUEST['property-'.$property->id.'-'.$lang.'-link'].
                                '##'.$_REQUEST['property-'.$property->id.'-'.$lang.'-title'].
                                '##'.$_REQUEST['property-'.$property->id.'-'.$lang.'-target'];

                    $dictionary[$lang]['property-'.$property->id.'-'.$lang] = $link;

                    if(!empty($default_language))   // property is NOT multilanguage, use the first value for all languages
                        $dictionary[$lang]['property-'.$property->id.'-'.$lang] = $dictionary[$lang]['property-'.$property->id.'-'.$default_language];
                }
            }
		}

		if(!empty($dictionary))
        {
            $property_element = $_REQUEST['property-element'];
            if($object_type=='block_group_block')
                $property_element = 'block_group_block';
            else if($object_type=='block_group-extension-block')
                $property_element = 'block_group-extension-block';

			webdictionary::save_element_strings('property-'.$property_element, $object_id, $dictionary, $website->id, $object_uid);
        }

        return true;
	}

    // save properties from an associative array (ID => VALUE)
    // multilanguage values (ID => array(LANG => VALUE, LANG => VALUE...)
    // moption values (ID => array(x,y,z...)
    // dates => timestamps
    // coordinates (ID => array("latitude" => ..., "longitude" => ...)
    // change only the given properties, not the other existing ones
    public static function save_properties_from_array($object_type, $object_id, $code, $properties_assoc=array(), $ws=null, $node_uid="")
   	{
   		global $DB;
   		global $website;
        global $theme;

        if(empty($ws))
            $ws = $website;

   		$dictionary = array();

        $property_object_type = $object_type;   // object_type: item, structure, block, block_group_block

        if($object_type=='block_group_block')
        {
            // we have to identify the block subtype: block, block_type, block_group_block or extension_block
            // so we can get its properties definition
            if(!is_numeric($object_id))
            {
                // assume there can only be one block group of the same type
                $block_group_id = $DB->query_single('MAX(id)', 'nv_block_groups', ' code = '.protect($code).' AND website = '.$ws->id);
                $object_id = $block_group_id;
                if(empty($block_group_id))
                    $object_id = 0;
            }

            if(!empty($node_uid))
            {
                $bg = new block_group();
                $bg->load($object_id);

                for($b=0; $b < count($bg->blocks); $b++)
                {
                    if($bg->blocks[$b]['uid'] == $node_uid)
                    {
                        $block_id = $bg->blocks[$b]['id'];
                        if($bg->blocks[$b]['type']=='extension')
                        {
                            // an extension block
                            $property_object_type = 'block_group-extension-block';

                            // load the extension, if installed in this instance
                            $extension = new extension();
                            $extension->load($bg->blocks[$b]['extension']);

                            // find the property declaration in the extension definition
                            if(isset($extension->definition->blocks))
                            {
                                for($eb = 0; $eb < count($extension->definition->blocks); $eb++)
                                {
                                    if($extension->definition->blocks[$eb]->id == $block_id)
                                    {
                                        $block = $extension->definition->blocks[$eb];
                                        break;
                                    }
                                }
                            }
                            else
                            {
                                // ignore this property, extension is not installed or it does not have the requested block definition
                                continue;
                            }
                        }
                        else
                        {
                            // a block group block
                            $property_object_type = 'block_group_block';
                            $block = block::block_group_block($code, $block_id);
                        }
                        // note: standard blocks don't "embed" their properties in a block group,
                        // they have their own separate values
                        break;
                    }
                }
            }
            else
            {
                // compatibility with < Navigate 2.1 themes (to be removed)
                $properties_names = array_keys($properties_assoc);
                $block = block::block_group_block_by_property($properties_names[0]);
            }

            if(!isset($block->properties) || empty($block->properties))
                return false;

            $properties = $block->properties;
        }
        else
        {
            $properties = property::elements($code, $object_type);
        }

        if(!is_array($properties))
            $properties = array();

        foreach($properties as $property)
   		{
            if(!isset($properties_assoc[$property->name]) && !isset($properties_assoc[$property->id]))
                continue;

            $values_dict = array();
            $value = '';

            // we try to find the property value by "property name", if empty then we try to find it via "property id"
            if(isset($properties_assoc[$property->name]))
                $value = $properties_assoc[$property->name];

            if(empty($value))
                $value = $properties_assoc[$property->id];

            // multilanguage property?
            if( in_array($property->type, array('text', 'textarea', 'link', 'rich_textarea')) ||
                @$property->multilanguage=='true' ||
                @$property->multilanguage===true
            )
            {
	            if(isset($properties_assoc[$property->name]))
                    $values_dict = $properties_assoc[$property->name];

	            if(empty($values_dict))
                    $values_dict = $properties_assoc[$property->id];

                $value = '[dictionary]';
            }

            if($property->type=='coordinates')
            {
                if(is_array($value))
                    $value = $value['latitude'].'#'.$value['longitude'];
                // if it isn't an array, then we suppose it already has the right format
            }

            // property->type "decimal"; we don't need to reconvert, it should already be in the right format
            // (no thousands separator, dot as decimal separator)

            if($property->type=='webuser_groups' && !empty($value))
                $value = 'g'.implode(',g', $value);

            // boolean (checkbox): if not checked,  form does not send the value
            if($property->type=='boolean' && empty($value))
                $value = 0;

            if(is_null($value))
                $value = ""; // should not be needed because of value_or_default, but doing this here fixes some warnings

            // remove the old property value row
            $DB->execute('
				DELETE FROM nv_properties_items
                	  WHERE property_id = '.protect($property->id).'
                        AND element = '.protect($property_object_type).'
                        AND node_id = '.protect($object_id).
                        (empty($node_uid)? '' : ' AND node_uid = '.protect($node_uid)).'
                        AND website = '.$ws->id
            );

            // now we insert a new row
            $DB->execute('
			    INSERT INTO nv_properties_items
				    (id, website, property_id, element, node_id, node_uid, name, value)
				VALUES
				    (   0,
						:website,
						:property_id,
						:type,
						:object_id,
						:node_uid,
						:name,
						:value
                    )',
                array(
                    ':website' => $ws->id,
                    ':property_id' => $property->id,
                    ':type' => $property_object_type,
                    ':object_id' => value_or_default($object_id, 0),
                    ':node_uid' => value_or_default($node_uid, ""),
                    ':name' => $property->name,
                    ':value' => value_or_default($value, "")
                )
            );

            // $error = $DB->get_last_error();

            // set the dictionary for the multilanguage properties
            $default_language = '';
            if(isset($property->multilanguage) && ($property->multilanguage === 'false' || $property->multilanguage === false))
                $default_language = $ws->languages_list[0];

            if(in_array($property->type, array('text', 'textarea', 'rich_textarea', 'link')) || @$property->multilanguage=='true' || @$property->multilanguage===true)
            {
                foreach($ws->languages_list as $lang)
                {
                    if(!empty($default_language))   // property is NOT multilanguage, use the first value for all languages
	                    $dictionary[$lang]['property-'.$property->id.'-'.$lang] = $values_dict[$default_language];
	                else
	                    $dictionary[$lang]['property-'.$property->id.'-'.$lang] = $values_dict[$lang];
                }
            }
   		}

   		if(!empty($dictionary))
        	webdictionary::save_element_strings('property-'.$property_object_type, $object_id, $dictionary, $ws->id, $node_uid);

       return true;
   	}

    public static function remove_properties($element_type, $element_id, $website_id)
    {
        global $DB;
        global $website;

        if(empty($website_id))
            $website_id = $website->id;

        webdictionary::save_element_strings('property-'.$element_type, $element_id, array());

        $DB->execute('
            DELETE FROM nv_properties_items
                  WHERE website = '.$website_id.'
                    AND element = '.protect($element_type).'
                    AND node_id = '.intval($element_id).'
        ');
    }

    public static function country_name_by_code($code, $language="")
    {
        global $DB;

        $lang = core_get_language($language);

        $DB->query('SELECT name
					FROM nv_countries
		 			WHERE lang = '.protect($lang).'
					  AND country_code = '.protect($code));

        $row = $DB->first();

        return $row->name;
    }

	public static function countries($lang="", $alpha3=false)
	{
		global $DB;
        global $user;

        // static function can be called from navigate or from a webget (user then is not a navigate user)
		if(empty($lang))
			$lang = $user->language;

        $code = 'country_code';
        if($alpha3)
            $code = 'alpha3';

		$DB->query('SELECT '.$code.' AS country_code, name
					FROM nv_countries
		 			WHERE lang = '.protect($lang).'
					ORDER BY name ASC');
					
		$rs = $DB->result();
		
		if(empty($rs))
		{
			// failback, load English names	
			$DB->query('SELECT '.$code.' AS country_code, name
						FROM nv_countries
						WHERE lang = "en"
						ORDER BY name ASC');
						
			$rs = $DB->result();
		}
		
		$out = array();
		
		foreach($rs as $country)
		{
			$out[$country->country_code] = $country->name;	
		}
		
		return $out;
	}

	public static function countries_regions($country_id="")
	{
		global $DB;

		// note: regions have no translation to any language right now

        $country_query = " 1=1 ";
        if(!empty($country_id))
            $country_query = ' AND r.country = '.protect($country_id);

		$DB->query('
            SELECT r.`numeric` AS region_id, c.country_code, r.name
            FROM nv_countries c, nv_countries_regions r
            WHERE c.lang = "en" AND
                  c.`numeric` = r.country AND
                  r.lang = "" AND
                  '.$country_query.'
            ORDER BY name ASC
        ');

		$rs = $DB->result();

		return $rs;
	}

    public static function country_region_name_by_code($code, $language="")
    {
        global $DB;

        // TODO: region names have no translation in database at this time
        // $lang = core_get_language($language);

        $DB->query('SELECT name
					FROM nv_countries_regions
		 			WHERE `numeric` = '.protect($code));

        $row = $DB->first();

        return $row->name;
    }

    public static function languages()
	{
		global $DB;

        $DB->query('SELECT code, name FROM nv_languages');
        $languages_rs = $DB->result();
        $out = array();

        foreach($languages_rs as $lang)
            $out[$lang->code] = $lang->name;

		return $out;
	}
	
	public static function timezones($country=null, $lang="")
	{
		$out = array();
		
		if(!empty($country))
			$timezone_identifiers = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, strtoupper($country));
		else
			$timezone_identifiers = DateTimeZone::listIdentifiers(); // DateTimeZone::ALL
			
		foreach( $timezone_identifiers as $value )
		{
			//if ( preg_match( '/^(America|Antartica|Arctic|Asia|Atlantic|Europe|Indian|Pacific)\//', $value ) || true )
			//{
				$ex = explode("/", $value, 2); // obtain continent, city	

				$this_tz = new DateTimeZone($value);
				$now = new DateTime("now", $this_tz);
				$offset = $this_tz->getOffset($now);
				$utc = $offset / 3600;
										
				if($utc > 0) $utc = '+'.$utc;
				else if($utc == 0) $utc = '-'.$utc;

				$continent = $ex[0];	 

				switch($continent)
				{
					case 'Africa':
						$continent = t(284, 'Africa');
						break;

					case 'America':
						$continent = t(310, 'America');
						break;
						
					case 'Antartica':
						$continent = t(311, 'Antartica');
						break;						

					case 'Arctic':
						$continent = t(312, 'Arctic');
						break;						

					case 'Asia':
						$continent = t(313, 'Asia');
						break;						

					case 'Atlantic':
						$continent = t(314, 'Atlantic');
						break;						

					case 'Europe':
						$continent = t(315, 'Europe');
						break;						

					case 'Indian':
						$continent = t(316, 'Indian');
						break;						

					case 'Pacific':
						$continent = t(317, 'Pacific');
						break;	
						
					default:
						// leave it in english
				}
				$city = str_replace('_', ' ', $ex[1]);
				
				if(!empty($city))			
					$out[$value] = $offset.'#'.'(UTC'.$utc.') '.$continent.'/'.$city;
				else
					$out[$value] = $offset.'#'.'(UTC'.$utc.') '.$value;
			//}
		}		
		
		asort($out, SORT_NUMERIC);
		
		$rows = array();
		
		foreach($out as $value => $text)
		{
			$rows[$value] = substr($text, strpos($text, '#')+1);
		}
		
		return $rows;
	}

    public static function find($type, $property, $value)
    {
        global $DB;
        global $website;

        $DB->query('
            SELECT * FROM nv_properties_items
            WHERE website = '.protect($website->id).'
              AND property_id = '.protect($property).'
              AND value = '.protect($value),
            'object');

        return $DB->result();
    }
	
    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_properties WHERE website = '.protect($website->id), 'object');

        if($type='json')
            $out['nv_properties'] = json_encode($DB->result());

        $DB->query('SELECT * FROM nv_properties_items WHERE website = '.protect($website->id), 'object');

        if($type='json')
            $out['nv_properties_items'] = json_encode($DB->result());

        if($type='json')
            $out = json_encode($out);

        return $out;
    }
}

?>