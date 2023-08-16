<?php
require_once(NAVIGATE_PATH.'/lib/external/force-utf8/Encoding.php');
nvweb_webget_load('content');

function nvweb_properties($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $cache;
	global $properties;

	$out = '';

    switch(@$vars['mode'])
	{
        case 'website':
            $wproperty = new property();
            $wproperty->load_from_theme($vars['property']);
            if(!empty($wproperty))
                $out = nvweb_properties_render($wproperty, $vars);
            break;

        case 'webuser':
            $wuproperty = new property();
            $wuproperty->load_from_webuser($vars['property']);
            if(!empty($wuproperty))
                $out = nvweb_properties_render($wuproperty, $vars);
            break;


        case 'element':
		case 'item': // deprecated, may be removed in a future version

            // if item ID is not given and the current object an element or a structure category?
            if(empty($vars['id']) && $current['type']=='structure')
            {
                // find the first embedded element for the current category
                // (because the template code has requested specifically to return the property from an element!)
                $itm = nvweb_content_items($current['object']->id, true, 1, true, 'priority');

                if(!empty($itm) && isset($itm[0]))
                    $vars['id'] = $itm[0]->id;
                else
                    $vars['id'] = 0;
            }

            if(!isset($properties['item-'.$vars['id']]) && !empty($vars['id']))
			{
				// load item template
				if(empty($vars['template']))
					$vars['template'] = $DB->query_single('template', 'nv_items', ' id = '.intval($vars['id']));

                // if template is not defined (embedded element), take its category template
                if(empty($vars['template']))
                    $vars['template'] = $DB->query_single(
                        'template',
                        'nv_structure',
                        ' id = (
                            SELECT category
                            FROM nv_items
                            WHERE id = '.intval($vars['id']).'
                        )'
                    );

				$properties['item-'.$vars['id']] = property::load_properties("item", $vars['template'], 'item', $vars['id']);
			}
            else if(empty($vars['id']))
            {
                $vars['type'] = $current['object']->template;

                if($current['type'] == "item")
                {
                    $vars['id'] = $current['object']->id;
                }
                else if($current['type'] == "structure")
                {
                    // find the first embedded content associated with this structure entry
                    // (because the template code has requested specifically to return the property from an element!)
                    $itm = nvweb_content_items($current['object']->id, true, 1, true, 'priority');

                    if(!empty($itm) && isset($itm[0]))
                        $vars['id'] = $itm[0]->id;
                    else
                        $vars['id'] = 0;
                }

                if(!isset($properties['item-'.$vars['id']]))
                    $properties['item-'.$current['object']->id] = property::load_properties("item", $vars['type'], 'item', $vars['id']);
            }

			$current_properties	= $properties['item-'.$vars['id']];

			// now we find the property requested
			if(!is_array($current_properties)) $current_properties = array();
			foreach($current_properties as $property)
			{
				if($property->id == $vars['property'] || $property->name == $vars['property'])
				{
					$out = nvweb_properties_render($property, $vars);
					break;	
				}
			}				
			break;

        case 'block':
			if(!isset($properties['block-'.$vars['id']]))
			{
				// load item type
				if(empty($vars['type']))
                {
                    $vars['type'] = $DB->query_single('type', 'nv_blocks', ' id = '.intval($vars['id']));

                    if(empty($cache['block_types']))
                        $cache['block_types'] = block::types();

                    // we need to know if the block is defined in the active theme or in the database (numeric ID)
                    foreach($cache['block_types'] as $bt)
                    {
                        if($bt['code']==$vars['type'])
                        {
                            $vars['type'] = $bt['id'];
                            break;
                        }
                    }
                }

				$properties['block-'.$vars['id']] = property::load_properties("block", $vars['type'], 'block', $vars['id']);
			}

			$current_properties	= $properties['block-'.$vars['id']];

			// now we find the property requested
			if(!is_array($current_properties)) $current_properties = array();
			foreach($current_properties as $property)
			{
				if($property->id == $vars['property'] || $property->name == $vars['property'])
				{
					$out = nvweb_properties_render($property, $vars);
					break;
				}
			}
			break;

        case 'block_group_block':
            // find block_group block definition
            $block_group = null; // unknown
            $block_code = $vars['id'];
            $block_uid = $vars['uid'];

            if(empty($block_code))
            {
                // find the block group block which has the requested property (property name must be unique!)
                $block = block::block_group_block_by_property($vars['property']);
                $block_code = $block->type;
            }
            else
            {
                // find the block group block by its type
                $block = block::block_group_block($block_group, $block_code);
            }

            $properties = $block->properties;

            $current_properties = property::load_properties($block_code, $block->_block_group_id, 'block_group_block', $block_code, $block_uid);

			// now we try to find the property requested

            if(!is_array($current_properties))
                $current_properties = array();

			foreach($current_properties as $property)
			{
				if($property->id == $vars['property'] || $property->name == $vars['property'])
				{
					$out = nvweb_properties_render($property, $vars);
					break;
				}
			}
			break;
		
		case 'structure':
			if(empty($vars['id']))
			{
				if($current['type']=='structure')
					$vars['id'] = $current['id'];
				else
					$vars['id'] = $current['object']->category;
			}
						
			if(!isset($properties['structure-'.$vars['id']]))	
			{
				// load category template
				$category_template = $DB->query_single('template', 'nv_structure', ' id = '.intval($vars['id']));
				if(!empty($category_template))
				{
					$properties['structure-'.$vars['id']] = property::load_properties("structure", $category_template, 'structure', $vars['id']);
				}
			}

			$current_properties	= $properties['structure-'.$vars['id']];
			
			// now we try to find the property requested

            if(!is_array($current_properties))
			    $current_properties = array();

			foreach($current_properties as $property)
			{
				if($property->id == $vars['property'] || $property->name == $vars['property'])
				{
                    if($vars['return']=='object')
                        $out = $property;
                    else
					    $out = nvweb_properties_render($property, $vars);
					break;	
				}
			}			
			break;

        case 'comment':
            if(!isset($properties['comment-'.$vars['id']]))
                $properties['comment-'.$vars['id']] = property::load_properties("comment", $vars['template'], 'comment', $vars['id']);

            $current_properties	= $properties['comment-'.$vars['id']];

            // now we find the property requested
            if(!is_array($current_properties)) $current_properties = array();
            foreach($current_properties as $property)
            {
                if($property->id == $vars['property'] || $property->name == $vars['property'])
                {
                    if($vars['return']=='object')
                        $out = $property;
                    else
                        $out = nvweb_properties_render($property, $vars);
                    break;
                }
            }
            break;

        case 'product':
            if(!isset($properties['product-'.$vars['id']]))
                $properties['product-'.$vars['id']] = property::load_properties("product", $vars['template'], 'product', $vars['id']);

            $current_properties	= $properties['product-'.$vars['id']];

            // now we find the property requested
            if(!is_array($current_properties)) $current_properties = array();
            foreach($current_properties as $property)
            {
                if($property->id == $vars['property'] || $property->name == $vars['property'])
                {
                    if($vars['return']=='object')
                        $out = $property;
                    else
                        $out = nvweb_properties_render($property, $vars);
                    break;
                }
            }
            break;
		
		default:
            // find the property source by its name
            $current_properties = array();

            // is a theme property?
            $current_properties[] = new property();
            $current_properties[0]->load_from_theme($vars['property']);

			if($current['type']=='item')
			{
				if(!isset($properties['item-'.$current['object']->id]))
					$properties['item-'.$current['object']->id] = property::load_properties("item", $current['object']->template, 'item', $current['object']->id);

                $current_properties = array_merge($current_properties, $properties['item-'.$current['object']->id]);
			}
			else if($current['type']=='product')
			{
				if(!isset($properties['product-'.$current['object']->id]))
					$properties['product-'.$current['object']->id] = property::load_properties("product", $current['object']->template, 'product', $current['object']->id);

                $current_properties = array_merge($current_properties, $properties['product-'.$current['object']->id]);
			}
			else if($current['type']=='structure')
			{
				if(!isset($properties['structure-'.$current['object']->id]))
					$properties['structure-'.$current['object']->id] = property::load_properties("structure", $current['object']->template, 'structure', $current['object']->id);

                $current_properties = array_merge($current_properties, $properties['structure-'.$current['object']->id]);

                // the property could also be in the first item associated to this structure element
                $structure_items = nvweb_content_items($current['object']->id, true, 1);

                if(!empty($structure_items))
                {
                    if(empty($structure_items[0]->template))
                        $structure_items[0]->template = $current['template'];
                    $properties['item-'.$structure_items[0]->id] = property::load_properties("item", $structure_items[0]->template, 'item', $structure_items[0]->id);
                }

                if(!empty($properties['item-'.$structure_items[0]->id]))
                    $current_properties = array_merge($current_properties, $properties['item-'.$structure_items[0]->id]);
			}
            else
            {
                // unknown object type, maybe is an object managed by an extension?
                if(!isset($properties[$current['type'].'-'.$current['object']->id]))
					$properties[$current['type'].'-'.$current['object']->id] = property::load_properties($current['type'], $current['object']->template, $current['type'], $current['object']->id);

                $current_properties = array_merge($current_properties, $properties[$current['type'].'-'.$current['object']->id]);
            }

			// now we find the property requested
			if(!is_array($current_properties))
			    $current_properties = array();

			foreach($current_properties as $property)
            {
                if($property->id == $vars['property'] || $property->name == $vars['property'])
                {
                    $out = nvweb_properties_render($property, $vars);
                    break;
                }
            }

			break;			
	}
		
	return $out;
}

