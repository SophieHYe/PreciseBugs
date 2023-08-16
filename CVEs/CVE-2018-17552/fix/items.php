<?php
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/templates/template.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');
require_once(NAVIGATE_PATH.'/lib/packages/items/item.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/paths/path.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_group.class.php');
require_once(NAVIGATE_PATH.'/lib/webgets/menu.php');
require_once(NAVIGATE_PATH.'/lib/webgets/tags.php');

function run()
{
	global $layout;
	global $DB;
	global $website;
	global $theme;
	global $user;
		
	$out = '';
	$item = new item();
			
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

                case 'elements_by_tag':
                    $DB->query('
                        SELECT i.id, i.access, i.date_created AS date, d.text as title                               
                               FROM nv_items i
                          LEFT JOIN nv_webdictionary d
                                     ON i.id = d.node_id
                                    AND d.node_type = "item"
                                    AND d.subtype = "title"
                                    AND d.lang = :lang
                                    AND d.website = :wid
                            WHERE i.id IN (
                                SELECT node_id FROM nv_webdictionary
                                 WHERE website = :wid
                                   AND node_type = "item"
                                   AND subtype = "tags"
                                   AND lang = :lang
                                   AND FIND_IN_SET(:tag, text) > 0
                            )
                        ORDER BY i.id DESC',
                        'object',
                        array(
                            ':lang' => $_REQUEST['lang'],
                            ':wid' => $website->id,
                            ':tag' => $_REQUEST['tag']
                        )
                    );

                    $rs = $DB->result();

                    for($r=0; $r < count($rs); $r++)
                        $rs[$r]->date = core_ts2date($rs[$r]->date);

                    echo json_encode($rs);
                    break;
					
				default: // list or search	
					// translation of request search & order fields
					switch($_REQUEST['searchField'])
					{
						case 'id':
							$_REQUEST['searchField'] = 'i.id';
							break;
						case 'title':
							$_REQUEST['searchField'] = 'd.text';
							break;
						case 'language':
							$_REQUEST['searchField'] = 'd.lang';
							break;							
						case 'category':
							$_REQUEST['searchField'] = 'i.category';						
							break;
						case 'dates':
							$_REQUEST['searchField'] = 'i.date_published';
							break;
						case 'permission':
							$_REQUEST['searchField'] = 'i.permission';
							break;
						default:
					}
								
					if($_REQUEST['sidx']=='dates')
						$_REQUEST['sidx'] = 'i.date_published';
				
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$orderby = $_REQUEST['sidx'].' '.$_REQUEST['sord'];
					$where = ' i.website = '.$website->id;
					
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
							$where .= $item->quicksearch($_REQUEST['quicksearch']);
                        else if(isset($_REQUEST['filters']))
						{
                            if(is_array($_REQUEST['filters']))
                                $filters = json_decode(json_encode($_REQUEST['filters']), FALSE);
                            else
							    $filters = json_decode($_REQUEST['filters']);

							for($r=0; $r < count($filters->rules); $r++)
							{
								switch($filters->rules[$r]->field)
								{
									case 'id':
										$filters->rules[$r]->field = 'i.id';
										break;
									case 'title':
										$filters->rules[$r]->field = 'd.text';
										break;
									case 'language':
										$filters->rules[$r]->field = 'd.lang';
										break;							
									case 'category':
										$filters->rules[$r]->field = 'i.category';						
										break;
									case 'dates':
										$filters->rules[$r]->field = 'i.date_published';
										break;
									case 'permission':
										$filters->rules[$r]->field = 'i.permission';
										break;
									default:
								}
							}

							$where .= navitable::jqgridsearch(json_encode($filters));
						}
						else	// single search
							$where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
					}


					$sql = ' SELECT SQL_CALC_FOUND_ROWS
					                i.*, d.text as title, d.lang as language,
                                    u.username as author_username,
                                    (   SELECT COUNT(*)
                                        FROM nv_comments cm
                                        WHERE cm.object_type = "item"
                                          AND cm.object_id = i.id
                                          AND cm.website = '.$website->id.'
                                    ) as comments
							   FROM nv_items i
						  LEFT JOIN nv_webdictionary d
						  		 	 ON i.id = d.node_id
								 	AND d.node_type = "item"
									AND d.subtype = "title"
									AND d.lang = "'.$website->languages_list[0].'"
									AND d.website = '.$website->id.'
						  LEFT JOIN nv_users u
						  			 ON u.id = i.author
							  WHERE '.$where.'	
						   GROUP BY i.id, d.text, d.lang, u.username						   
						   ORDER BY '.$orderby.' 
							  LIMIT '.$max.'
							 OFFSET '.$offset;

					if(!$DB->query($sql, 'array'))
						throw new Exception($DB->get_last_error());

					$dataset = $DB->result();	
					$total = $DB->foundRows();

					$dataset = grid_notes::summary($dataset, 'item', 'id');

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

                    $hierarchy = structure::hierarchy(0);

					// we need to format the values and retrieve the needed strings from the dictionary
					$out = array();								
					for($i=0; $i < count($dataset); $i++)
					{
						if(empty($dataset[$i])) continue;
						
						if(empty($dataset[$i]['date_published'])) 
							$dataset[$i]['date_published'] = '&infin;';
						else
							$dataset[$i]['date_published'] = core_ts2date($dataset[$i]['date_published'], false);
							
						if(empty($dataset[$i]['date_unpublish'])) 
							$dataset[$i]['date_unpublish'] = '&infin;';	
						else
							$dataset[$i]['date_unpublish'] = core_ts2date($dataset[$i]['date_unpublish'], false);

                        if(empty($dataset[$i]['date_to_display']))
                            $dataset[$i]['date_to_display'] = '';
                        else
                            $dataset[$i]['date_to_display'] = core_ts2date($dataset[$i]['date_to_display'], false);
						
						if($dataset[$i]['category'] > 0)
                        {
                            $category_path = structure::hierarchyPath($hierarchy, $dataset[$i]['category']);
                            if(is_array($category_path))
                                $dataset[$i]['category_path'] = implode(' › ', $category_path);
                            else
                                $dataset[$i]['category_path'] = $category_path;
                        }

                        $category_text = '';
                        if($dataset[$i]['association']=='free')
                            $category_text = '[ '.mb_strtolower(t(100, 'Free')).' ]';
                        else
                            $category_text = $dataset[$i]['category_path'];

						$item_views = $dataset[$i]['views'];
						if($item_views > 1000)
							$item_views = round($item_views/1000) . "K";

						$item_comments = $dataset[$i]['comments'];
						if($item_comments > 1000)
							$item_comments = round($item_comments/1000) . "K";

                        //$social_rating = '<img src="img/icons/silk/star.png" align="absmiddle" width="12px" height="12px" /> '.
                        //    '<span style="font-size: 90%;">'.$dataset[$i]['score'].' ('.$dataset[$i]['votes'].')</span>';

                        //$social_rating = '<i class="fa fa-fw fa-eye" /> <span style="font-size: 90%;">'.$dataset[$i]['views'].'</span>';
                        $social_rating = '<img src="img/icons/silk/eye.png" align="absmiddle" width="12px" height="12px" /> '.
                            '<span style="font-size: 90%;">'.$item_views.'</span>';

                        //$social_comments = '<i class="fa fa-fw fa-comments-o" /> <span style="font-size: 90%;">'.$dataset[$i]['comments'].'</span>';

						$social_comments = '<img src="img/icons/silk/comments.png" align="absmiddle" width="12px" height="12px" /> '.
							'<span style="font-size: 90%;">'.$item_comments.'</span>';

                        if(empty($dataset[$i]['title']))
                        {
                            // if title is empty for the default language,
                            // try to load the title in another language
                            $DB->query('
                                SELECT lang, text
                                  FROM nv_webdictionary
                                 WHERE website = '.$website->id.' AND
                                        node_type = "item" AND
                                        subtype="title" AND
                                        node_id = '.$dataset[$i]['id'].' AND
                                        text != ""
                                ORDER BY id ASC');

                            $titles = $DB->result();
                            if(!empty($titles))
                            {
                                $dataset[$i]['title'] = '<img src="img/icons/silk/comment.png" align="absmiddle" />';
                                $dataset[$i]['title'] .= '<small>'.$titles[0]->lang.'</small>&nbsp;&nbsp;';
                                $dataset[$i]['title'] .= $titles[0]->text;
                            }
                        }

						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1 	=> '<div class="list-row" data-permission="'.$dataset[$i]['permission'].'">'.$dataset[$i]['title'].'</div>',
							2 	=> $social_rating.'&nbsp;&nbsp;'.$social_comments,
							3	=> $category_text,
							//4	=> $dataset[$i]['author_username'],
							4	=> $dataset[$i]['date_to_display'],
							5	=> $dataset[$i]['date_published'].' - '.$dataset[$i]['date_unpublish'],
							6	=> $access[$dataset[$i]['access']].' '.$permissions[$dataset[$i]['permission']],
							7 	=> $dataset[$i]['_grid_notes_html']
						);
					}

					navitable::jqgridJson($out, $page, $offset, $max, $total);
				    break;
			}
			
			core_terminate();
			break;
		
		case 'load':
        case 'create':
        case 'edit':
		case 2: // edit/new form		
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));

				if($user->permission("items.edit")=="false" && $item->author != $user->id)
				{
					$layout->navigate_notification(t(610, "Sorry, you are not allowed to execute the requested function"), true);
					$_REQUEST['act'] = 'list';
					return run();
				}

				// check if the current user can edit this item
				if($item->association=='category' && !empty($item->category))
				{
					if(!structure::category_allowed($item->category))
					{
						$layout->navigate_notification(t(610, "Sorry, you are not allowed to execute the requested function"), true);
						$_REQUEST['act'] = 'list';
						return run();
					}
				}
			}

			if(isset($_REQUEST['form-sent']))
			{
				$item->load_from_post();
				try
				{
					$item->save();
					property::save_properties_from_post('item', $item->id);

                    if(!empty($_REQUEST['items-order']))
                        item::reorder($_REQUEST['items-order']);

					$layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
					$item->load($item->id);
					users_log::action($_REQUEST['fid'], $item->id, 'save', $item->dictionary[$website->languages_list[0]]['title'], json_encode($_REQUEST));
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
			else
				users_log::action($_REQUEST['fid'], $item->id, 'load', $item->dictionary[$website->languages_list[0]]['title']);
		
			$out = items_form($item);
			break;	

        case 'delete':
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{			
				$item->load(intval($_REQUEST['id']));

				try
				{
					if(!empty($item->id))
					{
						$deleted = ($item->delete() > 0);
						if($deleted)
						{
							$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
							$out = items_list();
							users_log::action($_REQUEST['fid'], $item->id, 'remove', $item->dictionary[$website->languages_list[0]]['title'], json_encode($_REQUEST));
						}
					}

					if(!$deleted)
					{
						$layout->navigate_notification(t(56, 'Unexpected error.'), false);
						if(!empty($item->id))
							$out = items_form($item);
						else
							$out = items_list();
					}
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true);
					if(!empty($item->id))
						$out = items_form($item);
				}
			}
			break;

        case 'duplicate':
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));

				if($item->association == 'category' && $item->embedding == 1)
				{
					// get structure template
					$category = new structure();
					$category->load($item->category);

					$properties = property::load_properties_associative(
						'structure', $category->template,
						'item', $item->id
					);
				}
				else
				{
                    $properties = property::load_properties_associative(
	                    'item', $item->template,
	                    'item', $item->id
                    );
				}

                // try to duplicate
                $item->id = 0;
                $ok = $item->insert();

                if($ok)
                {
                    // duplicate item properties too (but don't duplicate comments)
	                if($item->association == 'category' && $item->embedding == 1)
	                {
		                $ok = property::save_properties_from_array('item', $item->id, $category->template, $properties);
	                }
	                else
                        $ok = property::save_properties_from_array('item', $item->id, $item->template, $properties);
                }

				if($ok)
				{
					$layout->navigate_notification(t(478, 'Item duplicated successfully.'), false, false, 'fa fa-check');
                    $out = items_form($item);
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
                    $item = new item();
                    $item->load(intval($_REQUEST['id']));
                    $out = items_form($item);
				}

				users_log::action($_REQUEST['fid'], $item->id, 'duplicate', $item->dictionary[$website->languages_list[0]]['title'], json_encode($_REQUEST));
			}
			break;
		
        case "delete_content_history":
            $ok = false;
            if(!empty($_REQUEST['id']))
            {
                $ok = $DB->execute('
                        DELETE FROM nv_webdictionary_history 
                        WHERE id = :id AND
                              node_type = :node_type,
                              website = :website
                        LIMIT 1',
                    array(
                        ':id'      => intval($_REQUEST['id']),
                        ':node_type' => "item",
                        ':website' => $website->id
                    )
                );
            }

            if($ok)
                echo 'true';
            else
                echo 'false';

			core_terminate();
			break;
			
		case 90:
			$DB->query(
			    'SELECT id, date_created, autosave
                      FROM nv_webdictionary_history
                     WHERE node_type = "item"
                       AND subtype = :subtype
                       AND lang = :lang 
                       AND node_id = :node_id
                       AND website = :wid 
                  ORDER BY date_created DESC',
                    'array',
                array(
                    ':wid' => $website->id,
                    ':node_id' => $_REQUEST['id'],
                    ':lang' => $_REQUEST['lang'],
                    ':subtype' => 'section-'.$_REQUEST['section']
                )
            );
			
			$result = $DB->result();
			
			if(!is_array($result)) $result = array();
			for($i=0; $i < count($result); $i++)
			{
				$result[$i]['date'] = core_ts2date($result[$i]['date_created'], true);
				if($result[$i]['autosave']==1)
					$result[$i]['date'] .= ' ('.t(273, 'Autosave').')';
			}
			
			echo json_encode($result);
							  
			core_terminate();		
			break;

		case "search_by_title":
		    // json search title request (for "copy from" dialog)
			$DB->query('
				SELECT node_id as id, text as label, text as value
				  FROM nv_webdictionary
				 WHERE node_type = "item"
				   AND subtype = "title"
				   AND lang = :lang
				   AND website = :wid
				   AND text LIKE :text
		      ORDER BY text ASC
			     LIMIT 20',
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

		case "raw_zone_content": // return raw contents from a product

			if(empty($_REQUEST['section']))
				$_REQUEST['section'] = 'main';
		
			if($_REQUEST['history']=='true')
			{
				$DB->query('SELECT text
							  FROM nv_webdictionary_history
							 WHERE node_type = "item"
							   AND website = '.intval($website->id).' 
							   AND id = '.intval($_REQUEST['id']),
							'array');	
							
				$data = $DB->first();				
				echo $data['text'];		
			}
			else if($_REQUEST['zone'] == 'section')
			{		
				$DB->query(
				    'SELECT text
                          FROM nv_webdictionary
                         WHERE node_type = "item"
                           AND subtype = :subtype
                           AND lang = :lang
                           AND website = :wid 
                           AND node_id = :node_id',
                    'array',
                    array(
                        ':wid' => $website->id,
                        ':lang' => $_REQUEST['lang'],
                        ':node_id' => $_REQUEST['node_id'],
                        ':subtype' => 'section-'.$_REQUEST['section']
                    )
                );

				$data = $DB->first();				
				echo $data['text'];
			}
			else if($_REQUEST['zone'] == 'property')
			{
				$DB->query(
				    'SELECT text
                          FROM nv_webdictionary
                         WHERE node_type = "property-item"
                           AND subtype = :subtype
                           AND lang = :lang
                           AND website = :wid 
                           AND node_id = :node_id',
                        'array',
                        array(
                            ':wid' => $website->id,
                            ':lang' => $_REQUEST['lang'],
                            ':node_id' => $_REQUEST['node_id'],
                            ':subtype' => 'property-'.$_REQUEST['section'].'-'.$_REQUEST['lang']
                        )
                    );
                
				$data = $DB->first();
				echo $data['text'];
			}
							  
			core_terminate();
			break;

		// return raw template content
		case 93:
			$DB->query(
			    'SELECT file
                    FROM nv_templates
                   WHERE enabled = 1
                     AND id = '.intval($_REQUEST['id']).'
                     AND website = '.intval($website->id),
                'array'
            );
						
			$data = $DB->first();
			
			echo @file_get_contents(NAVIGATE_PRIVATE.'/'.$website->id.'/templates/'.$data['file']);
							  
			core_terminate();
			break;	
			
		case "copy_from_template_zones":
            // return template sections and (textarea) properties for a content id
			$item = new item();
			$item->load(intval($_REQUEST['id']));
			$template = $item->load_template();

			$zones = array();
            for($ts=0; $ts < count($template->sections); $ts++)
            {
	            $title = $template->sections[$ts]['name'];
				if(!empty($theme))
					$title = $theme->t($title);

	            if($title == '#main#')
		            $title = t(238, 'Main content');
	            $zones[] = array(
		            'type' => 'section',
		            'id' => $template->sections[$ts]['id'],
		            'title' => $title
	            );
            }

			for($ps=0; $ps < count($template->properties); $ps++)
			{
				// ignore structure properties
				if(isset($template->properties[$ps]->element) && $template->properties[$ps]->element != 'item')
					continue;

				// ignore non-textual properties
				if(!in_array($template->properties[$ps]->type, array("text", "textarea", "rich_textarea")))
					continue;

				$title = $template->properties[$ps]->name;
				if(!empty($theme))
					$title = $theme->t($title);

				$zones[] = array(
		            'type' => 'property',
		            'id' => $template->properties[$ps]->id,
		            'title' => $title
	            );
			}

			echo json_encode($zones);
							  
			core_terminate();
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
                        ':path' => $path,
                        ':wid' => $website->id
                    )
                );
			
			$rs = $DB->result();
			
			echo json_encode($rs);
			
			core_terminate();
			break;
			
		case "structure_entry_path": // return category paths
			echo json_encode(path::loadElementPaths('structure', intval($_REQUEST['id'])));
			core_terminate();
			break;

		case 'json_find_user': // json find user by name request (for "moderator" autocomplete)
			$DB->query('
				SELECT id, username as text
				  FROM nv_users
				 WHERE username LIKE :username
		      ORDER BY username ASC
			     LIMIT 30',
				'array',
				array(
				    ':username' => '%' . $_REQUEST['username'] . '%'
                )
			);
						
			$rows = $DB->result();
            $total = $DB->foundRows();

            if(empty($_REQUEST['format']) || $_REQUEST['format']=='select2')
            {
                echo json_encode(array('items' => $rows, 'totalCount' => $total));
            }
            else if($_REQUEST['format'] == 'autocomplete')
            {
                $out = array();
                foreach($rows as $row)
                    $out[] = array("id" => $row->id, "label" => $row->text, "value" => $row->text);
                echo json_encode($out);
            }

            core_terminate();
			break;

        case 'json_find_item':
            // find items by its title
            // any language

            $text = $_REQUEST['title'];
            if(!empty($_REQUEST['term'])) // tagit request
                $text = $_REQUEST['term'];

            $query_params = array(
                ':wid' => $website->id,
                ':lang' => $website->languages_published[0],
                ':text' => '%' . $text . '%'
            );

            $template_filter = '';

	        if(!empty($_REQUEST['template']))
            {
                $template_filter = ' AND nvi.template = :template ';
                $query_params[':template'] = $_REQUEST['template'];
            }

	        if(!empty($_REQUEST['association']))
            {
                $template_filter = ' AND nvi.association = :association ';
                $query_params[':association'] = $_REQUEST['association'];
            }

	        if(isset($_REQUEST['embedding']))
            {
                $template_filter = ' AND nvi.embedding = :embedding ';
                $query_params[':embedding'] = $_REQUEST['embedding'];
            }

            $limit = intval($_REQUEST['page_limit']);
            if(empty($limit)) $limit = null;
            $limit = value_or_default($limit, 1000);

            $sql = '
				SELECT SQL_CALC_FOUND_ROWS DISTINCT nvw.node_id as id, nvw.text as text
				  FROM nv_webdictionary nvw, nv_items nvi
				 WHERE nvw.node_type = "item"
				   AND nvw.node_id = nvi.id
				   '.$template_filter.'
				   AND nvw.subtype = "title"
				   AND nvw.website = :wid
				   AND nvw.website = nvi.website
				   AND ( nvw.text LIKE :text 
				         OR ( nvi.id LIKE :text AND nvw.lang = :lang )
				   )
		        GROUP BY nvw.node_id, nvw.text
		        ORDER BY nvw.text ASC
			     LIMIT '.$limit.'
			     OFFSET '.max(0, intval($_REQUEST['page_limit']) * (intval($_REQUEST['page'])-1));

            $DB->query($sql, 'array', $query_params);
            $rows = $DB->result();
			$total = $DB->foundRows();

			if($_REQUEST['association']=='free')
			{
				for($i = 0; $i < count($rows); $i++)
				{
					$rows[$i]['path'] = $DB->query_single(
						'path',
						'nv_paths',
						'	website = '.intval($website->id).' AND 
							type="item" AND 
							object_id="'.$rows[$i]['id'].'" AND 
							lang="'.$website->languages_list[0].'"
						'
					);

					if(empty($rows[$i]['path']))
						$rows[$i]['path'] = '/node/'.$rows[$i]['id'];
				}
			}

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
			
		case "change_comment_status": // change comment status
			if(empty($_REQUEST['id']))
			{
				echo "false"; 
				core_terminate();
			}
			
			switch($_REQUEST['opt'])
			{
				case 'publish':
					$DB->execute('
						UPDATE nv_comments
						   SET status = 0
						 WHERE website = '.$website->id.' AND
						       id = '.$_REQUEST['id']);
					break;
					
				case 'unpublish':
					$DB->execute('
						UPDATE nv_comments
						   SET status = 1
						 WHERE website = '.$website->id.' AND
						       id = '.$_REQUEST['id']);
					break;
					
				case 'delete':
					$DB->execute('
						DELETE FROM nv_comments
						 WHERE website = '.$website->id.' AND
							   id = '.$_REQUEST['id']);
					break;
			}
		
			$error = $DB->get_last_error();
			if(empty($error)) echo 'true';
			else			  echo 'false';
							  
			core_terminate();
			break;			
			
		case 'autosave':
			
			if(!empty($_REQUEST['id']))
			{					
				$iDictionary = array();
				
				foreach($_REQUEST as $key => $value)
				{
					if(strpos($key, 'section-')===0)
					{
						$lang = substr($key, -2, 2);
						$kname = substr($key, 0, strlen($key) - 3);
						$iDictionary[$lang][$kname] = $value;
					}
				}
				
				$changed = webdictionary_history::save_element_strings('item', intval($_REQUEST['id']), $iDictionary, true);
                if($changed)
                    echo 'changes_saved';
                else
                    echo 'no_changes';
				core_terminate();
			}

			echo 'false';
			core_terminate();			
			break;
			
		case 'votes_reset':
			webuser_vote::remove_object_votes('item', intval($_REQUEST['id']));
			
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
				
				webuser_vote::update_object_score('item', $vote->object_id);
					
				echo 'true';
				core_terminate();	
			}
		
			$max = intval($_GET['rows']);
			$page = intval($_GET['page']);
			$offset = ($page - 1) * $max;	
		
			if($_REQUEST['_search']=='false')
				list($dataset, $total) = webuser_vote::object_votes_by_webuser('item', intval($_REQUEST['id']), $_REQUEST['sidx'].' '.$_REQUEST['sord'], $offset, $max);
		
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

        case 'items_order':
            if(!empty($_POST['items-order']))
            {
                // save new order
                $response = item::reorder($_POST['items-order']);
                if($response!==true)
                {
                    echo $response['error'];
                }
                else
                    echo 'true';
            }
            else    // show ordered list
                echo items_order($_REQUEST['category']);

            core_terminate();
            break;

		case 'json_tags_search':
			$tags = nvweb_tags_retrieve(null, null, 'top', $_REQUEST['term'], $_REQUEST['lang']);

			$tags_json = array();
			foreach(array_keys($tags) as $tag)
				$tags_json[] = json_decode('{ "id": "'.$tag.'", "label": "'.$tag.'", "value": "'.$tag.'" }');
			echo json_encode($tags_json);

			core_terminate();
			break;

		case 'json_tags_ranking':
			$tags = nvweb_tags_retrieve(100, null, 'top', null, $_REQUEST['lang']);
			$tags = array_keys($tags);
			echo json_encode($tags);
			core_terminate();
			break;

        case 'labels':
            $out = items_labels_form();
            break;

        case 'list':
		case 0: // list / search result
		default:			
			$out = items_list();
			break;
	}
	
	return $out;
}

