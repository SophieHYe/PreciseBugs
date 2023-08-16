<?php
require_once(NAVIGATE_PATH.'/lib/packages/themes/theme.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/templates/template.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new template();
			
	switch($_REQUEST['act'])
	{
        case 'json':
		case 1:	// json data retrieval & operations
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
					$ids = $_REQUEST['ids'];
					foreach($ids as $id)
					{
						$item->load($id);
						$item->delete();
					}
					echo json_encode(true);
					break;
					
				default: // list or search

                    // we have to merge the theme templates with the custom private templates (which are defined in the DB)
                    // as we don't expect a lot of templates, we will always return the whole dataset
                    // for this reason, paginate is useless

                    // filter orderby vars
                    if( !in_array($_REQUEST['sord'], array('', 'desc', 'DESC', 'asc', 'ASC')) ||
                        !in_array($_REQUEST['sidx'], array('id', 'title', 'enabled', 'permission'))
                    )
                    {
                        return false;
                    }
                    $orderby = $_REQUEST['sidx'].' '.$_REQUEST['sord'];

					if(isset($_REQUEST['quicksearch']))
                    {
                        $dataset = template::search($orderby, array('quicksearch' => $_REQUEST['quicksearch']));
                    }
	                else
                    {
                        $dataset = template::search($orderby);
                    }

					$total = count($dataset);
					
					$out = array();		
					$permissions = array(	
							0 => '<img src="img/icons/silk/world.png" align="absmiddle" /> '.t(69, 'Published'),
							1 => '<img src="img/icons/silk/world_dawn.png" align="absmiddle" /> '.t(70, 'Private'),
							2 => '<img src="img/icons/silk/world_night.png" align="absmiddle" /> '.t(81, 'Hidden')
						);		
					
					if(empty($dataset)) $rows = 0;
					else				$rows = count($dataset);
	
					for($i=0; $i < $rows; $i++)
					{
						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1 	=> $dataset[$i]['title'],
							2 	=> $dataset[$i]['theme'],
							3	=> $permissions[$dataset[$i]['permission']],
							4	=> (($dataset[$i]['enabled']==1)? '<img src="img/icons/silk/accept.png" />' : '<img src="img/icons/silk/cancel.png" />')
						);
					}

					navitable::jqgridJson($out, 1, 0, PHP_INT_MAX, $total);
					break;
			}
			
			core_terminate();
			break;
				
		case 'load':
		case 2: // edit/new form		
			if(!empty($_REQUEST['id']))
			{
                if(is_numeric($_REQUEST['id']))
                {
                    $item->load(intval($_REQUEST['id']));
                }
                else
                {
                    $item->load_from_theme($_REQUEST['id']);
                }
			}
		
			if(isset($_REQUEST['form-sent']))
			{
				$item->load_from_post();
				try
				{
					$item->save();
					if(!empty($_REQUEST['property-enabled']))
						$enableds = array_values($_REQUEST['property-enabled']);
					else
						$enableds = array();
					property::reorder("template", $item->id, $_REQUEST['template-properties-order'], $enableds);
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
				users_log::action($_REQUEST['fid'], $item->id, 'save', $item->title, json_encode($_REQUEST));				
			}
			else
            {
                users_log::action($_REQUEST['fid'], $item->id, 'load', $item->title);
            }
		
			$out = templates_form($item);
			break;
		
		case 'save_template_file':	// save template html
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
			}
			
			$data = $_REQUEST['templates-file-edit-area'];
			
			$data = str_replace("\r\n", "\r", $data);
			
			$x = file_put_contents(NAVIGATE_PRIVATE.'/'.$website->id.'/templates/'.$item->file, $data);
						
			echo json_encode(($x > 0));
			
			session_write_close();
			exit;
		
			break;	
			
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = templates_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = webdictionary_list();
				}
				
				users_log::action($_REQUEST['fid'], $item->id, $item->title, 'remove');
			}
			break;
			
		case 'template_property_load':
		
			$property = new property();
			
			if(!empty($_REQUEST['id']))
            {
                if(is_numeric($_REQUEST['id']))
				    $property->load(intval($_REQUEST['id']));
                else
                    $property->load_from_theme($_REQUEST['id'], null, 'template', $_REQUEST['template']);
            }

			header('Content-type: text/json');

			$types = property::types();
			$property->type_text = $types[$property->type];

			echo json_encode($property);
						
			session_write_close();
			exit;
			break;			
					
		case 'template_property_save': // save property details
		
			$property = new property();
			
			if(!empty($_REQUEST['property-id']))
				$property->load(intval($_REQUEST['property-id']));

			$property->load_from_post();
			$property->save();
			
			header('Content-type: text/json');

			$types = property::types();
			$property->type_text = $types[$property->type];

			echo json_encode($property);			
			
			session_write_close();
			exit;
			break;	

		case 'template_property_remove': // remove property
		
			$property = new property();
			
			if(!empty($_REQUEST['property-id']))
				$property->load(intval($_REQUEST['property-id']));

			$property->delete();
			
			session_write_close();
			exit;
			break;	

					
		case 0: // list / search result
		default:			
			$out = templates_list();
			break;
	}
	
	return $out;
}

