<?php
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');

function navigate_property_layout_form($element, $code, $object, $object_id)
{
    global $website;

	$out = array();
    $property_rows = array();
	
	// load the property values of the object
	$properties = property::load_properties($element, $code, $object, $object_id);

	// translate extension strings
	$obj = null;
    if($element == 'extension')
    {
        $obj = new extension();
        $obj->load($code);
    }

	// generate the form
	for($p = 0; $p < count($properties); $p++)
	{
		if( $properties[$p]->enabled === '0' ||
            $properties[$p]->enabled === 0 ||
            $properties[$p]->enabled === "false" ||
            $properties[$p]->enabled === false)
		    continue;

		$property_rows[] = navigate_property_layout_field($properties[$p], $obj);
	}

    if(!empty($property_rows) && !empty($property_rows[0]))  // no properties => no form
    {
        $out[] = '<div id="navigate-properties-form">';
        $out[] = '<input type="hidden" name="property-element" value="'.$element.'" />';
        $out[] = '<input type="hidden" name="property-template" value="'.$code.'" />';

        $property_rows = implode("\n", $property_rows);

        // language selector (only if it's a multilanguage website and we have almost one multilanguage property)
        if(count($website->languages) > 1 && strpos($property_rows, 'lang="'.$website->languages_list[1].'"') !== false)
        {
            $website_languages_selector = $website->languages();
            $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

            $naviforms = new naviforms();

            $out[] = '<div class="navigate-form-row">';
            $out[] = '<label>'.t(63, 'Languages').'</label>';
            $out[] = $naviforms->buttonset('properties_language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);");
            $out[] = '</div>';
        }

        $out[] = $property_rows;

        $out[] = '</div>';

	    navigate_property_layout_scripts();
    }
	
	return implode("\n", $out);	
}

function navigate_property_layout_field($property, $object="", $website_id="")
{
	global $website;
	global $layout;
    global $theme;
    global $user;
    global $DB;

	$ws = $website;
	$ws_theme = $theme;
	if(!empty($website_id) && $website_id!=$website->id)
	{
		$ws = new website();
		$ws->load($website_id);
		$ws_theme = new theme();
		$ws_theme->load($ws->theme);
	}

	// object used for translations (theme or extension)
	if(empty($object))
		$object = $ws_theme;

	$naviforms = new naviforms();
	$langs = $ws->languages_list;

	$field = array();

	if(!isset($property->value))
        $property->value = $property->dvalue;

    if(!isset($property->multilanguage))
        $property->multilanguage = 'false';

	$property_name = $property->name;
	if(!empty($object))
		$property_name = $object->t($property_name);

	if(in_array($property->type, array("text", "textarea", "rich_textarea", "link")) || $property->multilanguage=='true')
	{
		if(!isset($property->multilanguage) || $property->multilanguage !== false || $property->multilanguage == "false")
            $property->multilanguage = 'true';
		else
			$property->multilanguage = 'false';

        if(is_object($property->value))
            $property->value = (array)$property->value;

        if(!is_array($property->value))
            $property->value = array();

		foreach($langs as $lang)
		{
			if(!isset($property->value[$lang]) && isset($property->dvalue))
				$property->value[$lang] = $property->dvalue;
		}
	}

	// auto show/hide properties by other properties values --> "conditional": [ { "source_property_id" : [value1,"value2"] } ]
    if(!empty($property->conditional))
    {
        foreach($property->conditional as $conditional)
        {
            foreach($conditional as $conditional_property => $conditional_values)
            {
                if(!is_array($conditional_values))
                    $conditional_values = array($conditional_values);

                $conditional_values = '["'.implode('", "', $conditional_values).'"]';

                $layout->add_script('
                    navigate_tabform_conditional_property("'.$property->id.'", "'.$conditional_property.'", '.$conditional_values.');
                ');
            }
        }
    }

	switch($property->type)
	{
		case 'value':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->textfield("property-".$property->id, $property->value);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';			
			break;

        case 'decimal':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';

			$field[] = $naviforms->decimalfield("property-".$property->id, $property->value, $property->precision, $user->decimal_separator, $user->thousands_separator, @$property->prefix, @$property->suffix);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';
			break;

		case 'rating':
            $half_stars_enabled = value_or_default($property->max, true);
            if(isset($property->max))
            {
                $stars = $property->max;
                if(!isset($property->value))
                    $property->value = $property->dvalue;
            }
            else // navigate cms < 2.2 compatability
            {
                $default = explode('#', $property->dvalue);
                $stars = @$default[1];

                // if no default value is specified, we take the old navigate 1.x half star format, that is:
                // defaults to 5 stars with half stars enabled (so 10 stars), having to divide by two the value in the website
                if(empty($stars))
                    $stars = 10;

                $half_stars_enabled = false;

                if($property->value == $property->dvalue)
                    $property->value = intval($default[0]);
            }

			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" style=" min-height: 18px; ">';
			$field[] = '<label>'.$property_name.'</label>';
            $field[] = $naviforms->textfield("property-".$property->id, $property->value, '30px');
            $field[] = '<div id="property-'.$property->id.'_control" class="nv_property_rating_control" data-half-stars="'.$half_stars_enabled.'" data-stars="'.$stars.'" data-property="property-'.$property->id.'"></div>';

			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';			
			break;			

		case 'boolean':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->checkbox("property-".$property->id, ($property->value=='1'));
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';
			break;
		
		case 'option':
            $options = $property->options;

            if(is_string($options))
                $options = mb_unserialize($options);
            else if(is_object($options))
                $options = (array)$options;

            // translate each option text
            if(!empty($object) && !empty($options))
            {
                foreach($options as $value => $text)
                    $options[$value] = $object->t($text);
            }

			if(!isset($property->option_html))
			{
				$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
				$field[] = '<label>'.$property_name.'</label>';
				$field[] = $naviforms->selectfield("property-".$property->id, array_keys($options), array_values($options), $property->value);
	            if(!empty($property->helper))
	            {
		            $helper_text = $property->helper;
		            if(!empty($object))
			            $helper_text = $object->t($helper_text);
	                $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	            }
				$field[] = '</div>';
			}
			else
			{
				// each option formatted in a specific html fragment
				if(isset($property->stylesheet))
				{
					$custom_stylesheet = $property->stylesheet;
					if(strpos($custom_stylesheet, 'http')===false)
                        $custom_stylesheet = NAVIGATE_URL.'/themes/'.$ws->theme.'/'.$custom_stylesheet.'?bogus='.time();

					$layout->add_style_tag($custom_stylesheet, false);

					if(empty($options)) // parse stylesheet and try to identify all possible values
					{
						$custom_stylesheet_contents = file_get_contents(NAVIGATE_PATH.'/themes/'.$ws->theme.'/'.$property->stylesheet);
						$custom_stylesheet_contents = stylesheet_parse($custom_stylesheet_contents);

						$options = array();
						if(is_array($custom_stylesheet_contents))
						{
							foreach($custom_stylesheet_contents as $rule => $rule_content)
							{
								if(in_array(substr($rule, 0, 1), array('.', '#')))
								{
									$rule = str_replace(array('.', '#', ':before', ':after', ':focus', ':visited'), '', $rule);
									$options[$rule] = $rule;
									if(!empty($object))
										$options[$rule] = $object->t($rule);
								}
							}
						}
					}
				}

				$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
				$field[] = '<label>'.$property_name.'</label>';
				$field[] = $naviforms->selectfield("property-".$property->id, array_keys($options), array_values($options), $property->value, NULL, false, NULL, NULL, false, false);


				$layout->add_script('
					$("#property-'.$property->id.'").select2(
				        {
				            selectOnBlur: true,
				            minimumResultsForSearch: 6,
							escapeMarkup: function (markup)
					        {
					            return markup; // let our custom formatter work
					        },
					        templateSelection: function(row)
					        {					        
					            var option_html = "'.str_replace('"', '\"', $property->option_html).'";
					            option_html = option_html.replace(/{{VALUE}}/g, row.id);
					            option_html = option_html.replace(/{{TEXT}}/g, row.text);
					        
					            if(row.id)
					                return option_html;
					            else
					                return "("  + navigate_t(581, "None") + ")";
					        },
					        templateResult: function(data)
					        {
					            var option_html = "'.str_replace('"', '\"', $property->option_html).'";
					            option_html = option_html.replace(/{{VALUE}}/g, data.id);
					            option_html = option_html.replace(/{{TEXT}}/g, data.text);
					        
					            if(data.id)
					                return option_html;
					            else
					                return "("  + navigate_t(581, "None") + ")";
					        }
				        }
				    );
			    ');

				
	            if(!empty($property->helper))
	            {
		            $helper_text = $property->helper;
		            if(!empty($object))
			            $helper_text = $object->t($helper_text);
	                $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	            }
				$field[] = '</div>';
			}
			break;

			
		case 'moption':
            $options = $property->options;
            if(is_string($options))
                $options = mb_unserialize($options);
            else if(is_object($options))
                $options = (array)$options;

            // translate each option text
            if(!empty($object))
            {
                foreach($options as $value => $text)
                    $options[$value] = $object->t($text);
            }

            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->selectfield("property-".$property->id, array_keys($options), array_values($options), explode(',', $property->value), "", true);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';			
			break;			
			
		case 'country': 				
			$options = property::countries();

			$country_codes = array_keys($options);
			$country_names = array_values($options);

			// include "country not defined" item
			array_unshift($country_codes, '');
			array_unshift($country_names, '('.t(307, "Unspecified").')');

			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->selectfield("property-".$property->id, $country_codes, $country_names, strtoupper($property->value));
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';			
			break;	
			
		case 'coordinates':
			$coordinates = explode('#', $property->value);
			$latitude  = @$coordinates[0];
			$longitude = @$coordinates[1];			
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->textfield("property-".$property->id.'-latitude',  $latitude, '182px');
			$field[] = $naviforms->textfield("property-".$property->id.'-longitude', $longitude, '182px');
			$field[] = '<img src="img/icons/silk/map_magnify.png" align="absmiddle" hspace="3px" id="property-'.$property->id.'-show" />';
			$field[] = '<div id="property-'.$property->id.'-map-container" style=" display: none; ">';
			$field[] = '	<div class="navigate-form-row" id="property-'.$property->id.'-search" style=" width: 278px; height: 24px; margin-top: 9px; margin-left: 40px; position: absolute; z-index: 1000; opacity: 0.95; ">';
			$field[] = '		<input type="text" name="property-'.$property->id.'-search-text" style=" width: 240px; " /> ';
			$field[] = '		<img class="ui-widget ui-button ui-state-default ui-corner-all" sprite="false" style=" cursor: pointer; padding: 3px; " src="'.NAVIGATE_URL.'/img/icons/silk/zoom.png" align="right" />';			
			$field[] = '	</div>';
			$field[] = '	<div id="property-'.$property->id.'-map" style=" width: 400px; height: 200px; "></div>';
			$field[] = '</div>';
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';

            $layout->add_script('
                // auto parse standard Google Maps URLs when pasting them in the latitude field
                $("#property-'.$property->id.'-latitude").on("keyup", function()
                {
                    var value = $(this).val();                                        
                    if(value.indexOf("https://www.google")==0)
                    {                    
                        // locate the @ symbol
                        value = value.substr(value.indexOf("@")+1);
                        value = value.substr(0, value.indexOf("z"));
                        value = value.split(",");
                                                                        
                        if(value.length == 3) // parsed values seem fine
                        {
                            $("#property-'.$property->id.'-latitude").val(value[0]);                            
                            $("#property-'.$property->id.'-longitude").val(value[1]);                            
                        }
                    }
                });
            ');

			$layout->add_script('
				var property_'.$property->id.'_lmap = null;
			    var marker = null;
			    
			    L.Icon.Default.imagePath = "'.NAVIGATE_URL.'/lib/external/leaflet/images/";
			    
			    // initialize leaflet map
                property_'.$property->id.'_lmap = L.map(
                    "property-'.$property->id.'-map",
                    {
                        doubleClickZoom: false
                    }
                );					    
                
                // create the tile layer with correct attribution
                var osmUrl = "http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
                var osmAttrib = "Map data © <a href=\"http://openstreetmap.org\">OpenStreetMap</a> contributors";
                var osm = new L.TileLayer(osmUrl, {minZoom: 0, maxZoom: 19, attribution: osmAttrib });
                
                property_'.$property->id.'_lmap.addLayer(osm);
							
				$("#property-'.$property->id.'-search input").on("keyup", function(e)
				{	if(e.keyCode == 13)	property'.$property->id.'search();	});
				
				$("#property-'.$property->id.'-search img").on("click", property'.$property->id.'search);
				
				$("#property-'.$property->id.'-show").on("click", function()
				{
					var myLatlng = new L.LatLng(
					    $("#property-'.$property->id.'-latitude").val(),
					    $("#property-'.$property->id.'-longitude").val()
					);																
												
                    property_'.$property->id.'_lmap.setView(myLatlng, 17);
	                
	                marker = L.marker(myLatlng).addTo(property_'.$property->id.'_lmap);
	                
	                property_'.$property->id.'_lmap.on("dblclick", function(e)
	                {	                    
                        $("#property-'.$property->id.'-latitude").val(e.latlng.lat);
						$("#property-'.$property->id.'-longitude").val(e.latlng.lng)
	                    
	                    marker.remove();
	                    marker = L.marker(e.latlng).addTo(property_'.$property->id.'_lmap);                        
	                });
					                    
					$("#property-'.$property->id.'-map-container").dialog(
					{
						width: 600,
						height: 400,
						title: "'.t(300, 'Map').': '.t(301, 'Double click a place to set the coordinates').'",
						resize: property'.$property->id.'resize,
						open: function()
						{
						    $(this).css("padding", 0);
						    property_'.$property->id.'_lmap.invalidateSize();
                        }
                    }).dialogExtend(
					{
						maximizable: true,
						"maximize" : property'.$property->id.'resize,
						"restore" : property'.$property->id.'resize
					});
					
					property'.$property->id.'resize();

				}).css("cursor", "pointer");	
				
				function property'.$property->id.'resize()
				{
					$("#property-'.$property->id.'-map").width($("#property-'.$property->id.'-map-container").width()); 
					$("#property-'.$property->id.'-map").height($("#property-'.$property->id.'-map-container").height());	
					property_'.$property->id.'_lmap.invalidateSize();
				}
				
				function property'.$property->id.'search()
				{				
					var address = $("#property-'.$property->id.'-search input").val();
                    var geocode_request_url = "http://services.gisgraphy.com/geocoding/geocode?format=json&callback=?&address=" + address;
                    if( window.location.href.indexOf("https://")==0 )
                    {
                        // gisgraphy does not support HTTPS requests,
                        // so we have to proxy the request through the server
                        geocode_request_url = "?fid=utils&act=geocode&format=gisgraphy_json&address=" + address;
                    }
                    
                    $.getJSON(geocode_request_url, function(data)
                    {                                    
                        if(!data.result || data.result.length < 1)
                            alert("Geocode was not successful for the following reason: " + status);
                        else
                        {
                            property_'.$property->id.'_lmap.setView([data.result[0].lat, data.result[0].lng], 19);
                        }
                    });						        

					return false;
				}		
                
			');
			break;
			
		case 'text':
			foreach($langs as $lang)
			{
				if(!is_array($property->value))
				{
					$ovalue = $property->value;
					$property->value = array();
					foreach($langs as $lang_value)
						$property->value[$lang_value] = $ovalue;
				}

                $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

				$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" lang="'.$lang.'">';
				$field[] = '<label>'.$property_name.' '.$language_info.'</label>';
				$field[] = $naviforms->textfield("property-".$property->id."-".$lang, $property->value[$lang]);
				if(!empty($property->helper))
				{
					$helper_text = $property->helper;
					if(!empty($object))
						$helper_text = $object->t($helper_text);
					$field[] = '<div class="subcomment">'.$helper_text.'</div>';
				}
				$field[] = '</div>';
			}
			break;
			
		case 'textarea':
			foreach($langs as $lang)
			{
				if(!is_array($property->value))
				{
					$ovalue = $property->value;
					$property->value = array();
					foreach($langs as $lang_value)
						$property->value[$lang_value] = $ovalue;
				}

				$style = "";
				if(!empty($property->width))
					$style = ' width: '.$property->width.'px; ';

				$language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';
				if($property->multilanguage == 'false')
					$language_info = '';

				$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" lang="'.$lang.'">';
				$field[] = '<label>'.$property_name.' '.$language_info.'</label>';
				$field[] = $naviforms->textarea("property-".$property->id."-".$lang, $property->value[$lang], 4, 48, $style);
				$field[] = '<button class="navigate-form-row-property-action" data-field="property-'.$property->id.'-'.$lang.'" data-action="copy-from" title="'.t(189, 'Copy from').'…"><img src="img/icons/silk/page_white_copy.png" align="absmiddle"></button>';
				if(!empty($property->helper))
				{
					$helper_text = $property->helper;
					if(!empty($object))
						$helper_text = $object->t($helper_text);
					$field[] = '<div class="subcomment">'.$helper_text.'</div>';
				}
				$field[] = '</div>';

				if($property->multilanguage == 'false')
					break;
			}		
			break;

        case 'rich_textarea':
            foreach($langs as $lang)
            {
                if(!is_array($property->value))
                {
                    $ovalue = $property->value;
                    $property->value = array();
                    foreach($langs as $lang_value)
                        $property->value[$lang_value] = $ovalue;
                }

                $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';
	            if($property->multilanguage == 'false')
		            $language_info = '';

                $width = NULL;
                if(!empty($property->width))
                    $width = $property->width.'px';

                $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" lang="'.$lang.'">';
                $field[] = '<label>'.$property_name.' '.$language_info.'</label>';
                $field[] = $naviforms->editorfield("property-".$property->id."-".$lang, $property->value[$lang], $width, NULL, $website_id);
	            if(!empty($property->helper))
	            {
		            $helper_text = $property->helper;
		            if(!empty($object))
			            $helper_text = $object->t($helper_text);
		            $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	            }

                // additional control buttons
                $translate_menu = '';
                if(!empty($translate_extensions))
                {
                    $translate_extensions_titles = array();
                    $translate_extensions_actions = array();

                    foreach($translate_extensions as $te)
                    {
                        if($te['enabled']=='0') continue;
                        $translate_extensions_titles[] = $te['title'];
                        $translate_extensions_actions[] = 'javascript: navigate_tinymce_translate_'.$te['code'].'(\'property-'.$property->id.'-'.$lang.'\', \''.$lang.'\');';
                    }

                    if(!empty($translate_extensions_actions))
                    {
                        $translate_menu = $naviforms->splitbutton(
                            'translate_'.$lang,
                            '<img src="img/icons/silk/comment.png" align="absmiddle"> '.t(188, 'Translate'),
                            $translate_extensions_actions,
                            $translate_extensions_titles
                        );
                    }
                }

                $field[] = '<div style="clear:both; margin-top:5px; float:left; margin-bottom: 10px;">';
                $field[] = '<label>&nbsp;</label>';
                $field[] = $translate_menu;
                $field[] = '<button class="navigate-form-row-property-action" data-field="property-'.$property->id.'-'.$lang.'" data-action="copy-from"><img src="img/icons/silk/page_white_copy.png" align="absmiddle">'.t(189, 'Copy from').'...</button> ';
                $field[] = (!empty($theme->content_samples)? '<button class="navigate-form-row-property-action" data-action="theme-samples" data-property-id="'.$property->id.'" data-property-lang="'.$lang.'" data-property-field="tinymce"><img src="img/icons/silk/rainbow.png" align="absmiddle"> '.t(553, 'Fragments').' | '.$theme->title.'</button> ' : '');
                $field[] = '</div>';

                $field[] = '</div>'; // divformrow

	            if($property->multilanguage == 'false')
		            break;
            }
            break;

        case 'color':
            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
            $field[] = '<label>'.$property_name.'</label>';
            $field[] = $naviforms->colorfield("property-".$property->id, $property->value, @$property->options);
	        if(!empty($property->helper))
	        {
		        $helper_text = $property->helper;
		        if(!empty($object))
			        $helper_text = $object->t($helper_text);
		        $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	        }
            $field[] = '</div>';
            break;

		case 'date':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->datefield("property-".$property->id, $property->value, false);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';			
			break;
			
		case 'datetime':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->datefield("property-".$property->id, $property->value, true);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';					
			break;

        case 'source_code':
            if($property->multilanguage!='true' && $property->multilanguage!='1')
            {
                $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
                $field[] = '<label>'.$property_name.'</label>';
                $field[] = $naviforms->scriptarea("property-".$property->id, $property->value);
	            $field[] = '&nbsp;<button class="navigate-form-row-property-action" data-field="property-'.$property->id.'-'.$lang.'" data-action="copy-from" title="'.t(189, 'Copy from').'…"><img src="img/icons/silk/page_white_copy.png" align="absmiddle"></button>';
	            if(!empty($property->helper))
	            {
		            $helper_text = $property->helper;
		            if(!empty($object))
			            $helper_text = $object->t($helper_text);
		            $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	            }
                $field[] = '</div>';
            }
            else
            {
                foreach($langs as $lang)
                {
                    if(!is_array($property->value))
                    {
                        $ovalue = $property->value;
                        $property->value = array();
                        foreach($langs as $lang_value)
                            $property->value[$lang_value] = $ovalue;
                    }

                    $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

                    $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" lang="'.$lang.'">';
                    $field[] = '<label>'.$property_name.' '.$language_info.'</label>';
                    $field[] = $naviforms->scriptarea("property-".$property->id."-".$lang, $property->value[$lang]);
	                $field[] = '&nbsp;<button class="navigate-form-row-property-action" data-field="property-'.$property->id.'-'.$lang.'" data-action="copy-from" title="'.t(189, 'Copy from').'…"><img src="img/icons/silk/page_white_copy.png" align="absmiddle"></button>';
	                if(!empty($property->helper))
	                {
		                $helper_text = $property->helper;
		                if(!empty($object))
			                $helper_text = $object->t($helper_text);
		                $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	                }
                    $field[] = '</div>';
                }
            }
            break;
		
		case 'link':
			foreach($langs as $lang)
			{
				if(!is_array($property->value))
				{
					$ovalue = $property->value;
					$property->value = array();
					foreach($langs as $lang_value)
						$property->value[$lang_value] = $ovalue;
				}

                $link = explode('##', $property->value[$lang]);
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
                    $title = $property->value[$lang];
                    $link = $property->value[$lang];
                    $target = '_self';
                }

                $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';
				if($property->multilanguage == 'false')
					$language_info = '';

				$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" lang="'.$lang.'" style="margin-bottom: 0px;">';
				$field[] = '<label>'.$property_name.' '.$language_info.'</label>';
                $field[] = $naviforms->textfield("property-".$property->id."-".$lang."-title", $title);
                $field[] = '<span class="navigate-form-row-info">'.t(67, 'Title').'</span>';
                $field[] = '</div>';
                $field[] = '<div class="navigate-form-row" lang="'.$lang.'" style="margin-bottom: 0px;" nv_property="'.$property->id.'" >';
                $field[] = '<label>&nbsp;</label>';
                $field[] = $naviforms->textfield("property-".$property->id."-".$lang."-link", $link);
                $field[] = '<span class="navigate-form-row-info">'.t(197, 'Link').'</span>';
                $field[] = '</div>';
                $field[] = '<div class="navigate-form-row" lang="'.$lang.'" nv_property="'.$property->id.'" >';
                $field[] = '<label>&nbsp;</label>';
                $field[] = $naviforms->selectfield(
                    "property-".$property->id."-".$lang."-target",
                    array(
                        '_self',
                        '_blank'
                    ),
                    array(
                        t(173, "Follow URL"),
                        t(174, "Open URL (new window)")
                    ),
                    $target
                );
                $field[] = '<span class="navigate-form-row-info">'.t(172, 'Action').'</span>';
				if(!empty($property->helper))
				{
					$helper_text = $property->helper;
					if(!empty($object))
						$helper_text = $object->t($helper_text);
					$field[] = '<div class="subcomment">'.$helper_text.'</div>';
				}
                $field[] = '</div>';

				if($property->multilanguage == 'false')
					break;
			}		
			break;
			
		case 'image':
            if($property->multilanguage!='true' && $property->multilanguage!='1')
            {
                $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
                $field[] = '<label>'.$property_name.'</label>';
                $field[] = $naviforms->dropbox("property-".$property->id, $property->value, "image", false, @$property->dvalue, @$property->options, $website_id);
	            if(!empty($property->helper))
	            {
		            $helper_text = $property->helper;
		            if(!empty($object))
			            $helper_text = $object->t($helper_text);
		            $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	            }
                $field[] = '</div>';
            }
            else
            {
                foreach($langs as $lang)
                {
                    if(!is_array($property->value))
                    {
                        $ovalue = $property->value;
                        $property->value = array();
                        foreach($langs as $lang_value)
                            $property->value[$lang_value] = $ovalue;
                    }

                    $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

                    $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" lang="'.$lang.'">';
                    $field[] = '<label>'.$property_name.' '.$language_info.'</label>';
                    $field[] = $naviforms->dropbox("property-".$property->id."-".$lang, $property->value[$lang], "image", false, @$property->dvalue, $website_id);
	                if(!empty($property->helper))
	                {
		                $helper_text = $property->helper;
		                if(!empty($object))
			                $helper_text = $object->t($helper_text);
		                $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	                }
                    $field[] = '</div>';
                }
            }
			break;

        case 'video':
			if($property->multilanguage!='true' && $property->multilanguage!='1')
            {
	            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
	            $field[] = '<label>'.$property_name.'</label>';
	            $field[] = $naviforms->dropbox("property-".$property->id, $property->value, "video", false, $property->dvalue, $website_id);
		        if(!empty($property->helper))
		        {
			        $helper_text = $property->helper;
			        if(!empty($object))
				        $helper_text = $object->t($helper_text);
			        $field[] = '<div class="subcomment">'.$helper_text.'</div>';
		        }
	            $field[] = '</div>';
            }
			else
			{
				foreach($langs as $lang)
                {
                    if(!is_array($property->value))
                    {
                        $ovalue = $property->value;
                        $property->value = array();
                        foreach($langs as $lang_value)
                            $property->value[$lang_value] = $ovalue;
                    }

                    $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

	                $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
		            $field[] = '<label>'.$property_name.' '.$language_info.'</label>';
		            $field[] = $naviforms->dropbox("property-".$property->id."-".$lang, $property->value[$lang], "video", false, $property->dvalue, $website_id);
			        if(!empty($property->helper))
			        {
				        $helper_text = $property->helper;
				        if(!empty($object))
					        $helper_text = $object->t($helper_text);
				        $field[] = '<div class="subcomment">'.$helper_text.'</div>';
			        }
		            $field[] = '</div>';
                }
			}
            break;

		case 'file':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->dropbox("property-".$property->id, $property->value, NULL, NULL, NULL, NULL, $website_id);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';						
			break;
			
		case 'comment':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$comment_text = $property->value;
			if(!empty($object))
				$comment_text = $object->t($property->value);
			$field[] = '<div class="subcomment" style="clear: none;">'.$comment_text.'</div>';
			$field[] = '</div>';								
			break;
			
		case 'category':
            $hierarchy = structure::hierarchy(0, $website_id);
            $categories_list = structure::hierarchyList($hierarchy, $property->value);

            if(empty($categories_list))
                $categories_list = '<ul><li value="0">'.t(428, '(no category)').'</li></ul>';

            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
            $field[] = '<label>'.$property_name.'</label>';
            $field[] = $naviforms->dropdown_tree("property-".$property->id, $categories_list, $property->value);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
            $field[] = '</div>';
            break;

        case 'categories':
            $hierarchy = structure::hierarchy(0, $website_id);
            $selected = explode(',', $property->value);
            if(!is_array($selected))
                $selected = array($property->value);
            $categories_list = structure::hierarchyList($hierarchy, $selected);

            if($property->format == 'list')
            {
                $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
                $field[] = '<label>'.$property_name.'</label>';

                $field[] = $naviforms->textfield("property-".$property->id);
                $field[] = '<button class="navigate-form-row-property-action" data-field="property-'.$property->id.'" data-action="tree_hierarchy" title="'.t(330, 'Categories').'…"><img src="img/icons/silk/sitemap_color.png" align="absmiddle"></button>';

                $layout->add_script('			                
                    $("#property-'.$property->id.'").tagit({
                        removeConfirmation: true,
                        allowSpaces: true,
                        singleField: true,
                        singleFieldDelimiter: ",",
                        placeholderText: "+",
                        autocompleteOnly: true,
                        autocomplete: {
                            delay: 0, 
                            minLength: 1,
                            source: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=structure&act=json_find_structure&format=tagit&page_limit=10"
                        },
                        afterTagAdded: function(event, ui)
                        {                           
                            var tags = $(this).tagit("assignedValues");
                            if(tags.length > 0) tags = tags.join(",");
                            else                tags = "";
                                
                            $("#property-'.$property->id.'").val(tags).trigger("change");
                        },
                        afterTagRemoved: function(event, ui)
                        {
                            var tags = $(this).tagit("assignedValues");
                            if(tags.length > 0) tags = tags.join(",");
                            else                tags = "";
    
                            $("#property-'.$property->id.'").val(tags).trigger("change");
                        }
                    });
                                    
                    $("#property-'.$property->id.'").next().sortable(
                    {
                        items: ">li:not(.tagit-new)",
                        update: function(ui, event)
                        {
                            var tags = $("#property-'.$property->id.'").tagit("assignedValues");
                            if(tags.length > 0) tags = tags.join(",");
                            else                tags = "";
    
                            $("#property-'.$property->id.'").val(tags).trigger("change");
                        }
                    });    
                ');

                $field[] = '<div style="float: left;" id="tree_wrapper_'.$property->id.'">'.$categories_list.'</div>';

                $layout->add_script('
                    $("#tree_wrapper_'.$property->id.' span").wrap("<a>").css("cursor", "pointer");
                    $("#tree_wrapper_'.$property->id.' ul:first").menu({                        
                        select: function(event, ui)
                        {
                            var value = $(ui.item).attr("value");
                            var text = ($(ui.item).text()).trim();
                            $("#property-'.$property->id.'").tagit("createTag", text, "", "", value);
                        }
                    });
                    $("#tree_wrapper_'.$property->id.' ul:first").css(
                        {
                            "position": "absolute",
                            "z-index": 1000,
                            "margin": 1,
                            "width": $("#tree_path_'.$property->id.'").width()
                        }
                    ).addClass("navi-ui-widget-shadow").hide();
                    $("#tree_wrapper_'.$property->id.'").find(".ui-menu-icon").css("float", "right");
                    $("#tree_path_'.$property->id.'").on("click", function() 
                    {
                        setTimeout(function()
                        {
                            $("#tree_wrapper_'.$property->id.' ul:first").fadeIn("fast");
                        }, 50);
                    });
                    
                    $(".navigate-form-row-property-action[data-field=property-'.$property->id.'][data-action=tree_hierarchy]").on("click", function(e)
                    {
                        e.stopPropagation();
                        e.preventDefault();
                                                
                        setTimeout(function()
                            {
                                $("#tree_wrapper_'.$property->id.' ul:first").show();
                                $("#tree_wrapper_'.$property->id.' ul:first").offset({
                                    top: $(".navigate-form-row-property-action[data-field=property-'.$property->id.'][data-action=tree_hierarchy]").offset().top + 8,
                                    left: $(".navigate-form-row-property-action[data-field=property-'.$property->id.'][data-action=tree_hierarchy]").offset().left + 8                                    
                                });
                            },
                            100
                        );
                    });
                ');

                if(!empty($property->value))
                {
                    $values = explode(",", $property->value);
                    $values = array_filter($values);

                    foreach ($values as $cid)
                    {
                        $content_title = $DB->query_single(
                            'text',
                            'nv_webdictionary',
                            '    
                                node_type = "structure" AND
                                website = :wid AND
                                node_id = :node_id AND
                                subtype = "title" AND
                                lang = :lang',
                            NULL,
                            array(
                                ':wid' => $ws->id,
                                ':lang' => $ws->languages_published[0],
                                ':node_id' => $cid
                            )
                        );

                        $layout->add_script('
                            $("#property-' . $property->id . '").tagit("createTag", "' . $content_title . '", "", "", "' . $cid . '");                
                        ');
                    }

                    $layout->add_script('
                        $("#property-' . $property->id . '").trigger("change");
                    ');
                }
            }
            else if($property->format = 'tree' || empty($property->format))
            {
                $field[] = '<div class="navigate-form-row" nv_property="' . $property->id . '">';
                $field[] = '<label>' . $property_name . '</label>';
                $field[] = '<div class="category_tree" id="categories-tree-property-' . $property->id . '">
                                <img src="img/icons/silk/world.png" align="absmiddle" /> ' . $ws->name .
                    '<div class="tree_ul">' . $categories_list . '</div>' .
                    '</div>';
                $field[] = $naviforms->hidden('property-' . $property->id, $property->value);
                $field[] = '<label>&nbsp;</label>';
                $field[] = '<button id="categories_tree_select_all_categories-property-' . $property->id . '">' . t(481, 'Select all') . '</button>';

                $layout->add_script('              
                    $("#categories-tree-property-' . $property->id . ' .tree_ul").jstree({
                        plugins: ["changed", "types", "checkbox"],
                        "types" :
                        {
                            "default":  {   "icon": "img/icons/silk/folder.png"    },
                            "leaf":     {   "icon": "img/icons/silk/page_white.png"      }
                        },
                        "checkbox":
                        {
                            three_state: false,
                            cascade: "undetermined"
                        },
                        "core":
                        {
                            dblclick_toggle: false
                        }
                    })
                    .on("dblclick.jstree", function(e)
                    {
                        e.preventDefault();
                        e.stopPropagation();
                    
                        var li = $(e.target).closest("li");
                        $("#categories-tree-property-' . $property->id . ' .tree_ul").jstree("open_node", "#" + li[0].id);
                    
                        var children_nodes = new Array();
                        children_nodes.push(li);
                        $(li).find("li").each(function() {
                            children_nodes.push("#" + $(this)[0].id);
                        });
                    
                        $("#categories-tree-property-' . $property->id . ' .tree_ul").jstree("select_node", children_nodes);
                    
                        return false;
                    })
                    .on("changed.jstree", function(e, data)
                    {
                        var i, j, r = [];
                        var categories = new Array();
                        $("#property-' . $property->id . '").val("");       
                    
                        for(i = 0, j = data.selected.length; i < j; i++)
                        {
                            var id = data.instance.get_node(data.selected[i]).data.nodeId;
                            categories.push(id);
                        }
                        
                        if(categories.length > 0)
                            $("#property-' . $property->id . '").val(categories);                                                                
                    });
    
                    $("#categories_tree_select_all_categories-property-' . $property->id . '").on("click", function(e)
                    {
                        e.stopPropagation();
                        e.preventDefault();
                        $("#categories-tree-property-' . $property->id . ' .tree_ul").jstree("select_all");
                        return false;
                    });                                
                ');
            }

            if (!empty($property->helper))
            {
                $helper_text = $property->helper;
                if (!empty($object))
                    $helper_text = $object->t($helper_text);
                $field[] = '<div class="subcomment">' . $helper_text . '</div>';
            }
            $field[] = '</div>';
            break;

		case 'element':
        case 'item':
            $property_item_title = '';
			$property_item_id = '';

            if(!empty($property->value))
            {
                $property_item_title = $DB->query_single(
                    'text',
                    'nv_webdictionary',
                    '   node_type = "item" AND
                        website = "'.$ws->id.'" AND
                        node_id = "'.$property->value.'" AND
                        subtype = "title" AND
                        lang = "'.$ws->languages_published[0].'"'
                );
	            $property_item_title = array($property_item_title);
	            $property_item_id = array($property->value);
            }

            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
            $field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->selectfield("property-".$property->id, $property_item_id, $property_item_title, $property->value, null, false, null, null, false);
	        if(!empty($property->helper))
	        {
		        $helper_text = $property->helper;
		        if(!empty($object))
			        $helper_text = $object->t($helper_text);
		        $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	        }
            $field[] = '</div>';

            $template_filter = @$property->element_template;
            if(empty($template_filter))
                $template_filter = $property->item_template;

            $layout->add_script('
                $("#property-'.$property->id.'").select2(
                {
                    placeholder: "'.t(533, "Find element by title").'",
                    minimumInputLength: 1,
                    ajax: {
                        url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=items&act=json_find_item",
                        dataType: "json",
                        delay: 100,
                        data: function(params)
                        {
	                        return {
				                title: params.term,
				                template: "'.$template_filter.'",
				                nd: new Date().getTime(),
				                page_limit: 30, // page size
				                page: params.page // page number
				            };
                        },
                        processResults: function (data, params)
				        {
				            params.page = params.page || 1;
				            return {
								results: data.items,
								pagination: { more: (params.page * 30) < data.total_count }
							};
				        }
                    },
                    templateSelection: function(row)
					{
						if(row.id)
							return row.text + " <helper style=\'opacity: .5;\'>#" + row.id + "</helper>";
						else
							return row.text;
					},
					escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
                    triggerChange: true,
                    allowClear: true
                });
            ');

            break;

        case 'elements':
            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
            $field[] = '<label>'.$property_name.'</label>';

            $field[] = $naviforms->textfield("property-".$property->id);

	        if(!empty($property->helper))
	        {
		        $helper_text = $property->helper;
		        if(!empty($object))
			        $helper_text = $object->t($helper_text);
		        $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	        }
            $field[] = '</div>';

            $template_filter = @$property->element_template;
            if(empty($template_filter))
                $template_filter = $property->item_template;

            $layout->add_script('			                
                $("#property-'.$property->id.'").tagit({
                    removeConfirmation: true,
                    allowSpaces: true,
                    singleField: true,
                    singleFieldDelimiter: ",",
                    placeholderText: "+",
                    autocompleteOnly: true,
                    autocomplete: {
                        delay: 0, 
                        minLength: 1,
                        source: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=items&act=json_find_item&format=tagit&page_limit=10&template='.$template_filter.'"
                    },
                    afterTagAdded: function(event, ui)
                    {                           
                        var tags = $(this).tagit("assignedValues");
                        if(tags.length > 0) tags = tags.join(",");
                        else                tags = "";
                            
                        $("#property-'.$property->id.'").val(tags).trigger("change");
                    },
                    afterTagRemoved: function(event, ui)
                    {
                        var tags = $(this).tagit("assignedValues");
                        if(tags.length > 0) tags = tags.join(",");
                        else                tags = "";

                        $("#property-'.$property->id.'").val(tags).trigger("change");
                    }
                });
                                
                $("#property-'.$property->id.'").next().sortable(
                {
                    items: ">li:not(.tagit-new)",
                    update: function(ui, event)
                    {
                        var tags = $("#property-'.$property->id.'").tagit("assignedValues");
                        if(tags.length > 0) tags = tags.join(",");
                        else                tags = "";

                        $("#property-'.$property->id.'").val(tags).trigger("change");
                    }
                });    
			');

            if(!empty($property->value))
            {
                $values = explode(",", $property->value);
                $values = array_filter($values);

                foreach($values as $cid)
                {
                    $content_title = $DB->query_single(
                        'text',
                        'nv_webdictionary',
                        '   
                            node_type = "item" AND
                            website = :wid AND
                            node_id = :node_id AND
                            subtype = "title" AND
                            lang = :lang',
                        NULL,
                        array(
                            ':wid' => $ws->id,
                            ':node_id' => $cid,
                            ':lang' => $ws->languages_published[0]
                        )
                    );

                    $layout->add_script('
                        $("#property-'.$property->id.'").tagit("createTag", "'.$content_title.'", "", "", "'.$cid.'");                
                    ');
                }

                $layout->add_script('
                    $("#property-'.$property->id.'").trigger("change");
                ');
            }

            break;

        case 'webuser_groups':
            $webuser_groups = webuser_group::all_in_array();

            // to get the array of groups first we remove the "g" character
            $property->value    = str_replace('g', '', $property->value);
            $property->value    = explode(',', $property->value);

            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
            $field[] = '<label>'.$property_name.'</label>';
            $field[] = $naviforms->multiselect(
                'property-'.$property->id,
                array_keys($webuser_groups),
                array_values($webuser_groups),
                $property->value
            );
	        if(!empty($property->helper))
	        {
		        $helper_text = $property->helper;
		        if(!empty($object))
			        $helper_text = $object->t($helper_text);
		        $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	        }
            $field[] = '</div>';
            break;

        case 'product':
            // TO DO (when navigate has products!)

            break;
			
		default:
	}
	
	return implode("\n", $field);
}

function navigate_property_layout_scripts($website_id="")
{
	global $layout;
	global $website;
	global $theme;
    global $current_version;

	$ws = $website;
	if(!empty($website_id) && $website->id!=$website_id)
	{
		$ws = new website();
		$ws->load($website_id);
	}

	$ws_languages = $ws->languages();
	$default_language = array_keys($ws_languages);
    $default_language = $default_language[0];

	$naviforms = new naviforms();

	$layout->add_content('
		<div id="navigate-properties-copy-from-dialog" style=" display: none; ">
			<div class="navigate-form-row">
				<label>'.t(191, 'Source').'</label>
				'.$naviforms->buttonset(
					'navigate_properties_copy_from_dialog_type',
					array(
						'language'   => t(46, 'Language'),
						'item'	    => t(180, 'Item'),
						'structure'	=> t(16, 'Structure')
					),
					'0',
					"navigate_properties_copy_from_change_origin(this);"
				).'
			</div>
			<div class="navigate-form-row" style=" display: none; ">
				<label>'.t(46, 'Language').'</label>
				'.$naviforms->selectfield(
					'navigate_properties_copy_from_language_selector',
					array_keys($ws_languages),
					array_values($ws_languages),
					$default_language,
					"navigate_properties_copy_from_change_language(this);"
				).'
			</div>

			<div class="navigate-form-row" style=" display: none; ">
				<label>'.t(67, 'Title').'</label>
				'.$naviforms->textfield('navigate_properties_copy_from_item_title').'
				<button id="navigate_properties_copy_from_item_reload"><i class="fa fa-repeat"></i></button>
				'.$naviforms->hidden('navigate_properties_copy_from_item_id', '').'
			</div>

			<div class="navigate-form-row" style=" display: none; ">
				<label>'.t(67, 'Title').'</label>
				'.$naviforms->textfield('navigate_properties_copy_from_structure_title').'
				<button id="navigate_properties_copy_from_structure_reload"><i class="fa fa-repeat"></i></button>
				'.$naviforms->hidden('navigate_properties_copy_from_structure_id', '').'
			</div>

			<div class="navigate-form-row" style=" display: none; ">
				<label>'.t(239, 'Section').'</label>
				'.$naviforms->select_from_object_array('navigate_properties_copy_from_section', array(), 'code', 'name', '').'
			</div>
		</div>
	');

	$layout->add_content('
	    <div id="navigate_properties_copy_from_theme_samples" style=" display: none; ">
            <div class="navigate-form-row">
                <label>'.t(79, 'Template').'</label>
                <select id="navigate_properties_copy_from_theme_samples_options"
                        name="navigate_properties_copy_from_theme_samples_options"
                        onchange="navigate_properties_copy_from_theme_samples_preview(this.value, $(this).attr(\'type\'), $(this).find(\'option:selected\').attr(\'source\'));">
                </select>
            </div>
            <div class="navigate-form-row">
                <div id="navigate_properties_copy_from_theme_samples_text"
                     name="navigate_properties_copy_from_theme_samples_text"
                     style="border: 1px solid #CCCCCC; float: left; height: auto; min-height: 20px; overflow: auto; width: 97%; padding: 3px; background: #f7f7f7;">
                </div>
                <div id="navigate_properties_copy_from_theme_samples_text_raw" style=" display: none; "></div>
            </div>
        </div>
	');

    $layout->add_script('
        var theme_content_samples = '.json_encode($theme->content_samples).';
        var website_theme = "'.$website->theme.'";
        
	    $.ajax({
	        type: "GET",
	        dataType: "script",
	        cache: true,
	        url: "lib/packages/properties/properties.js?r='.$current_version->revision.'",
	        complete: function()
	        {
                $(".navigate-content").on("click", "button.navigate-form-row-property-action", function(e)
                {                                      
                    switch($(this).data("action"))
                    {
                        case "copy-from":                                    
                            e.stopPropagation();
                            e.preventDefault();                        
                            
                            var that = this;
                            if(!$(this).parent().hasClass("navigate-form-row"))
                                that = $(this).parent();
                            
                            navigate_properties_copy_from_dialog(that);                                  
                            return false;
                            break;
                            
                        case "theme-samples":
                            e.stopPropagation();
                            e.preventDefault();                        
                                                            
                            navigate_properties_copy_from_theme_samples( 
                                "property-" + $(this).data("property-id") + "-" + $(this).data("property-lang"), 
                                $(this).data("property-id"), 
                                $(this).data("property-lang"), 
                                $(this).data("property-field") 
                            );
                            
                            return false;
                            break;
                            
                        default:
                            break;             
                    }
                });
	        }
	    });
	');
}

?>