function items_list()
{
    global $layout;
	global $user;

	$navibars = new navibars();
	$navitable = new navitable("items_list");
	
	$navibars->title(t(22, 'Items'));

	$navibars->add_actions(
        array(
			'<a href="?fid=items&act=labels"><img height="16" align="absmiddle" width="16" src="img/icons/silk/tag_yellow.png"> '.t(265, 'Labels').'</a>',
        )
    );

	$navibars->add_actions(
        array(
            ($user->permission("items.create") == 'false'? '' : '<a href="?fid='.$_REQUEST['fid'].'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>'),
			'<a href="?fid='.$_REQUEST['fid'].'&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );

    $navibars->search_form_suggest(
        "?fid=items&act=json_find_item&format=tagit&page_limit=12",
        "?fid=items&act=edit&id="
    );
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=json&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=json');
	$navitable->sortBy('date_modified', 'DESC');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=edit&id=');
	$navitable->enableSearch();
	if($user->permission("items.delete") == 'true')
		$navitable->enableDelete();
	$navitable->setGridNotesObjectName("item");
	
	$navitable->addCol("ID", 'id', "40", "true", "left");	
	$navitable->addCol(t(67, 'Title'), 'title', "320", "true", "left");
	$navitable->addCol(t(309, 'Social'), 'comments', "50", "true", "center");
	$navitable->addCol(t(78, 'Category'), 'category', "210", "true", "left");
	//$navitable->addCol(t(266, 'Author'), 'author_username', "80", "true", "left");
	$navitable->addCol(t(551, 'Date to display'), 'date_to_display', "60", "true", "center");
	$navitable->addCol(t(85, 'Date published'), 'dates', "100", "true", "center");
	$navitable->addCol(t(68, 'Status'), 'permission', "80", "true", "center");
	$navitable->addCol(t(168, 'Notes'), 'note', "50", "false", "center");

    $navitable->setLoadCallback('
        $("td[aria-describedby=\'items_list_category\']").truncate({
            "width": "auto",
            "token": "…",
            "side": "center",
            "addtitle": true
        });

        if($("#jqgh_items_list_category button").length < 1)
        {
            $("#jqgh_items_list_category").prepend("<button><i class=\"fa fa-bars\"></i></button>");
            $("#jqgh_items_list_category button")
            	.button()
            	.css(
            	{
                	"float": "right",
                	"margin-top": "0px",
                	"padding": "0px"
            	})
            	.on("click", function(e)
            	{
            	    e.stopPropagation();
            	    e.preventDefault();
            	    setTimeout(items_list_choose_categories, 150);
                });

            $("#jqgh_items_list_category span.ui-button-text").css({"padding-top": "0", "padding-bottom": "0"});
        }
    ');

    // add categories filter
    $hierarchy = structure::hierarchy();
    array_unshift($hierarchy, (object) array('id' => 0, 'label' => t(428, "(no category)")));
    $hierarchy = structure::hierarchyListClasses($hierarchy);

    $navibars->add_content('<div id="filter_categories_window" style="display: none;">'.$hierarchy.'</div>');
    $layout->add_script('$("#filter_categories_window ul").attr("data-name", "filter_categories_field");');
    $layout->add_script('
        $("#filter_categories_window ul").jAutochecklist({
            popup: false,
            absolutePosition: true,
            width: 0,
            listWidth: 400,
            listMaxHeight: 400,
            onItemClick: function(nval, li, selected_before, selected_after)
            {
                selected_after = selected_after.join(",");
                var filters = {
                    "groupOp" : "AND",
                    "rules": [
                        {
                            "field" : "category",
                            "op" : "in",
                            "data" : selected_after
                        },
                        {
                            "field" : "title",
                            "op" : "cn",
                            "data" : $("#navigate-quicksearch").val()
                        }
                    ]
                };

                $("#items_list").jqGrid(
                    "setGridParam",
                    {
                        search: true,
                        postData: { "filters": filters }
                    }
                ).trigger("reloadGrid");
            }
        });');

    $layout->add_script('
        function items_list_choose_categories()
        {
            $("#navigate-quicksearch").parent().on("submit", function(){
                $("#filter_categories_window ul").jAutochecklist("deselectAll");
            });

            $("#filter_categories_window ul").jAutochecklist("open");
            $(".jAutochecklist_list").css({"position": "absolute"});
            $(".jAutochecklist_list").css($("#jqgh_items_list_category button").offset());
            $(".jAutochecklist_dropdown_wrapper").hide();
            $(".jAutochecklist_list").css({
                "border-radius": "8px",
                "margin-left": "-373px",
                "margin-top": "16px"
            });
            $(".jAutochecklist_list").addClass("navi-ui-widget-shadow ui-menu ui-widget ui-widget-content ui-corner-all");

            return false;
        }
    ');

	$navibars->add_content($navitable->generate());

	return $navibars->generate();
}

function items_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
    global $theme;
    global $events;
    global $current_version;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	$layout->navigate_media_browser();	// we can use media browser in this function

    $extra_actions = array();

	if(empty($item->id))
		$navibars->title(t(22, 'Items').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(22, 'Items').' / '.t(170, 'Edit').' ['.$item->id.']');	

	$navibars->add_actions(
		array(
			'<a href="#" onclick="javascript: navigate_media_browser();" title="Ctrl+M">
				<img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'
			</a>'
		)
	);

	if(empty($item->id))
	{
		$navibars->add_actions(
            array(
	            ($user->permission('items.create')=='true'?
	            '<a href="#" onclick="navigate_items_tabform_submit(1);" title="Ctrl+S" data-action="save">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'
				</a>' : "")
            )
        );
	}
	else
	{
		$navibars->add_actions(
            array(
	            (($user->permission('items.edit')=='true' || $item->author == $user->id) ?
	            '<a href="#" onclick="navigate_items_tabform_submit(1);" title="Ctrl+S" data-action="save">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'
				</a>' : ""),
	            ($user->permission("items.delete") == 'true'?
                '<a href="#" onclick="navigate_delete_dialog();">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'
				</a>' : "")
            )
        );

        $extra_actions[] = '<a href="#" onclick="navigate_items_preview();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/monitor.png"> '.t(274, 'Preview').'</a>';
		if($user->permission("items.create") != 'false')
            $extra_actions[] = '<a href="?fid=items&act=duplicate&id='.$item->id.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/page_copy.png"> '.t(477, 'Duplicate').'</a>';

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=items&act=delete&id='.$item->id.'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}

    if(!empty($item->id))
    {
        $notes = grid_notes::comments('item', $item->id);
        $navibars->add_actions(
            array(
                '<a href="#" onclick="javascript: navigate_display_notes_dialog();">
					<span class="navigate_grid_notes_span" style=" width: 20px; line-height: 16px; ">'.count($notes).'</span>
					<img src="img/skins/badge.png" width="20px" height="18px" style="margin-top: -2px;" class="grid_note_edit" align="absmiddle" /> '.t(168, 'Notes').'
				</a>'
            )
        );
    }

    if(!empty($item->id))
    {
        // we attach an event to "items" which will be fired by navibars to put an extra button
        $events->add_actions(
            'items',
            array(
                'item' => &$item,
                'navibars' => &$navibars
            ),
            $extra_actions
        );
    }

    if(!empty($item->id))
        $layout->navigate_notes_dialog('item', $item->id);
	
	$navibars->add_actions(
        array(
            (!empty($item->id)? '<a href="?fid=items&act=edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=items&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );

    $navibars->search_form_suggest(
        "?fid=items&act=json_find_item&format=tagit&page_limit=12",
        "?fid=items&act=edit&id="
    );

	// languages
    $ws_languages = $website->languages();

	$navibars->form('', 'fid=items&act=edit&id='.$item->id);

    $layout->add_script("
        $(document).on('keydown.ctrl_s', function (evt) { navigate_items_tabform_submit(1); return false; } );
        $(document).on('keydown.ctrl_m', function (evt) { navigate_media_browser(); return false; } );
    ");

	$layout->add_script('
		var template_sections = [];	
	');		

	$navibars->add_tab(t(43, "Main")); // tab #0
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(
        array(
            '<label>ID</label>',
			'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>'
        )
    );

    if(empty($item->id))
        $item->date_to_display = core_time();

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(551, 'Date to display').'</label>',
			$naviforms->datefield('date_to_display', $item->date_to_display, true),
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(85, 'Date published').'</label>',
			$naviforms->datefield('date_published', $item->date_published, true),
        )
    );
										
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(90, 'Date unpublished').'</label>',
			$naviforms->datefield('date_unpublish', $item->date_unpublish, true),
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

	$permission_options = array(
		0 => t(69, 'Published'),
		1 => t(70, 'Private'),
		2 => t(81, 'Hidden')
	);

	if($user->permission("items.publish") == 'false')
	{
		if(!isset($item->permission))
			$item->permission = 1;

		$navibars->add_tab_content_row(
	        array(
	            '<label>'.t(68, 'Status').'</label>',
		        $permission_options[$item->permission],
		        $naviforms->hidden("permission", $item->permission)
	        )
	    );
	}
	else
	{
		$navibars->add_tab_content_row(
	        array(
	            '<label>'.t(68, 'Status').'</label>',
				$naviforms->selectfield(
	                'permission',
                    array_keys($permission_options),
					array_values($permission_options),
	                $item->permission,
	                '',
	                false,
	                array(
                        0 => t(360, 'Visible to everybody'),
                        1 => t(359, 'Visible only to Navigate CMS users'),
                        2 => t(358, 'Hidden to everybody')
	                )
	            ),
                '<span id="status_info" class="ui-icon ui-icon-alert"
                       data-message="'.t(618, 'Change the status to Published to see the item on the future publication date currently assigned', false, true).'"
					   style="display: none; float: none; vertical-align: middle; "></span>'
	        )
	    );
	}

									
	if(empty($item->id))
        $item->author = $user->id;
	$author_webuser = $DB->query_single('username', 'nv_users', ' id = '.$item->author);	
	$navibars->add_tab_content($naviforms->hidden('item-author', $item->author));
	$navibars->add_tab_content_row(array(
        '<label>'.t(266, 'Author').'</label>',
		$naviforms->textfield('item-author-text', $author_webuser)
    ));
	
	// script#1									

	if($item->date_modified > 0)
	{																		
		$navibars->add_tab_content_row(
            array(
                '<label>'.t(227, 'Date modified').'</label>',
				core_ts2date($item->date_modified, true)
            )
        );
	}
	
	if($item->date_created > 0)
	{
		$navibars->add_tab_content_row(
            array(
                '<label>'.t(226, 'Date created').'</label>',
				core_ts2date($item->date_created, true)
            )
        );
	}

    if(!empty($item->id))
    {
        $navibars->add_tab_content_row(
            array(
                '<label>' . t(280, 'Page views') . '</label>',
                $item->views
            ),
            "div_page_views"
        );
    }

	$navibars->add_tab(t(87, "Association")); // tab #1

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(87, "Association").'</label>',
			$naviforms->buttonset(
                'association',
                array(  'free' => t(100, 'Free'),
                        'category' => t(78, 'Category')
                ),
                (empty($item->id)? 'category' : $item->association),
                "navigate_change_association(this);"
            )
        )
    );
										
	$hierarchy = structure::hierarchy(0);
	$categories_list = structure::hierarchyList($hierarchy, $item->category);

    if(empty($categories_list))
        $categories_list = '<ul><li value="0">'.t(428, '(no category)').'</li></ul>';

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(78, 'Category').'</label>',
            $naviforms->dropdown_tree('category', $categories_list, $item->category, 'navigate_item_category_change')
        ),
        'div_category_tree'
    );

    $layout->add_script('
        function navigate_item_category_change(id)
        {
            $.ajax(
            {
                url: NAVIGATE_APP + "?fid=" + navigate_query_parameter("fid") + "&act=structure_entry_path&id=" + id,
                dataType: "json",
                data: {},
                success: function(data, textStatus, xhr)
                {
                    item_category_path = data;
                }
            });
        }
    ');

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(162, 'Embedding').'</label>',
			$naviforms->buttonset(
                'embedding',
                array( '1' => t(163, 'Embedded'),
                       '0' => t(164, 'Own path')
                     ),
                (empty($item->id)? '1' : intval($item->embedding)),
                "navigate_change_association();"
            ),
            '<span id="embedding_info" class="ui-icon ui-icon-info"
			        data-message-title-1="'.t(163, 'Embedded', false, true).'"
					data-message-content-1="'.t(165, 'Full content is shown on category page. Ex. "Who we are?"', false, true).'"
					data-message-title-2="'.t(164, 'Own path', false, true).'"
					data-message-content-2="'.t(166, 'The content is accessed through its own url. Ex. "News"', false, true).'" 
					style="float: left; margin-left: -4px;">
			</span>'
        ),
        'div_category_embedded'
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(22, 'Elements').'</label>',
            '<button style="float: left;">'.t(171, 'Order').'</button>',
            '<span id="order_info" class="ui-icon ui-icon-info"
 				   data-message="'.t(425, 'Order elements of a category (unless the template forces other sorting)', false, true).'"
				   style="float: left; margin-left: 2px;">				   
			</span>',
            '<div id="items_order_window" style="display: none;"></div>'
        ),
        'div_category_order'
    );

	$layout->add_script('
	    $("#div_category_order button").button(
	    {
	        icons:
	        {
                primary: "ui-icon-arrowthick-2-n-s"
            }
	    }).on("click", function(e)
	    {
	        e.stopPropagation();
	        e.preventDefault();
	        navigate_status(navigate_t(6, "Loading") + "...", "loader");

	        $("#items_order_window").load("?fid=items&act=items_order&category=" + $("#category").val() + "&_bogus=" + new Date().getTime(), function()
	        {
	            navigate_status(navigate_t(42, "Ready"), "ready");
                $("#items_order_window").dialog({
                    modal: true,
                    title: "'.t(171, 'Order').'",
                    width: 600,
                    height: 500,
                    buttons:
                    {
                        "'.t(58, 'Cancel').'": function()
                        {
                            $(this).dialog("destroy");
                        },
                        "'.t(190, 'Ok').'": function()
                        {
                            var dialog = this;
                            // save
                            $.post(
                                "?fid=items&act=items_order&category=" + $("#category").val() + "&_bogus=" + new Date().getTime(),
                                {
                                    "items-order": $("#items-order").val()
                                },
                                function(response)
                                {
                                    if(response=="true")
                                    {
                                        $(dialog).dialog("destroy");
                                    }
                                    else
                                    {
                                        $("<div>"+response+"</div>").dialog({
                                            modal: true,
                                            title: "'.t(56, "Unexpected error").'"
                                        });
                                    }
                                }
                            );
                        }
                    }
                });
            });
	    });
	');

	$templates = template::elements('element');
	$template_select = $naviforms->select_from_object_array('template', $templates, 'id', 'title', $item->template);

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(79, 'Template').'</label>',
			$template_select,
	        '<span id="template_info" class="ui-icon ui-icon-alert"
 				   data-message="'.t(619, "Template changed, please Save now to see the changes in the next tabs", false, true).'"
				   style="display: none; float: none; vertical-align: middle; "></span>'
        ),
		'div_template_select'
    );

	$layout->add_script('
		var last_check = [];
		var active_languages = ["'.implode('", "', array_keys($ws_languages)).'"];
		$("#div_template_select").hide();
	');

	// script#3	
	if(!empty($item->id))
	{									
		$navibars->add_tab(t(9, "Content"));	// tab #2
		
		$navibars->add_tab_content_row(
			array(
				'<label>'.t(63, 'Languages').'</label>',
				$naviforms->buttonset('language_selector', $ws_languages, $website->languages_list[0], "navigate_items_select_language(this);")
			)
		);
		
		$template = $item->load_template();

        $translate_extensions = extension::list_installed('translate', false);

        foreach($website->languages_list as $lang)
		{
			$navibars->add_tab_content('<div class="language_fields" id="language_fields_'.$lang.'" style=" display: none; ">');
			
			$navibars->add_tab_content_row(
				array(
					'<label>'.t(67, 'Title').'</label>',
					$naviforms->textfield('title-'.$lang, @$item->dictionary[$lang]['title'])
				)
			);

			$open_live_site = '';												
			if(!empty($item->paths[$lang]))
				$open_live_site = ' <a target="_blank" href="'.$website->absolute_path(true).$item->paths[$lang].'"><img src="img/icons/silk/world_go.png" align="absmiddle" /></a>';
												
			$navibars->add_tab_content_row(
                array(
                    '<label>'.t(75, 'Path').$open_live_site.'</label>',
                    $naviforms->textfield('path-'.$lang, @$item->paths[$lang], NULL, 'navigate_items_path_check(this, event);'),
                    '<span>&nbsp;</span>'
                ),
               'div_path_'.$lang
            );

			if(!isset($template->sections))
				$template->sections[] = array(
                    0 => array(
	                    'id' => 'main',
                        'name' => '#main#',
                        'editor' => 'tinymce',
                        'width' => '960px'
                    )
                );
			
			if(!is_array($template->sections))
				$template->sections = array();

			// compatibility fix: auto-correct template sections with missing ID (only "code" provided)
			for($s=0; $s < count($template->sections); $s++)
			{
				if(!isset($template->sections[$s]['id']))
					$template->sections[$s]['id'] = $template->sections[$s]['code'];
			}

			foreach($template->sections as $section)
			{								
				if(is_object($section))
					$section = (array)$section;

                // ignore empty sections
                if(empty($section))
                    continue;

				if($section['editor']=='tinymce')
				{
                    $translate_menu = '';
                    if(!empty($translate_extensions))
                    {
                        $translate_extensions_titles = array();
                        $translate_extensions_actions = array();

                        foreach($translate_extensions as $te)
                        {
                            if($te['enabled']=='0') continue;
                            $translate_extensions_titles[] = $te['title'];
                            $translate_extensions_actions[] = 'javascript: navigate_tinymce_translate_'.$te['code'].'(\'section-'.$section['id'].'-'.$lang.'\', \''.$lang.'\');';
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

					$navibars->add_tab_content_row(
                        array(
                            '<label>'.
                                template::section_name($section['name']).
                                '<span class="editor_selector" for="section-'.$section['id'].'-'.$lang.'">'.
                                    //'<i class="fa fa-border fa-fw fa-lg fa-th-large" data-action="composer" title="'.t(616, "Edit with NV Composer").'"></i> '.
                                    '<i class="fa fa-border fa-fw fa-lg fa-file-text-o active" data-action="tinymce" title="'.t(614, "Edit with TinyMCE").'"></i> '.
                                    '<i class="fa fa-border fa-fw fa-lg fa-code" data-action="html" title="'.t(615, "Edit as source code").'"></i> '.
                                    '<i class="fa fa-border fa-fw fa-lg fa-eraser" data-action="clear" title="'.t(208, "Remove all content").'"></i>'.
                                '</span>'.
                            '</label>',
                            $naviforms->editorfield('section-'.$section['id'].'-'.$lang, @$item->dictionary[$lang]['section-'.$section['id']], ($section['width']+48).'px', $lang),
                            '<div style="clear:both; margin-top:5px; float:left; margin-bottom: 10px;">',
                            '<label>&nbsp;</label>',
                            $translate_menu,
                            '<button onclick="navigate_items_copy_from_dialog(\'section-'.$section['id'].'-'.$lang.'\'); return false;"><img src="img/icons/silk/page_white_copy.png" align="absmiddle"> '.t(189, 'Copy from').'...</button> ',
                            '<button onclick="navigate_items_copy_from_history_dialog(\'section-'.$section['id'].'-'.$lang.'\', \''.$section['id'].'\', \''.$lang.'\', \''.$section['editor'].'\'); return false;"><img src="img/icons/silk/time_green.png" align="absmiddle"> '.t(40, 'History').'</button> ',
                            (!empty($theme->content_samples)? '<button onclick="navigate_properties_copy_from_theme_samples(\'section-'.$section['id'].'-'.$lang.'\', \''.$section['id'].'\', \''.$lang.'\', \''.$section['editor'].'\'); return false;"><img src="img/icons/silk/rainbow.png" align="absmiddle"> '.t(553, 'Fragments').' | '.$theme->title.'</button> ' : ''),
                            '</div>',
                            '<br />'
                        ),
						'',
						'lang="'.$lang.'"'
                    );
				}
				else if($section['editor']=='html')	// html source code (codemirror)
				{
					$navibars->add_tab_content_row(
                        array(
                            '<label>'.template::section_name($section['name']).'</label>',
                            $naviforms->scriptarea('section-'.$section['id'].'-'.$lang, @$item->dictionary[$lang]['section-'.$section['id']], 'html', ' width: '.$section['width'].'px'),
                            '<div style="clear:both; margin-top:5px; float:left; margin-bottom: 10px;">',
                            '<label>&nbsp;</label>',
                            '<button onclick="navigate_items_copy_from_history_dialog(\'section-'.$section['id'].'-'.$lang.'\', \''.$section['id'].'\', \''.$lang.'\', \''.$section['editor'].'\'); return false;"><img src="img/icons/silk/time_green.png" align="absmiddle"> '.t(40, 'History').'</button> ',
                            (!empty($theme->content_samples)? '<button onclick="navigate_properties_copy_from_theme_samples(\'section-'.$section['id'].'-'.$lang.'\', \''.$section['id'].'\', \''.$lang.'\', \''.$section['editor'].'\'); return false;"><img src="img/icons/silk/rainbow.png" align="absmiddle"> '.t(553, 'Fragments').' | '.$theme->title.'</button> ' : ''),
                            '</div>',
                            '<br />'
                        ),
						'',
						'lang="'.$lang.'"'
                    );
				}
				else	// plain textarea (raw)
				{
					$translate_menu = '';
                    if(!empty($translate_extensions))
                    {
                        $translate_extensions_titles = array();
                        $translate_extensions_actions = array();

                        foreach($translate_extensions as $te)
                        {
                            if($te['enabled']=='0') continue;
                            $translate_extensions_titles[] = $te['title'];
                            $translate_extensions_actions[] = 'javascript: navigate_textarea_translate_'.$te['code'].'(\'section-'.$section['id'].'-'.$lang.'\', \''.$lang.'\');';
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

					$navibars->add_tab_content_row(
                        array(
                            '<label>'.template::section_name($section['name']).'</label>',
                            $naviforms->textarea('section-'.$section['id'].'-'.$lang, @$item->dictionary[$lang]['section-'.$section['id']], 8, 48, ' width: '.$section['width'].'px'),
                            '<div style="clear:both; margin-top:5px; margin-bottom: 10px; ">',
                            '<label>&nbsp;</label>',
	                        $translate_menu,
                            '<button onclick="navigate_items_copy_from_history_dialog(\'section-'.$section['id'].'-'.$lang.'\', \''.$section['id'].'\', \''.$lang.'\', \''.$section['editor'].'\'); return false;"><img src="img/icons/silk/time_green.png" align="absmiddle"> '.t(40, 'History').'</button> ',
                            (!empty($theme->content_samples)? '<button onclick="navigate_properties_copy_from_theme_samples(\'section-'.$section['id'].'-'.$lang.'\', \''.$section['id'].'\', \''.$lang.'\', \''.$section['editor'].'\'); return false;"><img src="img/icons/silk/rainbow.png" align="absmiddle"> '.t(553, 'Fragments').' | '.$theme->title.'</button> ' : ''),
                            '</div>'
                        ),
						'',
						'lang="'.$lang.'"'
                    );
				}
			}

			if($template->tags==1 || $template->tags=='true')
			{
				$tags_copy_select = '';
				$tags_copy_select_pre = '';
				$tags_copy_select_after = '';

				// allow copying tags between languages?
				if(count($website->languages_list) > 1)
				{
                    $tags_copy_select = $naviforms->selectfield(
                        '',
                        array_keys($ws_languages),
                        array_values($ws_languages),
                        '',
                        '',
                        false,
                        '',
                        ' width: auto; position: absolute; margin-top: 1px; ',
                        false
                    );

					$tags_copy_select = '
						<div style=" position: relative; margin-left: 600px; margin-top: 2px; width: 200px; height: 32px; ">
							<a href="#" class="uibutton" title="'.t(189, "Copy from").'…"
							   onclick=" navigate_items_tags_copy_from_language($(this).next().val(), \''.$lang.'\'); return false; ">
								<img src="img/icons/silk/page_white_copy.png" width="16" height="16" align="absmiddle" style=" cursor: pointer; " />
							</a>&nbsp;'.
							$tags_copy_select.'
						</div>
					';
				}

				$tags_top_list = '
					<div style=" position: relative; margin-left: 600px; margin-top: 24px; width: 200px; height: 32px; ">
						<a href="#" class="uibutton" onclick=" navigate_items_tags_ranking(\''.$lang.'\', this); return false; ">
							<img src="img/icons/silk/award_star_gold_3.png" width="16" height="16" align="absmiddle" style=" cursor: pointer; " />
							'.t(613, "Most used").'
						</a>
					</div>
				';

				$navibars->add_tab_content_row(
                    array(
                        '<label>'.t(265, 'Tags').'</label>',
                        $naviforms->textfield('tags-'.$lang, @$item->dictionary[$lang]['tags']),		// foo,bar,baz
	                    $tags_top_list,
                        $tags_copy_select
                    )
                );
			}

			$layout->add_script('			                
                $("#tags-'.$lang.'").tagit({
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
                            
                        $("#tags-'.$lang.'")
                            .val(tags)
                            .trigger("change");
                    },
                    afterTagRemoved: function(event, ui)
                    {                    
                        var tags = $(this).tagit("assignedTags");
                        if(tags.length > 0)
                            tags = tags.join(",");
                        else
                            tags = "";
                            
                        $("#tags-'.$lang.'")
                            .val(tags)
                            .trigger("change");
                    }
                });
                
                $("#tags-'.$lang.'").next().sortable({
                    items: ">li:not(.tagit-new)",
                    update: function(ui, event)
                    {
                        var tags = [];
                        
                        $("#tags-'.$lang.'").next().find("span.tagit-label").each(function()
                        {
                            if($(this).text() != "")
                                tags.push($(this).text());
                        });
                        if(tags.length > 0) tags = tags.join(",");
                        else                tags = "";
                                                    
                        $("#tags-'.$lang.'").val(tags);
                        $("#tags-'.$lang.'").trigger("change");                                                
                    }
                });                
			');

			// script#4
			
			$navibars->add_tab_content('</div>');		
		}

		// translate content_samples titles
		if(is_array($theme->content_samples))
		{
			for($i=0; $i < count($theme->content_samples); $i++)
				$theme->content_samples[$i]->title = $theme->t($theme->content_samples[$i]->title);
		}

		$layout->add_script('
			var template_sections = '.json_encode($template->sections).';
		');

		$category = new structure();		
		$category->paths = array();
		if(!empty($item->category))
			$category->load($item->category);
			
		$layout->add_script('
			var item_category_path = '.json_encode($category->paths).';
			var item_id = "'.$item->id.'";
		');

		// script#5
        // select the first language of the website as the default origin when copying content
        $default_language = array_keys($ws_languages);
        $default_language = $default_language[0];

		$layout->add_content('
			<div id="navigate_items_copy_from" style=" display: none; ">
				<div class="navigate-form-row">
					<label>'.t(191, 'Source').'</label>
					'.$naviforms->buttonset(
						'navigate_items_copy_from_type',
						array(
							'language' => t(46, 'Language'),
							//'template' => t(79, 'Template'),
							'item'	  => t(180, 'Item')
						),
						'0',
						"navigate_items_copy_from_change_origin(this);"
					).'
				</div>
				<div class="navigate-form-row" style=" display: none; ">
					<label>'.t(46, 'Language').'</label>
					'.$naviforms->selectfield(
						'navigate_items_copy_from_language_selector',
						array_keys($ws_languages),
						array_values($ws_languages),
						$default_language
			        ).'
				</div>
				<div class="navigate-form-row" style=" display: none; ">
					<label>'.t(79, 'Template').'</label>
					'.$naviforms->select_from_object_array('navigate_items_copy_from_template', $templates, 'id', 'title', '', '').'
				</div>			
				<div class="navigate-form-row" style=" display: none; ">		
					<label>'.t(67, 'Title').'</label>			
					'.$naviforms->textfield('navigate_items_copy_from_title').'
					'.$naviforms->hidden('navigate_items_copy_from_item_id', '').'
				</div>
				<div class="navigate-form-row" style=" display: none; ">
					<label>'.t(239, 'Section').'</label>
					'.$naviforms->select_from_object_array('navigate_items_copy_from_section', array(), 'id', 'name', '').'
				</div>			
			</div>
			
			<div id="navigate_items_copy_from_history" style=" display: none; ">
				<div class="navigate-form-row">
					<label>'.t(196, 'Date & time').'</label>
					<select id="navigate_items_copy_from_history_options" 
							name="navigate_items_copy_from_history_options" 
							onchange="navigate_items_copy_from_history_preview(this.value, $(this).attr(\'type\'));">
					</select>
					<a href="#" onclick="navigate_items_copy_from_history_remove();" title="'.t(35, "Delete").'"><img src="img/icons/silk/cancel.png" align="absmiddle"></a>
				</div>			
				<div class="navigate-form-row">
					<!--<div id="navigate_items_copy_from_history_text"
						 name="navigate_items_copy_from_history_text"
						 style="border: 1px solid #CCCCCC; float: left; height: auto; min-height: 20px; overflow: auto; width: 97%; padding: 3px; background: #f7f7f7;">
					</div>
					-->
					<textarea style="display: none;" id="navigate_items_copy_from_history_stylesheets">'.$website->content_stylesheets('link_tag').'</textarea>
					<iframe id="navigate_items_copy_from_history_text"
						 name="navigate_items_copy_from_history_text"
						 src="about:blank"
						 style="border: 1px solid #CCCCCC; float: left; height: 300px; min-height: 20px; overflow: auto; width: 97%; padding: 3px; ">
					</iframe>
					<div id="navigate_items_copy_from_history_text_raw" style=" display: none; "></div>
				</div>			
			</div>
		');
		
		// script will be bound to onload event at the end of this php function (after getScript is done)
		$onload_language = $_REQUEST['tab_language'];
		if(empty($onload_language))
			$onload_language = $website->languages_list[0];

		$layout->add_script('
			function navigate_items_onload()
			{
				navigate_items_select_language("'.$onload_language.'");
				navigate_change_association("'.(empty($item->id)? 'category' : $item->association).'");
				
	            // try to prevent erroneus "Unsaved changes" message when the user is about to save
	            $("#navigate-content-actions a[data-action=save]").on("mouseenter", navigate_beforeunload_unregister);
	            
				setTimeout(function()
				{
					$(navigate_codemirror_instances).each(function() { this.refresh(); } );
				}, 500);
			};
		');	
		
		/* IMAGE GALLERIES */

		if($template->gallery==='true' || $template->gallery > 0)
		{
			$navibars->add_tab(t(210, "Gallery")); // tab #3

            $access = array(
                0 => '', //<img src="img/icons/silk/page_white_go.png" align="absmiddle" title="'.t(254, 'Everybody').'" />',
                1 => '<img src="img/icons/silk/lock.png" align="absmiddle" title="'.t(361, 'Web users only').'" />',
                2 => '<img src="img/icons/silk/user_gray.png" align="absmiddle" title="'.t(363, 'Users who have not yet signed up or signed in').'" />',
                3 => '<img src="img/icons/silk/group_key.png" align="absmiddle" title="'.t(512, "Selected web user groups").'" />'
            );

            $permissions = array(
                0 => '', //'<img src="img/icons/silk/world.png" align="absmiddle" title="'.t(69, 'Published').'" />',
                1 => '<img src="img/icons/silk/world_dawn.png" align="absmiddle" title="'.t(70, 'Private').'" />',
                2 => '<img src="img/icons/silk/world_night.png" align="absmiddle" title="'.t(81, 'Hidden').'" />'
            );

            if(!is_array($item->galleries[0])) 
	            $item->galleries[0] = array();
			$gallery_elements_order = implode('#', array_keys($item->galleries[0]));
			
			$navibars->add_tab_content(
                $naviforms->hidden('items-gallery-elements-order', $gallery_elements_order)
			);
					
			$gallery = '<ul id="items-gallery-elements" class="items-gallery">';
			
			$ids = array_keys($item->galleries[0]);

			//$default_img = 'data:image/gif;base64,R0lGODlhAQABAPAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='; // transparent pixel
			$default_img = 'img/icons/ricebowl/mimetypes/image.png';
			for($g=0; $g < count($ids); $g++)
			{
				$f = new file();
                $f->load($ids[$g]);
				$gallery .= '
				    <li>
                        <div id="items-gallery-item-'.$ids[$g].'-droppable" class="navigate-droppable ui-corner-all" data-file-id="'.$f->id.'">
                            <div class="file-access-icons">'.$access[$f->access].$permissions[$f->permission].'</div>
                            <img title="'.$ids[$g].'" src="'.$default_img.'" data-src="'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$ids[$g].'&amp;disposition=inline&amp;width=75&amp;height=75" width="75" height="75" />
                        </div>
                        <div class="navigate-droppable-cancel" style="display: block;"><img src="img/icons/silk/cancel.png" /></div>
                    </li>
                ';
			}
		
			// empty element
			$gallery .= '
                <li class="gallery-item-empty-droppable">
                    <div id="items-gallery-item-empty-droppable" class="navigate-droppable ui-corner-all">
                        <img src="img/icons/misc/dropbox.png" vspace="18" />
                    </div>
                </li>
            ';
			
			$gallery.= '</ul>';
			
			// now the image captions	
			foreach($item->galleries[0] as $image_id => $image_dictionary)
			{		
				if(!is_array($image_dictionary)) $image_dictionary = array();	
				foreach($website->languages_list as $lang)
				{
					$gallery .= $naviforms->hidden('items-gallery-item-'.$image_id.'-dictionary-'.$lang, $image_dictionary[$lang]);
				}
			}	
			
			$navibars->add_tab_content_row(
                array(
                    '<label>'.t(210, 'Gallery').'</label>',
				    '<div>'.$gallery.'</div>'
                )
            );

			$layout->add_content('
				<ul id="contextmenu-gallery-items" style="display: none" class="ui-corner-all">
	                <li id="contextmenu-gallery-items-properties"><a href="#"><span class="ui-icon ui-icon-contact"></span>'.t(213, "Image caption").'</a></li>
	                <li id="contextmenu-gallery-items-permissions"><a href="#"><span class="ui-icon ui-icon-key"></span>'.t(17, "Permissions").'</a></li>
	                <li id="contextmenu-gallery-items-focalpoint"><a href="#"><span class="ui-icon ui-icon-image"></span>'.t(540, "Focal point").'</a></li>
	                <li id="contextmenu-gallery-items-description"><a href="#"><span class="ui-icon ui-icon-comment"></span>'.t(334, 'Description').'</a></li>
	                <li><!--divider--></li>
	                <li id="contextmenu-gallery-items-remove"><a href="#"><span class="ui-icon ui-icon-minus"></span>'.t(627, 'Remove').'</a></li>
	                <li id="contextmenu-gallery-items-move-beginning"><a href="#"><span class="ui-icon ui-icon-arrowthickstop-1-n"></span>'.t(628, 'Move to the beginning').'</a></li>
	                <li id="contextmenu-gallery-items-move-end"><a href="#"><span class="ui-icon ui-icon-arrowthickstop-1-s"></span>'.t(629, 'Move to the end').'</a></li>
	            </ul>
			');

			// script#6
			
			$layout->add_script('
				$(window).on("load", function()
				{
					new LazyLoad({
					    threshold: 200,
					    container: $("#items-gallery-elements-order").parent()[0],
					    elements_selector: "#items-gallery-elements img",
					    throttle: 40,
					    data_src: "src",
					    show_while_loading: true
					});
				});
			');
						
			$captions_form = '
				<div id="navigate_items_gallery_captions_form" style=" display: none; ">
					<div class="navigate-form-row">
						<label>'.t(157, 'Image').'</label>
						'.$naviforms->dropbox('navigate_items_gallery_captions_form_image', '', 'image', true).'
					</div>
			';
				
			$caption_langs = array_values($website->languages_list);
			
			foreach($caption_langs as $caption_language)
			{
				$captions_form .= '
					<div class="navigate-form-row">
						<label>'.language::name_by_code($caption_language).'</label>
						'.$naviforms->textfield('navigate_items_gallery_captions_form_image_'.$caption_language, '').'
					</div>
				';
			}
			$captions_form .= '
				</div>
			';
		
			$layout->add_content($captions_form);
		}
		
		// Properties TAB (only if needed)
		$properties_html = '';

		if($item->association == 'free' && !empty($item->template) && $item->template != '0')
		{
			// we already know the properties to show: template is set on item
			$properties_html = navigate_property_layout_form('item', $item->template, 'item', $item->id);
		}
		else if($item->association == 'category' && ($item->embedding==0) && !empty($item->template))
		{
			// we already know the properties to show: template is set on item
			$properties_html = navigate_property_layout_form('item', $item->template, 'item', $item->id);
		}
		else if($item->association == 'category' && ($item->category > 0))
		{
			// we have to get the template set in the category of the item
			$template_id = $DB->query_single('template', 'nv_structure', ' id = '.intval($item->category).' AND website = '.intval($website->id));
			$properties_html = navigate_property_layout_form('item', $template_id, 'item', $item->id);
		}

		if(!empty($properties_html))
		{
			$navibars->add_tab(t(77, "Properties")); // tab #4
			$navibars->add_tab_content($properties_html);
		}
		else
        {
            // we need to load helper functions anyway
            navigate_property_layout_scripts();
        }

		if($template->comments > 0 || $template->comments=='true' || $template->comments===true || is_object($template->comments))
		{
			$navibars->add_tab(t(250, "Comments"));	 // tab #5
			
			$navibars->add_tab_content_row(
				array(
						'<label>'.t(252, 'Comments enabled to').'</label>',
						$naviforms->selectfield('item-comments_enabled_to',
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
							$item->comments_enabled_to
						)
				)
			);

			$moderator_id = '';
			if(!empty($item->comments_moderator))
			{
				$moderator_username = $DB->query_single('username', 'nv_users', ' id = '.$item->comments_moderator);
				if(!empty($moderator_username))
				{
					$moderator_username = array($moderator_username);
					$moderator_id = array($item->comments_moderator);
				}
			}

			$navibars->add_tab_content_row(array(
                '<label>'.t(255, 'Moderator').'</label>',
				$naviforms->selectfield('item-comments_moderator', $moderator_id, $moderator_username, $item->comments_moderator, null, false, null, null, false),
                '<span style="display: none;" id="item-comments_moderator-helper">'.t(535, "Find user by name").'</span>',
				'<div class="subcomment"><img align="absmiddle" src="'.NAVIGATE_URL.'/img/icons/silk/information.png" /> '.t(256, 'Leave blank to accept all comments').'</div>'
			));

			// script#7
			
			// comments list
			// removed filter: AND nvwu.website = nvc.website ... reason: the webuser could be from another website if sharing webusers is enabled
			// TODO: retrieve comments by AJAX call to avoid memory issues. right now we just retrieve the first 500 comments
			$DB->query(
			    'SELECT nvc.*, nvwu.username, nvwu.avatar
                      FROM nv_comments nvc
                     LEFT OUTER JOIN nv_webusers nvwu 
                                  ON nvwu.id = nvc.user
                     WHERE nvc.website = '.intval($website->id).'
                       AND nvc.object_type = "item"
                       AND nvc.object_id = '.intval($item->id).'
                    ORDER BY nvc.date_created ASC
                    LIMIT 500'
            );
												
			$comments = $DB->result();
			$comments_total = count($comments);
			
			for($c=0; $c < $comments_total; $c++)
			{				
				if($comments[$c]->status==2)		$comment_status = 'hidden';
				else if($comments[$c]->status==1)	$comment_status = 'private';
				else if($comments[$c]->status==-1)	$comment_status = 'new';		
				else								$comment_status = 'public';		
			
				$navibars->add_tab_content_row(array(
					'<span class="object-comment-label">'.
						core_ts2date($comments[$c]->date_created, true).'<br />'.
						'<strong>'.(empty($comments[$c]->username)? $comments[$c]->name : $comments[$c]->username).'</strong>'.
						'<br />'.
						$comments[$c]->ip.
					'</span>',
					'<div id="object-comment-'.$comments[$c]->id.'" class="object-comment-message object-comment-status-'.$comment_status.'">'.nl2br($comments[$c]->message).'</div>',
					(empty($comments[$c]->avatar)? '' : '<img style=" margin-left: 5px; " src="'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$comments[$c]->avatar.'&amp;disposition=inline&amp;width=46&amp;height=46" />')
					)
				);
			}	

			$navibars->add_tab_content('
				<div id="object-comments-toolbar">
					<img id="object-comments-toolbar-publish" src="'.NAVIGATE_URL.'/img/icons/silk/accept.png" title="'.t(258, 'Publish').'" />
					<img id="object-comments-toolbar-unpublish" src="'.NAVIGATE_URL.'/img/icons/silk/delete.png" title="'.t(259, 'Unpublish').'" />
					<img id="object-comments-toolbar-delete" src="'.NAVIGATE_URL.'/img/icons/silk/decline.png" title="'.t(35, 'Delete').'" />				
				</div>
			');
			
			// script#8
			// comments moderation
		}


		if($item->votes > 0)
		{
			$navibars->add_tab(t(352, "Votes"));	 // tab #6
			
			$score = $item->score / $item->votes;			
			
			$navibars->add_tab_content_panel(
			    '<img src="img/icons/silk/chart_pie.png" align="absmiddle" /> '.t(337, 'Summary'),
				array(
				    '<div class="navigate-panels-summary ui-corner-all"><h2>'.$item->votes.'</h2><br />'.t(352, 'Votes').'</div>',
					'<div class="navigate-panels-summary ui-corner-all""><h2>'.$score.'</h2><br />'.t(353, 'Score').'</div>',
					'<div style=" float: left; margin-left: 8px; "><a href="#" class="uibutton" id="items_votes_webuser">'.t(15, 'Users').'</a></div>',
					'<div style=" float: right; margin-right: 8px; "><a href="#" class="uibutton" id="items_votes_reset">'.t(354, 'Reset').'</a></div>',
					'<div id="items_votes_webuser_window" style=" display: none; width: 600px; height: 350px; "></div>'
                ),
				'navigate-panel-web-summary',
                '385px',
                '200px'
            );

			$layout->add_script('
				$("#items_votes_reset").on("click", function()
				{
				    navigate_confirmation_dialog(
				        function()
                        {                         
                            $.post("?fid=items&act=votes_reset&id='.$item->id.'", function(data)
                            {
                                $("#navigate-panel-web-summary").addClass("ui-state-disabled");
                                navigate_notification("'.t(355, 'Votes reset').'");
                            });
                        },
                        "'.t(497, "Do you really want to erase this data?").'",
                        null,
				        "'.t(354, 'Reset').'"
                    );					    
				});
				
				$("#items_votes_webuser").on("click", function()
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
							
							$("#items_votes_webuser_grid").jqGrid(	
							    "navGrid", 
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
			
			$navibars->add_tab_content_panel(
                '<img src="img/icons/silk/chart_line.png" align="absmiddle" /> '.t(353, 'Score'),
				array(	'<div id="navigate-panel-web-score-graph" style=" margin: 8px; height: 150px; width: 360px; "></div>' ),
				'navigate-panel-web-score',
                '385px',
                '200px'
            );
											 											 
			$votes_by_score = webuser_vote::object_votes_by_score('item', $item->id);
			
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

            $navibars->add_tab_content('
                <div class="hidden" id="navigate-panel-web-data-score">'.json_encode($gdata).'</div>
            ');

			$navibars->add_tab_content_panel(
                '<img src="img/icons/silk/chart_line.png" align="absmiddle" /> '.t(352, 'Votes').' ('.t(356, 'last 90 days').')',
                 array(	'<div id="navigate-panel-web-votes-graph" style=" margin: 8px; height: 150px; width: 360px; "></div>' ),
                 'navigate-panel-web-votes',
                '385px',
                '200px'
            );

			$votes_by_date = webuser_vote::object_votes_by_date('item', $item->id, 90);
            $navibars->add_tab_content('
                <div class="hidden" id="navigate-panel-web-data-votes_by_date">'.json_encode($votes_by_date).'</div>
            ');
            // script#9
		}

        $nvweb_preview = NAVIGATE_PARENT.NAVIGATE_FOLDER.'/web/nvweb.php?preview=true&wid='.$website->id.'&route=';
		
		$layout->add_script('
			function navigate_items_preview()
			{
				// navigate_items_disable_spellcheck(); not needed in tinymce 4?
				navigate_periodic_event_delegate(); // force saving current data in history
				var url = "'.$nvweb_preview.'";
				var active_language = $("input[name=\'language_selector[]\']:checked").val();

				if($("#template").parent().css("display")=="block")
					url = url + "node/'.$item->id.'&lang=" + active_language + "&template=" + $("#template").val();
			    else // category URL
			        url = url + item_category_path[active_language].slice(1);

				setTimeout(function() { window.open(url); }, 1000);
			}
		');

        $events->trigger(
            'items',
            'edit',
            array(
                'item' => &$item,
                'navibars' => &$navibars,
                'naviforms' => &$naviforms
            )
        );
    }

    $layout->add_script('
	    $.ajax({
	        type: "GET",
	        dataType: "script",
	        url: "lib/packages/items/items.js?r='.$current_version->revision.'",
	        cache: true,
	        complete: function()
	        {
                if(typeof navigate_items_onload == "function")
				    navigate_items_onload();
	        }
	    });
	');

	return $navibars->generate();
}

function items_order($category)
{
    global $website;
    global $DB;
    global $layout;

    $out = array();
    $layout = new layout('free');
    $naviforms = new naviforms();

    // order blocks of the same type (for lists with priority ordering)
    $DB->query('SELECT i.id as id, d.text as title
                  FROM nv_items i, nv_webdictionary d
                 WHERE i.association = "category"
                   AND i.category = "'.$category.'"
                   AND d.node_type = "item"
                   AND d.subtype = "title"
                   AND d.lang = "'.$website->languages_list[0].'"
                   AND d.node_id = i.id
                   AND i.website = '.$website->id.'
                   AND d.website = '.$website->id.'
                ORDER BY i.position ASC');

    $item_ids = $DB->result('id');
    $items = $DB->result();

    $out[] = $naviforms->hidden('items-order', implode('#', $item_ids));

    $table = new naviorderedtable("items_order_table");
    $table->setWidth("560px");
    $table->setHiddenInput("items-order");

    $table->addHeaderColumn('ID', 50);
    $table->addHeaderColumn(t(67, 'Title'), 450);

    foreach($items as $row)
    {
        $table->addRow($row->id, array(
            array('content' => $row->id, 'align' => 'left'),
            array('content' => $row->title, 'align' => 'left')
        ));
    }

    $out[] = '<div class="subcomment" style=" margin-left: 0px; margin-bottom: 10px; "><img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'</div>';
    $out[] = '<div>'.$table->generate().'</div>';
    $out[] = '<div class="subcomment" style=" margin-left: 0px; margin-top: 10px; "><span class="ui-icon ui-icon-alert" style="float: left; margin-right: 4px; "></span> '.t(408, 'Order is only used on lists ordered by priority').'</div>';

    $out[] = $layout->generate();

    return implode("\n", $out);
}

function items_labels_form()
{
    global $website;
    global $layout;

    $navibars = new navibars();

    $navibars->title(t(22, 'Elements').' / '.t(265, 'Labels'));

    $navibars->add_actions(
        array(
            '<a href="?fid=items&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/page.png"> '.t(22, 'Elements').'</a>'
        )
    );

    $navibars->form();

    foreach($website->languages() as $lang => $lang_name)
    {
        // retrieve ALL tags for the current language
        $labels = nvweb_tags_retrieve(PHP_INT_MAX, array(), 'top', '', $lang, array("item"));
        if(empty($labels))
            continue;

        $tags = array();
        $max = NULL;
        foreach($labels as $label => $count)
        {
            // we expect the tags to be ordered desc
            if(is_null($max))
                $max = $count;

            // fontsize by count --> max: 200% min: 100%
            $font_size = 200 * $count / $max;
            if($font_size < 100 || $max == 1) $font_size = 100;

            $tags[] = '<a class="uibutton" style="margin: 0 6px 8px 0; font-size: '.$font_size.'%;" data-tag="'.$label.'" data-lang="'.$lang.'">'.
                $label.
                '<span style="position: absolute; top: 0; right: 4px; font-size: 8px; opacity: .5;">'.$count.'</span></a>';
        }

        $navibars->add_tab($lang_name);
        
        $navibars->add_tab_content('
            <div id="items-labels-'.$lang.'">
                <table style="width: 100%;">
                    <tr>
                        <td width="50%" valign="top">'.implode("", $tags).'</td>
                        <td valign="top"><div id="items-labels-elements-'.$lang.'" style="display: none;">
                            <div class="ui-widget-content ui-corner-all navigate-panel" style=" min-height: 162px; max-width: 100%; visibility: visible;">
                                <div class="ui-state-default ui-corner-top navigate-panel-header ui-sortable-handle" style=" padding: 5px; "><img src="img/icons/silk/tag_yellow.png" align="absmiddle"> <span class="items-labels-elements-tag"></span></div>
                                <div class="navigate-panel-body"></div>
                            </div>
                        </div></td>
                    </tr>
                </table>
            </div>
        ');
    }

    $layout->add_script('
        $("div[id^=\'items-labels\']").find("a.uibutton").on("click", function(e)
        {
            var lang = $(this).data("lang");
            var tag = $(this).data("tag");
            var opacity = 1;
            e.stopPropagation();
            e.preventDefault();
            
            $("div[id^=\'items-labels\']").find("a.uibutton").removeClass("ui-state-highlight");
            $(this).addClass("ui-state-highlight");
            
            $("#items-labels-elements-" + lang).hide();
            $("#items-labels-elements-" + lang).css("margin-top", $(this).offset().top - $(this).offsetParent().offset().top + $(this).offsetParent().scrollTop() - 12);
            $("#items-labels-elements-" + lang).find("span.items-labels-elements-tag").html(tag);
            var panel = $("#items-labels-elements-" + lang).find(".navigate-panel-body");
            $(panel).html("");
            $(panel).off("mouseover").on("mouseover", ".navigate-panel-body-title", function()
            {
                $(this).addClass("ui-state-highlight");
            });
            $(panel).off("mouseout").on("mouseout", ".navigate-panel-body-title", function()
            {
                $(this).removeClass("ui-state-highlight");
            });
                        
            $.getJSON(
                "?fid=items&act=json&oper=elements_by_tag&lang=" + lang + "&tag=" + tag,
                function(data)
                {                
                    for(d in data)
                    {
                        if(data[d].access > 0) opacity = 0.5;
                        $(panel).append(\'<div class="navigate-panel-body-title ui-corner-all" style="padding: 5px; opacity: \'+opacity+\';"></div>\');
                        $(panel).find("div:last").html(\'<a href="?fid=items&act=edit&tab=2&tab_language=\'+lang+\'&id=\'+data[d].id+\'">\'+data[d].date+\' <strong>\'+data[d].title+\'</strong> #\'+data[d].id+\'</a>\');
                    }
                                        
                    $("#items-labels-elements-" + lang).show();
                }
            );
        });
    ');

    return $navibars->generate();
}

?>