function templates_list()
{
	$navibars = new navibars();
	$navitable = new navitable("templates_list");
	
	$navibars->title(t(20, 'Templates'));

	$navibars->add_actions(
	    array(
	        '<a href="?fid='.$_REQUEST['fid'].'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
			'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );
	
	if($_REQUEST['quicksearch']=='true')
    {
        $navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=json&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);
    }
	
	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=json');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=load&id=');
    $navitable->disableSelect();
	
	$navitable->addCol("ID", 'id', "60", "true", "left");	
	$navitable->addCol(t(67, 'Title'), 'title', "260", "true", "left");		
	$navitable->addCol(t(368, 'Theme'), 'theme', "180", "false", "left");	
	$navitable->addCol(t(68, 'Status'), 'permission', "80", "true", "center");
	$navitable->addCol(t(65, 'Enabled'), 'enabled', "60", "true", "center");		
	
	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
}

function templates_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	if(empty($item->id))
		$navibars->title(t(20, 'Templates').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(20, 'Templates').' / '.t(170, 'Edit').' ['.$item->id.']');		

    $readonly = false;
    if(!empty($item->id) && !is_numeric($item->id))
    {
        $layout->navigate_notification(t(432, "Read only mode"), false, true);
        $readonly = true;
    }
	else if(empty($item->id))
	{
		$navibars->add_actions(		array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
									);
	}
	else
	{
		$navibars->add_actions(		array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
											'<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>'
										)
									);		
		
		$delete_html = array();
		$delete_html[] = '<div id="navigate-delete-dialog" class="hidden">'.t(57, 'Do you really want to delete this item?').'</div>';
		$delete_html[] = '<script language="javascript" type="text/javascript">';
		$delete_html[] = 'function navigate_delete_dialog()';		
		$delete_html[] = '{';
        $delete_html[] = '$("#navigate-delete-dialog").removeClass("hidden");';
		$delete_html[] = '$("#navigate-delete-dialog").dialog({
							resizable: true,
							height: 150,
							width: 300,
							modal: true,
							title: "'.t(59, 'Confirmation').'",
							buttons: {
								"'.t(58, 'Cancel').'": function() {
									$(this).dialog("close");
								},
								"'.t(35, 'Delete').'": function() {
									$(this).dialog("close");
									window.location.href = "?fid='.$_REQUEST['fid'].'&act=4&id='.$item->id.'";
								}
							}
						});';		
		$delete_html[] = '}';							
		$delete_html[] = '</script>';						
									
		$navibars->add_content(implode("\n", $delete_html));
	}
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid=templates&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid=templates&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));

	$navibars->add_tab_content_row(array(	'<label>'.t(67, 'Title').'</label>',
											$naviforms->textfield('title', $item->title),
										));		

    if($readonly)
    {
        $navibars->add_tab_content_row(array(	'<label>'.t(82, 'File').'</label>',
                                                        '<span>'.$item->file.'</span>'
                                                    ));
    }
    else
    {
        $navibars->add_tab_content_row(
            array(
                '<label>'.t(82, 'File').'</label>',
                '<span>'.NAVIGATE_PRIVATE.'/'.$website->id.'/templates/</span>',
                $naviforms->textfield('file', $item->file, '236px'),
                empty($item->file)? '' : '<a href="#" onclick="navigate_templates_editor();"><img src="'.NAVIGATE_URL.'/img/icons/silk/pencil.png" /></a>'
            )
        );
    }
	
	$navibars->add_content('
		<div id="templates-file-edit-dialog" style=" display: none; ">
			<textarea name="templates-file-edit-area" id="templates-file-edit-area" style=" width: 99%; height: 98%; ">'.htmlentities(@file_get_contents(NAVIGATE_PRIVATE.'/'.$website->id.'/templates/'.$item->file), ENT_COMPAT, 'UTF-8').'</textarea>
		</div>
	');
							
	$layout->add_script('
		var current_template_editor = null;
		function navigate_templates_editor()
		{
			var file = $("#file").val();

			$("#templates-file-edit-dialog").dialog(
			{
				title: \'<img src="'.NAVIGATE_URL.'/img/icons/silk/pencil.png" align="absmiddle" /> '.t(170, 'Edit').' \' + file,
				resizable: true,
				draggable: true,
				width: $(window).width() - 60,
				height: $(window).height() - 50,
				position: { my: "center", at: "center", of: window },
				modal: true,
				open: function()
				{
                    current_template_editor = CodeMirror.fromTextArea(
                        $("#templates-file-edit-area")[0],
                        {
                            mode: "text/html",
                            tabMode: "indent",
                            lineNumbers: true,
                            matchBrackets: true
                        }
                    );

					$(".CodeMirror").css({ width: "99%", height: "98%"});
					$(".CodeMirror-scroll").css({ width: "100%", height: "100%"});
				},
				beforeClose: function() {
				    // dialog may be closed by clicking on a footer button or clicking the close "x" icon on top
				    if(current_template_editor)
				    {
				        current_template_editor.toTextArea();
					    current_template_editor = null;
                    }
				},
				buttons: {
					"'.t(58, 'Cancel').'": function() {
						$("#templates-file-edit-dialog").dialog("close");
					},
					"'.t(34, 'Save').'": function()
					{ 					
						current_template_editor.toTextArea();					
						current_template_editor = null;
						
						$.ajax({
						   type: "POST",
						   async: false,
						   dateType: "text",
						   url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&id='.$item->id.'&act=save_template_file",
						   data: $("#templates-file-edit-area").serialize(),
						   success: function(data)
						   {			
							   	if(data=="true")
								{						
									$("#templates-file-edit-dialog").dialog("close"); 
									navigate_notification("'.t(235, 'File saved successfully.').'");
								}
								else
								{
									$("#templates-file-edit-dialog").dialog("close"); 
									navigate_notification("'.t(56, 'Unexpected error.').'");
								}
						   }
						 });
					}					
				}				
			}).dialogExtend(
			{
				maximizable: true
			});
		}
	');
																				
	$navibars->add_tab_content_row(array(	'<label>'.t(68, 'Status').'</label>',
											$naviforms->selectfield('permission', 
												array(
														0 => 0,
														1 => 1,
														2 => 2
													),
												array(
														0 => t(69, 'Published'),
														1 => t(70, 'Private'),
														2 => t(81, 'Hidden')
													),
												$item->permission,
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
									
	$navibars->add_tab_content_row(array(	'<label>'.t(62, 'Statistics').'</label>',
											$naviforms->checkbox('statistics', $item->statistics),
										));																									
													
										
	$navibars->add_tab_content_row(array(	'<label>'.t(65, 'Enabled').'</label>',
											$naviforms->checkbox('enabled', $item->enabled),
										));	

	if(!empty($item->id))
	{											
		$navibars->add_tab(t(236, "Sections"));																
	
		$table = new naviorderedtable("template_sections_table");
		$table->setWidth("600px");
		$table->setHiddenInput("template-sections-order");

		$navibars->add_tab_content( $naviforms->hidden('template-sections-order', "") );			

		$table->addHeaderColumn(t(237, 'Code'), 100);			
		$table->addHeaderColumn(t(159, 'Name'), 250);
		$table->addHeaderColumn(t(267, 'Editor'), 100);
		$table->addHeaderColumn(t(155, 'Width'), 100);				
		$table->addHeaderColumn(t(35, 'Remove'), 100);		

		for($p=0; $p < count($item->sections); $p++)
		{
			$disabled = ($item->sections[$p]['code']=='main');
			unset($selected);
			$selected = array();
			$selected[$item->sections[$p]['editor']] = ' selected="selected" ';
			
			$select_editor = '<select name="template-sections-editor[]" style=" width: 125px; ">';
			$select_editor.= '	<option value="tinymce" '.$selected['tinymce'].'>TinyMCE</option>';
			$select_editor.= '	<option value="html" '.$selected['html'].'>'.t(269, 'HTML code').'</option>';
			$select_editor.= '	<option value="raw" '.$selected['raw'].'>'.t(268, 'Raw').'</option>';			
			$select_editor.= '</select>';
			
			$table->addRow($p, array(
					array('content' => '<input type="text" name="template-sections-code[]" value="'.$item->sections[$p]['code'].'" style="width: 140px;" />', 'align' => 'left'),			
					array('content' => '<input type="text" name="template-sections-name[]" value="'.template::section_name($item->sections[$p]['name']).'" style="width: 290px;" />', 'align' => 'left'),
					array('content' => $select_editor, 'align' => 'left'),
					array('content' => '<div style=" white-space: nowrap; "><input type="text" name="template-sections-width[]" value="'.template::section_name($item->sections[$p]['width']).'" style="width: 40px;" /> px</div>', 'align' => 'left'),
					array('content' => ((!empty($disabled) || $readonly)? '' : '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" onclick="navigate_templates_sections_remove(this);" />'), 'align' => 'center')
				));			
		}

        if($readonly)
        {
            $navibars->add_tab_content_row(array(	'<label>'.t(236, 'Sections').'</label>',
                                                    '<div>'.$table->generate().'</div>'));
        }
        else
        {
            $navibars->add_tab_content_row(array(	'<label>'.t(236, 'Sections').'</label>',
                                                    '<div>'.$table->generate().'</div>',
                                                    '<div class="subcomment">
                                                        <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'.
                                                         '.t(192, 'Double click any row to edit').'
                                                    </div>' ));

            $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                                    '<button id="templates-sections-create"><img src="img/icons/silk/add.png" align="absmiddle" style="cursor:pointer;" /> '.t(38, 'Create').'</button>'));
		
        }

		$navibars->add_content('
			<form id="templates-sections-edit-dialog" style="display: none;">
				'.$naviforms->hidden('section-id', '').'
				<div class="navigate-form-row">
					<label>'.t(67, 'Title').'</label>
					'.$naviforms->textfield('section-name', '').'
				</div>
				<div class="navigate-form-row">
					<label>'.t(237, 'Code').'</label>
					'.$naviforms->textfield('section-code', '').'					
				</div>			
			</form>');
			
		
		$section_widths = templates_section_widths();
		
		$layout->add_script('
			$("#templates-sections-create").on("click", function() {
				var tr = \'<tr id="\'+(new Date().getTime())+\'">\';
				tr += \'<td><input type="text" name="template-sections-code[]" value="" style="width: 140px;" /></td>\';
				tr += \'<td><input type="text" name="template-sections-name[]" value="" style="width: 290px;" /></td>\';
				tr += \'<td><select style="width: 125px;" name="template-sections-editor[]"><option selected="selected" value="tinymce">TinyMCE</option><option value="html">'.t(269, 'HTML code').'</option><option value="raw">'.t(268, 'Raw').'</option></select></td>\';
				tr += \'<td><div style="white-space: nowrap;"><input type="text" style="width: 40px;" value="950" name="template-sections-width[]"> px</div></td>\';
				tr += \'<td align="center"><img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" onclick="navigate_templates_sections_remove(this);" style="cursor:pointer;" /></td>\';				
				tr += \'</tr>\';
				
				$("#template_sections_table").find("tbody:last").append(tr);
				$("#template_sections_table").tableDnD(
				{
					onDrop: function(table, row) 
					{		navigate_naviorderedtable_template_sections_table_reorder();		}
				});			

				$(\'input[name="template-sections-width[]"]\').autocomplete(
				{
					source: function(request, response) { response('.json_encode($section_widths).'); },
					minLength: 0			
				});				

				return false;
			});
			
			function navigate_templates_sections_remove(el)
			{				
				$(el).parent().parent().remove();
			}
			
			$(\'input[name="template-sections-width[]"]\').autocomplete(
			{
				source: function(request, response) { response('.json_encode($section_widths).'); },
				minLength: 0			
			});
			
			$(document).on(\'click\', \'input[name="template-sections-width[]"]\', function()			
			{
				$(this).autocomplete( "search" , $(this).val());
			});
		');
		
		$navibars->add_tab_content_row(array(	'<label>'.t(210, 'Gallery').'</label>',
												$naviforms->checkbox('gallery', $item->gallery),
											));	
											
		$navibars->add_tab_content_row(array(	'<label>'.t(250, 'Comments').'</label>',
												$naviforms->checkbox('comments', $item->comments),
											));	
											
		$navibars->add_tab_content_row(array(	'<label>'.t(265, 'Tags').'</label>',
												$naviforms->checkbox('tags', $item->tags),
											));												


		$navibars->add_tab(t(77, "Properties"));
	
		$table = new naviorderedtable("template_properties_table");
		$table->setWidth("550px");
		$table->setHiddenInput("template-properties-order");
		$table->setDblclickCallback("navigate_templates_edit_property");
		
		$navibars->add_tab_content( $naviforms->hidden('template-properties-order', "") );			
		
		$table->addHeaderColumn(t(159, 'Name'), 250, true);
		$table->addHeaderColumn(t(160, 'Type'), 150);
		$table->addHeaderColumn(t(87, 'Association'), 100);		
		$table->addHeaderColumn(t(65, 'Enabled'), 50);	

        $properties = property::elements($item->id);
		$types		= property::types();

        $element_types = array(	'item'	=> 	t(180, 'Item'),
								'structure' => t(16, 'Structure')/*,
								'product' => t(214, 'Product')*/);
		
		for($p=0; $p < count($properties); $p++)
		{
			$table->addRow($properties[$p]->id, array(
					array('content' => $properties[$p]->name, 'align' => 'left'),
					array('content' => $types[$properties[$p]->type], 'align' => 'left'),
					array('content' => $element_types[$properties[$p]->element], 'align' => 'left'),					
					array('content' => '<input type="checkbox" name="property-enabled[]" class="raw-checkbox" value="'.$properties[$p]->id.'" '.(($properties[$p]->enabled=='1'? ' checked=checked ' : '')).' />', 'align' => 'center'),
				));
		}

        if($readonly)
        {
            $navibars->add_tab_content_row(array(	'<label>'.t(77, 'Properties').'</label>',
                                                    '<div>'.$table->generate().'</div>'));
        }
        else
        {
            $navibars->add_tab_content_row(array(	'<label>'.t(77, 'Properties').'</label>',
                                                    '<div>'.$table->generate().'</div>',
                                                    '<div class="subcomment">
                                                        <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'.
                                                         '.t(192, 'Double click any row to edit').'
                                                    </div>' ));

            $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                                    '<button id="templates-properties-create"><img src="img/icons/silk/add.png" align="absmiddle" /> '.t(38, 'Create').'</button>'));
        }
	
		$navibars->add_content('
		<form id="templates-properties-edit-dialog" style="display: none;">
			<div class="navigate-form-row">
				<label>ID</label>
				<span id="property-id-span">'.t(52, '(new)').'</span>
				'.$naviforms->hidden('property-id', '').'
				'.$naviforms->hidden('property-template', $item->id).'			
			</div>	
			<div class="navigate-form-row">
				<label>'.t(67, 'Title').'</label>
				'.$naviforms->textfield('property-name', '').'
			</div>
			<div class="navigate-form-row">
				<label>'.t(87, 'Association').'</label>
				'.$naviforms->selectfield('property-element', 
											array_keys($element_types),
											array_values($element_types),
											'value'
										).'
			</div>			
			<div class="navigate-form-row">
				<label>'.t(160, 'Type').'</label>
				'.$naviforms->selectfield('property-type', 
											array_keys($types),
											array_values($types),
											'value',
											'navigate_templates_property_type_change()'
										).'
			</div>
			<div class="navigate-form-row">
				<label>'.t(200, 'Options').'</label>
				'.$naviforms->textarea('property-options', '').'
				<div class="subcomment">
					'.t(201, 'One line per option, formatted like this: value#title').'
				</div>				
			</div>
			<div class="navigate-form-row">
				<label>'.t(547, 'Multilanguage').'</label>
				'.$naviforms->checkbox('property-multilanguage', false).'
			</div>
			<div class="navigate-form-row">
				<label>'.t(199, 'Default value').'</label>
				'.$naviforms->textfield('property-dvalue', '').'	
				<div class="subcomment">
				    <span id="property-comment-boolean">'.t(426, 'Enter "1" for true, "0" for false').'</span>
					<span id="property-comment-option">'.t(202, 'Enter only the value').'</span>
					<span id="property-comment-moption">'.t(212, 'Enter the selected values separated by commas').': 3,5,8</span>
					<span id="property-comment-text">'.t(203, 'Same value for all languages').'</span>
					<span id="property-comment-rating">'.t(223, 'Default is 5 stars, if you want a different number: default_value#number_of_stars').' 5#10</span>
					<span id="property-comment-date">'.t(50, 'Date format').': '.date($user->date_format).'</span>										
					<span id="property-comment-color">'.t(442, 'Hexadecimal color code').': #ffffff</span>
					<span id="property-comment-country">'.t(225, 'Alpha-2 country code').' (es, us, uk...)</span>
					<span id="property-comment-file">'.t(204, 'ID of the file').'</span>
					<span id="property-comment-video">'.t(490, 'ID of the file or public video URL').'</span>
					<span id="property-comment-coordinates">'.t(298, 'Latitude').'#'.t(299, 'Longitude').': 40.689231#-74.044505</span>
				</div>
			</div>
			<div class="navigate-form-row">
				<label>'.t(65, 'Enabled').'</label>
				'.$naviforms->checkbox('property-enabled', 1).'
			</div>
            <div class="navigate-form-row">
				<label>'.t(550, 'Help text').'</label>
				'.$naviforms->textfield('property-helper', '').'
			</div>
		</form>');

		$layout->add_script('
			$("#templates-properties-create").on("click", function()
			{
				navigate_templates_edit_property();
				return false;
			});
		
			function navigate_templates_edit_property(el)
			{	
				if(!el)	// new property
				{
					$("#property-id").val("");
					$("#property-id-span").html("'.t(52, '(new)').'");
					$("#property-element").val("template");
					$("#property-template").val("'.$item->id.'");
					$("#property-name").val("");
					$("#property-type").val("value");
					$("#property-options").val("");
					$("#property-dvalue").val("");
					$("#property-helper").val("");
					$("#property-multilanguage").removeAttr("checked");
				    $("#property-enabled").attr("checked", "checked");
				}
				else
				{
					$.ajax({
					   type: "GET",
					   async: false,
					   dateType: "json",
					   url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=templates&act=template_property_load&template='.$item->id.'&id=" + $(el).attr("id"),
					   success: function(data)
					   {
						   $("#property-id-span").html(data.id);
						   $("#property-id").val(data.id);
						   $("#property-element").val(data.element);
						   $("#property-template").val(data.template);
						   $("#property-name").val(data.name);
						   $("#property-type").val(data.type);
						   $("#property-options").val(data.options);
						   $("#property-dvalue").val(data.dvalue);
						   $("#property-helper").val(data.helper);

						   if(data.multilanguage=="true")
							   $("#property-multilanguage").attr("checked", "checked");
							else
							   $("#property-multilanguage").removeAttr("checked");

						   if(data.enabled=="1")
							   $("#property-enabled").attr("checked", "checked");
							else
							   $("#property-enabled").removeAttr("checked");
							   
						   var options = "";
						   for(var o in data.options)
						   {
							   options += o + "#" + data.options[o] + "\n";
						   }
						   $("#property-options").val(options);

					   }
					 });					
				}
				
				navigate_templates_property_type_change();
				
				var navigate_templates_element_types = new Array();
				navigate_templates_element_types["item"] = "'.t(180, 'Item').'";
				navigate_templates_element_types["structure"] = "'.t(16, 'Structure').'";
				//navigate_templates_element_types["product"] = "'.t(214, 'Product').'";

				if('.($readonly? 'true' : 'false').')
				{
                    $("#templates-properties-edit-dialog").dialog(
                    {
                        title: \'<img src="img/icons/silk/pencil.png" align="absmiddle" /> '.t(170, 'Edit').'\',
                        resizable: true,
                        height: 360,
                        width: 650,
                        modal: true,
                    });
				}
				else // show dialog with action buttons
				{
                    $("#templates-properties-edit-dialog").dialog(
                    {
                        title: \'<img src="img/icons/silk/pencil.png" align="absmiddle" /> '.t(170, 'Edit').'\',
                        resizable: true,
                        height: 410,
                        width: 650,
                        modal: true,
                        buttons: {
                            "'.t(58, 'Cancel').'": function() {
                                $(this).dialog("close");
                            },
                            "'.t(35, 'Delete').'": function() {
                                $.ajax({
                                   type: "POST",
                                   async: false,
                                   dateType: "text",
                                   url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=template_property_remove",
                                   data: $("#templates-properties-edit-dialog").serialize(),
                                   success: function(msg)
                                   {
                                     $("#template_properties_table").find("#" + $("#property-id").val()).remove();
                                     navigate_naviorderedtable_template_properties_table_reorder();
                                     $("#templates-properties-edit-dialog").dialog("close");
                                   }
                                 });
                            },
                            "'.t(190, 'Ok').'": function()
                            {
                                $.ajax({
                                   type: "POST",
                                   async: false,
                                   dateType: "text",
                                   url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=template_property_save",
                                   data: $("#templates-properties-edit-dialog").serialize(),
                                   success: function(data)
                                   {
                                       if($("#property-id").val() > 0)
                                       {
                                           // update
                                           var tr = $("#template_properties_table").find("#" + $("#property-id").val());
                                           tr.find("td").eq(0).html(data.name);
                                           tr.find("td").eq(1).html(data.type_text);
                                           tr.find("td").eq(2).html(navigate_templates_element_types[data.element]);
                                           tr.find("input[type=checkbox]").attr("checked", (data.enabled==1));
                                       }
                                       else
                                       {
                                           // insert
                                           var checked = "";
                                           if(data.enabled) checked = \' checked="checked" \';
                                           var tr = \'<tr id="\'+data.id+\'"><td>\'+data.name+\'</td><td>\'+data.type_text+\'</td><td>\'+navigate_templates_element_types[data.element]+\'</td><td align="center"><input name="property-enabled[]" class="raw-checkbox" type="checkbox" value="\'+data.id+\'" \'+checked+\' /></td></tr>\';
                                           $("#template_properties_table").find("tbody:last").append(tr);
                                           $("#template_properties_table").find("tr:last").on("dblclick", function() { navigate_templates_edit_property(this); });
                                           $("#template_properties_table").tableDnD(
                                            {
                                                onDrop: function(table, row)
                                                {		navigate_naviorderedtable_template_properties_table_reorder();		}
                                            });
                                       }
                                       navigate_naviorderedtable_template_properties_table_reorder();
                                       $("#templates-properties-edit-dialog").dialog("close");
                                   }
                                 });
                            }
                        }
                    });
                }
			}
			
			function navigate_templates_property_type_change()
			{
				$("#property-options").parent().hide();
				$("#property-multilanguage").parent().hide();
				$("#property-dvalue").next().find("span").hide();
				
				switch($("#property-type").val())
				{						
					case "option":
						$("#property-options").parent().show();
						$("#property-comment-option").show();
						break;
						
					case "moption":
						$("#property-options").parent().show();
						$("#property-comment-moption").show();
						break;						
						
					case "text":
					case "textarea":
					case "link":
					case "rich_textarea":
					case "source_code":
						$("#property-comment-text").show();
						break;
						
					case "date":
					case "datetime":
						$("#property-comment-date").show();
						break;
						
					case "image":
					case "file":
						$("#property-comment-file").show();
						$("#property-multilanguage").parent().show();
						break;

                    case "video":
						$("#property-comment-video").show();
						break;

					case "rating":
						$("#property-comment-rating").show();
						break;	

                    case "color":
						$("#property-comment-color").show();
						break;

					case "coordinates":
						$("#property-comment-coordinates").show();
						break;							
						
					case "country":
						$("#property-comment-country").show();
						break;

                    case "boolean":
						$("#property-comment-boolean").show();
						break;
					
					case "comment":
					case "value":
					default:
				}
			}
			
			navigate_naviorderedtable_template_properties_table_reorder();	
		');			

	}

	return $navibars->generate();
}

// find all website section widths
function templates_section_widths()
{
	global $DB;
	global $website;
	
	$DB->query('SELECT sections
				  FROM nv_templates
				 WHERE website = '.intval($website->id),
				'array');
				
	$result = $DB->result();
	
	$widths = array();
	foreach($result as $sections)
	{
		$sections = mb_unserialize($sections['sections']);
		if(!is_array($sections)) $sections = array();
		foreach($sections as $section)
		{
			if(!empty($section['width']) && !in_array($section['width'], $widths))
				array_push($widths, $section['width']);
		}
	}	
	
	return $widths;
}

?>