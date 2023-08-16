<?php
require_once(NAVIGATE_PATH.'/lib/packages/menus/menu.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/functions/nv_function.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	
	$out = '';
	$item = new menu();
			
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
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$where = " 1=1 ";
										
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
                        {
                            $where .= $item->quicksearch($_REQUEST['quicksearch']);
                        }
						else if(isset($_REQUEST['filters']))
                        {
                            $where .= navitable::jqgridsearch($_REQUEST['filters']);
                        }
						else	// single search
                        {
                            $where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
                        }
					}

                    // filter orderby vars
                    if( !in_array($_REQUEST['sord'], array('', 'desc', 'DESC', 'asc', 'ASC')) ||
                        !in_array($_REQUEST['sidx'], array('id', 'codename', 'icon', 'lid', 'enabled'))
                    )
                    {
                        return false;
                    }
                    $orderby = $_REQUEST['sidx'].' '.$_REQUEST['sord'];
				
					$DB->queryLimit(
					    'id,lid,codename,icon,enabled',
                        'nv_menus',
                        $where,
                        $orderby,
                        $offset,
                        $max
                    );
									
					$dataset = $DB->result();
					$total = $DB->foundRows();
					
					//echo $DB->get_last_error();
					
					$out = array();					
											
					for($i=0; $i < count($dataset); $i++)
					{													
						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> $dataset[$i]['codename'],
							2	=> '<img src="'.NAVIGATE_URL.'/'.$dataset[$i]['icon'].'" />',		
							3 	=> '['.$dataset[$i]['lid'].'] '.t($dataset[$i]['lid'], $dataset[$i]['lid']),							
							4	=> (($dataset[$i]['enabled']==1)? '<img src="img/icons/silk/accept.png" />' : '<img src="img/icons/silk/cancel.png" />')
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);					
					break;
			}
			
			session_write_close();
			exit;
			break;

        case 'edit':
		case 2: // edit/new form		
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
			}
		
			if(isset($_REQUEST['form-sent']))
			{
				$item->load_from_post();
				try
				{
                    naviforms::check_csrf_token();

					$item->save();
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = functions_form($item);
			break;

        case 'delete':
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = functions_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = functions_form($item);
				}
			}
			break;

        case 'list':
		case 0: // list / search result
		default:			
			$out = functions_list();
			break;
	}
	
	return $out;
}

function functions_list()
{
	$navibars = new navibars();
	$navitable = new navitable("functions_list");
	
	$navibars->title(t(244, 'Menus'));

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
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=edit&id=');
	
	$navitable->addCol("ID", 'id', "80", "true", "left");	
	$navitable->addCol(t(237, 'Code'), 'codename', "100", "true", "left");		
	$navitable->addCol(t(242, 'Icon'), 'icon', "50", "true", "center");		
	$navitable->addCol(t(67, 'Title'), 'lid', "200", "true", "left");	
	$navitable->addCol(t(65, 'Enabled'), 'enabled', "80", "true", "center");		
	
	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
	
}

