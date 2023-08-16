<?php
require_once(NAVIGATE_PATH.'/lib/packages/shipping_methods/shipping_method.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/products/product.class.php');

function run()
{
    global $DB;
    global $website;
    global $layout;

	$out = '';
	$object = new shipping_method();
			
	switch($_REQUEST['act'])
	{
        case 'json':
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
					$ids = $_REQUEST['ids'];
					foreach($ids as $id)
					{
						$object->load($id);
						$object->delete();
					}
					echo json_encode(true);
					break;

                case 'load_regions':
                    $country_code = $_REQUEST['country'];
                    $shipping_method_id = @$_REQUEST['id'];
                    $shipping_method = new shipping_method();

                    if(!empty($shipping_method_id))
                        $shipping_method->load($shipping_method_id);

                    $country_id = $DB->query_single(
                        '`numeric`',
                        'nv_countries',
                        'country_code = :ccode',
                        null,
                        array(
                            ':ccode' => $country_code
                        )
                    );

                    $DB->query('
                        SELECT `numeric`, name 
                        FROM nv_countries_regions 
                        WHERE country = '.$country_id.' 
                        ORDER BY name ASC
                    ');

                    $data = $DB->result();

                    echo json_encode($data);
                    break;
					
				default: // list or search	
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$orderby= $_REQUEST['sidx'].' '.$_REQUEST['sord'];
					$where = " sm.website = ".intval($website->id)." ";

                    $permissions = array(
                        0 => '<img src="img/icons/silk/world.png" align="absmiddle" /> '.t(69, 'Published'),
                        1 => '<img src="img/icons/silk/world_dawn.png" align="absmiddle" /> '.t(70, 'Private'),
                        2 => '<img src="img/icons/silk/world_night.png" align="absmiddle" /> '.t(81, 'Hidden')
                    );

					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
							$where .= $object->quicksearch($_REQUEST['quicksearch']);
						else if(isset($_REQUEST['filters']))
							$where .= navitable::jqgridsearch($_REQUEST['filters']);
						else	// single search
							$where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
					}

                    $sql = ' SELECT SQL_CALC_FOUND_ROWS
					                sm.id, sm.codename, sm.image, sm.permission, d.text as title                                    
							   FROM nv_shipping_methods sm
						  LEFT JOIN nv_webdictionary d
						  		 	 ON sm.id = d.node_id
								 	AND d.node_type = "shipping_method"
									AND d.subtype = "title"
									AND d.lang = "'.$website->languages_list[0].'"
									AND d.website = '.$website->id.'
							  WHERE '.$where.'						   
						   ORDER BY '.$orderby.' 
							  LIMIT '.$max.'
							 OFFSET '.$offset;

                    if(!$DB->query($sql, 'array'))
                        throw new Exception($DB->get_last_error());
									
					$dataset = $DB->result();
					$total = $DB->foundRows();

                    $dataset = grid_notes::summary($dataset, 'shipping_method', 'id');

					$out = array();					
											
					for($i=0; $i < count($dataset); $i++)
					{
					    $shipping_method_image = $dataset[$i]['image'];
                        if(!empty($shipping_method_image))
                            $shipping_method_image = '<img src="'.file::file_url($shipping_method_image, 'inline').'&width=64&height=48&border=true" />';
                        else
                            $shipping_method_image = '-';

						$out[$i] = array(
							0	=> $dataset[$i]['id'],
                            1	=> $dataset[$i]['codename'],
                            2	=> $shipping_method_image,
                            3   => $dataset[$i]['title'],
                            4   => $permissions[$dataset[$i]['permission']],
                            5 	=> $dataset[$i]['_grid_notes_html']
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);					
					break;
			}
			
			session_write_close();
			exit;
			break;

        case 'create':
		case 'edit':
			if(!empty($_REQUEST['id']))
				$object->load(intval($_REQUEST['id']));

			if(isset($_REQUEST['form-sent']))
			{
				$object->load_from_post();
				try
				{
					$object->save();
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = shipping_methods_form($object);
			break;
					
		case 'delete':
			if(!empty($_REQUEST['id']))
			{
				$object->load(intval($_REQUEST['id']));	
				if($object->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = shipping_methods_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = shipping_methods_form($object);
				}
			}
			break;
					
		case 'list':
		default:			
			$out = shipping_methods_list();
			break;
	}
	
	return $out;
}

function shipping_methods_list()
{
	$navibars = new navibars();
	$navitable = new navitable("shipping_methods_list");
	
	$navibars->title(t(28, 'Shipping'));

	$navibars->add_actions(
	    array(
	        '<a href="?fid=shipping_methods&act=create"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
			'<a href="?fid=shipping_methods&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=shipping_methods&act=json&_search=true&quicksearch=".$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid=shipping_methods&act=json');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid=shipping_methods&act=edit&id=');
    $navitable->setGridNotesObjectName("shipping_method");

    $navitable->addCol("ID", 'id', "40", "true", "left");
    $navitable->addCol(t(237, 'Code'), 'codename', "64", "true", "left");
    $navitable->addCol(t(157, 'Image'), 'image', "64", "false", "center");
    $navitable->addCol(t(67, 'Title'), 'title', "320", "true", "left");
    $navitable->addCol(t(68, 'Status'), 'permission', "80", "true", "center");
    $navitable->addCol(t(168, 'Notes'), 'note', "50", "false", "center");

	$navibars->add_content($navitable->generate());

	return $navibars->generate();
}

function shipping_methods_form($object)
{
    global $DB;
    global $layout;
    global $events;
    global $user;
    global $website;
	global $current_version;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
    $layout->navigate_media_browser();
	
	if(empty($object->id))
		$navibars->title(t(28, 'Shipping').' / '.t(38, 'Create'));
	else
		$navibars->title(t(28, 'Shipping').' / '.t(170, 'Edit').' ['.$object->id.']');

    $navibars->add_actions(
        array(
            '<a href="#" onclick="javascript: navigate_media_browser();" title="Ctrl+M">
				<img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'
			</a>'
        )
    );

    if(empty($object->id))
    {
        $navibars->add_actions(
            array(
                ($user->permission('shipping_methods.create')=='true'?
                    '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+S" data-action="save">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'
				</a>' : "")
            )
        );
    }
    else
    {
        $navibars->add_actions(
            array(
                ($user->permission('shipping_methods.edit')=='true'?
                    '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+S" data-action="save">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'
				</a>' : ""),
                ($user->permission("shipping_methods.delete") == 'true'?
                    '<a href="#" onclick="navigate_delete_dialog();">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'
				</a>' : "")
            )
        );

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=shipping_methods&act=delete&id='.$object->id.'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}

    if(!empty($object->id))
    {
        $notes = grid_notes::comments('shipping_method', $object->id);
        $navibars->add_actions(
            array(
                '<a href="#" onclick="javascript: navigate_display_notes_dialog();">
					<span class="navigate_grid_notes_span" style=" width: 20px; line-height: 16px; ">'.count($notes).'</span>
					<img src="img/skins/badge.png" width="20px" height="18px" style="margin-top: -2px;" class="grid_note_edit" align="absmiddle" /> '.t(168, 'Notes').'
				</a>'
            )
        );
    }


	$extra_actions = array();
    if(!empty($object->id))
    {
        // we attach an event to which will be fired by navibars to put an extra button
        $events->add_actions(
            'shipping_method',
            array(
                'item' => &$object,
                'navibars' => &$navibars
            ),
            $extra_actions
        );
    }

    if(!empty($object->id))
        $layout->navigate_notes_dialog('shipping_method', $object->id);
	
	$navibars->add_actions(
	    array(
	        (!empty($object->id)? '<a href="?fid=shipping_methods&act=create"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=shipping_methods&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );

	$navibars->form();

	$navibars->add_tab(t(43, "Main"), "", 'fa fa-database');
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $object->id));	
	
	$navibars->add_tab_content_row(
	    array(
	        '<label>ID</label>',
			'<span>'.(!empty($object->id)? $object->id : t(52, '(new)')).'</span>'
        )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(237, 'Code').'</label>',
			$naviforms->textfield('codename', $object->codename)
        )
    );
										
	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(157, 'Image').'</label>',
			$naviforms->dropbox('image', $object->image, 'image')
        )
    );

    $permission_options = array(
        0 => t(69, 'Published'),
        1 => t(70, 'Private'),
        2 => t(81, 'Hidden')
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(68, 'Status').'</label>',
            $naviforms->selectfield(
                'permission',
                array_keys($permission_options),
                array_values($permission_options),
                $object->permission,
                '',
                false,
                array(
                    0 => t(360, 'Visible to everybody'),
                    1 => t(359, 'Visible only to Navigate CMS users'),
                    2 => t(358, 'Hidden to everybody')
                )
            )
        )
    );

    $ws_languages = $website->languages();
    $default_language = array_keys($ws_languages);
    $default_language = $default_language[0];

    $navibars->add_tab('<i class="fa fa-pencil"></i> '.t(9, "Content"));

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(63, 'Languages').'</label>',
            $naviforms->buttonset('language_selector', $ws_languages, $website->languages_list[0], "navigate_shipping_methods_select_language(this);")
        )
    );

    foreach($website->languages_list as $lang)
    {
        $navibars->add_tab_content('<div class="language_fields" id="language_fields_'.$lang.'" style=" display: none; ">');

        $navibars->add_tab_content_row(
            array(
                '<label>'.t(67, 'Title').'</label>',
                $naviforms->textfield('title-'.$lang, @$object->dictionary[$lang]['title'])
            )
        );

        $navibars->add_tab_content_row(
            array(
                '<label>'.t(334, "Description").'</label>',
                $naviforms->editorfield('description-'.$lang, @$object->dictionary[$lang]['description'], NULL, $lang),
                '<br />'
            ),
            '',
            'lang="'.$lang.'"'
        );

        $navibars->add_tab_content('</div>');
    }

    $navibars->add_tab(t(27, "Rates"), "", 'fa fa-truck');

    $countries = property::countries();
    $country_codes = array_keys($countries);
    $country_names = array_values($countries);
    // include "country not defined" item
    array_unshift($country_codes, '');
    array_unshift($country_names, '('.t(443, "All").')');

    $table = new naviorderedtable("shipping_methods_rates_table");
    //$table->setWidth("600px");
    $table->setHiddenInput("shipping_methods-order");

    $table->addHeaderColumn(t(224, 'Country'), 160);
    $table->addHeaderColumn(t(684, 'Regions'), 240);
    $table->addHeaderColumn(t(670, 'Weight').'<br />('.t(686, 'min').' - '.t(687, 'max').')', 100);
    $table->addHeaderColumn(t(685, 'Subtotal').'<br />('.t(686, 'min').' - '.t(687, 'max').')', 100);
    $table->addHeaderColumn(t(678, 'Cost'), 60);
    $table->addHeaderColumn(t(677, 'Tax'), 50);
    $table->addHeaderColumn(t(65, 'Enabled'), 60);
    $table->addHeaderColumn(t(35, 'Remove'), 60);

    if(empty($object->rates))
        $object->rates = array();

    $rates_order = array();

    $tax_classes = product::tax_classes();
    $currencies = product::currencies();

    foreach($object->rates as $rate)
    {
        $rates_order[] = $rate->id;
        $rate_country_name = @$country_names[array_search($rate->country, $country_codes)];
        $rate_regions = [];
        if(!empty($rate->regions))
        {
            foreach ($rate->regions as $rr)
            {
                $rate_regions[] = $DB->query_single(
                    'name',
                    'nv_countries_regions',
                    '`numeric` = ' . intval($rr)
                );
            }
        }

        $rate_weight =  ($rate->weight->min==0? '&infin;' : core_decimal2string($rate->weight->min)).
                        ' - '.
                        ($rate->weight->max==0? '&infin;' : core_decimal2string($rate->weight->max)).
                        ' '.
                        $rate->weight->unit;

        $rate_subtotal = ($rate->subtotal->min==0? '&infin;' : core_decimal2string($rate->subtotal->min)).
                         ' - '.
                         ($rate->subtotal->max==0? '&infin;' : core_decimal2string($rate->subtotal->max)).
                         ' '.
                        $currencies[$rate->subtotal->currency];

        $rate_cost = core_decimal2string($rate->cost->value).' '.$currencies[$rate->cost->currency];
        $rate_tax = ($rate->tax->class=="custom")? core_decimal2string($rate->tax->value).' %' : $tax_classes[$rate->tax->class];

        $rate_details = base64_encode(json_encode($rate));

        $table->addRow(
            $rate->id,
            array(
                array('content' => $rate_country_name, 'align' => 'left', 'style' => 'vertical-align: top;'),
                array('content' => implode(", ", $rate_regions), 'align' => 'left', 'style' => 'vertical-align: top;'),
                array('content' => $rate_weight, 'align' => 'left', 'style' => 'vertical-align: top;'),
                array('content' => $rate_subtotal, 'align' => 'left', 'style' => 'vertical-align: top;'),
                array('content' => $rate_cost, 'align' => 'center', 'style' => 'vertical-align: top;'),
                array('content' => $rate_tax, 'align' => 'center', 'style' => 'vertical-align: top;'),
                array('content' => ($rate->enabled=='1'? '<i class="fa fa-fw fa-lg fa-check"></i>' : '<i class="fa fa-fw fa-lg fa-eye-slash"></i>'), 'align' => 'center', 'style' => 'vertical-align: top;'),
                array('content' => '<i class="fa fa-fw fa-lg fa-trash" data-action="remove"></i>
                                    <input type="hidden" name="rate_details['.$rate->id.']" value="'.$rate_details.'" />', 'align' => 'center', 'style' => 'vertical-align: top;')
            )
        );
    }

    $navibars->add_tab_content($naviforms->hidden('shipping_methods-order', implode('#', $rates_order)));
    $navibars->add_tab_content($naviforms->hidden('shipping_methods-rates', base64_encode(json_encode($object->rates))));

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(27, 'Rates').'</label>',
            '<div>'.$table->generate().'</div>',
            '<div class="subcomment">
                <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'.
                '.t(192, "Double click on a row to edit it").'.
            </div>'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;</label>',
            '<button id="shipping_methods_rates_table-add"><img src="img/icons/silk/add.png" align="absmiddle" style="cursor:pointer;" /> '.t(472, 'Add').'</button>'
        )
    );

    $navibars->add_tab_content('
        <div id="shipping_methods_rates_edit_dialog" style="display: none;">
            <div class="navigate-form-row">
                <label>'.t(224, "Country").'</label>
                '.$naviforms->selectfield('shipping_methods_dialog-country', $country_codes, $country_names, "", "nv_shipping_methods_dialog_regions_how_change();").'                            
            </div>
            <div class="navigate-form-row">
                <label>'.t(684, "Regions").'</label>
                '.$naviforms->selectfield(
                    'shipping_methods_dialog-regions-how',
                    array(0 => 'all', 1 => 'selection'),
                    array(0 => t(443, "All"), 1 => t(405, "Selection")),
                    'all',
                    "nv_shipping_methods_dialog_regions_how_change();").'                
            </div>
            <div class="navigate-form-row" id="shipping_methods_dialog-regions-selection" style="display: none;">
                <label><!--'.t(684, "Regions").'--></label>
                '.$naviforms->multiselect('shipping_methods_dialog-regions', array(), array(), array(), NULL, NULL, "height: 216px; width: 620px; ").'                
            </div>
            <div class="navigate-form-row">
                <label>'.t(670, "Weight").'</label>'.
                $naviforms->decimalfield('shipping_methods_dialog-weight_min', "",2, $user->decimal_separator, $user->thousands_separator, '', '', '60px').' - '.
                $naviforms->decimalfield('shipping_methods_dialog-weight_max', "", 2, $user->decimal_separator, $user->thousands_separator, '', '', '60px').'&nbsp;&nbsp;'.
                $naviforms->selectfield('shipping_methods_dialog-weight_unit', product::weight_units(), product::weight_units(), $website->weight_unit, "", false, array(), "width: 80px;", true, false, "select2-align-with-input").'
                <div class="subcomment">'.t(686, 'min').' - '.t(687, 'max').'</div>
            </div>
            <div class="navigate-form-row">
                <label>'.t(685, "Subtotal").'</label>'.
                $naviforms->decimalfield('shipping_methods_dialog-subtotal_min', "",2, $user->decimal_separator, $user->thousands_separator, '', '', '60px').' - '.
                $naviforms->decimalfield('shipping_methods_dialog-subtotal_max', "", 2, $user->decimal_separator, $user->thousands_separator, '', '', '60px').'&nbsp;&nbsp;'.
                $naviforms->selectfield('shipping_methods_dialog-subtotal_currency', array_keys(product::currencies()), array_values(product::currencies()), $website->currency, "", false, array(), "width: 80px;", true, false, "select2-align-with-input").'
                <div class="subcomment">'.t(686, 'min').' - '.t(687, 'max').' (0 =&gt; &infin;)</div>                                                            
            </div>
            <div class="navigate-form-row">
                <label>'.t(678, "Cost").'</label>
                '.$naviforms->decimalfield('shipping_methods_dialog-cost', "", 2, $user->decimal_separator, $user->thousands_separator, '', '', '60px').'                            
                '.$naviforms->selectfield('shipping_methods_dialog-cost_currency', array_keys(product::currencies()), array_values(product::currencies()), $website->currency, "", false, array(), "width: 80px;", true, false, "select2-align-with-input").'                            
            </div>
            <div class="navigate-form-row">
                <label>'.t(677, "Tax").'</label>
                '.$naviforms->selectfield('shipping_methods_dialog-tax_class', array_keys(product::tax_classes()), array_values(product::tax_classes()), "", "", false, array(), "width: 80px;", true, false, "select2-align-with-input").'                            
                '.$naviforms->decimalfield('shipping_methods_dialog-tax_value', "", 2, $user->decimal_separator, $user->thousands_separator, '', '%', '60px').'                            
            </div>
            <div class="navigate-form-row">
                <label>'.t(65, "Enabled").'</label>
                '.$naviforms->checkbox("shipping_methods_dialog-enabled", false).'
            </div>
        </div>
    ');

    $layout->add_script('
        var navigate_shipping_methods_object_id = '.($object->id + 0).';
        var navigate_shipping_methods_rates = "";
        var navigate_shipping_methods_country_selected = "";
    
        $("#shipping_methods_rates_table").on("dblclick", "tr", function(e)
        {
            e.preventDefault();
            e.stopPropagation();
            
            navigate_shipping_methods_rates_table_edit_dialog($(this).attr("id"));
            
            return false;
        });
    
        $("#shipping_methods_rates_table-add").on("click", function()
        {
            // create a row via dialog
            navigate_shipping_methods_rates_table_edit_dialog();
            return false;
        });                                      
    ');

    // script will be bound to onload event at the end of this php function (after getScript is done)
    $onload_language = $_REQUEST['tab_language'];
    if(empty($onload_language))
        $onload_language = $website->languages_list[0];

    $layout->add_script('        
        $(document).on("keydown.ctrl_s", function (evt) { navigate_tabform_submit(1); return false; } );
        $(document).on("keydown.ctrl_m", function (evt) { navigate_media_browser(); return false; } );
    ');

    $layout->add_script('
        $.ajax({ 
            type: "GET",
	        dataType: "script",
	        url: "lib/packages/shipping_methods/shipping_methods.js?r='.$current_version->revision.'",
	        cache: true,
	        complete: function()
	        {
                if(typeof navigate_shipping_methods_onload == "function")
				    navigate_shipping_methods_onload("'.$onload_language.'");
	        }
	    });
    ');

	return $navibars->generate();
}
?>