<?php
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');
require_once(NAVIGATE_PATH.'/lib/packages/products/product.class.php');

function run()
{
	global $user;
	global $layout;
	global $DB;
	global $website;

	$out = '';
	$item = new website();

	switch(@$_REQUEST['act'])
	{
		case 'json':
		case 1:	// json data retrieval & operations
			switch(@$_REQUEST['oper'])
			{
				case 'search_links': // active website only!
					$text = $_REQUEST['text'];
					$lang = $_REQUEST['lang'];
					if(empty($lang))
                    {
                        $lang = array_keys($website->languages);
                        $lang = $lang[0];
                    }

					$DB->query('
						SELECT p.path, d.text
						  FROM nv_paths p, nv_webdictionary d
						 WHERE p.website = '.protect($website->id).' AND
						       p.lang = '.protect($lang).' AND
						       d.website = p.website AND
						       d.node_type = p.type AND
						       d.node_id = p.object_id AND
						       d.lang = p.lang AND
						       d.subtype = "title" AND 
						       (    
						            p.path LIKE '.protect('%'.$text.'%').'  OR  
									d.text LIKE '.protect('%'.$text.'%').' 
						       )
						 ORDER BY d.id DESC
						 LIMIT 10
					');

					$result = $DB->result();


					echo json_encode($result);
					core_terminate();
					break;

				case 'del':	// remove rows
                    if($user->permission('websites.delete')=='true')
                    {
                        $ids = $_REQUEST['ids'];
                        foreach($ids as $id)
                        {
                            $item->load($id);
                            $item->delete();
                        }
                        echo json_encode(true);
                    }
                    core_terminate();
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

					$DB->queryLimit(
						'id,name,subdomain,domain,folder,homepage,permission,favicon',
						'nv_websites',
						$where,
						$orderby,
						$offset,
						$max
					);

					$dataset = $DB->result();
					$total = $DB->foundRows();

					//echo $DB->get_last_error();

					$out = array();

					$permissions = array(
						0 => '<img src="img/icons/silk/world.png" align="absmiddle" /> '.t(69, 'Published'),
						1 => '<img src="img/icons/silk/world_dawn.png" align="absmiddle" /> '.t(70, 'Private'),
						2 => '<img src="img/icons/silk/world_night.png" align="absmiddle" /> '.t(81, 'Hidden')
					);

					for($i=0; $i < count($dataset); $i++)
					{
						$homepage = 'http://';
						$homepage_relative_url = $dataset[$i]['homepage'];
						if(is_numeric($homepage_relative_url))
						{
							$homepage_relative_url = path::loadElementPaths('structure', $homepage_relative_url);
							$homepage_relative_url = array_shift($homepage_relative_url);
						}

						if(!empty($dataset[$i]['subdomain']))
							$homepage .= $dataset[$i]['subdomain'].'.';
						$homepage .= $dataset[$i]['domain'].$dataset[$i]['folder'].$homepage_relative_url;

                        $favicon = '';
                        if(!empty($dataset[$i]['favicon']))
                            $favicon = '<img src="'.NVWEB_OBJECT.'?type=img&id='.$dataset[$i]['favicon'].'&width=24&height=24" align="absmiddle" height="24" />';

						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> $favicon,
							2	=> $dataset[$i]['name'],
							3	=> '<a href="'.$homepage.'" target="_blank"><img align="absmiddle" src="'.NAVIGATE_URL.'/img/icons/silk/house_link.png"></a> '.$homepage,
							4	=> $permissions[$dataset[$i]['permission']]
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

			if(isset($_REQUEST['form-sent']) && $user->permission('websites.edit')=='true')
			{
				$item->load_from_post();
				try
				{
					$item->save();
					$id = $item->id;
					unset($item);
					$item = new website();
					$item->load($id);

                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);
				}
				if(!empty($item->id))
					users_log::action($_REQUEST['fid'], $item->id, 'save', $item->name, json_encode($_REQUEST));
			}
			else
			{
				if(!empty($item->id))
					users_log::action($_REQUEST['fid'], $item->id, 'load', $item->name);
			}

			$out = websites_form($item);
			break;

		case 'remove':
		case 4:
			if(!empty($_REQUEST['id']) && ($user->permission('websites.delete')=='true'))
			{
				$item->load(intval($_REQUEST['id']));
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);

					if(!empty($item->id))
						users_log::action($_REQUEST['fid'], $item->id, 'remove', $item->name, json_encode($_REQUEST));

                    // if we don't have any websites, tell user a new one will be created
                    $test = $DB->query_single('id', 'nv_websites');

                    if(empty($test) || !$test)
                    {
                        $layout->navigate_notification(t(520, 'No website found; a default one has been created.'), false, true);
                        $nwebsite = new website();
                        $nwebsite->create_default();
                    }

                    $out = websites_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = websites_form($item);
				}
			}
			break;

		case 5:	// search an existing path
			$DB->query('SELECT path as id, path as label, path as value
						  FROM nv_paths
						 WHERE path LIKE '.protect('%'.$_REQUEST['term'].'%').'
						   AND website = '.protect($_REQUEST['wid']).'
				      ORDER BY path ASC
					     LIMIT 30',
						'array');

			echo json_encode($DB->result());

			core_terminate();
			break;
			
		case 'email_test':
			$website->mail_mailer = $_REQUEST['mail_mailer'];
			$website->mail_server = $_REQUEST['mail_server'];
			$website->mail_port = $_REQUEST['mail_port'];
			$website->mail_address = $_REQUEST['mail_address'];
			$website->mail_user = $_REQUEST['mail_user'];
            $website->mail_security = $_REQUEST['mail_security'];
            $website->mail_ignore_ssl_security = $_REQUEST['mail_ignore_ssl_security'];

			if(!empty($_REQUEST['mail_password']))
				$website->mail_password = $_REQUEST['mail_password'];

			$ok = navigate_send_email(APP_NAME, APP_NAME.'<br /><br />'.NAVIGATE_URL, $_REQUEST['send_to']);
			echo json_encode($ok);
			core_terminate();

			break;

        case 'reset_statistics':
            if($user->permission('websites.edit')=='true')
            {
				$website_id = trim($_REQUEST['website']);
				$website_id = intval($website_id);

                $DB->execute('UPDATE nv_items SET views = 0 WHERE website = '.$website_id);
                $DB->execute('UPDATE nv_paths SET views = 0 WHERE website = '.$website_id);
                $DB->execute('UPDATE nv_structure SET views = 0 WHERE website = '.$website_id);
                echo 'true';

				users_log::action($_REQUEST['fid'], $website_id, 'reset_statistics', "", json_encode($_REQUEST));
            }
            core_terminate();
            break;

		case 'replace_urls':
			$old = trim($_REQUEST['old']);
			$new = trim($_REQUEST['new']);
			$website_id = trim($_REQUEST['website']);

			if(!empty($old) && !empty($new))
			{
				// replace occurrences in nv_webdictionary
				$ok = $DB->execute('
					UPDATE nv_webdictionary
					   SET text = replace(text, :old, :new)
					 WHERE website = :wid',
					array(
						':old' => $old,
						':new' => $new,
						':wid' => $website_id
					)
				);

				// replace occurrences in nv_blocks (triggers & actions)
				$ok = $DB->execute('
					UPDATE nv_blocks
					   SET `trigger` = replace(`trigger`, :old, :new),
					   	   `action` = replace(`action`, :old, :new)
					 WHERE website = :wid',
					array(
						':old' => $old,
						':new' => $new,
						':wid' => $website_id
					)
				);

				echo ($ok? 'true' : 'false');

				if($ok)
					users_log::action($_REQUEST['fid'], $website_id, 'replace_urls', "", json_encode($_REQUEST));
			}
			else
			{
				echo 'false';
			}
			core_terminate();
			break;

		case 'remove_content':
			$website_id = trim($_REQUEST['website']);
			$website_id = intval($website_id);
			$password = trim($_REQUEST['password']);

			$authenticated = $user->authenticate($user->username, $password);

			if($authenticated)
			{
				// remove all content except Webusers and Files
				@set_time_limit(0);

				$ok = $DB->execute('
					DELETE FROM nv_blocks WHERE website = '.$website_id.';
					DELETE FROM nv_block_groups WHERE website = '.$website_id.';
					DELETE FROM nv_comments WHERE website = '.$website_id.';
					DELETE FROM nv_structure WHERE website = '.$website_id.';
					DELETE FROM nv_feeds WHERE website = '.$website_id.';
					DELETE FROM nv_items WHERE website = '.$website_id.';
					DELETE FROM nv_notes WHERE website = '.$website_id.';
					DELETE FROM nv_paths WHERE website = '.$website_id.';
					DELETE FROM nv_properties WHERE website = '.$website_id.';
					DELETE FROM nv_properties_items WHERE website = '.$website_id.';
					DELETE FROM nv_search_log WHERE website = '.$website_id.';
					DELETE FROM nv_webdictionary WHERE website = '.$website_id.';
					DELETE FROM nv_webdictionary_history WHERE website = '.$website_id.';
				');

				if($ok)
					users_log::action($_REQUEST['fid'], $website_id, 'remove_content', "", json_encode($_REQUEST));

				echo ($ok? 'true' : $DB->error());
			}
			else
			{
				echo '';
			}
			
			core_terminate();
			break;

		case 0: // list / search result
		default:
			$out = websites_list();
			break;
	}

	return $out;
}

function websites_list()
{
    global $user;

	$navibars = new navibars();
	$navitable = new navitable("websites_list");

	$navibars->title(t(241, 'Websites'));

	$navibars->add_actions(
        array(
            (($user->permission('websites.edit')=='true')? '<a href="?fid='.$_REQUEST['fid'].'&act=edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
            '<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
            'search_form'
        )
    );

	if(@$_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=1&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);

	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=1');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=2&id=');

	$navitable->addCol("ID", 'id', "80", "true", "left");
    $navitable->addCol(t(328, 'Favicon'), 'favicon', "32", "true", "center");
	$navitable->addCol(t(159, 'Name'), 'name', "200", "true", "left");
	$navitable->addCol(t(187, 'Homepage'), 'homepage', "300", "true", "center");
	$navitable->addCol(t(68, 'Status'), 'permission', "100", "true", "center");

	$navibars->add_content($navitable->generate());

	return $navibars->generate();

}

function websites_form($item)
{
	global $user;
	global $DB;
	global $layout;
    global $events;

	$navibars = new navibars();
	$naviforms = new naviforms();
	$layout->navigate_media_browser();	// we want to use media browser in this function
    $layout->navigate_editorfield_link_dialog();

	$theme = new theme();
	if(!empty($item->theme))
		$theme->load($item->theme);

	if(empty($item->id))
		$navibars->title(t(241, 'Websites').' / '.t(38, 'Create'));
	else
		$navibars->title(t(241, 'Websites').' / '.t(170, 'Edit').' ['.$item->id.']');

    if($user->permission('websites.edit')=='true')
    {
	    $navibars->add_actions(
		    array(
			    '<a href="#" onclick="javascript: navigate_media_browser();" title="Ctrl+m">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media')
			    .'</a>'
		    )
	    );

	    $extra_actions = array();
	    $extra_actions[] = '<a href="#" action="navigate_reset_statistics" onclick="javascript: navigate_reset_statistics();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/chart_line.png"> '.t(429, 'Reset statistics').'</a>';

        $layout->add_script('
            function navigate_reset_statistics()
            {
                navigate_confirmation_dialog(
                    function()
                    {
                        $.post(
                            "?fid=websites&act=reset_statistics&website='.$item->id.'",
                            {},
                            function(data)
                            {
                                $("a[action=\'navigate_reset_statistics\']").parent().fadeOut();
                            }
                        );
                    }, 
                    "<div>'.t(430, 'Do you really want to remove all statistics of this website?').'</div>" 
                );
            }
        ');

	    if(!empty($item->id))
	    {
		    $extra_actions[] = '<a href="#" action="navigate_replace_urls" onclick="javascript: navigate_replace_urls();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/database_refresh.png"> '.t(603, 'Replace URLs').'</a>';

		    // try to find the OLD url for NAVIGATE_DOWNLOAD
		    $old_url_guessed = "";
		    $DB->query('
				SELECT text
				  FROM nv_webdictionary
				  WHERE node_type = "item"
				    AND website = '.$item->id.'
				    AND text LIKE '.protect("%navigate_download.php%").'
			    LIMIT 1
		    ');
		    $rs = $DB->result('text');
		    preg_match("/<img .*?(?=src)src=\"([^\"]+)\"/si", $rs[0], $old_url_guessed);
		    $old_url_guessed = @$old_url_guessed[1];
		    $old_url_guessed = substr($old_url_guessed, 0, strpos($old_url_guessed, NAVIGATE_FOLDER));

		    $layout->add_content('
		        <div id="navigate_replace_urls_dialog" style="display: none;">
		            <div id="" class="navigate-form-row">
						<label>'.t(604, "Old").'</label>
						<input type="text" style=" width: 300px;" id="replace_urls_old" name="replace_urls_old" value="'.$old_url_guessed.'/" />
					</div>
					<div id="" class="navigate-form-row">
						<label>'.t(605, "New").'</label>
						<input type="text" style=" width: 300px;" id="replace_urls_new" name="replace_urls_new" value="'.NAVIGATE_PARENT.'/" />
					</div>
					<div class="navigate-form-row">
						<div class="subcomment">'.t(523, "This action can NOT be undone.").'</div>
					</div>
		        </div>
		    ');

		    $layout->add_script('
	            function navigate_replace_urls()
	            {
	                $("#navigate_replace_urls_dialog").dialog({
	                        resizable: true,
	                        height: 180,
	                        width: 520,
	                        modal: true,
	                        title: "'.t(603, 'Replace URLs').'",
	                        buttons: {
	                            "'.t(190, 'Ok').'": function()
	                            {
	                                $.post(
	                                    "?fid=websites&act=replace_urls",
	                                    {
	                                        old: $("#replace_urls_old").val(),
	                                        new: $("#replace_urls_new").val(),
	                                        website: '.$item->id.'
	                                    },
	                                    function(data)
	                                    {
	                                        if(data!="true")
					                            navigate_notification("'.t(56, "Unexpected error.").'");
					                        else
					                        {
					                            navigate_notification("'.t(53, "Data saved successfully").'", false, "fa fa-check");
	                                            $("#navigate_replace_urls_dialog").dialog("close");
	                                        }
	                                    }
	                                );
	                            },
	                            "'.t(58, 'Cancel').'": function()
	                            {
	                                $("#navigate_replace_urls_dialog").dialog("close");
	                            }
	                        }
	                });
	            }
	        ');

		    $extra_actions[] = '<a href="#" action="navigate_remove_website_data" onclick="javascript: navigate_remove_website_data();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cross.png"> '.t(208, 'Remove all content').'</a>';
		    $layout->add_script('
            function navigate_remove_website_data()
            {
                var confirmation = "<div>";
                confirmation += "<br /><div><strong>'.t(497, 'Do you really want to erase this data?').'</strong> ('.t(16, "Structure").', '.t(22, "Elements").', '.t(23, "Blocks").', '.t(250, "Comments").'...)</div><br />";
                confirmation += "<form action=\"?\" onSubmit=\"return false;\"><div class=\"navigate-form-row\"><label>'.t(2, "Password").'</label></div><input type=\"password\" id=\"navigate_remove_website_data_password\" style=\"width: 90%;\" /></form></div>";
                confirmation += "</div>";

                $(confirmation).dialog({
                    resizable: true,
                    height: 250,
                    width: 400,
                    modal: true,
                    title: "'.t(59, 'Confirmation').'",
                    buttons: {
                        "'.t(190, 'Ok').'": function()
                        {
                            $(this).dialog("close");

                            $.post(
                                "?fid=websites&act=remove_content",
                                {
                                    website: $("#id").val(),
                                    password: $("#navigate_remove_website_data_password").val()
                                },
                                function(data)
                                {
                                    if(data=="true")
                                    {
                                        navigate_notification("'.t(419, "Process complete").'");
                                        $("a[action=\'navigate_remove_website_data\']").parent().fadeOut();
                                    }
                                    else
                                        navigate_notification("'.t(56, "Unexpected error.").' " + data, true);
                                }
                            );
                        },
                        "'.t(58, 'Cancel').'": function()
                        {
                            $(this).dialog("close");
                        }
                    }
                });
            }
        ');
	    }

	    // we attach an event to "websites" which will be fired by navibars to put an extra button
	    $events->add_actions(
		    'websites',
		    array(
			    'website' => &$item,
			    'navibars' => &$navibars
		    ),
		    $extra_actions
	    );

        if(empty($item->id))
        {
            $navibars->add_actions(
                array(
	                '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+s">
						<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save')
	                .'</a>'
                )
            );
        }
        else
        {
            $navibars->add_actions(
                array(
                    '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+s">
						<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save')
                    .'</a>',
                    (($user->permission('websites.delete')=='true')?
	                    '<a href="#" onclick="navigate_delete_dialog();">
							<img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete')
	                    .'</a>'
	                    : ''
                    )
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
                                        window.location.href = "?fid='.$_REQUEST['fid'].'&act=4&id='.$item->id.'";
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

	    $layout->add_script("
            $(document).on('keydown.ctrl_s', function (evt) { navigate_items_tabform_submit(1); return false; } );
            $(document).on('keydown.ctrl_m', function (evt) { navigate_media_browser(); return false; } );
        ");
    }

	$navibars->add_actions(
        array(
            (($user->permission('websites.edit')=='true' && !empty($item->id))? '<a href="?fid=websites&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
            '<a href="?fid=websites&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
            'search_form'
        )
    );

	$navibars->form();

	$navibars->add_tab(t(7, "Settings"));

	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(67, 'Title').'</label>',
			$naviforms->textfield('title', $item->name)
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(287, 'Protocol').'</label>',
			$naviforms->selectfield(
				'protocol',
				array(
					0 => 'http://',
					1 => 'https://'
				),
				array(
					0 => 'HTTP',
					1 => 'HTTPS ['.t(288, 'Secured site (requires certificate)').']'
				),
				$item->protocol
			)
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(228, 'Subdomain').'</label>',
			$naviforms->textfield('subdomain', $item->subdomain),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' www</span>'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(229, 'Domain').'</label>',
			$naviforms->textfield('domain', $item->domain),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' naviwebs.net</span>'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(141, 'Folder').'</label>',
			$naviforms->textfield('folder', $item->folder),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' /new-website</span>'
		)
	);

	$homepage_url = "";
	if(!empty($item->homepage))
		$homepage_url = $item->homepage_from_structure();

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(187, 'Homepage').'</label>',
			$naviforms->hidden('homepage_from_structure', (is_numeric($item->homepage)? $item->homepage : "")),
			$naviforms->autocomplete('homepage', $homepage_url, '?fid='.$_REQUEST['fid'].'&wid='.$item->id.'&act=5'),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' /en/home</span>'
		)
	);

	$navibars->add_tab_content_row(array(
        '<div class="subcomment"><img src="img/icons/silk/house.png" align="absmiddle" /> <span id="navigate-website-home-url"></span></div>'
    ));

	$layout->add_content('
		<div id="homepage_change_dialog" style="display: none;">
			'.t(595, "Right now the homepage is set from a structure element which allows multilanguage redirecting.").'
			<br /><br />
			'.t(596, "Do you want to enter a fixed path for the homepage?").'
		</div>
	');

	$layout->add_script('
		$("#homepage").on("click keydown", function(ev)
		{
			if($("#homepage_from_structure").val()!="")
			{
				$("#homepage_change_dialog").dialog({
					title: "'.t(59, "Confirmation").'",
					modal: true,
					width: 400,
					height: 150,
					buttons: [
					    {
					      text: "'.t(190, "Ok").'",
					      icons: {  primary: "ui-icon-check"    },
					      click: function()
					      {
					            $("#homepage_from_structure").val("");
					            $("#homepage").focus();
					            $("#homepage_change_dialog").dialog("close");
					      }
					    },
					    {
					      text: "'.t(58, "Cancel").'",
					      icons: { primary: "ui-icon-close" },
					      click: function()
					      {
			                 setTimeout(
			                    function()
			                    {
	                                $("div.ui-widget-overlay").hide();
			                        $("#homepage").blur();
		                        }, 100
	                         );
					         $("#homepage_change_dialog").dialog("close");
					      }
					    }
					]
				});
			}
		});

		$("#subdomain,#domain,#folder,#homepage").on("keyup", navigate_website_update_home_url);
		$("#protocol").on("change", navigate_website_update_home_url);

		function navigate_website_update_home_url()
		{
			var url = $("#protocol").val();
			if($("#subdomain").val().length > 0)
				url += $("#subdomain").val() + ".";
			url += $("#domain").val();
			url += $("#folder").val();
			url += $("#homepage").val();

			$("#navigate-website-home-url").html(url);
		}

		navigate_website_update_home_url();
	');


	if(!empty($item->theme))
	{
		$navibars->add_tab_content_row(array(
            '<label>'.t(368, 'Theme').'</label>',
			'<strong>
				<a href="?fid=8&act=themes">
					<img height="16" width="16" align="absmiddle" src="img/icons/silk/rainbow.png" />
				</a> '.$theme->title.'
			</strong>'
		));
	}

    $navibars->add_tab_content_row(array(
            '<label>'.t(515, 'Not found paths').'...</label>',
            $naviforms->selectfield(
                'wrong_path_action',
                array(
                    0 => 'blank',
                    1 => 'homepage',
                    2 => 'theme_404',
                    3 => 'http_404',
                    4 => 'website_path'
                ),
                array(
                    0 => t(516, 'Show a blank page'),
                    1 => t(517, 'Redirect to home page'),
                    2 => t(518, 'Use the custom 404 template of a theme (if exists)'),
                    3 => t(519, 'Send a 404 HTTP error header'),
                    4 => t(642, 'Redirect to a website page'),
                ),
                $item->wrong_path_action,
                'navigate_websites_wrong_path_action_change(this)',
                false
            ),
            '<a class="uibutton nv_website_wrong_path_trigger hidden"><i class="fa fa-sitemap"></i></a>',
            '<span id="navigate-website-wrong-path-redirect" class="nv_website_wrong_path_info navigate-form-row-info">'.$item->wrong_path_redirect.'</span>',
            $naviforms->hidden('wrong_path_redirect', $item->wrong_path_redirect)
        )
    );

    
    $layout->add_script('
        function navigate_websites_wrong_path_action_change(el)
        {
            $(el).parent().find(".nv_website_wrong_path_trigger").addClass("hidden");
            $(el).parent().find(".nv_website_wrong_path_info").addClass("hidden");
            
            if($(el).val()=="website_path")
            {
                $(el).parent().find(".nv_website_wrong_path_trigger").removeClass("hidden");
                $(el).parent().find(".nv_website_wrong_path_info").removeClass("hidden");                
            }
        }
                
        navigate_websites_wrong_path_action_change($("#wrong_path_action"));
    
        $(".nv_website_wrong_path_trigger").on("click", function()
        {
            var trigger = this;
        
            // hide "replace title" when calling the dialog from the block action
            // leave it enabled when calling the dialog from the Links table
            if($(this).parents("table.box-table").length == 0)
                $("#nv_link_dialog_replace_text").parent().css("visibility", "hidden");
        
            $("#nv_link_dialog").removeClass("hidden");
            $("#nv_link_dialog").dialog({
                title: $("#nv_link_dialog").attr("title"),
                modal: true,
                width: 620,
                height: 400,
                buttons: [
                    {
                        text: "Ok",
                        click: function(event, ui)
                        {
                            // check if there is any path selected
                            if(!$("#nv_link_dialog_dynamic_path").hasClass("hidden"))
                            {
                                var input_path = $("#wrong_path_redirect");
                                input_path.val($("#nv_link_dialog_dynamic_path").text());
                                $(".nv_website_wrong_path_info").html($("#nv_link_dialog_dynamic_path").text());
        
                                $("#nv_link_dialog").dialog("close");
                            }
                        }
                    },
                    {
                        text: "Cancel",
                        click: function(event, ui)
                        {
                            $("#nv_link_dialog").dialog("close");
                        }
                    }
                ],
                close: function()
                {
                    $("#nv_link_dialog_replace_text").parent().css("visibility", "visible");
                }
            });
        });    
    ');

	// when no path is given
    $navibars->add_tab_content_row(array(
            '<label>'.t(625, 'Empty paths').'...</label>',
            $naviforms->selectfield(
                'empty_path_action',
                array(
                    0 => 'homepage_redirect',
	                1 => 'homepage_noredirect',
	                2 => 'blank',
	                3 => 'theme_404',
                    4 => 'http_404'
                ),
                array(
	                0 => t(517, 'Redirect to home page'),
	                1 => t(626, 'Display the home page, without changing the route'),
	                2 => t(516, 'Show a blank page'),
                    3 => t(518, 'Use the custom 404 template of a theme (if exists)'),
	                4 => t(519, 'Send a 404 HTTP error header')
                ),
                $item->empty_path_action,
                '',
                false
            )
        )
    );

	$navibars->add_tab_content_row(array(
            '<label>'.t(68, 'Status').'</label>',
            $naviforms->selectfield(
                'permission',
                array(
                        0 => 0,
                        1 => 1,
                        2 => 2
                ),
                array(
                        0 => t(69, 'Published'),
                        1 => t(70, 'Private'),
                        2 => t(71, 'Closed')
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

    $layout->add_script('
        $("#permission").on("change", function()
        {
            if($(this).val() > 0)
                $("#redirect_to").parent().show();
            else
                $("#redirect_to").parent().hide();
        });

        $("#permission").trigger("change");
    ');

    $navibars->add_tab_content_row(array(
            '<label>'.t(505, 'Redirect to').'</label>',
            $naviforms->textfield('redirect_to', $item->redirect_to),
            '<span class="navigate-form-row-info">'.t(230, 'Ex.').' /landing_page.html</span>'
        )
    );


	$navibars->add_tab(t(730, "Internationalization"));

    // system locales
    $locales = $item->unix_locales();
    $system = PHP_OS;
    if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && empty($locales)) // seems like a MS Windows Server (c)
    {
        $locales = $item->windows_locales();
        $system = 'MS Windows';
    }

	/* Languages selector */
	if(!is_array($item->languages_list))
        $item->languages_list = array();

    $table = new naviorderedtable("website_languages_table");
    //$table->setWidth("600px");
    $table->setHiddenInput("languages-order");

    $navibars->add_tab_content($naviforms->hidden('languages-order', implode('#', $item->languages_list)));

    $table->addHeaderColumn(t(159, 'Name'), 160);
    $table->addHeaderColumn(t(237, 'Code'), 60);
    $table->addHeaderColumn(t(471, 'Variant').'/'.t(473, 'Region'), 120);
    $table->addHeaderColumn(t(474, 'System locale').' ('.$system.')', 150);
    $table->addHeaderColumn(t(64, 'Published'), 60);
    $table->addHeaderColumn(t(35, 'Remove'), 60);

    $DB->query('SELECT code, name FROM nv_languages');
    $languages_rs = $DB->result();
    $languages = array();

    foreach($languages_rs as $lang)
        $languages[$lang->name] = $lang->code;

    if(empty($item->languages))
    {
        // load default language settings
        $item->languages_list = array('en');
        $item->languages_published = array('en');
        $item->languages = array(
            'en' => array(
                'language' => 'en',
                'variant' => '',
                'code' => 'en',
                'system_locale' => (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'? 'ENU_USA' : 'en_US.utf8')
            )
        );
    }

    if(empty($item->languages))
        $item->languages = array();

	// add previously assigned locales if they are missing
	foreach($item->languages as $lcode => $ldef)
	{
		if(!in_array($ldef['system_locale'], $locales))
			$locales[$ldef['system_locale']] = '? ['.$ldef['system_locale'].']';
	}

    $p = 0;
    foreach($item->languages as $lcode => $ldef)
    {
        $p++;
        $published = (array_search($lcode, $item->languages_published)!==false);
        $variant = !empty($ldef['variant']);

        $select_language = $naviforms->select_from_object_array('language-id['.$p.']', $languages_rs, 'code', 'name', $ldef['language'], ' width: 150px; ');

        if(empty($locales))
            $select_locale   = $naviforms->textfield('language-locale['.$p.']', $ldef['system_locale'], '300px');
        else
            $select_locale   = $naviforms->selectfield('language-locale['.$p.']', array_keys($locales), array_values($locales), $ldef['system_locale'], '', false, array(), 'width: 300px;');

	    $uid = uniqid();
        $table->addRow($p, array(
            array('content' => $select_language, 'align' => 'left'),
            array('content' => '<div style=" white-space: nowrap; "><input type="text" name="language-code[]" value="'.$ldef['language'].'" style="width: 30px;" /></div>', 'align' => 'left'),
            array('content' => '<input type="checkbox" name="language-variant[]" id="language-variant['.$uid.']" value="1" '.($variant? 'checked="checked"': '').' style="float:left;" class="raw-checkbox" /> <input type="text" name="language-variant-code[]" value="'.$ldef['variant'].'" style="width: 75px;" />', 'align' => 'left'),
            array('content' => $select_locale, 'align' => 'left'),
            array('content' => '<input type="hidden" name="language-published[]" value="'.($published? '1' : '0').'" /><input type="checkbox" id="language-published['.$uid.']" value="'.$lcode.'" '.($published? 'checked="checked"': '').' onclick=" if($(this).is(\':checked\')) { $(this).prev().val(1); } else { $(this).prev().val(0); }; " /><label for="language-published['.$uid.']"></label>', 'align' => 'center'),
            array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" onclick="navigate_websites_language_remove(this);" />', 'align' => 'center')
        ));
    }

    $navibars->add_tab_content_row(array(
            '<label>'.t(63, 'Languages').'</label>',
            '<div>'.$table->generate().'</div>',
            '<div class="subcomment">
                <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'
            </div>' )
    );

    $navibars->add_tab_content_row(array(
        '<label>&nbsp;</label>',
        '<button id="websites-languages-add"><img src="img/icons/silk/add.png" align="absmiddle" style="cursor:pointer;" /> '.t(472, 'Add').'</button>')
    );

    $layout->add_script('
        $("#website_languages_table tr").eq(1).find("td:last").children().hide();
        $(\'input[name="language-variant[]"]\').each(function(i, el)
        {
            if($(el).is(":checked"))
                $(el).next().removeClass("ui-state-disabled");
            else
                $(el).next().val("").addClass("ui-state-disabled");
        });

        $(\'input[name="language-variant-code[]"]\').on("click", function()
        {
            if(!$(this).prev().is(":checked"))
                $(this).prev().trigger("click");
        });

        $("#website_languages_table").on("change", \'select[name="language-id[]"]\', function()
        {
            var input = $(this).parent().next().find("input");
            $(input).val($(this).val());
            $(input).effect("highlight", {}, 2000);
        });

        $("#website_languages_table").on("change", \'input[name="language-variant[]"]\', function()
        {
            if($(this).is(":checked"))
                $(this).next().removeClass("ui-state-disabled");
            else
                $(this).next().val("").addClass("ui-state-disabled");
        });

        $("#websites-languages-add").on("click", function()
        {
            var tr = $("#website_languages_table").find("tr").eq(1).clone();            
            var tsid = new Date().getTime();
            $(tr).attr("id", tsid);
            
            $(tr).find("input,label,select").each(function()
		    {
		        if($(this).attr("id"))
		        {
		            var new_name = ($(this).attr("id").split("["))[0];
		            $(this).attr("id", new_name + "[" + tsid + "]");
		        }
		
		        if($(this).attr("for"))
		        {
		            var new_name = ($(this).attr("for").split("["))[0];
		            $(this).attr("for", new_name + "[" + tsid + "]");
		        }
		    });

            $("#website_languages_table").find("tbody:last").append(tr);
            $("#website_languages_table").tableDnD({
                onDrop: function(table, row)
                {
                    navigate_naviorderedtable_website_languages_table_reorder();
                }
            });

            navigate_naviorderedtable_website_languages_table_reorder();

            $(tr).find("td:first").find("a,div,span").remove();
            $(tr).find("td").eq(3).find("a,div,span").remove();

            navigate_selector_upgrade($(tr).find("td:first").find("select"));

            if($(tr).find("td").eq(3).find("select").length > 0)
                navigate_selector_upgrade($(tr).find("td").eq(3).find("select"));

            return false;
        });

        function navigate_websites_language_remove(el)
        {
            $(el).parent().parent().remove();
        }

        function navigate_naviorderedtable_website_languages_table_reorder()
        {
            $("#website_languages_table tr").find("td:last").not(":first").children().show();
            $("#website_languages_table tr").eq(1).find("td:last").children().hide();
        }
    ');


    // other i18n fields

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(693, "Default values").'</label>'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(50, 'Date format').'</label>',
            $naviforms->selectfield(
                'date_format',
                array(
                    0 => 'd/m/Y',
                    1 => 'd-m-Y',
                    2 => 'm/d/Y',
                    3 => 'm-d-Y',
                    4 => 'Y-m-d',
                    5 => 'Y/m/d'
                ),
                array(
                    0 => date('d/m/Y'),
                    1 => date('d-m-Y'),
                    2 => date('m/d/Y'),
                    3 => date('m-d-Y'),
                    4 => date('Y-m-d'),
                    5 => date('Y/m/d')
                ),
                $item->date_format
            )
        )
    );

    $timezones = property::timezones();

    if(empty($item->default_timezone))
        $item->default_timezone = date_default_timezone_get();

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(97, 'Timezone').'</label>',
            $naviforms->selectfield("default_timezone", array_keys($timezones), array_values($timezones), $item->default_timezone)
        )
    );

    // number | decimals separator
    $data = array(
        0	=> json_decode('{"code": ",", "name": ", ---> 1234,25"}'),
        1	=> json_decode('{"code": ".", "name": ". ---> 1234.25"}'),
        2	=> json_decode('{"code": "\'", "name": "\' ---> 1234\'25"}'),
    );

    $select = $naviforms->select_from_object_array('website-decimal_separator', $data, 'code', 'name', $item->decimal_separator);
    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(49, 'Decimal separator').'</label>',
            $select
        )
    );

    // number format | thousands separator
    $data = array(
        0	=> json_decode('{"code": "", "name": "('.strtolower(t(581, "None")).') ---> 1234567"}'),
        1	=> json_decode('{"code": ",", "name": ", ---> 1,234,567"}'),
        2	=> json_decode('{"code": ".", "name": ". ---> 1.234.567"}'),
    );

    $select = $naviforms->select_from_object_array('website-thousands_separator', $data, 'code', 'name', $item->thousands_separator);
    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(644, 'Thousands separator').'</label>',
            $select
        )
    );

    $layout->add_script('
        $("#website-decimal_separator,#website-thousands_separator").on("change", function()
        {
            $("#website-decimal_separator").parent().find("label:first").removeClass("ui-state-error");
            $("#website-thousands_separator").parent().find("label:first").removeClass("ui-state-error");
        
            if($("#website-decimal_separator").val()==$("#website-thousands_separator").val())
            {
                $("#website-decimal_separator").parent().find("label:first").addClass("ui-state-error");
                $("#website-thousands_separator").parent().find("label:first").addClass("ui-state-error");
            }
        });
        
        $("#website-decimal_separator").trigger("change"); // force checking on load
    ');

    // default currency
    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(690, 'Currency').'</label>',
            $naviforms->selectfield("website-default_currency", array_keys(product::currencies('all')), array_values(product::currencies('all')), $item->currency)
        )
    );

    // default size unit
    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(691, 'Size unit').'</label>',
            $naviforms->selectfield("website-default_size_unit", product::size_units(), product::size_units(), $item->size_unit)
        )
    );

    // default weight unit
    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(692, 'Weight unit').'</label>',
            $naviforms->selectfield("website-default_weight_unit", product::weight_units(), product::weight_units(), $item->weight_unit)
        )
    );


    $navibars->add_tab(t(485, "Aliases"));

    $table = new naviorderedtable("website_aliases_table");

    $table->addHeaderColumn(t(486, 'Alias'), 160);
    $table->addHeaderColumn('', 24);
    $table->addHeaderColumn(t(487, 'Real URL'), 60);
    $table->addHeaderColumn(t(35, 'Remove'), 60);

    $table->addRow($lang->code, array(
        array('content' => '<div style="width: 308px;">http://example.domain.com/demo</div>', 'align' => 'left'),
        array('content' => '&rarr;', 'align' => 'center'),
        array('content' => '<div style="width: 308px;">http://www.domain.com/example/demo</div>', 'align' => 'left'),
        array('content' => '', 'align' => 'left')
    ));

    if(!is_array($item->aliases))
        $item->aliases = array();
    foreach($item->aliases as $alias => $realurl)
    {
        $table->addRow($lang->code, array(
            array('content' => '<input type="text" name="website-aliases-alias[]" value="'.$alias.'" style="width: 300px;" />', 'align' => 'left'),
            array('content' => '&rarr;', 'align' => 'center'),
            array('content' => '<input type="text" name="website-aliases-real[]" value="'.$realurl.'" style="width: 300px;" />', 'align' => 'left'),
            array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" onclick="navigate_websites_aliases_remove(this);" />', 'align' => 'center')
        ));
    }

    $navibars->add_tab_content_row(array(
            '<label>'.t(485, 'Aliases').'</label>',
            '<div>'.$table->generate().'</div>',
            '<div class="subcomment">
                <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'
            </div>' )
    );

    $navibars->add_tab_content_row(array(
            '<label>&nbsp;</label>',
            '<button id="websites-aliases-add"><img src="img/icons/silk/add.png" align="absmiddle" style="cursor:pointer;" /> '.t(472, 'Add').'</button>')
    );

    $layout->add_script('
        $("#websites-aliases-add").on("click", function()
        {
            var tr = $("<tr><td></td><td></td><td></td><td></td></tr>");
            $(tr).attr("id", new Date().getTime());
            $(tr).find("td").eq(0).html("<input type=\"text\" name=\"website-aliases-alias[]\" style=\"width: 300px;\" />");
            $(tr).find("td").attr("align", "center").eq(1).html("&rarr;");
            $(tr).find("td").eq(2).html("<input type=\"text\" name=\"website-aliases-real[]\" style=\"width: 300px;\" />");
            $(tr).find("td").attr("align", "center").eq(3).html("<img src=\"'.NAVIGATE_URL.'/img/icons/silk/cancel.png\" onclick=\"navigate_websites_aliases_remove(this);\" />");

            $("#website_aliases_table").find("tbody:last").append(tr);
            $("#website_aliases_table").tableDnD();
            return false;
        });

        function navigate_websites_aliases_remove(el)
        {
            $(el).parent().parent().remove();
        }
    ');


	$navibars->add_tab(t(9, "Content"));

    // keep the default value for Navigate CMS < 2.0
    if(empty($item->word_separator))
        $item->word_separator = "_";

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(633, 'Word separator in paths').'</label>',
			$naviforms->selectfield(
                'word_separator',
                array(
                    0 => '-',
                    1 => '_'
                ),
                array(
                    0 => t(634, "Hyphen")." /navigate-cms",
                    1 => t(635, "Underscore")." /navigate_cms"
                ),
                $item->word_separator
            ),
            '<span class="navigate-form-row-info">'.t(636, 'Existing paths will not be modified').'</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(433, 'Resize uploaded images').'</label>',
			$naviforms->selectfield('resize_uploaded_images',
                array(
                    0 => 0,
                    1 => 600,
                    2 => 800,
                    3 => 960,
                    4 => 1200,
                    5 => 1600,
                    6 => 2000
                ),
                array(
                    0 => t(434, 'Keep original file'),
                    1 => '600 px',
                    2 => '800 px',
                    3 => '960 px',
                    4 => '1200 px',
                    5 => '1600 px',
                    6 => '2000 px'
                ),
                $item->resize_uploaded_images
            ),
            '<span class="navigate-form-row-info">'.t(435, 'Maximum width or height').'</span>'
        )
    );

    // navigate cms 2.0.2: website->tinymce_css field is DEPRECATED (will be removed in a future revision)
    if(!empty($item->tinymce_css))
    {
        $navibars->add_tab_content_row(
            array(
                '<label>tinyMCE CSS</label>',
                $naviforms->textfield('tinymce_css', $item->tinymce_css),
                '<span class="navigate-form-row-info">'.t(230, 'Ex.').' /css/style.content.css</span>'
            )
        );
    }

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(328, 'Favicon').'</label>',
			$naviforms->dropbox('website-favicon', $item->favicon, "image")
        )
    );

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(597, 'Share files in media browser').'</label>',
			$naviforms->checkbox('share_files_media_browser', ($item->share_files_media_browser=='1')),
			'<span class="navigate-form-row-info">('.t(598, 'Only between websites of the current Navigate CMS installation').')</span>'
		)
	);

    // default comment options for elements
    $navibars->add_tab_content_row(array(
            '<label>'.t(252, 'Comments enabled for').'</label>',
            $naviforms->selectfield('comments_enabled_for',
                array(
                    0 => 0,
                    1 => 1,
                    2 => 2
                ),
                array(
                    0 => t(253, 'Nobody'),
                    1 => t(24, 'Registered users'),
                    2 => t(254, 'Everyone')
                ),
                $item->comments_enabled_for
            )
        )
    );

    $webuser_name = '';
    if($item->comments_default_moderator=="c_author")
        $webuser_name = t(545, 'Content author');
    else if(!empty($item->comments_default_moderator))
        $webuser_name = $DB->query_single('username', 'nv_users', ' id = '.intval($item->comments_default_moderator));

	$moderator_id = array('c_author');
	$moderator_username = array('{'.t(545, 'Content author').'}');
	if(!empty($item->comments_default_moderator))
	{
        if($item->comments_default_moderator!='c_author')
        {
            $moderator_username[] = $DB->query_single('username', 'nv_users', ' id = '.intval($item->comments_default_moderator));
	        $moderator_id[] = $item->comments_default_moderator;
        }
	}

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(255, 'Moderator').'</label>',
	        $naviforms->selectfield('comments_default_moderator', $moderator_id, $moderator_username, $item->comments_default_moderator, null, false, null, null, false),
            '<span style="display: none;" id="comments_default_moderator-helper">'.t(535, "Find user by name").'</span>',
            '<div class="subcomment"><img align="absmiddle" src="'.NAVIGATE_URL.'/img/icons/silk/information.png" /> '.t(256, 'Leave blank to accept all comments').'</div>'
        )
    );

    $layout->add_script('
        // comments moderator autocomplete
        $("#comments_default_moderator").select2(
        {
            placeholder: $("#comments_default_moderator-helper").text(),
            minimumInputLength: 0,
            ajax: {
                url: "?fid=items&act=json_find_user",
                dataType: "json",
                delay: 100,
                data: function (params)
		        {
		            return {
		                username: params.term,
		                nd: new Date().getTime(),
		                page_limit: 30, // page size
		                page: params.page // page number
		            };
		        },
		        processResults: function (data, params)
		        {
		            params.page = params.page || 1;
		            data.items.unshift({id: "c_author", text: "{'.t(545, 'Content author').'}" });
		            data.total_count++;
		            return {
						results: data.items,
						pagination: { more: (params.page * 30) < data.total_count }
					};
		        }
            },
            templateSelection: function(row)
			{
				if(row.id && row.id != "c_author")
					return row.text + " <helper style=\'opacity: .5;\'>#" + row.id + "</helper>";
				else
					return row.text;
			},
			escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
            triggerChange: true,
            allowClear: true
        });

        $("#comments_default_moderator-text").on("change", function(e)
        {
            $("#comments_default_moderator").val(e.val);
        });
    ');

    $navibars->add_tab_content_row(array(
            '<label>'.t(750, 'Page cache').'</label>',
            $naviforms->selectfield('page_cache',
                array(
                    0 => 0,
                    1 => 1
                ),
                array(
                    0 => t(460, 'Disable'),
                    1 => t(462, 'Enable')
                ),
                $item->page_cache
            )
        )
    );

    // (FUTURE) TAB Shop

    $navibars->add_tab_content_row(array(
            '<label>'.t(773, 'Purchase conditions').'</label>',
            $naviforms->pathfield('website-purchase_conditions_path', $item->purchase_conditions_path, null, null, null)
        )
    );


    /* TAB EMAIL */

	$navibars->add_tab(t(44, "E-Mail"));

    $navibars->add_tab_content_row(array(
            '<label>'.t(548, "Method").'</label>',
            $naviforms->buttonset(
                'mail_mailer',
                array(
                    'smtp' => 'SMTP',
                    'sendmail' => 'Sendmail',
                    'mail' => 'PHP mail'
                ),
                (empty($item->mail_mailer)? 'smtp' : $item->mail_mailer),
                "navigate_change_mail_transport(this);"
            )
        )
    );

    $layout->add_script('
        function navigate_change_mail_transport(el)
        {
            var mail_mailer = "";
            if(el=="smtp" || el=="sendmail" || el=="mail")
                mail_mailer = el;
            else
                mail_mailer = $("input#" + $(el).attr("for")).val();

            $("#mail_server").parent().show();
            $("#mail_port").parent().show();
            $("#mail_security").parent().show();
            $("#mail_user").parent().show();
            $("#mail_password").parent().show();
            if(mail_mailer=="sendmail" || mail_mailer=="mail")
            {
                $("#mail_server").parent().hide();
                $("#mail_port").parent().hide();
                $("#mail_security").parent().hide();
                $("#mail_user").parent().hide();
                $("#mail_password").parent().hide();
            }
        }

        navigate_change_mail_transport("'.(empty($item->mail_mailer)? 'smtp' : $item->mail_mailer).'");
    ');

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(231, 'Server').'</label>',
			$naviforms->textfield('mail_server', $item->mail_server),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' localhost, mail.yourdomain.com</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(232, 'Port').'</label>',
			$naviforms->textfield('mail_port', $item->mail_port),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' 25</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(427, 'Security protocol').'</label>',
			$naviforms->selectfield(
				'mail_security',
				array(0, 1, 2),
				array(
					t(581, "None"),
					"SSL / TLS",
					"STARTTLS"
				),
				$item->mail_security
			)
        )
    );


    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;</label>',
            $naviforms->checkbox('mail_ignore_ssl_security', ($item->mail_ignore_ssl_security=='1')),
            '<span>'.t(651, 'Disable SSL peer validation').'</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(1, 'User').'</label>',
			$naviforms->textfield('mail_user', $item->mail_user),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' web@yourdomain.com</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(233, 'Address').'</label>',
			$naviforms->textfield('mail_address', $item->mail_address),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' web@yourdomain.com</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(2, 'Password').'</label>',
			'<input type="password" name="mail_password" id="mail_password" autocomplete="off"  value="" size="32" />',
			'<span class="navigate-form-row-info">'.t(48, "Leave blank to keep the current value").'</span>'
        )
    );

	 // force removing the browser saved password
	$layout->add_script('
		setTimeout(function() {
			$("input[name=mail_password]").val("");
		}, 10);
	');


	if(empty($item->contact_emails))	$item->contact_emails = array();

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(263, 'Support E-Mails').'</label>',
			$naviforms->textarea('contact_emails', implode("\n", $item->contact_emails)),
			'<span class="navigate-form-row-info">'.t(264, "One entry per line").'</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>&nbsp;</label>',
			'<button id="mail_test"><img src="'.NAVIGATE_URL.'/img/icons/silk/email_go.png" align="absmiddle" /> '.t(390, "Test").'</button>'
        )
    );

	$layout->add_script('
		$("#mail_test").on("click", function()
		{
			navigate_status("'.t(391, "Trying to send a test e-mail…").'", "loader", true);
			$.ajax({
			  type: "POST",
			  url: "?fid='.$_GET['fid'].'&act=email_test",
			  data: {
				 mail_mailer: $("input[name=\"mail_mailer[]\"]:checked").val(),
				 mail_server: $("#mail_server").val(),
				 mail_port: $("#mail_port").val(),
				 mail_security: $("#mail_security").val(),
				 mail_user: $("#mail_user").val(),
				 mail_address: $("#mail_address").val(),
				 mail_password: $("#mail_password").val(),
				 mail_ignore_ssl_security: $("#mail_security_ignore_ssl").is(":checked"),
				 send_to: $("#contact_emails").val()
			  },
			  success: function(data)
			  {
				  navigate_status(navigate_lang_dictionary[42], "ready"); 
				  
				  if(!data)
				  	navigate_notification("'.t(56, "Unexpected error.").'");
				  else
				  	navigate_notification("'.t(392, "E-Mail sent").'");
			  },
			  error: function(data)
			  {
			        navigate_status(navigate_lang_dictionary[42], "ready");
			        var error_message = (data.responseText).split("<br />")[0];
			        if(error_message!="")
			            error_message = ": " + error_message;

			        navigate_notification("'.t(56, "Unexpected error.").'" + error_message, true);
			  },
			  dataType: "json"
			});
			return false;
		});
	');

    /* METATAGS TAB */
    if(!empty($item->id) && !empty($item->languages))
    {
        $navibars->add_tab(t(513, "Metatags"));

        $website_languages_selector = $item->languages();
        $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

        $navibars->add_tab_content_row(array(	'<label>'.t(63, 'Languages').'</label>',
            $naviforms->buttonset('metatags_language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);")
        ));

        $navibars->add_tab_content_row(
            array(
                '<label>'.t(67, 'Title').'</label>',
                $naviforms->selectfield(
                    'metatag_title_order',
                    array(
                        0 => "website | category | section",
                        1 => "section | category | website"
                    ),
                    array(
                        0 => t(177, "Website") . " | " . t(78, "Category") . " | " . t(239, "Section"),
                        1 =>  t(239, "Section"). " | " . t(78, "Category") . " | " . t(177, "Website")
                    ),
                    $item->metatag_title_order
                ),
            )
        );

        foreach($item->languages_list as $lang)
        {
            $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(334, 'Description').' '.$language_info.'</label>',
                    $naviforms->textfield('metatag_description-'.$lang, $item->metatag_description[$lang]),
                    '<span class="navigate-form-row-info">150-160</span>'
                ),
                '',
                'lang="'.$lang.'"'
            );


            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(536, 'Keywords').' '.$language_info.'</label>',
                    $naviforms->textfield('metatag_keywords-'.$lang, $item->metatag_keywords[$lang]),
                ),
                '',
                'lang="'.$lang.'"'
            );

	        $layout->add_script('               
                $("#metatag_keywords-'.$lang.'").tagit({
                    removeConfirmation: true,
                    allowSpaces: true,
                    singleField: true,
                    singleFieldDelimiter: ",",
                    placeholderText: "+",
                    autocomplete: 
                    {
                        delay: 0, 
                        minLength: 1,
                        source: "?fid=items&act=json_tags_search&lang='.$lang.'"
                    },
                    afterTagAdded: function(event, ui)
                    {
                        var tags = $(this).tagit("assignedTags");
                        if(tags.length > 0)
                            tags = tags.join(",");
                        else
                            tags = "";
                            
                        $("#metatag_keywords-'.$lang.'").val(tags);
                    }
                });
			');

            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(514, "Additional metatags").' '.$language_info.'</label>',
                    $naviforms->scriptarea('metatags-'.$lang, $item->metatags[$lang], 'html', ' width: 75%; height: 100px; ' )
                ),
                '',
                'lang="'.$lang.'"'
            );
        }
    }


    /* SERVICES TAB */

    $navibars->add_tab('HTML');

    $navibars->add_tab_content_row(array(
            '<label>'.t(160, "Type").'</label>',
            $naviforms->buttonset(
                'website_additional_code',
                array(
                    'tracking_scripts' => t(657, 'Tracking scripts'),
                    'additional_scripts' => t(498, 'Additional scripts'),
                    'additional_styles' => t(656, 'Additional styles')
                ),
                (!isset($_REQUEST['website_additional_code'][0])? 'tracking_scripts' : $_REQUEST['website_additional_code'][0]),
                "navigate_change_website_additional_code(this);"
            )
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(657, 'Tracking scripts').'<br /><span style="font-weight: normal;">('.t(658, "disabled for Navigate CMS users").')</span></label>',
            $naviforms->scriptarea('tracking_scripts', $item->tracking_scripts, 'js', ' width: 600px; height: 250px; ' ),
            '<div style="clear: both;"><label>&nbsp;</label>&lt;script type="text/javascript"&gt;...&lt;/script&gt;</div>'
        ),
        'tracking_scripts_wrapper'
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(498, 'Additional scripts').'</label>',
            $naviforms->scriptarea('additional_scripts', $item->additional_scripts, 'js', ' width: 600px; height: 250px; ' ),
            '<div style="clear: both;"><label>&nbsp;</label>&lt;script type="text/javascript"&gt;...&lt;/script&gt;</div>'
        ),
        'additional_scripts_wrapper'
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(656, 'Additional styles').'</label>',
            $naviforms->scriptarea('additional_styles', $item->additional_styles, 'css', ' width: 600px; height: 250px; ' ),
            '<div style="clear: both;"><label>&nbsp;</label>&lt;style&gt;...&lt;/style&gt;</div>'
        ),
        'additional_styles_wrapper'
    );

    $layout->add_script('
        function navigate_change_website_additional_code(el)
        {        
            var selected = $("input[name=\'website_additional_code[]\']:checked").val();
        
            if(typeof(el)!="undefined")
                selected = $(el).prev().attr("value");         
            
            $("#tracking_scripts_wrapper,#additional_scripts_wrapper,#additional_styles_wrapper").hide();
            $("#"+selected+"_wrapper").show();
            
            $(navigate_codemirror_instances).each(function() { this.refresh(); } );
        }
        
        navigate_change_website_additional_code();
    ');


    if(!empty($item->theme))
    {
        $navibars->add_tab(t(368, 'Theme').': '.$theme->title);

        if(!is_array($theme->options))
            $theme->options = array();

        // show a language selector (only if it's a multilanguage website and has properties)
        if(!empty($theme->options) && count($item->languages) > 1)
        {
            $website_languages_selector = $item->languages();
            $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(63, 'Languages').'</label>',
                    $naviforms->buttonset('language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);")
                ),
	            "navigate-form-tab-theme-language_selector"
            );

	        // hide languages selector if there isn't a multilanguage property
		    $layout->add_script('
				$(document).ready(function()
			    {
					if($("#navigate-form-tab-theme-language_selector").parent().find(".navigate-form-row[lang]").length < 1)
					{
						$("#navigate-form-tab-theme-language_selector").css("display", "none");
					}
			    });
		    ');
        }

        // common property: style

        // 1: get available style IDs
        $styles_values = array_keys((array)$theme->styles);
        if(!is_array($styles_values))
            $styles_values = array();

        // 2: prepare array of style ID => style name
        $styles = array();
        foreach($styles_values as $sv)
        {
            $styles[$sv] = $theme->styles->$sv->name;
            if(empty($styles[$sv]))
                $styles[$sv] = $sv;

            $styles[$sv] = $theme->t($styles[$sv]);
        }

        $property = new property();
        $property->id = 'style';
        $property->name = t(431, 'Style');
        $property->type = 'option';
        $property->options = serialize($styles);
        $property->value = $item->theme_options->style;
        $navibars->add_tab_content(
	        navigate_property_layout_field($property)
        );

        foreach($theme->options as $theme_option)
        {
            $property = new property();
            $property->load_from_theme($theme_option, $item->theme_options->{$theme_option->id});
            $navibars->add_tab_content(
	            navigate_property_layout_field($property, "", $item->id)
            );
        }

	    navigate_property_layout_scripts($item->id);
    }

    $events->trigger(
        'websites',
        'edit',
        array(
            'item' => &$item,
            'navibars' => &$navibars,
            'naviforms' => &$naviforms
        )
    );

	return $navibars->generate();
}

?>