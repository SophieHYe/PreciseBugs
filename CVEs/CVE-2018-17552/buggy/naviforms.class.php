<?php
require_once(NAVIGATE_PATH.'/lib/packages/files/file.class.php');

class naviforms
{
	public function select_from_object_array($id, $data, $value_field, $title_field, $selected_value="", $style="", $remove_keys=array(), $control_replacement=true)
	{
		$out = array();

        $class = '';
        if($control_replacement)
        {
            $class = 'select2';
        }

		$out[] = '<select class="'.$class.'" name="'.$id.'" id="'.$id.'" style="'.$style.'">';
				
		if(!is_array($data)) $data = array();
		
		foreach($data as $row)
        {
            if(is_array($row))
                $row = json_decode(json_encode($row));

            if (in_array($row->{$value_field}, $remove_keys)) continue;
            if ($row->{$value_field} == $selected_value)
                $out[] = '<option value="' . $row->{$value_field} . '" selected="selected">' . $row->{$title_field} . '</option>';
            else
                $out[] = '<option value="' . $row->{$value_field} . '">' . $row->{$title_field} . '</option>';
		}
		
		$out[] = '</select>';		
		
		return implode("\n", $out);	
	}
	
	public function selectfield($id, $values, $texts, $selected_value="", $onChange="", $multiple=false, $titles=array(), $style="", $control_replacement=true, $allow_custom_value=false, $extra_classes="")
	{
        $class = '';
        if($control_replacement)
            $class = 'select2';

        $class.= ' '.$extra_classes;

		$out = array();
		if($multiple)
			$out[] = '<select name="'.$id.'[]" id="'.$id.'" onchange="'.$onChange.'" multiple="multiple" style=" height: 100px; '.$style.' ">';
		else
			$out[] = '<select class="'.$class.'" name="'.$id.'" id="'.$id.'" onchange="'.$onChange.'" style="'.$style.'">';

		if(!is_array($values))
		    $values = array();

        if(!is_array($titles))
            $titles = array();

		for($i=0; $i < count($values); $i++)
		{
            if(!isset($titles[$i]))
                $titles[$i] = "";

			if( (is_array($selected_value) && in_array($values[$i], $selected_value)) ||
				($values[$i]==$selected_value))
				$out[] = '<option value="'.$values[$i].'" selected="selected" title="'.$titles[$i].'">'.$texts[$i].'</option>';
			else
				$out[] = '<option value="'.$values[$i].'" title="'.$titles[$i].'">'.$texts[$i].'</option>';			
		}
		
		$out[] = '</select>';

        if($allow_custom_value)
        {
            $out[] = '<a href="#" class="uibutton" data-action="create_custom_value"><i class="fa fa-plus"></i></a>';
        }
		
		return implode("\n", $out);	
	}
	
	public function buttonset($name, $options, $default, $onclick="", $jqueryui_icons=array(), $multiple=false)
	{
		$buttonset = array();
		$buttonset[] = '<div class="buttonset">';

		foreach($options as $key => $val)
		{
		    if($multiple)
            {
                $buttonset[] = '<input type="checkbox" id="' . $name . '_' . $key . '" name="' . $name . '[]" value="' . $key . '" ' . ((is_array($default) && (in_array($key, $default))) ? ' checked="checked" ' : '') . ' />';
            }
            else
            {
                $buttonset[] = '<input type="radio" id="' . $name . '_' . $key . '" name="' . $name . '[]" value="' . $key . '" ' . ((!is_null($default) && ($default == $key)) ? ' checked="checked" ' : '') . ' />';
            }
            //    $buttonset[] = '<label for="'.$name.'_'.$key.'"  onclick="'.$onclick.'"><span class="ui-button-icon-primary ui-icon '.$icon.'" style=" float: left; "></span> '.$val.'</label>';
			$buttonset[] = '<label class="unselectable" for="'.$name.'_'.$key.'"  onclick="'.$onclick.'">'.$val.'</label>';
		}
		
		$buttonset[] = '</div>';
		
		return implode("\n", $buttonset);		
	}

