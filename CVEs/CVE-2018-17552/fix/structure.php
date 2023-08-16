<?php
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/templates/template.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/themes/theme.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/paths/path.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/items/item.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_group.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	global $website;
	global $theme;
		
	$out = '';
	$item = new structure();
			
	switch($_REQUEST['act'])
	{
		case 'load':
        case 'edit':
		case 2: // edit/new form		
			if(!empty($_REQUEST['id']))	
				$item->load(intval($_REQUEST['id']));	
																
			if(isset($_REQUEST['form-sent']))
			{
				$item->load_from_post();
				try
				{
					$item->save();
					property::save_properties_from_post('structure', $item->id);
					$item = $item->reload();

                    // reorder associated category elements
                    if(!empty($_POST['elements-order']))
                    {
                        $response = item::reorder($_POST['elements-order']);
                        if($response!==true)
                            throw new Exception($response);
                    }

                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}

				if(!empty($item->id))
					users_log::action($_REQUEST['fid'], $item->id, 'save', $item->dictionary[$website->languages_list[0]]['title'], json_encode($_REQUEST));
			}
			else
				if(!empty($item->id))
					users_log::action($_REQUEST['fid'], $item->id, 'load', $item->dictionary[$website->languages_list[0]]['title']);
		
			$out = structure_form($item);
			break;

		case 3:
		case "reorder":
			$ok = structure::reorder($_REQUEST['parent'], $_REQUEST['children_order']);
			echo json_encode($ok);
			core_terminate();
			break;

		case "homepager":
			$node = $_REQUEST['node'];
			$website->homepage = $node;
			$ok = $website->save();
			echo json_encode($ok);
			core_terminate();
			break;

		case 4:
		case "remove":
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$structure = structure::hierarchy(-1); // root level (0) including Web node (-1)
					$out = structure_tree($structure);
                    users_log::action($_REQUEST['fid'], $item->id, 'remove');
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = structure_form($item);
				}
			}
			break;


		case 95: // free path checking
			
			$path = $_REQUEST['path'];
			
			$DB->query(
			    'SELECT type, object_id, lang
	 			 	   FROM nv_paths
					  WHERE path = :path 
					    AND website = :wid',
                'object',
                array(
                    ':wid' => $website->id,
                    ':path' => $path
                ));
						 
			$rs = $DB->result();
			
			echo json_encode($rs);
			core_terminate();		
			break;			
		
		case "category_path": // return category paths
			echo json_encode(path::loadElementPaths('structure', intval($_REQUEST['id'])));
			core_terminate();		
			break;
			
		case 'json_find_item':
            // find elements by its title
            // the items must have its own path (free OR not embedded to a category)

            $text = $_REQUEST['title'];
            if(!empty($_REQUEST['term'])) // tagit request
                $text = $_REQUEST['term'];

            $query_params = array(
                ':wid' => $website->id,
                ':text' => '%' . $text . '%'
            );

            $language_filter = "";
            if(!empty($_REQUEST['lang']))
            {
                $language_filter = ' AND nvw.lang = :lang ';
                $query_params[':lang'] = $_REQUEST['lang'];
            }

			$DB->query('
				SELECT SQL_CALC_FOUND_ROWS nvw.node_id as id, nvw.text as text
				  FROM nv_webdictionary nvw, nv_items nvi
				 WHERE nvw.node_type = "item"
				   AND nvw.node_id = nvi.id
				   AND nvw.subtype = "title"
				   AND (	nvi.association = "free" OR
				            (nvi.association = "category" AND nvi.embedding = 0)
				   )
				   '.$language_filter.'
				   AND nvw.website = :wid
				   AND nvw.website = nvi.website
				   AND nvw.text LIKE :text
		      ORDER BY nvw.text ASC
			     LIMIT '.intval($_REQUEST['page_limit']).'
			     OFFSET '.max(0, (intval($_REQUEST['page_limit']) * (intval($_REQUEST['page'])-1))),
				'array',
                $query_params
			);

            $rows = $DB->result();
            $total = $DB->foundRows();

            if(empty($_REQUEST['format']) || $_REQUEST['format']=='select2')
            {
                echo json_encode(array('items' => $rows, 'totalCount' => $total));
            }
            else if($_REQUEST['format'] == 'tagit')
            {
                $tags_json = array();
                foreach($rows as $row)
                {
                    $tags_json[] = json_decode('{ "id": "'.$row['id'].'", "label": "'.$row['text'].'", "value": "'.$row['text'].'" }');
                }
                echo json_encode($tags_json);
            }

			core_terminate();					
			break;


        case 'json_find_structure':
            // find elements by its title
            // the items must have its own path (free OR not embedded to a category)

            $text = $_REQUEST['title'];
            if(!empty($_REQUEST['term'])) // tagit request
                $text = $_REQUEST['term'];

            $query_params = array(
                ':wid' => $website->id,
                ':text' => '%'.$text.'%'
            );

            $language_filter = "";
            if(!empty($_REQUEST['lang']))
            {
                $language_filter = ' AND nvw.lang = :lang ';
                $query_params[':lang'] = $_REQUEST['lang'];
            }

			$DB->query('
				SELECT SQL_CALC_FOUND_ROWS nvw.node_id as id, nvw.text as text
				  FROM nv_webdictionary nvw, nv_structure nvs
				 WHERE nvw.node_type = "structure"
				   AND nvw.node_id = nvs.id
				   AND nvw.subtype = "title"
				   '.$language_filter.'
				   AND nvw.website = :wid
				   AND nvw.website = nvs.website
				   AND nvw.text LIKE :text
		      ORDER BY nvw.text ASC
			     LIMIT '.intval($_REQUEST['page_limit']).'
			     OFFSET '.max(0, (intval($_REQUEST['page_limit']) * (intval($_REQUEST['page'])-1))),
				'array'
			);

            $rows = $DB->result();
            $total = $DB->foundRows();

            if(empty($_REQUEST['format']) || $_REQUEST['format']=='select2')
            {
                echo json_encode(array('items' => $rows, 'totalCount' => $total));
            }
            else if($_REQUEST['format'] == 'tagit')
            {
                $tags_json = array();
                foreach($rows as $row)
                {
                    $tags_json[] = json_decode('{ "id": "'.$row['id'].'", "label": "'.$row['text'].'", "value": "'.$row['text'].'" }');
                }
                echo json_encode($tags_json);
            }

			core_terminate();
			break;

		case "search_by_title":  // json search title request (for "copy from" properties dialog)
			$DB->query('
				SELECT node_id as id, text as label, text as value
					  FROM nv_webdictionary
					 WHERE node_type = "structure"
					   AND subtype = "title"
					   AND lang = :lang 
					   AND website = :wid
					   AND text LIKE :text
			      ORDER BY text ASC
				     LIMIT 30',
					'array',
                array(
                    ':wid' => $website->id,
                    ':lang' => $_REQUEST['lang'],
                    ':text' => '%'.$_REQUEST['title'].'%'
                )
			);

			echo json_encode($DB->result());

			core_terminate();
			break;

		case "copy_from_template_zones":
            // return template properties for a structure id
			$item = new structure();
			$item->load(intval($_REQUEST['id']));
			$template = new template();
			$template->load($item->template);

			$zones = array();

			for($ps=0; $ps < count($template->properties); $ps++)
			{
				// ignore non structure properties
				if(!isset($template->properties[$ps]->element) || $template->properties[$ps]->element != 'structure')
					continue;

				// ignore non-textual properties
				if(!in_array($template->properties[$ps]->type, array("text", "textarea", "rich_textarea")))
					continue;

				$title = $template->properties[$ps]->name;
				if(!empty($theme))
					$title = $theme->t($title);

				$zones[] = array(
		            'type' => 'property',
		            'code' => $template->properties[$ps]->id,
		            'title' => $title
	            );
			}

			echo json_encode($zones);

			core_terminate();
			break;

		case "raw_zone_content": // return raw item contents

			if($_REQUEST['zone'] == 'property')
			{
				$DB->query(
				    'SELECT text
                      FROM nv_webdictionary
                     WHERE node_type = "property-structure"
                       AND subtype = :subtype
                       AND lang = :lang							   
                       AND website = :wid
                       AND node_id = :node_id',
                    'array',
                    array(
                        ':wid' => $website->id,
                        ':lang' => $_REQUEST['lang'],
                        ':subtype' => 'property-'.$_REQUEST['section'].'-'.$_REQUEST['lang'],
                        ':node_id' => $_REQUEST['node_id']
                    ));

				$data = $DB->first();
				echo $data['text'];
			}

			core_terminate();
			break;

			
		case 'votes_reset':
			webuser_vote::remove_object_votes('structure', intval($_REQUEST['id']));
			echo 'true';
			core_terminate();
			break;

		case 'votes_by_webuser':
			if($_POST['oper']=='del')
			{
				$ids = explode(',', $_POST['id']);
				
				for($i=0; $i < count($ids); $i++)
				{
					if($ids[$i] > 0)	
					{
						$vote = new webuser_vote();
						$vote->load($ids[$i]);
						$vote->delete();	
					}
				}
				
				webuser_vote::update_object_score('structure', $vote->object_id);
					
				echo 'true';
				core_terminate();	
			}
		
			$max = intval($_GET['rows']);
			$page = intval($_GET['page']);
			$offset = ($page - 1) * $max;	
		
			if($_REQUEST['_search']=='false')
				list($dataset, $total) = webuser_vote::object_votes_by_webuser('structure', intval($_REQUEST['id']), $_REQUEST['sidx'].' '.$_REQUEST['sord'], $offset, $max);
		
			$out = array();								
			for($i=0; $i < count($dataset); $i++)
			{
				if(empty($dataset[$i])) continue;
														
				$out[$i] = array(
					0	=> $dataset[$i]['id'],
					1 	=> core_ts2date($dataset[$i]['date'], true),
					2	=> $dataset[$i]['username']
				);
			}

			navitable::jqgridJson($out, $page, $offset, $max, $total);
			core_terminate();
			break;

		
		case 0: // tree / search result
		default:			
			$structure = structure::hierarchy(-1); // root level (0) including Web node (-1)
			$out = structure_tree($structure);
			break;
	}
	
	return $out;
}

