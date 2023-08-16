<?php
require_once(NAVIGATE_PATH.'/lib/packages/profiles/profile.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/permissions/permissions.functions.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	
	$out = '';
	$item = new user();
			
	switch($_REQUEST['act'])
	{
        case 'json':
	    case 1:	// json data retrieval & operations
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
					$ids = $_REQUEST['ids'];
                    $deleted = 0;
					foreach($ids as $id)
					{
                        $item = new user();
						$item->load($id);
						$deleted = $deleted + $item->delete();
					}
					echo json_encode((count($ids)==$deleted));
					break;

				default: // list or search	
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$orderby= $_REQUEST['sidx'].' '.$_REQUEST['sord'];
					$where = " 1=1 ";
										
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
							$where .= $item->quicksearch($_REQUEST['quicksearch']);
						else if(isset($_REQUEST['filters']))
							$where .= navitable::jqgridsearch($_REQUEST['filters']);
						else	// single search
							$where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
					}
				
					$DB->queryLimit('id,username,email,profile,language,blocked', 
									'nv_users', 
									$where, 
									$orderby, 
									$offset, 
									$max);
									
					$dataset = $DB->result();
					$total = $DB->foundRows();
					
					//echo $DB->get_last_error();
					
					$out = array();				
										
					$profiles = profile::profile_names();
					$languages = language::language_names();
											
					for($i=0; $i < count($dataset); $i++)
					{													
						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> '<strong>'.$dataset[$i]['username'].'</strong>',
							2	=> $dataset[$i]['email'],
							3 	=> $profiles[$dataset[$i]['profile']],
							4	=> $languages[$dataset[$i]['language']],		
							5	=> (($dataset[$i]['blocked']==1)? '<img src="img/icons/silk/cancel.png" />' : '')
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);					
					break;
			}
			
			session_write_close();
			exit;
			break;
		
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
					$item->save();
                    permission::update_permissions(json_decode($_REQUEST['navigate_permissions_changes'], true), 0, $item->id);
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = users_form($item);
			break;
					
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = users_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = users_form($item);
				}
			}
			break;

					
		case 0: // list / search result
		default:			
			$out = users_list();
			break;
	}
	
	return $out;
}

function users_list()
{
	$navibars = new navibars();
	$navitable = new navitable("users_list");
	
	$navibars->title(t(15, 'Users'));

	$navibars->add_actions(
		array(
			'<a href="?fid=users&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
			'<a href="?fid=users&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
		)
	);
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=users&act=1&_search=true&quicksearch=".$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid=users&act=1');
    $navitable->sortBy('id', 'DESC');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid=users&act=2&id=');
    $navitable->enableDelete();
	
	$navitable->addCol("ID", 'id', "80", "true", "left");	
	$navitable->addCol(t(1, 'User'), 'username', "150", "true", "left");	
	$navitable->addCol(t(44, 'E-Mail'), 'email', "150", "true", "left");		
	$navitable->addCol(t(45, 'Profile'), 'profile', "100", "true", "left");		
	$navitable->addCol(t(46, 'Language'), 'language', "80", "true", "left");	
	$navitable->addCol(t(47, 'Blocked'), 'blocked', "50", "true", "center");		
	
	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
	
}