    public function splitbutton($id, $title, $links, $texts)
	{
        global $layout;

        $out = array();
        $out[] = '<div id="'.$id.'" class="nv-splitbutton" style="float: left;">';
        $out[] =    '<a class="'.$id.'_splitbutton_main" href="'.$links[0].'">'.$title.'</a><a href="#">'.t(200, 'Options').'</a>';
        $out[] = '</div>';
        $out[] = '<ul id="'.$id.'_splitbutton_menu" class="nv_splitbutton_menu" style="display: none; position: absolute; ">';
        for($i=0; $i < count($texts); $i++)
            $out[] = '<li><a href="'.$links[$i].'">'.$texts[$i].'</a></li>';
        $out[] = '</ul>';

        $layout->add_script('
            $(".'.$id.'_splitbutton_main").splitButton();
        ');

		return implode("\n", $out);
	}
	
	public function hidden($name, $value)
	{
		return '<input type="hidden" id="'.$name.'" name="'.$name.'" value="'.$value.'" />';
	}
	
	public function checkbox($name, $checked=false)
	{
		if($checked)
			$out = '<input id="'.$name.'" name="'.$name.'" type="checkbox" value="1" checked="checked" /><label for="'.$name.'"></label>';
		else
			$out = '<input id="'.$name.'" name="'.$name.'" type="checkbox" value="1" /><label for="'.$name.'"></label>';
			
		return $out;
	}
	
	public function textarea($name, $value="", $rows=4, $cols=48, $style="")
	{
        $value = htmlspecialchars($value);
		$out = 	'<textarea name="'.$name.'" id="'.$name.'" rows="'.$rows.'" cols="'.$cols.'" style="'.$style.'">'.$value.'</textarea>';
		return $out;
	}
	
	public function textfield($name, $value="", $width="400px", $action="", $extra="")
	{
        // may happen when converting a property type from (multilanguage) text to a (single) value
        if(is_array($value))
            $value = array_pop($value);
		$value = htmlspecialchars($value);

        if(!empty($width))
            $extra .= ' style=" width: '.$width.';"';

        if(!empty($action))
            $extra .= ' onkeyup="'.$action.'"';

		$out = '<input type="text" name="'.$name.'" id="'.$name.'" value="'.$value.'" '.$extra.' />';
		return $out;	
	}

	public function decimalfield($name, $value="", $precision=2, $decimal_separator=NULL, $thousands_separator=NULL, $prefix="", $suffix="", $width="400px", $action="", $extra="")
	{
	    global $layout;
	    global $user;

        // may happen when converting a property type from (multilanguage) text to a (single) value
        if(is_array($value))
            $value = array_pop($value);
		$value = htmlspecialchars($value);

		if(!isset($decimal_separator))
        {
            if(!empty($user))
                $decimal_separator = $user->decimal_separator;
            else
                $decimal_separator = ".";
        }

        if(!isset($thousands_separator))
        {
            if(!empty($user))
                $thousands_separator = $user->thousands_separator;
            else
                $thousands_separator = "";
        }

        if(!empty($width))
            $extra .= ' style=" width: '.$width.';"';

        if(!empty($action))
            $extra .= ' onkeyup="'.$action.'"';

        if(intval($value) == $value)
            $value = intval($value);

		$out = '<input type="text" name="'.$name.'" id="'.$name.'" value="'.$value.'" '.$extra.' />';

		if(!empty($prefix))
		    $prefix.= " ";
		else
		    $prefix = html_entity_decode("&zwnj;");

		if(!empty($suffix))
		    $suffix = " " . $suffix;
		else
		    $suffix = html_entity_decode("&zwnj;");

		// we need to add a "ZERO WIDTH NON-JOINER" character instead of empty prefixes/suffixes
        // to workaround a weird inputmask bug with negative values

        $layout->add_script('
            $("#'.$name.'").inputmask(
                { 
                    alias: "decimal",
                    rightAlign: false,
                    digitsOptional: true,
                    autoGroup: true,
                    allowMinus: true,
                    digits: '.$precision.',
                    radixPoint: "'.$decimal_separator.'",
                    groupSeparator: "'.$thousands_separator.'",                   
                    prefix: "'.$prefix.'",
                    suffix: "'.$suffix.'",
                    unmaskAsNumber: true,
                    autoUnmask: false
                }
            );
        ');

		return $out;
	}
	
	public function autocomplete($name, $value="", $source, $callback='""', $width="400px", $add_custom_value=false)
	{
		global $layout;
		
		$value = htmlspecialchars($value);
		
		$out = '<input type="text" name="'.$name.'" id="'.$name.'" value="'.$value.'" style=" width: '.$width.';" />';

        if(is_array($source))
            $source = '["'.implode('","', $source).'"]';
        else
            $source = '"'.$source.'"';

		$layout->add_script('
			$("#'.$name.'").autocomplete(
			{
				source: '.$source.',
				minLength: 1,
				select: '.$callback.'				
			});
		');

        if($add_custom_value)
        {
            $uid = uniqid();
            $out .= ' <a href="#" class="uibutton" data-action="create_custom_value" data-uid="'.$uid.'"><i class="fa fa-plus"></i></a>';
            $layout->add_script('
                var naviforms_autocomplete_'.$uid.' = [];
                
                $("a[data-action=create_custom_value][data-uid='.$uid.']").on("click", function()
                {
                    var text = prompt(navigate_t(159, "Name"));
                    text = text.trim();
                    if(text != "")
                    {
                        naviforms_autocomplete_'.$uid.'.unshift(text);
                        $("#'.$name.'").val(text);
                        $("#'.$name.'").trigger("navigate-added-custom-value");                         
                    }
                });
                
                $("#'.$name.'").on( "autocompleteresponse", function( event, ui ) 
                {
                    for(i in naviforms_autocomplete_'.$uid.')
                        ui.content.unshift({ "id": "custom-" + new Date().getTime(), "label": naviforms_autocomplete_'.$uid.'[i], "value": naviforms_autocomplete_'.$uid.'[i]});
                });
            ');
        }

		return $out;	
	}

    public function pathfield($name, $value="", $width="400px", $action="", $extra="", $language="")
    {
        global $website;
        global $layout;
        $lang = value_or_default($language, $website->languages_published[0]);

        // may happen when converting a property type from (multilanguage) text to a (single) value
        if(is_array($value))
            $value = array_pop($value);
        $value = htmlspecialchars($value);

        if(!empty($width))
            $extra .= ' style=" width: '.$width.';"';

        if(!empty($action))
            $extra .= ' onkeyup="'.$action.'"';

        $selected_path_title = "";
        if(!empty($value))
        {
            $path = explode('/', $value);
            if(count($path) > 0 && $path[0]=='nv:')
            {
                if($path[2]=='structure')
                {
                    $tmp = new structure();
                    $tmp->load($path[3]);
                    $selected_path_title = $tmp->dictionary[$lang]['title'];
                    $layout->add_script('
                        $("#'.$name.'")
                            .parent()
                            .find(".naviforms-pathfield-link-info[data-lang='.$lang.']")
                            .find("img[data-type=structure]")
                            .removeClass("hidden");
                    ');
                }
                else if($path[2]=='element')
                {
                    $tmp = new item();
                    $tmp->load($path[3]);
                    $selected_path_title = $tmp->dictionary[$lang]['title'];
                    $layout->add_script('
                        $("#'.$name.'")
                            .parent()
                            .find(".naviforms-pathfield-link-info[data-lang='.$lang.']")
                            .find("img[data-type=element]")
                            .removeClass("hidden");
                    ');
                }
            }
        }

        $out = '<input type="text" name="'.$name.'" id="'.$name.'" value="'.$value.'" '.$extra.' />';
        $out.= '<a class="uibutton naviforms-pathfield-trigger"><i class="fa fa-sitemap"></i></a>';
        $out.= '<div class="subcomment naviforms-pathfield-link-info" data-lang="'.$lang.'">
                    <img src="img/icons/silk/sitemap_color.png" class="hidden" data-type="structure" sprite="false" />
                    <img src="img/icons/silk/page.png" class="hidden" data-type="element" sprite="false" /> '.
                    '<span>'.$selected_path_title.'</span>'.
                '</div>';
        return $out;
    }
	
	public function datefield($name, $value="", $hour=false, $style="")
	{
		global $layout;
		global $user;

		if(!empty($value))
			$value = core_ts2date($value, $hour);
        else
		    $value = ""; // set empty also for value = 0

		$out = '<input type="text" class="datepicker" name="'.$name.'" id="'.$name.'" value="'.$value.'" style="'.$style.'" />
				<img src="img/icons/silk/calendar_delete.png" width="16" height="16" align="absmiddle" 
					 style=" cursor: pointer; " onclick=" $(this).parent().find(\'input\').val(\'\'); $(this).parent().find(\'input\').trigger(\'change\'); " />';
		
		$format = $user->date_format;   // custom user date format

        // format to jquery ui datepicker
        // http://docs.jquery.com/UI/Datepicker/formatDate
        $format = php_date_to_jquery_ui_datepicker_format($format);

        $translations = '
                monthNames: [
                    "'.t(101, "January").'",
                    "'.t(102, "February").'",
                    "'.t(103, "March").'",
                    "'.t(104, "April").'",
                    "'.t(105, "May").'",
                    "'.t(106, "June").'",
                    "'.t(107, "July").'",
                    "'.t(108, "August").'",
                    "'.t(109, "September").'",
                    "'.t(110, "October").'",
                    "'.t(111, "November").'",
                    "'.t(112, "December").'"
                ],
                monthNamesShort: [
                    "'.t(113, "Jan").'",
                    "'.t(114, "Feb").'",
                    "'.t(115, "Mar").'",
                    "'.t(116, "Apr").'",
                    "'.t(117, "May").'",
                    "'.t(118, "Jun").'",
                    "'.t(119, "Jul").'",
                    "'.t(120, "Aug").'",
                    "'.t(121, "Sept").'",
                    "'.t(122, "Oct").'",
                    "'.t(123, "Nov").'",
                    "'.t(124, "Dec").'"
                ],
                dayNames: [
                    "'.t(131, "Sunday").'",
                    "'.t(125, "Monday").'",
                    "'.t(126, "Tuesday").'",
                    "'.t(127, "Wednesday").'",
                    "'.t(128, "Thursday").'",
                    "'.t(129, "Friday").'",
                    "'.t(130, "Saturday").'"
                ],
                dayNamesShort: [
                    "'.t(138, "Sun").'",
                    "'.t(132, "Mon").'",
                    "'.t(133, "Tue").'",
                    "'.t(134, "Wed").'",
                    "'.t(135, "Thu").'",
                    "'.t(136, "Fri").'",
                    "'.t(137, "Sat").'"
                ],
                dayNamesMin: [
                    "'.t(138, "Sun").'",
                    "'.t(132, "Mon").'",
                    "'.t(133, "Tue").'",
                    "'.t(134, "Wed").'",
                    "'.t(135, "Thu").'",
                    "'.t(136, "Fri").'",
                    "'.t(137, "Sat").'"
                ],
                prevText: "'.t(501, "Previous").'",
                nextText: "'.t(502, "Next").'",
                closeText: "'.t(92, "Close").'",
                currentText: "'.t(503, "Now").'",
                timeText: "'.t(504, "Time").'",
                hourText: "'.t(93, "Hour").'",
                minuteText: "'.t(94, "Minute").'",
                secondText: "'.t(96, "Second").'",
        ';

		if(!$hour)
        {
            $format = str_replace('H:i', '', $format);
            $layout->add_script('
                $("#'.$name.'").datepicker(
                {
                    '.$translations.'
                    dateFormat: "'.trim($format).'",
                    changeMonth: true,
                    changeYear: true
                });
            ');
        }
        else
        {
            $format = str_replace('H:i', '', $format);
            $layout->add_script('
                navigatecms.forms.datepicker["'.$name.'"] = $("#'.$name.'").datetimepicker(
                {
                    '.$translations.'
                    dateFormat: "'.trim($format).'",
                    timeFormat: "HH:mm",
                    changeMonth: true,
                    changeYear: true,
                    timezone: null,
                    onClose: function()
                    {
                        if(navigatecms.forms.datepicker["'.$name.'"].qtip_obj)
						{
							navigatecms.forms.datepicker["'.$name.'"].qtip_obj.qtip("hide");
							navigatecms.forms.datepicker["'.$name.'"].qtip_obj.qtip("disable");
						}
                    },
                    onChangeMonthYear: function(year, month, instance)
                    {
						setTimeout(function()
						{
							if(navigatecms.forms.datepicker["'.$name.'"].qtip_obj)
							{
								navigatecms.forms.datepicker["'.$name.'"].qtip_obj.qtip("hide");
								navigatecms.forms.datepicker["'.$name.'"].qtip_obj.qtip("disable");
							}
							else
							{
	                            navigatecms.forms.datepicker["'.$name.'"].qtip_obj = $("table.ui-datepicker-calendar").qtip(
								{
								    content: "'.t(609, "Click a day of the month selected to update the value", null, true).'",
									overwrite: true,
									show: true,
							        hide:
							        {
								        event: "unfocus"
						            },
							        style:
							        {
								        tip: true,
								        width: 200,
								        classes: "qtip-cream"
							        },
							        position:
							        {
								        at: "center right",
								        my: "bottom left"
							        }
						        });
					        }
                        }, 100);
                    }
                });
            ');
        }

		return $out;
	}

    public function colorfield($name, $value="#ffffff", $swatches=array(), $onchange='')
    {
        global $layout;
        global $user;

        $out = '<input type="text" class="naviforms-colorpicker-text" name="'.$name.'" id="'.$name.'" value="'.$value.'" data-previous="'.$value.'" />
                <div id="'.$name.'-selector" class="naviforms-colorpicker-selector ui-corner-all"><div style="background: '.$value.'; "></div></div>';

        if(!is_array($swatches) || empty($swatches))
            $swatches = array();

        $swatches = array_map(function($c) { return hex2rgb($c); }, $swatches);

        $swatches_js = "{";
        for($s=0; $s < count($swatches); $s++)
        {
            $swatches_js .= '"'.$s.'": {r:'.($swatches[$s]['r']/255).', g:'.($swatches[$s]['g']/255).', b:'.($swatches[$s]['b']/255).'},';
        }
        $swatches_js.= "}";

        if($swatches_js == "{}")
            $swatches_js = "null";

        $layout->add_script('
            $("input[name=\"'.$name.'\"]").colorpicker({
                altField: $("input[name=\"'.$name.'\"]").next().find("> div"),
                altOnChange: true,
                regional: "'.$user->language.'",
                colorFormat: ["#HEX"],
                draggable: true,
                parts: ["header", "map", "bar", "hex", "hsv", "rgb", "preview", "swatches", "footer"],
                //layout: { "memory": [6,0,1,5] },
                alpha: false,
                showNoneButton: false,
                position: {
                    my: "left top",
                    at: "right+8 top",
                    of: $("input[name=\"'.$name.'\"]").next()
                },
                okOnEnter: true,
                revert: true,
                swatches: '.$swatches_js.',
                swatchesWidth: 80,
                open: function(event, color)
                {                
                    if($(".ui-colorpicker-dialog").position().top + $(".ui-colorpicker-dialog").height() > $(window).height())                    
                        $(".ui-colorpicker-dialog").css("top", $(window).height() - $(".ui-colorpicker-dialog").height() - 8);
                                        
                    $("input[name=\"'.$name.'\"]").data("previous", $("input[name=\"'.$name.'\"]").val());
                    $("input[name=\"'.$name.'\"]").next().children().css("backgroundColor", $("input[name=\"'.$name.'\"]").data("previous"));
                    $("input[name=\"'.$name.'\"]").colorpicker("setColor", $("input[name=\"'.$name.'\"]").data("previous"));
                },
                select: function(event, color)
                {
                    '.(!empty($onchange)? $onchange.'($("input[name=\"'.$name.'\"]"))' : '').'                    
                },
                cancel: function(event, color)
                {
                    $("input[name=\"'.$name.'\"]").val($("input[name=\"'.$name.'\"]").data("previous"));
                    $("input[name=\"'.$name.'\"]").next().children().css("backgroundColor", $("input[name=\"'.$name.'\"]").data("previous"));
                }
            });
        ');

        return $out;
    }

	public function scriptarea($name, $value, $syntax="js", $style= " width: 75%; height: 250px; ")
	{
		global $layout;
		
		$out = '<textarea name="'.$name.'" id="'.$name.'" style=" '.$style.' " rows="10">'.$value.'</textarea>';

		$layout->add_script('
			$(window).on("load", function()
			{
				var cm = CodeMirror.fromTextArea(
				    document.getElementById("'.$name.'"), 
                    {
                        mode: "text/html", 
                        tabMode: "indent",
                        lineNumbers: true,
                        styleActiveLine: true,
                        matchBrackets: true,
                        autoCloseTags: true,
                        extraKeys: {"Ctrl-Space": "autocomplete"}
                    }
                );

		        CodeMirror.commands.autocomplete = function(cm) {
                    CodeMirror.showHint(cm, CodeMirror.htmlHint);
                }

				navigate_codemirror_instances.push(cm);
	
				$("#'.$name.'").next().attr("style", "'.$style.'");
				$(".CodeMirror-scroll").css({ width: "100%", height: "100%"});
				
				cm.refresh();
			});
		');

		return $out;
	}
	
	public function editorfield($name, $value, $width="80%", $lang="es", $website_id=NULL)
	{
		global $layout;
		global $website;
		global $user;

		$height = 400;
        
        $ws = $website;
        if(!empty($website_id) && $website_id!=$website->id)
        {
            $ws = new website();
            $ws->load($website_id);
        }

		$text = htmlentities($value, ENT_HTML5 | ENT_NOQUOTES, 'UTF-8', true);

		// remove unneeded new lines (to fix a problem of extra spaces in pre/code tags)
		$text = str_replace('&NewLine;', '', $text);

		$out = '<textarea name="'.$name.'" id="'.$name.'" style=" width: '.$width.'; height: '.$height.'px; ">'.$text.'</textarea>';

        $content_css = $ws->content_stylesheets('tinymce', 'content');

        // note, a file in the "content_selectable" property must also be in the "content" property for TinyMCE to work as expected
        $content_css_selectable = $ws->content_stylesheets('tinymce', 'content_selectable');

		$tinymce_language = $user->language;

        $layout->add_script('    
            tinyMCE.baseURL = "'.NAVIGATE_URL.'/lib/external/tinymce4";
            $("#'.$name.'").tinymce(
            {
                language: "'.$tinymce_language.'",
                
                width: ($("#'.$name.'").width()) + "px",
                height: $("#'.$name.'").height() + "px",
                resize: "both",
                
                menubar: false,
                theme: "modern",
                skin: "navigatecms-cupertino",
                			    
			    plugins: [
				    "compat3x noneditable importcss",
				    "advlist autolink nv_link image lists charmap print preview hr anchor pagebreak",
				    "searchreplace wordcount visualblocks visualchars fullscreen media nonbreaking",
				    "table directionality template textcolor paste textcolor colorpicker textpattern",
				    "codesample codemirror imagetools paste magicline fontawesome nv_rollups" // add fullpage to edit full HTML code with head and body tags
				],
				
				external_plugins: {
				    "loremipsum": "'.NAVIGATE_URL.'/lib/external/tinymce4/plugins/loremipsum/editor_plugin.js",
				    "imgmap": "'.NAVIGATE_URL.'/lib/external/tinymce4/plugins/imgmap/editor_plugin.js",
				    "style": "'.NAVIGATE_URL.'/lib/external/tinymce4/plugins/style/editor_plugin.js",
				    "xhtmlxtras": "'.NAVIGATE_URL.'/lib/external/tinymce4/plugins/xhtmlxtras/editor_plugin.js"
				},
				
				toolbar: [
					"formatselect fontselect fontsizeselect | forecolor | backcolor | removeformat | searchreplace code",
                    "bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | outdent indent blockquote | bullist numlist | nv_rollup_special_char",
                    "styleselect | styleprops attribs | table | nv_rollup_links | image imgmap media codesample | magicline | undo redo"
                ],

				toolbar_items_size: "small",
				
				mobile: {
                    theme: "mobile"
                },
				
				// forced fix to avoid tinymce adding <p> element on non block elements (span, i, etc)
				// needed mainly for Codemirror plugin, but force_p_newlines is deprecated by the TinyMCE team
				forced_root_block: "",
				force_br_newlines : true,
                force_p_newlines : true,
				
			    browser_spellcheck: true,
                spellchecker_language: "'.$lang.'",
                
                noneditable_noneditable_class: "fa",    // without this, TinyMCE removes the Font Awesome icons when editing the content
                
                media_live_embeds: false, // disable iframe loading (like videos) to allow resizing
                
                magicline_color: "#0070a3",
                magicline_targetedItems: ["DIV", "IMG", "IFRAME", "PRE", "TABLE", "ARTICLE", "UL", "OL", "BLOCKQUOTE", "TR"],
                magicline_triggerMargin: 16,
			    
			    codemirror: {
					path:  "'.NAVIGATE_URL.'/lib/external/codemirror",
				    indentOnInit: true,
                    config: {
                        mode: "htmlmixed",
                        lineNumbers: true
                    },
                    jsFiles: [
                        "mode/htmlmixed/htmlmixed.js"
                    ]
				},
				
				image_advtab: true,
				
				automatic_uploads: true,
			    paste_data_images: true,
				images_upload_url: "navigate_upload.php?engine=tinymce&session_id='.session_id().'&debug",
				
				fontsize_formats: "8px 9px 10px 11px 12px 13px 14px 15px 16px 17px 18px 20px 24px 26px 28px 30px 32px 36px 42px 48px 56px 64px", 
                
                content_css: "'.$content_css.'",
                              
				style_formats_merge: true,
                importcss_append: false,
                importcss_file_filter: function(value) 
                {
                    var files = "'.$content_css_selectable.'";
                    if(files.indexOf(",") > -1)
                    {
                        files = files.split(",");             
	                    for(var i=0; i < files.length; i++)
	                    {
	                        if(value.indexOf(files[i]) !== -1)
	                        {
	                            return true;
	                        }
	                    }
	                    return false;
                    }
                    else
                    {
                        return (value==files);
                    }
                },              
                                
                //  https://www.tinymce.com/docs/configure/url-handling
                convert_urls: false,
                relative_urls: true,
                remove_script_host: false,
                
                // https://www.tinymce.com/docs/configure/content-filtering/
                valid_elements: "*[*],+a[*],+p[*],#i",
                custom_elements: "nv,code,pre,nvlist,nvlist_conditional,figure,article,header,footer,post,nav",
                extended_valid_elements: "+nv[*],+pre[*],+code[*],+nvlist[*],+nvlist_conditional[*],+figure[*],+article[*],+nav[*],+i[*],+span[*],+em[*],+b[*],*[*]",
                valid_children: "+a[div|p|li],+body[style|script|nv|nvlist|nvlist_conditional],+code[nv|nvlist|nvlist_conditional]",
                
                paste_as_text: true,
                
                // https://www.tinymce.com/docs/configure/content-filtering/#allow_html_in_named_anchor
                allow_html_in_named_anchor: true,          
                
                // events
                handle_event_callback : "navigate_tinymce_event",
                
                // before rendering this tinymce
                setup: function(editor)
                {
	                editor.on("init", function() 
	                { 	                
				        $(editor.getWin()).on("scroll blur focus", function(e)
				        {
                            navigate_tinymce_event(e, "'.$name.'");
				        });
				        
				        // restore last known iframe scroll position
				        navigate_tinymce_event({type: "focus"}, "'.$name.'", true);
	                    setTimeout(function()
	                    {
	                        navigate_tinymce_event({type: "focus"}, "'.$name.'", true);
	                    }, 25);
				    });					    		    				   
                },
                
                // just after rendering this tinymce 
                init_instance_callback: function(editor)
                {                      
					// find missing images
					$("#'.$name.'").parent().find("iframe").contents().find("img").each(function()
					{
						if( (typeof this.naturalWidth != "undefined" && this.naturalWidth == 0 ) 
					        || this.readyState == "uninitialized" )					         
				        {
					        $(this).addClass("nomagicline");
					    }
					});
                
                    $("#'.$name.'").parent().find("iframe").droppable(
                    {
                        drop: function(event, ui)
                        {
                            if(!$(ui.draggable).attr("id")) // not a file!
                            {
                                $("#'.$name.'_tbl").css("opacity", 1);
                                return;
                            }

                            var file_id = $(ui.draggable).attr("id").substring(5);
                            if(!file_id || file_id=="" || file_id==0) return;
                            var media = $(ui.draggable).attr("mediatype");
                            var mime = $(ui.draggable).attr("mimetype");
                            var web_id = "'.$ws->id.'";
                            navigate_tinymce_add_content($("#'.$name.':tinymce").attr("id"), file_id, media, mime, web_id, ui.draggable);
                            $("#'.$name.'").parent().find("> .mce-tinymce").css("opacity", 1);
                        },
                        over: function(event, ui)
                        {
                            if(!$(ui.draggable).attr("id")) // not a file!
                                return;

                            $("#'.$name.'").parent().find("> .mce-tinymce").css("opacity", 0.75);
                        },
                        out: function(event, ui)
                        {
                            $("#'.$name.'").parent().find("> .mce-tinymce").css("opacity", 1);
                        }
                    });
                                        
                    // deprecated, but the only way we found to activate the button on init
	                tinyMCE.get("'.$name.'").controlManager.setActive("magicline", true);
	                	                
                    // user warning to avoid losing unsaved changes
                    editor.on("change", function()
                    {
                        navigate_beforeunload_register();
                    });
                    
                }
            });
        ');

        $layout->navigate_editorfield_link_dialog();

		return $out;
	}
	
	public function dropbox($name, $value=0, $media="", $disabled=false, $default_value=null, $options=array(), $website_id=null)
	{
		global $layout;
		global $website;
        global $theme;

        if(empty($website_id))
            $website_id = $website->id;

		$out = array();
        $out[] = '<div id="'.$name.'-droppable-wrapper" class="navigate-droppable-wrapper">';

		$out[] = '<input type="hidden" id="'.$name.'" name="'.$name.'" value="'.$value.'" />';		

		$out[] = '<div id="'.$name.'-droppable" class="navigate-droppable ui-corner-all" data-media="'.$media.'">';

		if(!empty($value))
		{
			if($media=='image')
            {
                $f = new file();
                $f->load($value);
                $out[] = '<img title="'.$f->name.'" src="'.NAVIGATE_DOWNLOAD.'?wid='.$website_id.'&id='.$f->id.'&amp;disposition=inline&amp;width=75&amp;height=75"
                                                    data-src-original="'.NAVIGATE_DOWNLOAD.'?wid='.$website_id.'&id='.$f->id.'&amp;disposition=inline" />';
            }
            else if($media=='video')
            {
                $layout->add_script('
                    $(window).load(function() { navigate_dropbox_load_video("'.$name.'", "'.$value.'"); });
                ');

                $out[] = '<figure class="navigatecms_loader"></figure>';
            }
			else
            {
                $f = new file();
                $f->load($value);
				$out[] = '<img title="'.$f->name.'" src="'.(navibrowse::mimeIcon($f->mime, $f->type)).'" width="50" height="50" /><br />'.$f->name;
            }
		}
		else
        {
			$out[] = '	<img src="img/icons/misc/dropbox.png" vspace="18" />';
        }
		$out[] = '</div>';

        // set parent row as overflow:visible to let the whole contextmenu appear
        $layout->add_script('
            $(".navigate-droppable-wrapper").parent().css("overflow", "visible");
        ');

        $contextmenu = false;

		if(!$disabled)
		{
			$out[] = '<div class="navigate-droppable-cancel"><img src="img/icons/silk/cancel.png" /></div>';
            if($media=='image')
            {
                if($options == 'a:0:{}')
                    $options = array();

                if(empty($options) && !empty($default_value))
                    $options = array($default_value => t(199, "Default value"));
                else
                    $options = (array)$options;

                if(!empty($options))
                {
                    $out[] = '
                        <div class="navigate-droppable-create">
                            <img src="img/icons/silk/add.png" />
                        </div>
                    ';

                    // "create" context menu actions (image picker)
                    $ws = new website();
                    if($website_id==$website->id)
                        $ws = $website;
                    else
                        $ws->load($website_id);

                    $ws_theme = new theme();
                    if($website_id==$website->id)
                        $ws_theme = $theme;
                    else
                        $ws_theme->load($ws->theme);

                    $layout->add_content('
                        <ul id="'.$name.'-image_picker" class="navigate-image-picker navi-ui-widget-shadow">
                            '.implode("\n", array_map(
                                function($k, $v) use ($website_id, $ws_theme)
                                {
                                    if(!empty($ws_theme))
                                        $v = $ws_theme->t($v);

                                    return '
                                        <li data-value="'.$k.'" data-src="'.NAVIGATE_DOWNLOAD.'?wid='.$website_id.'&id='.$k.'&amp;disposition=inline&amp;width=75&amp;height=75">
                                            <a href="#">
                                                <img title="'.$v.'" src="'.NAVIGATE_DOWNLOAD.'?wid='.$website_id.'&id='.$k.'&amp;disposition=inline&amp;width=48&amp;height=48" />
                                                <span>'.$v.'</span>
                                            </a>
                                        </li>
                                    ';
                                },
                                array_keys($options),
                                array_values($options)
                            )).'
                        </ul>
                    ');

                    $layout->add_script('
                        $("#'.$name.'-droppable").parent().find(".navigate-droppable-create").on(
                        "click",
                        function(ev)
				        {
                            navigate_hide_context_menus();
                            setTimeout(function()
                            {
                                $("#'.$name.'-image_picker").menu();
                                $("#'.$name.'-image_picker").css({left: ev.pageX, top: ev.pageY});
                                $("#'.$name.'-image_picker").show();
                                
                                if($("#'.$name.'-image_picker").position().top + $("#'.$name.'-image_picker").height() > $(window).height())                    
                                    $("#'.$name.'-image_picker").css("top", $(window).height() - $("#'.$name.'-image_picker").height() - 8);

                                $("#'.$name.'-image_picker li").off().on("click", function()
                                {
                                    $("#'.$name.'").val($(this).data("value"));
                                    $("#'.$name.'-droppable").html("<img src=\"" + $(this).data("src") + "\" />");
                                    $("#'.$name.'-droppable").parent().find(".navigate-droppable-cancel").show();
                                    $("#'.$name.'-droppable").parent().find(".navigate-droppable-create").hide();
                                });
                            }, 100);
                        });
                    ');

                    $contextmenu = true;
                }

                // images: add context menu over the image itself to define focal point, description and title...
                $out[] = '
                    <ul class="navigate-droppable-edit-contextmenu" style="display: none;">
                        <li action="permissions"><a href="#"><span class="ui-icon ui-icon-key"></span>'.t(17, "Permissions").'</a></li>
                        <li action="focalpoint"><a href="#"><span class="ui-icon ui-icon-image"></span>'.t(540, "Focal point").'</a></li>
                        <li action="description"><a href="#"><span class="ui-icon ui-icon-comment"></span>'.t(334, 'Description').'</a></li>
                        <li action="preview"><a href="#"><span class="ui-icon ui-icon-zoomin"></span>'.t(274, 'Preview').'</a></li>
                    </ul>
                ';
                
                $layout->add_script('
                    $("#'.$name.'-droppable").on("contextmenu", function(ev)
                    {
                        ev.preventDefault();
                        navigate_hide_context_menus();
                        var file_id = $("#'.$name.'").val();
                        if(!file_id || file_id=="" || file_id==0) return;

                        setTimeout(function()
                        {
                            var menu_el = $("#'.$name.'-droppable").parent().find(".navigate-droppable-edit-contextmenu");

							var menu_el_clone = menu_el.clone();
							menu_el_clone.appendTo("body");

                            menu_el_clone.menu();

                            menu_el_clone.css({
                                "z-index": 100000,
                                "position": "absolute",
                                "left": ev.clientX,
                                "top": ev.clientY
                            }).addClass("navi-ui-widget-shadow").show();

	                        menu_el_clone.find("a").on("click", function(ev)
		                    {
		                        ev.preventDefault();
		                        var action = $(this).parent().attr("action");
		                        var file_id = $("#'.$name.'").val();

		                        switch(action)
		                        {
		                            case "permissions":
		                            navigate_contextmenu_permissions_dialog(file_id);
		                            break;

		                            case "focalpoint":
		                            navigate_media_browser_focalpoint(file_id);
		                            break;

		                            case "description":
		                            $.get(
		                                NAVIGATE_APP + "?fid=files&act=json&op=description&id=" + file_id,
		                                function(data)
		                                {
		                                    data = $.parseJSON(data);
		                                    navigate_contextmenu_description_dialog(file_id, $("#'.$name.'-droppable"), data.title, data.description);
		                                }
		                            );
		                            break;
		                            
		                            case "preview":
		                                $("#'.$name.'-droppable img[data-src-original]").trigger("dblclick");
		                            break;
		                        }
		                    });
                        }, 100);
                    });
                ');
            }
            else if($media=='video')
            {
                $out[] = '
                    <div class="navigate-droppable-create">
                        <img src="img/icons/silk/add.png" />
                        <ul class="navigate-droppable-create-contextmenu" data-field-id="'.$name.'">
                            <li action="default" value="'.$default_value.'"><a href="#"><span class="fa fa-lg fa-eraser"></span> '.t(199, "Default value").'</a></li>
                            <li action="youtube_url"><a href="#"><span class="fa fa-lg fa-youtube-square fa-align-center"></span> Youtube URL</a></li>
                            <li action="vimeo_url"><a href="#"><span class="fa fa-lg fa-vimeo-square fa-align-center"></span> Vimeo URL</a></li>
                        </ul>
                    </div>
                ';

                // context menu actions
                $layout->add_script('
                    if('.(empty($default_value)? 'true' : 'false').')
                        $("#'.$name.'-droppable").parent().find(".navigate-droppable-create-contextmenu li[action=default]").remove();

                    $("#'.$name.'-droppable").parent()
                        .find(".navigate-droppable-create")
                        .find(".navigate-droppable-create-contextmenu li")
                        .on("click", function()
                        {
                            setTimeout(function() { navigate_hide_context_menus(); }, 100);

                            switch($(this).attr("action"))
                            {
                                case "default":
                                    $("#'.$name.'-droppable").html(\'<figure class="navigatecms_loader"></figure>\');
                                    navigate_dropbox_load_video("'.$name.'", "'.$default_value.'");
                                    break;

                                case "youtube_url":
                                    $("<div><form action=\"#\" onsubmit=\"return false;\"><input type=\"text\" name=\"url\" value=\"\" style=\"width: 100%;\" /></form></div>").dialog({
                                        "title": "Youtube URL",
                                        "modal": true,
                                        "width": 500,
                                        "height": 120,
                                        "buttons": {
                                            "'.t(190, "Ok").'": function(e, ui)
                                            {
                                                var reference = navigate_youtube_reference_from_url($(this).find("input").val());
                                                if(reference && reference!="")
                                                {
                                                    $("#'.$name.'-droppable").html(\'<figure class="navigatecms_loader"></figure>\');
                                                    navigate_dropbox_load_video("'.$name.'", "youtube#" + reference);
                                                }
                                                $(this).dialog("close");
                                            },
                                            "'.t(58, "Cancel").'": function() { $(this).dialog("close"); }
                                        }
                                    });
                                    break;

                                case "vimeo_url":
                                    $("<div><form action=\"#\" onsubmit=\"return false;\"><input type=\"text\" name=\"url\" value=\"\" style=\"width: 100%;\" /></form></div>").dialog({
                                        "title": "Vimeo URL",
                                        "modal": true,
                                        "width": 500,
                                        "height": 120,
                                        "buttons": {
                                            "'.t(190, "Ok").'": function(e, ui)
                                            {
                                                var reference = navigate_vimeo_reference_from_url($(this).find("input").val());
                                                if(reference && reference!="")
                                                {
                                                    $("#'.$name.'-droppable").html(\'<figure class="navigatecms_loader"></figure>\');
                                                    navigate_dropbox_load_video("'.$name.'", "vimeo#" + reference);
                                                }
                                                $(this).dialog("close");
                                            },
                                            "'.t(58, "Cancel").'": function() { $(this).dialog("close"); }
                                        }
                                    });
                                    break;
                            }
                        }
                    );
                ');

                $contextmenu = true;
            }

			$layout->add_script('
				$("#'.$name.'-droppable").parent().find(".navigate-droppable-cancel").on("click", function()
				{
					$("#'.$name.'").val("0");
					$("#'.$name.'-droppable").html(\'<img src="img/icons/misc/dropbox.png" vspace="18" />\');
					$("#'.$name.'-droppable").parent().find(".navigate-droppable-cancel").hide();
					$("#'.$name.'-droppable").parent().find(".navigate-droppable-create").show();
					$("#'.$name.'-droppable-info").children().html("");
					navigate_media_browser_refresh_files_used();
				});

				$("#'.$name.'-droppable").parent().find(".navigate-droppable-create").on("click", function(ev)
				{
                    navigate_hide_context_menus();
                    $("ul[data-context-menu-temporary-clone=true]").remove();

                    setTimeout(function()
                    {
                        var menu_el = $("#'.$name.'-droppable").parent().find(".navigate-droppable-create-contextmenu");

                        menu_el.menu();

                        menu_el.css({
                            "z-index": 100000,
                            "position": "absolute"
                        }).addClass("navi-ui-widget-shadow").show();
                    }, 100);
				});
			');
			
			if(!empty($media))
				$accept = 'accept: ".draggable-'.$media.'",';
							
			$layout->add_script('
				$("#'.$name.'-droppable").droppable(
				{
					'.$accept.'
					hoverClass: "navigate-droppable-hover",
					drop: function(event, ui) 
					{
						var file_id = $(ui.draggable).attr("id").substring(5);
						$("#'.$name.'").val(file_id);
						var draggable_content = $(ui.draggable);

						if($(draggable_content).find(".file-image-wrapper").length > 0)
						{
						    draggable_content = $(draggable_content).find(".file-image-wrapper").html();
                        }
                        else
                        {
                            draggable_content = $(ui.draggable).html();
                        }

						$(this).html(draggable_content);
						$(this).find("div.file-access-icons").remove();

						$("#'.$name.'-droppable").parent().find(".navigate-droppable-cancel").show();
					    $("#'.$name.'-droppable").parent().find(".navigate-droppable-create").hide();
                        $("#'.$name.'-droppable-info").find(".navigate-droppable-info-title").html("");
                        $("#'.$name.'-droppable-info").find(".navigate-droppable-info-provider").html("");
                        $("#'.$name.'-droppable-info").find(".navigate-droppable-info-extra").html("");
					}
				});
			');

            if(empty($value) && $contextmenu)
            {
                $layout->add_script('
                    $("#'.$name.'-droppable").parent().find(".navigate-droppable-create").show();
                    $("#'.$name.'-droppable").parent().find(".navigate-droppable-cancel").hide();
                ');
            }
            else if(!empty($value))
            {
                $layout->add_script('
                    $("#'.$name.'-droppable").parent().find(".navigate-droppable-cancel").show();
                    $("#'.$name.'-droppable").parent().find(".navigate-droppable-create").hide();
                ');
            }

		}

        $out[] = '<div id="'.$name.'-droppable-info" class="navigate-droppable-info">';
        $out[] = '  <div class="navigate-droppable-info-title"></div>';
        $out[] = '  <div class="navigate-droppable-info-extra"></div>';
        $out[] = '  <div class="navigate-droppable-info-provider"></div>';
        $out[] = '</div>';

        $out[] = '</div>'; // close droppable wrapper
				
		return implode("\n", $out);
	}

    public function dropdown_tree($id, $tree, $selected_value="", $on_change="")
    {
        global $layout;

        $out = array();

        // TODO: check available dropdown_tree extensions or just use the default
        $out[] = '<input type="hidden" id="'.$id.'" name="'.$id.'" value="'.$selected_value.'" />';

        $path = "";

        $out[] = '<input type="text" id="tree_path_'.$id.'" value="'.$path.'" readonly="true" />';
        $out[] = '<img src="img/icons/silk/erase.png" width="16" height="16" align="absmiddle"'.
					 'style=" cursor: pointer; " onclick=" tree_wrapper_'.md5($id).'_reset(); " />';

        if(!empty($on_change))
            $on_change .= '(value);';

        if(empty($tree))
            $tree = '<ul><li value="0">&nbsp;</li></ul>';

        $out[] = '<div style="float: left;" id="tree_wrapper_'.$id.'">'.$tree.'</div>';

        $layout->add_script('
            $("#tree_wrapper_'.$id.' span").wrap("<a>").css("cursor", "pointer");
            $("#tree_wrapper_'.$id.' ul:first").menu({
                select: function(event, ui)
                {
                    var value = $(ui.item).attr("value");

                    if($(ui.item).find("div:first").hasClass("ui-state-disabled"))
                        value = $("#'.$id.'").val();

                    $("#'.$id.'").val(value);
                    tree_wrapper_'.md5($id).'_path(value);
                    '.$on_change.'
                }
            });
            $("#tree_wrapper_'.$id.' ul:first").css(
                {
                    "position": "absolute",
                    "z-index": 1000,
                    "margin": 1,
                    "width": $("#tree_path_'.$id.'").width()
                }
            ).addClass("navi-ui-widget-shadow").hide();
            $("#tree_wrapper_'.$id.'").find(".ui-menu-icon").css("float", "right");
            $("#tree_path_'.$id.'").on("click", function() {
                setTimeout(function()
                {
                    $("#tree_wrapper_'.$id.' ul:first").fadeIn("fast");
                }, 50);
            });

            function tree_wrapper_'.md5($id).'_reset()
            {
                $("#tree_path_'.$id.'").val("");
                $("#'.$id.'").val(0);
            }

            function tree_wrapper_'.md5($id).'_path(category)
            {
                var path = [];
                var first = $("#tree_wrapper_'.$id.'").find("li[value="+category+"]");

                path.push($(first).find("a:first").text());

                $(first).parentsUntil("div").each(function(i, el)
                {
                    if($(el).is("li"))
                        path.push($(el).find("a:first").text());
                })

                path = path.filter(function(e){return e});
                path = path.reverse();
                path = path.join(" › "); // ╱ ▶

                $("#tree_path_'.$id.'").val(path);

                return path;
            }

            tree_wrapper_'.md5($id).'_path('.$selected_value.');
        ');

        return implode("\n", $out);
    }

    function multiselect($id, $values, $texts, $selected_values=array(), $onChange="", $titles=array(), $style=" height: 216px; width: 742px;")
    {
        global $layout;

        $out = array();

        $out[] = '<select name="'.$id.'[]" id="'.$id.'" multiple="multiple" style=" '.$style.' " >';

        for($i=0; $i < count($values); $i++)
        {
            if( (is_array($selected_values) && in_array($values[$i], $selected_values)) ||
                ($values[$i]==$selected_values))
                $out[] = '<option value="'.$values[$i].'" selected="selected"  title="'.$titles[$i].'">'.$texts[$i].'</option>';
            else
                $out[] = '<option value="'.$values[$i].'"  title="'.$titles[$i].'">'.$texts[$i].'</option>';
        }

        $out[] = '</select>';

        $layout->add_script('
             $.uix.multiselect.i18n["navigatecms"] = {
                itemsSelected: "'.t(510, 'Selected items').': {count}",            // 0, 1
                itemsSelected_plural: "'.t(510, 'Selected items').': {count}",    // n
                //itemsSelected_plural_two: ...                      // 2
                //itemsSelected_plural_few: ...                      // 3, 4
                itemsAvailable: "'.t(511, 'Available items').': {count}",
                itemsAvailable_plural: "'.t(511, 'Available items').': {count}",
                //itemsAvailable_plural_two: ...
                //itemsAvailable_plural_few: ...
                itemsFiltered: "{count}",
                itemsFiltered_plural: "{count}",
                //itemsFiltered_plural_two: ...
                //itemsFiltered_plural_few: ...
                selectAll: "'.t(481, 'Select all').'",
                deselectAll: "'.t(507, 'Deselect all').'",
                search: "'.t(41, "Search").'",
                collapseGroup: "'.t(508, "Collapse").'",
                expandGroup: "'.t(509, "Expand").'",
                selectAllGroup: "'.t(481, 'Select all').'",
                deselectAllGroup: "'.t(507, 'Deselect all').'"
            };
            
            $("#'.$id.'").multiselect({
                "locale": "navigatecms",
                splitRatio: 0.55,
                sortable: true,
                moveEffect: "fade",
                multiselectChange: function(evt, iu)
                {
                    '.(!empty($onChange)? $onChange.'(evt, ui)' : '').'
                }
            });
        ');

        return implode("\n", $out);
    }

    function countryfield($name, $value="")
    {
        $countries = property::countries();
        $country_names = array_values($countries);
        $country_codes = array_keys($countries);
        // include "country not defined" item
        array_unshift($country_codes, '');
        array_unshift($country_names, '('.t(307, "Unspecified").')');

        $field = $this->selectfield($name, $country_codes, $country_names, strtoupper($value));
        return $field;
    }

    function countryregionfield($name, $value="", $country_field="")
    {
        global $layout;

        $regions = property::countries_regions();

        $out[] = '<select name="'.$name.'" id="'.$name.'" data-country-field="'.$country_field.'">';
        $out[] = '<option data-country="" value="">('.t(307, "Unspecified").')</option>';
        for($r = 0; $r < count($regions); $r++)
        {
            if($regions[$r]->region_id == $value)
                $out[] = '<option data-country="'.$regions[$r]->country_code.'" value="'.$regions[$r]->region_id.'" selected>'.$regions[$r]->name.'</option>';
            else
                $out[] = '<option data-country="'.$regions[$r]->country_code.'" value="'.$regions[$r]->region_id.'">'.$regions[$r]->name.'</option>';
        }
        $out[] = '</select>';

        if(!empty($country_field))
        {
            $layout->add_content('
                <style>
                    #select2-'.$name.'-results .select2-results__option[aria-disabled=true]   {   display: none;  }
                </style>
            ');

            $layout->add_script('
                $("select[name='.$country_field.']").on("change", function()
                {
                    var that = this;
                    if($("select[name='.$name.']").hasClass("select2-hidden-accessible"))
                        $("select[name='.$name.']").select2("destroy");
                    
                    // if the country has changed, remove any selected region
                    if($("select[name='.$name.']").find("option:selected").data("country")!=$(that).val())    
                        $("select[name='.$name.']").find("option:selected").removeAttr("selected");
                    
                    $("select[name='.$name.']").find("option").not(":first")
                        .hide()
                        .attr("disabled", "disabled");
                        
                    if($(that).find("option:selected").val()!="")
                    {
                        $("select[name='.$name.']")
                            .find("option[data-country="+$(that).find("option:selected").val()+"]")
                                .show()
                                .removeAttr("disabled");
                    }
                    
                    if(!$("select[name='.$name.']").hasClass("select2-hidden-accessible"))
                        $("select[name='.$name.']").select2();
                });
                                
                $("#'.$country_field.'").trigger("change");
            ');
        }

        $out = implode("\n", $out);
        return $out;
    }
}

?>