function structure_tree($hierarchy)
{
	global $layout;
	global $website;
		
	$navibars = new navibars();
	$navitree = new navitree("structure-".$website->id);
	
	$navibars->title(t(16, 'Structure'));

	$navibars->add_actions(
		array(
			'<a href="#" onclick="javascript: navigate_structure_expand();" data-action="nv_structure_expand">
				<img height="16" align="absmiddle" width="16" src="img/icons/silk/arrow_out.png"> '.t(295, 'Expand all').'
			</a>',
			'<a href="#" onclick="javascript: navigate_structure_collapse();" style="display: none;"  data-action="nv_structure_collapse">
				<img height="16" align="absmiddle" width="16" src="img/icons/silk/arrow_in.png"> '.t(508, 'Collapse').'
			</a>'
		)
	);

	$navibars->add_actions(
		array(
			'<a href="?fid=structure&act=edit">
				<img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'
			</a>',
			'search_form'
		)
	);
	
	$navitree->setURL('?fid=structure&act=edit&id=');
	$navitree->addURL('?fid=structure&act=edit&parent=');
	$navitree->orderURL('?fid=structure&act=3');
	$navitree->homepagerURL('?fid=structure&act=homepager');

	$access = array(
		0 => '<img src="img/icons/silk/page_white_go.png" align="absmiddle" title="'.t(254, 'Everybody').'" />',
		1 => '<img src="img/icons/silk/lock.png" align="absmiddle" title="'.t(361, 'Web users only').'" />',
		2 => '<img src="img/icons/silk/user_gray.png" align="absmiddle" title="'.t(363, 'Users who have not yet signed up or signed in').'" />',
        3 => '<img src="img/icons/silk/group_key.png" align="absmiddle" title="'.t(512, "Selected web user groups").'" />'
	);	
	
	$permissions = array(
		0 => '<img src="img/icons/silk/world.png" align="absmiddle" /> '.t(69, 'Published'),
		1 => '<img src="img/icons/silk/world_dawn.png" align="absmiddle" /> '.t(70, 'Private'),
		2 => '<img src="img/icons/silk/world_night.png" align="absmiddle" /> '.t(81, 'Hidden')
	);

    /* LANGUAGE SELECTOR */

    $lang_selector[] = '<ul id="structure-language-selector">';

    foreach($website->languages_list as $lang)
    {
        $lang_selector[] = '<li><a href="#" language="'.$lang.'">
                                <span class="ui-icon ui-icon-carat-1-e"></span> '.language::name_by_code($lang).'
                            </a></li>';
    }

    $navitree->setLanguages($website->languages_list);

    $lang_selector[] = '</ul>';
    $lang_selector = '&nbsp;<span id="structure-language-selector-icon" style="cursor: pointer;">'.
                        '<img src="img/icons/silk/comment.png" align="absmiddle" />'.
                        '<span class="structure-language-selector-name" style="font-size: 9px; font-weight: normal;">'.language::name_by_code($website->languages_list[0]).'</span>'.
                        '&#9662;'.
                        '</span>'.
                        implode("\n", $lang_selector);

    $layout->add_script('
        $("#structure-language-selector").menu();

        $("#structure-language-selector").css({
            "position": "absolute",
            "top": $("#structure-language-selector-icon").offset().top + 16,
            "left": $("#structure-language-selector-icon").offset().left,
            "z-index": 1000
        });
        $("#structure-language-selector").addClass("navi-ui-widget-shadow");

        $("#structure-language-selector a").each(function(i, el)
        {
            $(el).on("click", function()
            {
                var lang = $(this).attr("language");
                $(".navitree-text").hide();
                $(".navitree-text[language="+lang+"]").show();
                $(".structure-language-selector-name").text($(this).text());
            });
        });

        $("#structure-language-selector").hide();

        $("#structure-language-selector-icon").on("click", function()
        {
            $("#structure-language-selector").show();
        });
    ');

	$columns = array();
	$columns[] = array(	'name' => 'ID', 'property' => 'id', 'type' => 'text', 'width' => '5%', 'align' => 'left' );
	$columns[] = array(	'name' => t(67, 'Title').' '.$lang_selector, 'property'	=> 'dictionary|title', 'type' => 'text', 'width' => '53%', 	'align' => 'left'	);
	$columns[] = array(	'name' => t(73, 'Children'),	'property'	=> 'children', 	'type'	=> 'count', 	'width' => '5%', 	'align' => 'center'	);
	$columns[] = array(	'name' => t(79, 'Template'),	'property'	=> 'template_title', 	'type'	=> 'text', 	    'width' => '12%', 	'align' => 'left'	);
	$columns[] = array(	'name' => t(85, 'Date published'), 'property'	=> 'dates', 'type'	=> 'text', 		'width' => '10%', 	'align' => 'center'	);
	$columns[] = array(	'name' => '<span title="'.t(283, 'Show in menus').'">'.t(76, 'Visible').'</span>', 'property'	=> 'visible', 'type'	=> 'boolean',	'width' => '4%', 	'align' => 'center'	);
	$columns[] = array(	'name' => t(364, 'Access'), 'property'	=> 'access',	'type'	=> 'option', 	'width' => '4%', 	'align' => 'center', 	'options' => $access);
	$columns[] = array(	'name' => t(68, 'Status'), 'property'	=> 'permission',	'type'	=> 'option', 	'width' => '7%', 	'align' => 'center', 	'options' => $permissions);
	
	$navitree->setColumns($columns);

	$layout->add_script('
		function navitable_quicksearch(search)
		{
			$("#structure-'.$website->id.'").find("tr").each(function()
			{
				$(this).css("display", "");

				if($(this).find("th").length > 0) return;

				search = search.toLowerCase();

				var td_string = $(this).find("td").eq(1).html().toLowerCase();

				if(td_string.indexOf(search) < 0)
					navigate_structure_hide_table_row(this);
			});
		};

		function navigate_structure_hide_table_row(el)
		{
			$(el).css("display", "none");
		}
	');

	if(!empty($_REQUEST['navigate-quicksearch']))
	{
		$navitree->setState('expanded');
		$layout->add_script('
			$(window).on("load", function() { navitable_quicksearch("'.$_REQUEST['navigate-quicksearch'].'");});
		');
	}

	$navitree->setData($hierarchy);

	$navitree->setTreeColumn(1);
	
	$navibars->add_content('<div id="navigate-content-safe" class="ui-corner-all">'.$navitree->generate().'</div>');	
	
	$layout->add_script('
		function navigate_structure_expand()
		{
			var parents = 0;
			
			while(parents < $(".parent").length)
			{
				parents = $(".parent").length;
				$(".parent").expand();
			}

			// change action to collapse
			$("a[data-action=\"nv_structure_collapse\"]").css("display", "block");
			$("a[data-action=\"nv_structure_expand\"]").css("display", "none");
		}

		function navigate_structure_collapse()
		{
			$(".parent.expanded").each(function()
			{
				if($(this).data("node-id") == 0)
					return;

				$(".child-of-node-" + $(this).data("node-id")).removeClass("expanded").addClass("collapsed").css("display", "none");
				$("#node-" + $(this).data("node-id")).addClass("collapsed").removeClass("expanded");
			});

			// change action to expand
			$("a[data-action=\"nv_structure_collapse\"]").css("display", "none");
			$("a[data-action=\"nv_structure_expand\"]").css("display", "block");
		}

	');

	return $navibars->generate();
}

function structure_form($item)
{
	global $DB;
	global $website;
	global $layout;
    global $events;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	$layout->navigate_media_browser();	// we can use media browser in this function
	
	if(empty($item->id))
		$navibars->title(t(16, 'Structure').' / '.t(38, 'Create'));
	else
		$navibars->title(t(16, 'Structure').' / '.t(170, 'Edit').' ['.$item->id.']');

	$navibars->add_actions(
	    array(
	        '<a href="#" onclick="javascript: navigate_media_browser();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'
        )
    );

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

    $extra_actions = array();

    if(!empty($item->id))
    {
        $DB->query('
            SELECT s.id, wd.text as title, s.position
              FROM nv_structure s, nv_webdictionary wd
             WHERE s.website = '.$item->website.'
               AND s.parent = '.$item->parent.'
               AND wd.website  = '.$item->website.'
               AND wd.node_type = "structure"
               AND wd.lang = "'.$website->languages_list[0].'"
               AND wd.subtype = "title"
               AND wd.node_id = s.id
          ORDER BY s.position ASC, s.id ASC
        ');

        $brothers = $DB->result();

        $previous_brother = NULL;
        $next_brother = NULL;
        for($b=0; $b < count($brothers); $b++)
        {
            if($brothers[$b]->id == $item->id)
            {
                $previous_brother = @$brothers[$b-1]->id;
                $previous_brother_title = @$brothers[$b-1]->title;
                $next_brother = @$brothers[$b+1]->id;
                $next_brother_title = @$brothers[$b+1]->title;
            }
        }

        if(!empty($item->parent))
        {
            $parent = new structure();
            $parent->load($item->parent);

            $extra_actions[] = '    <a href="?fid=structure&act=edit&id='.$parent->id.'">
                                        <img height="16" align="absmiddle" width="16" src="img/icons/silk/resultset_first.png"> 
                                        <small>('.mb_strtolower(t(84, 'Parent')).')</small> '.$parent->dictionary[$website->languages_list[0]]["title"].
                                    '</a>';
        }

        if(!empty($previous_brother))
            $extra_actions[] = '    <a href="?fid=structure&act=edit&id='.$previous_brother.'">
                                        <img height="16" align="absmiddle" width="16" src="img/icons/silk/resultset_previous.png"> 
                                        <small>('.mb_strtolower(t(501, 'Previous')).')</small> '.$previous_brother_title.
                                    '</a>';

        if(!empty($next_brother))
            $extra_actions[] = '    <a href="?fid=structure&act=edit&id='.$next_brother.'">
                                        <img height="16" align="absmiddle" width="16" src="img/icons/silk/resultset_next.png"> 
                                        <small>('.mb_strtolower(t(502, 'Next')).')</small> '.$next_brother_title.
                                    '</a>';
    }

    $events->add_actions(
        'structure',
        array(
            'item' => &$item,
            'navibars' => &$navibars
        ),
        $extra_actions
    );
	
	$navibars->add_actions(
	    array(
	        (!empty($item->id)? '<a href="?fid=structure&act=edit&parent='.$item->parent.'&template='.$item->template.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=structure&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/sitemap_color.png"> '.t(61, 'Tree').'</a>',
			'search_form'
        )
    );

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	
	$navibars->add_tab_content_row(
	    array(
	        '<label>ID</label>',
			'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>'
        )
    );

	if(empty($item->id))
		$item->parent = $_GET['parent'];

	$navibars->add_tab_content($naviforms->hidden('id', $item->id));
	//$navibars->add_tab_content($naviforms->hidden('parent', $item->parent));

	$hierarchy = structure::hierarchy(0);
	$categories_list = structure::hierarchyList($hierarchy, $item->parent);

    if(empty($categories_list))
        $categories_list = '<ul><li value="0">'.t(428, '(no category)').'</li></ul>';

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(84, 'Parent').'</label>',
            $naviforms->dropdown_tree('parent', $categories_list, $item->parent, 'navigate_parent_category_change')
        ),
		'category_tree'
    );

    $layout->add_script('
        function navigate_parent_category_change(id)
        {
            $.ajax(
            {
                url: NAVIGATE_APP + "?fid=structure&act=category_path&id=" + id,
                dataType: "json",
                data: {},
                success: function(data, textStatus, xhr)
                {
                    item_category_path = data;
                }
            });
        }
    ');

    if(empty($item->template) && isset($_GET['template']))
        $item->template = $_GET['template'];

	$templates = template::elements('structure');
	$template_select = $naviforms->select_from_object_array('template', $templates, 'id', 'title', $item->template);
										                    
	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(79, 'Template').'</label>',
		    $template_select
        )
    );
										
	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(85, 'Date published').'</label>',
		    $naviforms->datefield('date_published', $item->date_published, true)
        )
    );
										
	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(90, 'Date unpublished').'</label>',
		    $naviforms->datefield('date_unpublish', $item->date_unpublish, true)
	    )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(364, 'Access').'</label>',
            $naviforms->selectfield('access',
                array(
                        0 => 0,
                        1 => 2,
                        2 => 1,
                        3 => 3
                    ),
                array(
                        0 => t(254, 'Everybody'),
                        1 => t(362, 'Not signed in'),
                        2 => t(361, 'Web users only'),
                        3 => t(512, 'Selected web user groups')
                    ),
                $item->access,
                'navigate_webuser_groups_visibility($(this).val());',
                false,
                array(
                        1 => t(363, 'Users who have not yet signed in')
                )
            )
        )
    );

    $webuser_groups = webuser_group::all_in_array();

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(506, "Groups").'</label>',
            $naviforms->multiselect(
                'groups',
                array_keys($webuser_groups),
                array_values($webuser_groups),
                $item->groups
            )
        ),
        'webuser-groups-field'
    );

    $layout->add_script('
        function navigate_webuser_groups_visibility(access_value)
        {
            if(access_value==3)
                $("#webuser-groups-field").show();
            else
                $("#webuser-groups-field").hide();
        }

        navigate_webuser_groups_visibility('.$item->access.');
    ');

																				
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(68, 'Status').'</label>',
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
									
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(283, 'Shown in menus').'</label>',
			$naviforms->checkbox('visible', $item->visible)
		)
	);

    if($item->views > 0)
    {
        $navibars->add_tab_content_row(
            array(
                '<label>'.t(280, 'Page views').'</label>',
                $item->views
            )
        );
    }
									
											
	$navibars->add_tab(t(54, "Text").' / '.t(74, "Paths"));

	$lang_selector = array();
	$lang_selector[] = '<div class="buttonset">';
	$checked = ' checked="checked" ';	

	foreach($website->languages_list as $lang_code)
	{	
		$lang_selector[] = '<input type="radio" id="language_selector_'.$lang_code.'" name="language_selector" value="'.$lang_code.'" '.$checked.' />
							<label for="language_selector_'.$lang_code.'"  onclick="navigate_structure_select_language(\''.$lang_code.'\');">'.language::name_by_code($lang_code).'</label>';
		$checked = "";
	}
	$lang_selector[] = '</div>';
	
	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(63, 'Languages').'</label>',
			implode("\n", $lang_selector)
        )
    );
											
	foreach($website->languages_list as $lang_code)
	{		
		$navibars->add_tab_content('<div class="language_fields" id="language_fields_'.$lang_code.'" style=" display: none; ">');
		
		$navibars->add_tab_content_row(
		    array(
		        '<label>'.t(67, 'Title').'</label>',
				$naviforms->textfield('title-'.$lang_code, @$item->dictionary[$lang_code]['title'])
            )
        );
											
		$open_live_site = '';												
		if(!empty($item->paths[$lang_code]))
			$open_live_site = ' <a target="_blank" href="'.$website->absolute_path(true).$item->paths[$lang_code].'"><img src="img/icons/silk/world_go.png" align="absmiddle" /></a>';
											
											
		$navibars->add_tab_content_row(
            array(
                '<label>'.t(75, 'Path').$open_live_site.'</label>',
				$naviforms->textfield('path-'.$lang_code, @$item->paths[$lang_code], NULL, 'navigate_structure_path_check(this);'),
				'<span>&nbsp;</span>'
            )
        );
		/*									
		$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
												'<div class="subcomment"><sup>*</sup> '.t(83, 'Leave blank to disable this item').'</div>',
											));		
		*/
											
		$navibars->add_tab_content_row(
            array(
                '<label>'.t(172, 'Action').'</label>',
                $naviforms->selectfield('action-type-'.$lang_code,
                    array(
                            0 => 'url',
                            1 => 'jump-branch',
                            2 => 'jump-item',
                            3 => 'masked-redirect',
                            4 => 'do-nothing'
                        ),
                    array(
                            0 => t(173, 'Open URL'),
                            1 => t(322, 'Jump to another branch'),
                            2 => t(323, 'Jump to an element'),
                            3 => t(688, 'Masked redirect'),
                            4 => t(183, 'Do nothing')
                        ),
                    $item->dictionary[$lang_code]['action-type'],
                    "navigate_structure_action_change('".$lang_code."', this);"
                )
            )
        );

		// load item title if action was "jump to an element"				
		$jump_item_id = '';
		$jump_item_title = '';
		if(!empty($item->dictionary[$lang_code]['action-jump-item']))
		{
			$tmp = new Item();
			$tmp->load($item->dictionary[$lang_code]['action-jump-item']);
			$jump_item_title = array($tmp->dictionary[$lang_code]['title']);
			$jump_item_id = array($item->dictionary[$lang_code]['action-jump-item']);
		}
        $navibars->add_tab_content_row(array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(180, 'Item').' ['.t(67, 'Title').']</label>',
			$naviforms->selectfield('action-jump-item-'.$lang_code, $jump_item_id, $jump_item_title, $item->dictionary[$lang_code]['action-jump-item'], null, false, null, null, false),
            '<div class="subcomment"><span class="ui-icon ui-icon-info" style=" float: left; margin-left: -3px; "></span> '.
                t(534, "You can only select elements which have their own path (no category embedded elements)").
            '</div>'
		));

        // show URL if action was "masked-redirect"
        $navibars->add_tab_content_row(array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(75, 'Path').'</label>',
            $naviforms->textfield('action-masked-redirect-'.$lang_code, $item->dictionary[$lang_code]['action-masked-redirect']),
            '<div class="subcomment"><span class="ui-icon ui-icon-info" style=" float: left; margin-left: -3px; "></span> '.
                '<span>'.t(689, "Load the content of an internal path without changing the browser URL").'</span>'.
            '</div>'
        ));


		$categories_list = structure::hierarchyList($hierarchy, $item->dictionary[$lang_code]['action-jump-branch'], $lang_code);

		$navibars->add_tab_content_row(
            array(
                '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(325, 'Branch').'</label>',
				'<div class="category_tree" id="category_tree_jump_branch_'.$lang_code.'">
				        <img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.
                        '<div class="category_tree_ul">'.$categories_list.'</div>'.
                '</div>',
				$naviforms->hidden('action-jump-branch-'.$lang_code, $item->dictionary[$lang_code]['action-jump-branch'])
            )
        );
										
		$navibars->add_tab_content_row(
            array(
                '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(324, 'New window').'</label>',
				$naviforms->checkbox('action-new-window-'.$lang_code, $item->dictionary[$lang_code]['action-new-window'])
            )
        );
										
		$navibars->add_tab_content('</div>');		
	}
	
	$parent = new structure();
	$parent->paths = array();
	if(!empty($item->parent))
		$parent->load($item->parent);
	
	$layout->add_script('
		function navigate_structure_select_language(code)
		{
			$(".language_fields").css("display", "none");
			$("#language_fields_" + code).css("display", "block");
		}
		
		var active_languages = ["'.implode('", "', $website->languages_list).'"];
		var last_check = [];
		var item_category_path = '.json_encode($parent->paths).';
		
		function navigate_structure_path_generate(el)
		{
			var language = $(el).attr("id").substr(5);
			var surl = "";
			if(item_category_path[language] && item_category_path[language]!="")
				surl = item_category_path[language];
			else
				surl = "/" + language;
			var title = $("#title-"+language).val();
            title = title.replace(/([\'"“”«»?:\+\&!¿#\\\\])/g, "");
			title = title.replace(/[.\s]+/g, navigate["word_separator"]);

			surl += "/" + title;
			$(el).val(surl.toLowerCase());
			navigate_structure_path_check(el);
		}		
		
		function navigate_structure_path_check(el)		
		{
		    var caret_position = null;
            if($(el).is("input") && $(el).is(":focus"))
                caret_position = $(el).caret();

			var path = $(el).val();
			
			if(path=="") return;			
			if(path==last_check[$(el).id]) return;
			if(path.indexOf("http")==0) return; // ignore paths starting with http/https

            path = path.replace(/([\'"“”«»?:\+\&!¿#\\\\])/g, "");
			path = path.replace(/[.\s]+/g, navigate["word_separator"]);

			$(el).val(path);
			
			last_check[$(el).id] = path;
			
			$(el).next().html("<img src=\"'.NAVIGATE_URL.'/img/loader.gif\" align=\"absmiddle\" />");
			
			$.ajax({
			  url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=95",
			  dataType: "json",
			  data: "id='.$item->id.'&path=" + $(el).val(),
			  type: "get",
			  success: function(data, textStatus)
			  {
				  var free = true;

				  if(data && data.length==1)
				  {
					 // same element?
					 if( data[0].object_id != "'.$item->id.'" ||
						 data[0].type != "structure" )
					 {
						free = false; 
					 }
				  }
				  else if(data && data.length > 1)
				  {
					  free = false;
				  }
				  
				  if(free)	free = "<img src=\"'.NAVIGATE_URL.'/img/icons/silk/tick.png\" align=\"absmiddle\" />";
				  else		free = "<img src=\"'.NAVIGATE_URL.'/img/icons/silk/cancel.png\" align=\"absmiddle\" />";

                  free += "<img class=\"erase_path\" src=\"" + NAVIGATE_URL + "/img/icons/silk/erase.png\" align=\"absmiddle\" />";
                  $(el).next().find(".erase_path").off();
                  $(el).next().html(free);
                  $(el).next().find(".erase_path").on("click", function()
                  {
                    $(el).focus();
                    $(el).val("");
                  }).css("cursor", "pointer");
			  }
			});

            if($(el).is("input") && $(el).is(":focus"))
            $(el).caret(caret_position)
		}
				
		function navigate_structure_action_change(language, element)
		{			
			$("#action-new-window-" + language).parent().hide();
			$("#action-jump-item-" + language).parent().hide();
			$("#action-masked-redirect-" + language).parent().hide();
			$("#action-jump-branch-" + language).parent().hide();			
			
			switch(jQuery(element).val())
			{
				case "do-nothing":
				
					break;
					
				case "jump-branch":
					$("#action-new-window-" + language).parent().show();
					$("#action-jump-branch-" + language).parent().show();
					
					$("#category_tree_jump_branch_" + language+ " .category_tree_ul").jstree({
                        plugins: ["changed", "types"],
                        "types" : 
                        {
                            "default":  {   "icon": "img/icons/silk/folder.png"    },
                            "leaf":     {   "icon": "img/icons/silk/page_white.png"      }
                        },
                        "core" : 
                        {
                            "multiple" : false
                        }
					}).on("changed.jstree", function(e, data) 
					{
                        var i, j, r = [];
                        for(i = 0, j = data.selected.length; i < j; i++) 
                        {
                            var selected_node = data.instance.get_node(data.selected[i]).data.nodeId;
                            $("#action-jump-branch-" + language).val(selected_node);
                        }
                    });                    
					break;
					
				case "jump-item":
					$("#action-new-window-" + language).parent().show();
					$("#action-jump-item-" + language).parent().show();
					break;		
								
				case "masked-redirect":
					$("#action-masked-redirect-" + language).parent().show();
					break;
					
				case "url":
					$("#action-new-window-" + language).parent().show();
					break;
			}
	
		}
		
		$(window).on("load", function()
		{
			for(al in active_languages)
			{
				navigate_structure_path_check($("#path-" + active_languages[al]));
				
				$("#path-" + active_languages[al]).on("focus", function()
				{
					if($(this).val() == "")
						navigate_structure_path_generate($(this));
				});

                $("#action-jump-item-" + active_languages[al]).select2(
                {
                    placeholder: "'.t(533, "Find element by title").'",
                    minimumInputLength: 1,
                    ajax: {
                        url: NAVIGATE_APP + "?fid=" + navigate_query_parameter(\'fid\') + "&act=json_find_item",
                        dataType: "json",
                        delay: 100,
                        data: function(params)
                        {
	                        return {
				                title: params.term,
				                lang: $("input[name=\"language_selector\"]:checked").val(),
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

				navigate_structure_action_change(active_languages[al], $("#action-type-" + active_languages[al]));
			}
		});
				
	');
	
	$layout->add_script('navigate_structure_select_language("'.$website->languages_list[0].'")');	

	if(!empty($item->template))
	{
		$properties_html = navigate_property_layout_form('structure', $item->template, 'structure', $item->id);

		if(!empty($properties_html))
		{
			$navibars->add_tab(t(77, "Properties"));
			$navibars->add_tab_content($properties_html);
		}
	}
		
	if($item->votes > 0)
	{
		$navibars->add_tab(t(352, "Votes"));
		
		$score = $item->score / $item->votes;			
		
		$navibars->add_tab_content_panel('<img src="img/icons/silk/chart_pie.png" align="absmiddle" /> '.t(337, 'Summary'), 
										 array(	'<div class="navigate-panels-summary ui-corner-all"><h2>'.$item->votes.'</h2><br />'.t(352, 'Votes').'</div>',
												'<div class="navigate-panels-summary ui-corner-all""><h2>'.$score.'</h2><br />'.t(353, 'Score').'</div>',
												'<div style=" float: left; margin-left: 8px; "><a href="#" class="uibutton" id="items_votes_webuser">'.t(15, 'Users').'</a></div>',
												'<div style=" float: right; margin-right: 8px; "><a href="#" class="uibutton" id="items_votes_reset">'.t(354, 'Reset').'</a></div>',
												'<div id="items_votes_webuser_window" style=" display: none; width: 600px; height: 350px; "></div>'
										 ), 
										 'navigate-panel-web-summary', '385px', '200px');	
											
										 
		$layout->add_script('
			$("#items_votes_reset").bind("click", function()
			{
				$.post("?fid='.$_REQUEST['fid'].'&act=votes_reset&id='.$item->id.'", function(data)
				{
					$("#navigate-panel-web-summary").addClass("ui-state-disabled");
					navigate_notification("'.t(355, 'Votes reset').'");
				});
			});
			
			$("#items_votes_webuser").bind("click", function()
			{
				$( "#items_votes_webuser_window" ).dialog(
				{
					title: "'.t(357, 'User votes').'",
					width: 700,
					height: 400,
					modal: true,
					open: function()
					{
						$( "#items_votes_webuser_window" ).html("<table id=\"items_votes_webuser_grid\"></table>");
						$( "#items_votes_webuser_window" ).append("<div id=\"items_votes_webuser_grid_pager\"></div>");
						
						jQuery("#items_votes_webuser_grid").jqGrid(
						{
						  url: "?fid='.$_REQUEST['fid'].'&act=votes_by_webuser&id='.$item->id.'",
						  editurl: "?fid='.$_REQUEST['fid'].'&act=votes_by_webuser&id='.$item->id.'",
						  datatype: "json",
						  mtype: "GET",
						  pager: "#items_votes_webuser_grid_pager",	
						  colNames:["ID", "'.t(86, 'Date').'", "'.t(1, 'Username').'"],
						  colModel:[
							{name:"id", index:"id", width: 75, align: "left", sortable:true, editable:false, hidden: true},
							{name:"date",index:"date", width: 180, align: "center", sortable:true, editable:false},
							{name:"username", index:"username", align: "left", width: 380, sortable:true, editable:false}
							
						  ],
						  scroll: 1,
						  loadonce: false,
						  autowidth: true,
						  forceFit: true,
						  rowNum: 12,
						  rowList: [12],	
						  viewrecords: true,
						  multiselect: true,		  
						  sortname: "date",
						  sortorder: "desc"
						});	
						
						$("#items_votes_webuser_grid").jqGrid(	"navGrid", 
																"#items_votes_webuser_grid_pager", 
																{
																	add: false,
																	edit: false,
																	del: true,
																	search: false
																}
															);
					}
				});
			});				
		');
		
		
		$navibars->add_tab_content_panel('<img src="img/icons/silk/chart_line.png" align="absmiddle" /> '.t(353, 'Score'), 
										 array(	'<div id="navigate-panel-web-score-graph" style=" height: 171px; width: 385px; "></div>' ), 
										 'navigate-panel-web-score', '385px', '200px');	
																					 
		$votes_by_score = webuser_vote::object_votes_by_score('structure', $item->id);
		
		$gdata = array();
		
		$colors = array(
			'#0a2f42',				
			'#62bbe8',
			'#1d8ec7',
			'#44aee4',
			'#bbe1f5'
		);
		
		foreach($votes_by_score as $vscore)
		{
			$gdata[] = (object) array(
				'label' => $vscore->value, 
				'data' => (int)$vscore->votes,
				'color' => $colors[($vscore->value % count($colors))]
			);
		}		
												
		$layout->add_script('
			$(document).ready(function()
			{		
				var gdata = '.json_encode($gdata).';				
			
				$.plot($("#navigate-panel-web-score-graph"), gdata,
				{						
						series: 
						{
							pie: 
							{
								show: true,
								radius: 1,
								tilt: 0.5,
								startAngle: 3/4,
								label: 
								{
									show: true,
									formatter: function(label, series)
									{
										return \'<div style="font-size:12px;text-align:center;padding:2px;color:#fff;"><span style="font-size: 20px; font-weight: bold; ">\'+label+\'</span><br/>\'+Math.round(series.percent)+\'% (\'+series.data[0][1]+\')</div>\';
									},
									background: { opacity: 0.6 }
								},
								stroke: 
								{
									color: "#F2F5F7",
									width: 4
								},
							}
						},
						legend: 
						{
							show: false
						}
				});
		');		
		
		$navibars->add_tab_content_panel(
		    '<img src="img/icons/silk/chart_line.png" align="absmiddle" /> '.t(352, 'Votes').' ('.t(356, 'last 90 days').')',
			array(	'<div id="navigate-panel-web-votes-graph" style=" height: 171px; width: 385px; "></div>' ),
			'navigate-panel-web-votes',
            '385px',
            '200px'
        );

		$votes_by_date = webuser_vote::object_votes_by_date('structure', $item->id, 90);

		$layout->add_script('								
				var plot = $.plot(
					$("#navigate-panel-web-votes-graph"), 
					['.json_encode($votes_by_date).'], 
					{
						series:
						{
							points: { show: true, radius: 3 }
						},
						xaxis: 
						{ 
							mode: "time", 
							tickLength: 5
						},
						yaxis:
						{
							tickDecimals: 0,
							zoomRange: false,
							panRange: false
						},
						grid: 
						{ 
							markings: function (axes) 
							{
								var markings = [];
								var d = new Date(axes.xaxis.min);
								// go to the first Saturday
								d.setUTCDate(d.getUTCDate() - ((d.getUTCDay() + 1) % 7))
								d.setUTCSeconds(0);
								d.setUTCMinutes(0);
								d.setUTCHours(0);
								var i = d.getTime();
								do {
									// when we dont set yaxis, the rectangle automatically
									// extends to infinity upwards and downwards
									markings.push({ xaxis: { from: i, to: i + 2 * 24 * 60 * 60 * 1000 } });
									i += 7 * 24 * 60 * 60 * 1000;
								} while (i < axes.xaxis.max);
						
								return markings;
							},
							markingsColor: "#e7f5fc"								
						},
						zoom: 
						{
							interactive: true
						},
						pan: 
						{
							interactive: true
						}
					});
				});							
		');
	}

    $elements = $item->elements();

    if(count($elements) > 0)
    {
        $ids = array();

        $navibars->add_tab(t(22, "Elements"));

        $table = new naviorderedtable("structure_elements");

        $table->setDblclickCallback("structure_elements_open");
        $table->setHiddenInput('elements-order');

        $table->addHeaderColumn('ID', 24);
        $table->addHeaderColumn(t(486, 'Title'), 500);

        foreach($elements as $element)
        {
            $table->addRow($element->id,
                array(
                    array('content' => $element->id, 'align' => 'left'),
                    array('content' => $element->dictionary[$website->languages_list[0]]['title'], 'align' => 'left')
                )
            );
            $ids[] = $element->id;
        }

        $navibars->add_tab_content_row(
            array(
                '<label>'.t(22, 'Elements').'</label>',
                '<div>'.$table->generate().'</div>',
                '<div class="subcomment">
                    <input type="hidden" name="elements-order" id="elements-order" value="'.implode("#", $ids).'" />
                    <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'
                </div>'
            )
        );

        $layout->add_script('
            function structure_elements_open(element)
            {
                window.location.replace("?fid=items&act=edit&id=" + $(element).attr("id") );
            }
        ');
    }

    $events->trigger(
        'structure',
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