<?php
require_once(NAVIGATE_PATH.'/lib/packages/feeds/feed.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new feed();
			
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
					$orderby= $_REQUEST['sidx'].' '.$_REQUEST['sord'];
					$where = " f.website = ".$website->id;
										
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
							$where .= $item->quicksearch($_REQUEST['quicksearch']);
						else if(isset($_REQUEST['filters']))
							$where .= navitable::jqgridsearch($_REQUEST['filters']);
						else	// single search
							$where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
					}
								
					$sql = ' SELECT SQL_CALC_FOUND_ROWS f.*, d.text as title
							   FROM nv_feeds f
						  LEFT JOIN nv_webdictionary d
						  		 	 ON f.id = d.node_id
								 	AND d.node_type = "feed"
									AND d.subtype = "title"
									AND d.lang = "'.$website->languages_list[0].'"
									AND d.website = '.$website->id.'
							  WHERE '.$where.'	
						   ORDER BY '.$orderby.' 
							  LIMIT '.$max.'
							 OFFSET '.$offset;	
				
					if(!$DB->query($sql, 'array'))
					{
						throw new Exception($DB->get_last_error());	
					}
					
					$dataset = $DB->result();	
					$total = $DB->foundRows();					
					
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
							2 	=> count(explode(',', $dataset[$i]['categories'])),
							3 	=> $dataset[$i]['format'],
							4 	=> $dataset[$i]['views'],
							5	=> $permissions[$dataset[$i]['permission']],
							6	=> (($dataset[$i]['enabled']==1)? '<img src="img/icons/silk/accept.png" />' : '<img src="img/icons/silk/cancel.png" />')
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);					
					break;
			}
			
			core_terminate();
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
					$id = $item->id;
					unset($item);
					$item = new feed();				
					$item->load($id);

                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = feeds_form($item);
			break;
			
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = feeds_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = feeds_list();
				}
			}
			break;
			
		case "path_check": // check if a requested path is not used
			$DB->query(
			    'SELECT type, object_id, lang
                      FROM nv_paths
                     WHERE path = :path
                       AND website = :wid',
                'object',
                array(
                    ':wid' => $this->website,
                    'path' => $_REQUEST['path']
                )
            );
						 
			$rs = $DB->result();
			
			echo json_encode($rs);
			core_terminate();		
			break;						

		case 0: // list / search result
		default:			
			$out = feeds_list();
			break;
	}
	
	return $out;
}

function feeds_list()
{
	$navibars = new navibars();
	$navitable = new navitable("feeds_list");
	
	$navibars->title(t(326, 'Feeds'));

	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
									'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=1&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=1');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=2&id=');
	
	$navitable->addCol("ID", 'id', "80", "true", "left");	
	$navitable->addCol(t(67, 'Title'), 'title', "400", "true", "left");	
	
	$navitable->addCol(t(330, 'Categories'), 'categories', "80", "true", "center");	
	$navitable->addCol(t(331, 'Format'), 'format', "80", "true", "center");	
	$navitable->addCol(t(332, 'Views'), 'views', "80", "true", "center");	
	
	$navitable->addCol(t(68, 'Status'), 'permission', "80", "true", "center");
	$navitable->addCol(t(65, 'Enabled'), 'enabled', "80", "true", "center");		
	
	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
	
}