function functions_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	if(empty($item->id))
    {
        $navibars->title(t(244, 'Menus').' / '.t(38, 'Create'));
    }
	else
    {
        $navibars->title(t(244, 'Menus').' / '.t(170, 'Edit').' ['.$item->id.']');
    }

	if(empty($item->id))
	{
		$navibars->add_actions(
		    array(
		        '<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'
            )
        );
	}
	else
	{
		$navibars->add_actions(
		    array(
		        '<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
				'<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>'
            )
        );

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=menus&act=delete&id='.$item->id.'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}
	
	$navibars->add_actions(
	    array(
	        (!empty($item->id)? '<a href="?fid=menus&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=menus&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));
    $navibars->add_tab_content($naviforms->csrf_token());
	
	$navibars->add_tab_content_row(
	    array(
	        '<label>ID</label>',
			'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>'
        )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(237, 'Code').'</label>',
			$naviforms->textfield('codename', $item->codename),
        )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(242, 'Icon').'</label>',
			$naviforms->textfield('icon', $item->icon),
			'<img src="'.NAVIGATE_URL.'/'.$item->icon.'" align="absmiddle" />'
        )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>#'.t(67, 'Title').' (lid)</label>',
			$naviforms->textfield('lid', $item->lid),
			(empty($item->lid)? '' : '<em>'.$item->lid.': <strong>'.t($item->lid, $item->lid).'</strong></em>')
        )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(245, 'Notes').'</label>',
			$naviforms->textarea('notes', $item->notes),
        )
    );
										
	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(65, 'Enabled').'</label>',
			$naviforms->checkbox('enabled', $item->enabled),
        )
    );
										

	$navibars->add_tab(t(200, "Options"));
	
	$functions = nv_function::load_all_functions();
	$func_cats = array_unique($DB->result('category'));
	sort($func_cats);
	
	$sortable_assigned = array();
	$sortable_unassigned = array();
	
	$sortable_assigned[] = '<ul id="sortable_assigned" class="connectedSortable">';
	$sortable_unassigned[] = '<ul id="sortable_unassigned" class="connectedSortable">';	
	
	if(empty($item->functions))
    {
        $item->functions = array();
    }
	
	// already included menus on the profile
	foreach($item->functions as $f)
	{
		foreach($functions as $function)
		{		
			if($function->id == $f)
			{
				if($function->enabled=='1')
                {
                    $sortable_assigned[] = '<li class="ui-state-highlight" value="'.$function->id.'" category="'.$function->category.'"><img src="'.NAVIGATE_URL.'/'.$function->icon.'" align="absmiddle" /> '.t($function->lid, $function->lid).'</li>';
                }
				else
                {
                    $sortable_assigned[] = '<li class="ui-state-highlight ui-state-disabled" value="'.$function->id.'" category="'.$function->category.'"><img src="'.NAVIGATE_URL.'/'.$function->icon.'" align="absmiddle" /> '.t($function->lid, $function->lid).'</li>';
                }
			}			
		}
	}
	
	// the other menus not included on the profile
	foreach($functions as $function)
	{
		if(!in_array($function->id, $item->functions))
		{
			if($function->enabled=='1')
            {
                $sortable_unassigned[] = '<li class="ui-state-default" value="'.$function->id.'" category="'.$function->category.'"><img src="'.NAVIGATE_URL.'/'.$function->icon.'" align="absmiddle" /> '.t($function->lid, $function->lid).'</li>';
            }
			else
            {
                $sortable_unassigned[] = '<li class="ui-state-default ui-state-disabled" value="'.$function->id.'" category="'.$function->category.'"><img src="'.NAVIGATE_URL.'/'.$function->icon.'" align="absmiddle" /> '.t($function->lid, $function->lid).'</li>';
            }
		}
	}
	
	$sortable_assigned[] = '</ul>';
	$sortable_unassigned[] = '</ul>';

	//$navibars->add_tab_content('<pre>'.print_r($item->menus, true).'</pre>'); // margin-top: 12px; margin-left: 5px; float: left;
	// position: absolute; margin-left: 483px; margin-top: -9px; width: 291px; height: 20px;

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(78, 'Category').'</label>',
			$naviforms->selectfield('menus_functions_category_select', $func_cats, $func_cats, 'web', "navigate_menus_change_functions_category();", false)
        )
    );
	
	$navibars->add_tab_content(
	    $naviforms->hidden("menu-functions", implode('#', $item->functions))
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(240, 'Functions').'</label>',
			implode("\n", $sortable_assigned),
			implode("\n", $sortable_unassigned)
        )
    );
									
	$layout->add_script('
		$("#menus_functions_category_select").css({ "width": "300px", "margin-left": "303px" });
		
		$("#sortable_assigned").sortable({
				connectWith: ".connectedSortable",
				receive: function(event, ui)
				{
					$(ui.item).addClass("ui-state-highlight");
					$(ui.item).removeClass("ui-state-default");
				},
				update: function()
				{
					$("#menu-functions").val("");
					$("#sortable_assigned li").each(function()
					{
						$("#menu-functions").val($("#menu-functions").val() + $(this).attr("value") + "#");					
					});
				}
			}).disableSelection();
			
		$("#sortable_unassigned").sortable({
				connectWith: ".connectedSortable",
				receive: function(event, ui)
				{
					$(ui.item).addClass("ui-state-default");
					$(ui.item).removeClass("ui-state-highlight");					
				}
			}).disableSelection();			
		
		function navigate_menus_change_functions_category()
		{	
			var cat = $("#menus_functions_category_select").val();
			$("#sortable_unassigned li").each(function()
			{
				if($(this).attr("category")==cat)	$(this).show();
				else								$(this).hide();
			});
		}
		
		navigate_menus_change_functions_category();
	');

	return $navibars->generate();
}

?>