function nvweb_properties_render($property, $vars)
{
	global $website;
	global $current;
	global $DB;
    global $session;
    global $theme;
    global $structure;

	$out = '';

    setlocale(LC_ALL, $website->languages[$session['lang']]['system_locale']);

    // if this property is null (no value assigned (null), (empty) is a value!)
    // get the default value
	if(!isset($property->value))
        $property->value = $property->dvalue;

    // check multilanguage properties, where the value can be saved in a language but may be (null) in another language
	if(in_array($property->type, array("text", "textarea", "rich_textarea", "link")) || $property->multilanguage == 'true')
	{
        // cast variable as array
        if(is_object($property->value))
            $property->value = (array)$property->value;

		if(!isset($property->value) || !isset($property->value[$current['lang']]))
        {
            // the property has no value saved (never was edited in Navigate CMS) or
            // the property has no value defined for the current language

            if(isset($property->dvalue->{$current['lang']}))
            {
                // good, there is a default value for the language requested
                $property->value[$current['lang']] = $property->dvalue->{$current['lang']};
            }
            else
            {
                if(!is_array($property->value))
                    $property->value = array();

                if(is_object($property->dvalue))
                    $property->dvalue = (array)$property->dvalue;

                if(is_array($property->dvalue))
                {
                    $dvalues = array_values($property->dvalue);
                    $property->value[$current['lang']] = $dvalues[0];
                }
                else
                {
                    $property->value[$current['lang']] = $property->dvalue;
                }
            }
        }
	}

	switch($property->type)
	{
		case 'value':
			$out = $property->value;
			break;

        case 'decimal':
            $out = $property->value;

            if(isset($vars['precision']))
                $out = number_format($property->value, $vars['precision']);
			break;
			
		case 'boolean':
			$out = $property->value;
			break;
		
		case 'option': 				
			$options = mb_unserialize($property->options);
            $options = (array)$options;

            switch(@$vars['return'])
            {
                case 'value':
                    $out = $property->value;
                    break;

                default:
                    $out = $theme->t($options[$property->value]);
            }
			break;
			
		case 'moption': 				
			$options = mb_unserialize($property->options);
			$selected = explode(",", $property->value);

            switch(@$vars['return'])
            {
                case 'value':
                case 'values':
                    $out = $property->value;
                    break;

                default:
                    $buffer = array();
                    foreach($selected as $seloption)
                    {
                        $buffer[] = '<span>'.$theme->t($options[$seloption]).'</span>';
                    }
                    $out .= implode(', ', $buffer);
            }
			break;
			
		case 'text':
			$out = htmlspecialchars($property->value[$current['lang']]);
			break;
			
		case 'textarea':
			$out = nl2br(htmlspecialchars($property->value[$current['lang']]));
			break;

        case 'rich_textarea':
            $out = $property->value[$current['lang']];
            break;

        case 'source_code':
            if(@$property->multilanguage=='true' || $property->multilanguage=='1')
                $out = $property->value[$current['lang']];
            else
                $out = $property->value;
            break;

		case 'date':
            if(!empty($vars['format']))
			    $out = Encoding::toUTF8(strftime($vars['format'], $property->value));
            else
                $out = date($website->date_format, $property->value);
			break;
			
		case 'datetime':
            if(!empty($vars['format']))
                $out = Encoding::toUTF8(strftime($vars['format'], $property->value));
            else
                $out = date($website->date_format.' H:i', $property->value);
			break;

		case 'link':
            // split title and link
            $link = explode('##', $property->value[$current['lang']]);
            if(is_array($link))
            {
                $target = @$link[2];
                $title = @$link[1];
                $link = $link[0];
                if(empty($title))
                    $title = $link;
            }
            else
            {
                $title = $property->value[$current['lang']];
                $link = $property->value[$current['lang']];
                $target = '_self';
            }

            if(strpos($link, '://')===false && strpos($link, 'mailto:')===false)
                $link = nvweb_prepare_link($link);

            if($vars['link']==='false')
            {
				$out = $link;
            }
			else if(isset($vars['return']))
            {
                if($vars['return']=='title')
                    $out = $title;
                else if($vars['return']=='link' || $vars['return']=='url')
                    $out = $link;
                else if($vars['return']=='target')
                    $out = $target;
            }
            else
            {
				$out = '<a href="'.$link.'" target="'.$target.'">'.$title.'</a>';
            }
			break;

		case 'image':
			$add = '';
			$extra = '';

            if(@$property->multilanguage=='true'  || $property->multilanguage=='1')
                $image_id = $property->value[$session['lang']];
            else
                $image_id = $property->value;
			
			if(isset($vars['width']))
            {
				$add .= ' width="'.$vars['width'].'" ';
                $extra .= '&width='.$vars['width'];
            }

			if(isset($vars['height']))
            {
				$add .= ' height="'.$vars['height'].'" ';
                $extra .= '&height='.$vars['height'];
            }

			if(isset($vars['border']))
				$extra .= '&border='.$vars['border'];

			if(isset($vars['opacity']))
				$extra .= '&opacity='.$vars['opacity'];

            if(isset($vars['quality']))
                $extra .= '&quality='.$vars['quality'];

			$img_url = NVWEB_OBJECT.'?type=image&id='.$image_id.$extra;

            if(empty($image_id))
            {
                $out = '';
            }
            else
            {
                if($vars['return']=='url')
                    $out = $img_url;
                else
                {
                    // retrieve additional info (title/alt), if available
                    if(is_numeric($image_id))
                    {
                        $f = new file();
                        $f->load($image_id);

                        $ftitle = $f->title[$current['lang']];
                        $falt = $f->description[$current['lang']];

                        if(!empty($ftitle))
                            $add .= ' title="'.$ftitle.'" ';

                        if(!empty($falt))
                            $add .= ' alt="'.$falt.'" ';
                    }

                    $out = '<img class="'.$vars['class'].'" src="'.$img_url.'" '.$add.' />';
                }
            }
			break;
			
		case 'file':
            if(!empty($property->value))
            {
			    $file = $DB->query_single('name', 'nv_files', ' id = '.intval($property->value).' AND website = '.intval($website->id));

                if($vars['return']=='url' || $vars['return']=='url-download')
                    $out = NVWEB_OBJECT.'?type=file&id='.$property->value.'&disposition=attachment';
                else if($vars['return']=='url-inline')
                    $out = NVWEB_OBJECT.'?type=file&id='.$property->value.'&disposition=inline';
                else
			        $out = '<a href="'.NVWEB_OBJECT.'?type=file&id='.$property->value.'&disposition=attachment">'.$file.'</a>';
            }
			break;
			
		case 'comment':
			$out = $property->value;		
			break;
			
		case 'coordinates':
			$coordinates = explode('#', $property->value);
			$out = implode(',', $coordinates);
			break;
			
		case 'rating':
			$out = $property->value;
			// we want nearest integer down
			if($vars['option']=='floor')
				$out = floor($out);
			break;

        case 'color':
            $out = $property->value;
            break;

        case 'video':
            // value may be a numeric file ID or a provider#id structure, f.e. youtube#3MteSlpxCpo
            // compatible providers: file,youtube,vimeo
            if(@$property->multilanguage=='true'  || $property->multilanguage=='1')
                $video_id = $property->value[$session['lang']];
            else
                $video_id = $property->value;

            $provider = '';
            $reference = '';

            $add = '';
            if(isset($vars['width']))
                $add .= ' width="'.$vars['width'].'" ';
            if(isset($vars['height']))
                $add .= ' height="'.$vars['height'].'" ';

            $url_add = '&type=image';
            if(isset($vars['width']))
                $url_add .= '&width='.$vars['width'].'';
            if(isset($vars['height']))
                $url_add .= '&height='.$vars['height'].'';
            if(isset($vars['border']))
                $url_add .= '&border='.$vars['border'].'';

            if(strpos($video_id, '#')!==false)
                list($provider, $reference) = explode("#", $video_id);

            if($provider=='file')
                $video_id = $reference;

            $file = new file();
            if(is_numeric($video_id))
            {
                $file->load($video_id);
                $embed = file::embed('file', $file, $add);
            }
            else if($provider == 'youtube')
            {
                $embed = file::embed('youtube', $reference, $add);
                if(!empty($vars['part']) || $vars['part']!='embed' || !empty($vars['return']))
                    $file->load_from_youtube($reference);
            }
            else if($provider == 'vimeo' || !empty($vars['return']))
            {
                $embed = file::embed('vimeo', $reference, $add);
                if(!empty($vars['part']) || $vars['part']!='embed')
                    $file->load_from_vimeo($reference);
            }

            switch(@$vars['return'])
            {
                case 'title':
                    $out = $file->title;
                    break;

                case 'mime':
                    $out = $file->mime;
                    break;

                case 'author':
                    if(is_numeric($file->uploaded_by))
                        $out = $website->name;
                    else
                        $out = $file->uploaded_by;
                    break;

                case 'path':
                case 'url':
                    $out = $file->extra['link'];
                    break;

                case 'thumbnail_url':
                    $out = file::file_url($file->extra['thumbnail_cache']).$url_add;
                    break;

                case 'thumbnail':
                    $out = '<img src="'.file::file_url($file->extra['thumbnail_cache']).$url_add.'" class="'.$vars['class'].'" '.$add.' />';
                    break;

                case 'reference':
                    $out = $reference;
                    break;

                case 'provider':
                    $out = $provider;
                    break;

                case 'embed':
                default:
                    $out = $embed;
            }
            break;
			
		case 'product':
			// TO DO
			break;
			
		case 'category':
            $return = @$vars['return'];

            switch($return)
            {
                case 'title':
                case 'name':
                    nvweb_menu_load_dictionary();
                    $out = $structure['dictionary'][$property->value];
                    break;

                case 'url':
                case 'link':
                    $out = nvweb_source_url('structure', $property->value);
                    break;

                default:
                    $out = $property->value;
            }
            break;

        case 'categories':
            $return = @$vars['return'];

            $value = explode(",", $property->value);
            $position = intval(@$vars['position']) + 0;

            switch($return)
            {
                case 'title':
                case 'name':
                    nvweb_menu_load_dictionary();
                    $out = $structure['dictionary'][$value[$position]];
                    break;

                case 'url':
                case 'link':
                    $out = nvweb_source_url('structure', $value[$position]);
                    break;

                default:
                    $out = $property->value;
            }
            break;

        case 'country':
	        $return = @$vars['return'];
	        switch($return)
	        {
		        case 'name':
			        $countries = property::countries();
					$out = $countries[$property->value];
			        break;

		        case 'id':
	            case 'code':
		        default:
			        $out = $property->value;
			        break;
	        }
            break;

        case 'elements':
            $out = $property->value;
            break;

        case 'element':
        case 'item': // deprecated
            $return = @$vars['return'];

            switch($return)
            {
                case 'title':
                    $item = new item();
                    $item->load($property->value);
                    $out = $item->dictionary[$current['lang']]['title'];
                    break;

                case 'url':
                case 'path':
                    $out = nvweb_source_url('item', $property->value, $current['lang']);
                    break;

                case 'section':
                    $item = new item();
                    $item->load($property->value);
                    $out = $item->dictionary[$current['lang']]['section-'.$vars['section']];
                    break;

                case 'property':
                    $params = array();
                    foreach($vars as $attr_name => $attr_value)
                    {
                        if(strpos($attr_name, 'element-property-')===0)
                        {
                            $attr_name = str_replace('element-property-', '', $attr_name);
                            $params[$attr_name] = $attr_value;
                        }
                        else if($attr_name == 'element-property')
                        {
                            $params['property'] = $attr_value;
                        }
                    }

                    //  default parameters
                    $params['mode'] = 'item';
                    $params['id'] = $property->value;
                    $out = nvweb_properties($params);

                    break;

                case 'id':
                default:
                    $out = $property->value;
                    break;
            }
            break;

		default:	
	}

	return $out;	
}

?>