function users_form($item)
{
	global $DB;
	global $layout;
    global $current_version;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	if(empty($item->id))
		$navibars->title(t(15, 'Users').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(15, 'Users').' / '.t(170, 'Edit').' ['.$item->id.']');		

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
								"'.t(35, 'Delete').'": function() {
									$(this).dialog("close");
									window.location.href = "?fid=users&act=4&id='.$item->id.'";
								},
								"'.t(58, 'Cancel').'": function() {
									$(this).dialog("close");
								}
							}
						});';		
		$delete_html[] = '}';							
		$delete_html[] = '</script>';						
									
		$navibars->add_content(implode("\n", $delete_html));
	}
	
	$navibars->add_actions(
		array(
			(!empty($item->id)? '<a href="?fid=users&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=users&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
		)
	);

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(
		array(
			'<label>ID</label>',
			'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(1, 'User').'</label>',
			$naviforms->textfield('user-username', $item->username),
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(2, 'Password').'</label>',
			'<input type="password" name="user-password" value="" size="32" autocomplete="off" />',
			'<span class="navigate-form-row-info">'.t(48, "Leave blank to keep the current value").'</span>'
		)
	);
	// force removing the browser saved password
	$layout->add_script('
		setTimeout(function() {
			$("input[name=user-password]").val("");
		}, 10);
	');

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(44, 'E-Mail').'</label>',
			'<input type="text" name="user-email" value="'.$item->email.'" size="64" />'
		)
	);
				
	// Profile selector
	$DB->query('SELECT id, name FROM nv_profiles');		
	$data = $DB->result();	
	$select = $naviforms->select_from_object_array('user-profile', $data, 'id', 'name', $item->profile);
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(45, 'Profile').'</label>',
			$select
		)
	);

	// Language selector
	$DB->query('SELECT code, name FROM nv_languages WHERE nv_dictionary != ""');		
	$data = $DB->result();	
	$select = $naviforms->select_from_object_array('user-language', $data, 'code', 'name', $item->language);
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(46, 'Language').'</label>',
			$select
		)
	);
											
	$timezones = property::timezones();
	
	if(empty($item->timezone))
		$item->timezone = date_default_timezone_get();	

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(97, 'Timezone').'</label>',
			$naviforms->selectfield("user-timezone", array_keys($timezones), array_values($timezones), $item->timezone)
		)
	);
																						
	// Decimal separator		
	$data = array(
		0	=> json_decode('{"code": ",", "name": ", ---> 1234,25"}'),
		1	=> json_decode('{"code": ".", "name": ". ---> 1234.25"}'),
		2	=> json_decode('{"code": "\'", "name": "\' ---> 1234\'25"}'),
	);
				
	$select = $naviforms->select_from_object_array('user-decimal_separator', $data, 'code', 'name', $item->decimal_separator);
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(49, 'Decimal separator').'</label>',
			$select
		)
	);

	// Thousands separator
	$data = array(
        0	=> json_decode('{"code": "", "name": "('.strtolower(t(581, "None")).') ---> 1234567"}'),
        1	=> json_decode('{"code": ",", "name": ", ---> 1,234,567"}'),
        2	=> json_decode('{"code": ".", "name": ". ---> 1.234.567"}'),
	);

	$select = $naviforms->select_from_object_array('user-thousands_separator', $data, 'code', 'name', $item->thousands_separator);
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(644, 'Thousands separator').'</label>',
			$select
		)
	);

	// Date format
	$data = array(
		0	=> json_decode('{"code": "Y-m-d H:i", "name": "'.date("Y").'-12-31 23:59"}'),
		1	=> json_decode('{"code": "d-m-Y H:i", "name": "31-12-'.date("Y").' 23:59"}'),
		2	=> json_decode('{"code": "m-d-Y H:i", "name": "12-31-'.date("Y").' 23:59"}'),
		3	=> json_decode('{"code": "Y/m/d H:i", "name": "'.date("Y").'/12/31 23:59"}'),
		4	=> json_decode('{"code": "d/m/Y H:i", "name": "31/12/'.date("Y").' 23:59"}'),
		5	=> json_decode('{"code": "m/d/Y H:i", "name": "12/31/'.date("Y").' 23:59"}')
	);

    $layout->add_script('
        $("#user-decimal_separator,#user-thousands_separator").on("change", function()
        {
            $("#user-decimal_separator").parent().find("label:first").removeClass("ui-state-error");
            $("#user-thousands_separator").parent().find("label:first").removeClass("ui-state-error");
        
            if($("#user-decimal_separator").val()==$("#user-thousands_separator").val())
            {
                $("#user-decimal_separator").parent().find("label:first").addClass("ui-state-error");
                $("#user-thousands_separator").parent().find("label:first").addClass("ui-state-error");
            }
        });
        
        $("#user-decimal_separator").trigger("change"); // force checking on load
    ');

	$select = $naviforms->select_from_object_array('user-date_format', $data, 'code', 'name', $item->date_format);
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(50, 'Date format').'</label>',
			$select
		)
	);

    $navibars->add_tab_content($naviforms->hidden('user-skin', 'cupertino'));
										
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(47, 'Blocked').'</label>',
			$naviforms->checkbox('user-blocked', $item->blocked),
		)
	);

	$navibars->add_tab(t(241, "Web sites"));

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(612, "Manages all websites").'</label>',
			$naviforms->checkbox("user-all-websites", empty($item->websites))
		)
	);

	$websites = website::all();
	if(empty($websites))
		$websites = array();
    $navibars->add_tab_content_row(
        array(
	        '<label>'.t(405, "Selection").'</label>',
            $naviforms->multiselect(
                'user-websites',
                array_keys($websites),
                array_values($websites),
                $item->websites
            )
        ),
        "user-websites-selector",
	    'style="display: none; padding-bottom: 16px; "'
    );

	$layout->add_script('
		$("#user-all-websites").on("change", function()
		{
			$("#user-websites-selector").hide();
			if(!$(this).is(":checked"))
				$("#user-websites-selector").show();
		});
		$("#user-all-websites").trigger("change");
	');


    $navibars->add_tab(t(17, "Permissions"));

    $navibars->add_tab_content($naviforms->hidden('navigate_permissions_changes', ''));

	$ws_tabs = '<div id="navigate-permissions-websites-tabs"><ul>';

	foreach($websites as $ws_id => $ws_name)
	{
		$ws_tabs .= '<li><a href="#navigate-permissions-websites-tab-'.$ws_id.'">'.$ws_name.'</a></li>';
	}

	$ws_tabs.= '</ul>';

	foreach($websites as $ws_id => $ws_name)
	{
        $rows = nvweb_permissions_rows($ws_id, 'user', $item->id);

        $ws_tabs .= '<div id="navigate-permissions-websites-tab-'.$ws_id.'" data-website="'.$ws_id.'">';

        $ws_tabs .= '<div id="permissions_list_website_'.$ws_id.'">';

        $ws_tabs .= '<table class="treeTable ui-corner-all">';

        $ws_tabs .= '
            <thead>
                <tr class="ui-state-default ui-th-column">
                    <th width="25%">'.t(159, 'Name').'</th>
                    <th width="13%">'.t(467, 'Scope').'</th>
                    <th width="12%">'.t(160, 'Type').'</th>
                    <th width="50%">'.t(193, 'Value').'</th>
                </tr>
            </thead>
        ';

        for($r=0; $r < count($rows); $r++)
        {
            $ws_tabs .= '<tr id="'.$rows[$r][0].'">';

            $ws_tabs .= '    <td>'.$rows[$r][1].'</td>';
            $ws_tabs .= '    <td>'.$rows[$r][2].'</td>';
            $ws_tabs .= '    <td>'.$rows[$r][3].'</td>';
            $ws_tabs .= '    <td>'.$rows[$r][4].'</td>';

            $ws_tabs .= '</tr>';
        }

        $ws_tabs .= '</table>';

        $ws_tabs .= '</div>';

        $ws_tabs .= '</div>';

        $layout->add_script('
			$("#permissions_list_website_'.$ws_id.'").data("website", '.$ws_id.');            
		');

        $scripts_after_load[] = 'navigate_permissions_list_callback($("#permissions_list_website_'.$ws_id.'"));';

        $navibars->add_content(navigate_permissions_structure_selector($ws_id, $ws_name));
	}

	$ws_tabs.= '</div>';

	$navibars->add_tab_content($ws_tabs);

	$layout->add_script('
		$("#navigate-permissions-websites-tabs").tabs({
			heightStyle: "fill",
			activate: function() {
				$(window).trigger("resize");
			}
		});
	');

    $layout->add_script('
	    $.ajax({
	        type: "GET",
	        dataType: "script",
	        cache: true,
	        url: "lib/packages/permissions/permissions.js?r='.$current_version->revision.'",
	        complete: function()
	        {
                navigate_window_resize();
			'.implode("\n", $scripts_after_load).'
	        }
	    });
	');

    return $navibars->generate();
}

?>