function feeds_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	$layout->navigate_media_browser();	// we can use media browser in this function
	
	if(empty($item->id))
		$navibars->title(t(326, 'Feeds').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(326, 'Feeds').' / '.t(170, 'Edit').' ['.$item->id.']');		

	$navibars->add_actions(		array(	'<a href="#" onclick="javascript: navigate_media_browser();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'	));

	if(empty($item->id))
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
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid=feeds&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid=feeds&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));
																														
	$navibars->add_tab_content_row(array(	'<label>'.t(331, 'Format').'</label>',
											$naviforms->selectfield('format', 
												array(
														0 => 'RSS2.0',
														1 => 'RSS0.91',
														2 => 'ATOM',
														3 => 'ATOM0.3',
														4 => 'OPML',
														5 => 'MBOX',
														6 => 'HTML'
													),
												array(
														0 => 'RSS 2.0 ('.t(333, 'Recommended').')',
														1 => 'RSS 0.91',
														2 => 'ATOM',
														3 => 'ATOM 0.3',
														4 => 'OPML',
														5 => 'mBox',
														6 => 'HTML'
													),
												$item->format
											)
										)
									);										
									
	$navibars->add_tab_content_row(array(	'<label>'.t(335, 'Entries').'</label>',
											$naviforms->selectfield('entries', 
												array(
														0 => 10,
														1 => 15,
														2 => 20,
														3 => 25,
														4 => 50
													),
												array(
														0 => 10,
														1 => 15,
														2 => 20,
														3 => 25,
														4 => 50
													),
												$item->entries
											)
										)
									);	
									
	$navibars->add_tab_content_row(array(	'<label>'.t(336, 'Display').'</label>',
											$naviforms->selectfield('content', 
												array(
														0 => 'title',
														1 => 'resume',
														2 => 'content'
													),
												array(
														0 => t(67, 'Title'),
														1 => t(337, 'Summary'),
														2 => t(9, 'Content')
													),
												$item->content
											)
										)
									);																																									

	$navibars->add_tab_content_row(array(	'<label>'.t(157, 'Image').'</label>',
											$naviforms->dropbox('image', $item->image, 'image'),
										));	

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
										
	$navibars->add_tab_content_row(array(	'<label>'.t(65, 'Enabled').'</label>',
											$naviforms->checkbox('enabled', $item->enabled),
										));	

	$navibars->add_tab_content_row(array(	'<label>'.t(332, 'Views').'</label>',
											intval($item->views),
										));						

	
	$navibars->add_tab(t(54, "Text").' / '.t(74, "Paths"));

	$lang_selector = array();
	$lang_selector[] = '<div class="buttonset">';
	$checked = ' checked="checked" ';	

	foreach($website->languages_list as $lang_code)
	{	
		$lang_selector[] = '<input type="radio" id="language_selector_'.$lang_code.'" name="language_selector" value="'.$lang_code.'" '.$checked.' />
							<label for="language_selector_'.$lang_code.'"  onclick="navigate_feeds_select_language(\''.$lang_code.'\');">'.language::name_by_code($lang_code).'</label>';
		$checked = "";
	}
	$lang_selector[] = '</div>';
	
	$navibars->add_tab_content_row(array(	'<label>'.t(63, 'Languages').'</label>',
											implode("\n", $lang_selector)
										));	

	foreach($website->languages_list as $lang_code)
	{		
		$navibars->add_tab_content('<div class="language_fields" id="language_fields_'.$lang_code.'" style=" display: none; ">');
		
		$navibars->add_tab_content_row(array(	'<label>'.t(67, 'Title').'</label>',
												$naviforms->textfield('title-'.$lang_code, @$item->dictionary[$lang_code]['title'])
											));		
											
		$open_live_site = '';												
		if(!empty($item->paths[$lang_code]))
			$open_live_site = ' <a target="_blank" href="'.$website->absolute_path(true).$item->paths[$lang_code].'"><img src="img/icons/silk/world_go.png" align="absmiddle" /></a>';
											
											
		$navibars->add_tab_content_row(
            array(
                '<label>'.t(75, 'Path').$open_live_site.'</label>',
				$naviforms->textfield('path-'.$lang_code, @$item->paths[$lang_code], NULL, 'navigate_feeds_path_check(this);'),
				'<span>&nbsp;</span>'
            )
        );

		$navibars->add_tab_content_row(
			array(
				'<div class="subcomment"><span class="ui-icon ui-icon-info" style=" float: left; margin-left: -3px; "></span>
				'.t(83, 'Leave blank to disable this item').
				'</div>'
			)
		);

		$navibars->add_tab_content_row(array(	'<label>'.t(334, 'Description').'</label>',
												$naviforms->textarea('description-'.$lang_code, @$item->dictionary[$lang_code]['description'])
											));													
											
		$navibars->add_tab_content('</div>');												
										
	}
	
	$layout->add_script('
		function navigate_feeds_select_language(code)
		{
			$(".language_fields").css("display", "none");
			$("#language_fields_" + code).css("display", "block");			
		}
		
		var active_languages = ["'.implode('", "', $website->languages_list).'"];
		var last_check = [];
		
		function navigate_feeds_path_generate(el)
		{
			var language = $(el).attr("id").substr(5);
			var surl = "";
			surl = "/" + language;
			var title = $("#title-"+language).val();
            title = title.replace(/([\'"“”«»?:\+\&!¿#\\\\])/g, "");            
			title = title.replace(/[.\s]+/g, navigate["word_separator"]);

			surl += "/" + title;
			$(el).val(surl.toLowerCase());
			navigate_feeds_path_check(el);
		}		
		
		function navigate_feeds_path_check(el)
		{
		    var caret_position = null;
            if($(el).is("input") && $(el).is(":focus"))
                caret_position = $(el).caret();

			var path = $(el).val();
			
			if(path=="") return;			
			if(path==last_check[$(el).id]) return;

			path = path.replace(/([\'"“”«»?:\+\&!¿#\\\\])/g, "");
			path = path.replace(/[.\s]+/g, navigate["word_separator"]);

			$(el).val(path);
			
			last_check[$(el).id] = path;
			
			$(el).next().html("<img src=\"'.NAVIGATE_URL.'/img/loader.gif\" align=\"absmiddle\" />");
			
			$.ajax({
			  url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=path_check",
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
						 data[0].type != "feed" )
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
		
		$(window).bind("load", function()
		{
			for(al in active_languages)
			{
				navigate_feeds_path_check($("#path-" + active_languages[al]));
				
				$("#path-" + active_languages[al]).bind("focus", function()
				{
					if($(this).val() == "")
						navigate_feeds_path_generate($(this));
				});
			}

		});
				
	');
	
	$layout->add_script('navigate_feeds_select_language("'.$website->languages_list[0].'")');		
	
	$navibars->add_tab(t(330, "Categories"));		
	
	$hierarchy = structure::hierarchy(0);
	$categories_list = structure::hierarchyList($hierarchy, $item->categories);

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(330, 'Categories').'</label>',
			'<div class="category_tree" id="category-tree-parent"><img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.
            '<div class="tree_ul">'.$categories_list.'</div>'.
            '</div>'
        )
    );
										
	if(!is_array($item->categories))
		$item->categories = array();
		
	$navibars->add_tab_content($naviforms->hidden('categories', implode(',', $item->categories)));		
										
	$layout->add_script('		
		$("#category-tree-parent .tree_ul").jstree({
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
            $("#category-tree-parent .tree_ul").jstree("open_node", "#" + li[0].id);
        
            var children_nodes = new Array();
            children_nodes.push(li);
            $(li).find("li").each(function() {
                children_nodes.push("#" + $(this)[0].id);
            });
        
            $("#category-tree-parent .tree_ul").jstree("select_node", children_nodes);
        
            return false;
        })
        .on("changed.jstree", function(e, data)
        {
            var i, j, r = [];
            var categories = new Array();
            $("#categories").val("");
        
            for(i = 0, j = data.selected.length; i < j; i++)
            {
                var id = data.instance.get_node(data.selected[i]).data.nodeId;
                categories.push(id);
            }
            if(categories.length > 0)
                $("#categories").val(categories);
        });		
	');	

	return $navibars->generate();
}
?>