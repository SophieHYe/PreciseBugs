<?php
require_once(NAVIGATE_PATH.'/lib/packages/feeds/feed_parser.class.php');

function run()
{
    global $user;

	switch(@$_REQUEST['act'])
	{
        case 'json':
            switch($_REQUEST['oper'])
            {
                case 'settings_panels':	// save dashboard panels state
                    $dashboard_panels = $_REQUEST['dashboard_panels'];
                    $user->setting('dashboard-panels', json_encode($dashboard_panels));
                    echo json_encode(true);
                    core_terminate();
                    break;

                case 'feed':
                    $feed = new feed_parser();
                    $feed->set_cache(4 * 3600); // once update each 4 hours
                    $feed->load($_REQUEST['url']);
                    list($channel, $articles, $count) = $feed->parse(0, $_REQUEST['limit'], 'newest');
                    $items = item::convert_from_rss($articles);

                    $display_language = $_REQUEST['language'];

                    if(!empty($items))
                    {
                        $feed_html = '';
                        for($c=0; $c < count($items); $c++)
                        {
                            if(empty($items[$c])) break;

                            if(!isset($items[$c]->dictionary[$display_language]))
                            {
                                // requested language not available, get the first available in the feed
                                $feed_languages = array_keys($items[$c]->dictionary);
                                $display_language = $feed_languages[0];
                            }

                            $tmp = array(
                                '<div class="navigate-panel-body-title ui-corner-all">'.
                                    '<a href="'.$items[$c]->paths[$display_language].'" target="_blank">'.
                                        core_ts2date($items[$c]->date_to_display, true).' '.
                                        '<strong>'.$items[$c]->dictionary[$display_language]['title'].'</strong>'.
                                    '</a>'.
                                '</div>',
                                '<div id="navigatecms-feed-item-'.$items[$c]->id.'" class="navigate-panel-recent-feed-element">'.
                                    $items[$c]->dictionary[$display_language]['section-main'].
                                '</div>');

                            $feed_html .= implode("\n", $tmp);
                        }
                    }
                    echo $feed_html;
                    core_terminate();
                    break;

                default: // list or search
            }
            break;

		case 'recent_items':
            $ri = users_log::recent_items(value_or_default($_REQUEST['limit']), 10);

            if(!is_array($ri))
                $ri = array();

            for($i=0; $i < count($ri); $i++)
            {
				$action = $ri[$i];
				$ri[$i]->_url = '?fid='.$action->function.'&wid='.$action->website.'&act=load&id='.$action->item;
                $ri[$i]->_link = '<a href="'.$ri[$i]->_url.'" title="'.htmlspecialchars($action->item_title).' | '.htmlspecialchars(t($action->function_title, $action->function_title)).'"><img src="'.$action->function_icon.'" align="absmiddle" /> '.core_string_cut($action->item_title, 33).'</a>';
            }

			echo json_encode($ri);
			core_terminate();

			break;

		default:
			$out = dashboard_create();
	}
	
	return $out;
}

function dashboard_create()
{
	global $user;
	global $events;
	global $website;
	global $layout;
    global $current_version;
		
	$navibars = new navibars();

	$navibars->title(t(18, 'Home'));
	
	if($user->profile==1) // Administrator
	{
		$installed_version = update::latest_installed();		
		$latest_update = $_SESSION['latest_update'];
		
		if(!empty($latest_update->Revision) && $latest_update->Revision > $installed_version->revision)
		{
			// current web settings
			$navibars->add_actions(
			    array(
			        '<a href="?fid=update&act=0">
                        <img height="16" align="absmiddle" width="16" src="img/icons/silk/asterisk_orange.png"> '.t(351, 'New update available!').
                    '</a>'
                )
            );
		}
	}
	
	// current web settings
	$navibars->add_actions(	 array(	'<a href="?fid=websites&act=2&id='.$website->id.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/world_edit.png"> '.t(177, 'Website').'</a>') );
	
	// user settings
	$navibars->add_actions(	 array(	'<a href="?fid=settings"><img height="16" align="absmiddle" width="16" src="img/icons/silk/user_edit.png"> '.t(14, 'Settings').'</a>') );
	
	$navibars->form();

	$navibars->add_tab(t(43, "Main"));

    $stats = array();
    dashboard_panel_web_summary(array("navibars" => &$navibars, "statistics" => &$stats));
    dashboard_panel_top_pages(array("navibars" => &$navibars, "statistics" => &$stats));
    dashboard_panel_recent_comments(array("navibars" => &$navibars, "statistics" => &$stats));
    dashboard_panel_recent_changes(array("navibars" => &$navibars, "statistics" => &$stats));
    dashboard_panel_top_elements(array("navibars" => &$navibars, "statistics" => &$stats));
    dashboard_panel_recent_elements(array("navibars" => &$navibars, "statistics" => &$stats));
    dashboard_panel_latest_searches(array("navibars" => &$navibars, "statistics" => &$stats));
    dashboard_panel_public_wall(array("navibars" => &$navibars, "statistics" => &$stats));
    dashboard_panel_navigatecms_news(array("navibars" => &$navibars, "statistics" => &$stats));

    $events->trigger(
        'dashboard',
        'panels',
        array(
            "navibars" => &$navibars,
            "statistics" => &$stats
        )
    );

    $layout->add_script('
        function navigate_dashboard_website_notes_update(object_type, object_id)
        {
            $("#navigate-panel-public-wall").find(".navigate-panel-public-wall-note").remove();
            $.getJSON("?fid=grid_notes&object="+object_type+"&act=grid_notes_comments&id="+object_id, function(data)
                {
                    $(data).each(function(i)
                    {                    
                        var model = $(".navigate-panel-model-row").html();            
                        var row = \'<div class="navigate-panel-public-wall-note" id="website-note-\'+this.id+\'">\';
                        row = row + model + \'</div>\';
                        $("#navigate-panel-public-wall > div:last").append(row);
                                                                                                                                    
                        var row = $("#navigate-panel-public-wall div.navigate-panel-public-wall-note:last");
                        $(row).find("span[data-field=date]").html(this.date);
                        $(row).find("span[data-field=username] strong").html(this.date);
                        $(row).find("div[data-field=note]").html(this.note);
                    });
                }
            );
        }
    ');

    // dashboard panels as portlets
    $navibars->add_tab_content('<div id="navigate-dashboard-trashcan" class="ui-state-active hidden"><i class="fa fa-trash"></i></div>');

    $navibars->add_tab_content('<div id="navigate-dashboard-column-1" class="navigate-dashboard-column"></div>');
    $navibars->add_tab_content('<div id="navigate-dashboard-column-2" class="navigate-dashboard-column"></div>');
    $navibars->add_tab_content('<div id="navigate-dashboard-column-3" class="navigate-dashboard-column"></div>');
    $navibars->add_tab_content('<div id="navigate-dashboard-column-4" class="navigate-dashboard-column"></div>');

    $layout->add_content('
        <ul id="contextmenu-dashboard">
            <li>
                <a href="#">
                    <img src="img/icons/silk/bin.png" style="vertical-align: middle; " /> '.t(639, "Restore panel").'
                </a>
                <ul id="contextmenu-dashboard-panels-removed"></ul>
            </li>
            <li>-</li>
            <li>
                <a href="?fid=dashboard&reset_panels">
                    <img src="img/icons/silk/layout_error.png" style="vertical-align: middle; " /> '.t(640, "Default arrangement").'
                </a>
            </li>                    
        </ul>
    ');

    $default_order = array(
        0 => array(
            json_decode('{"id": "navigate-panel-web-summary"}'),
            json_decode('{"id": "navigate-panel-public-wall"}')
        ),
        1 => array(
            json_decode('{"id": "navigate-panel-top-pages"}'),
            json_decode('{"id": "navigate-panel-recent-comments"}')
        ),
        2 => array(
            json_decode('{"id": "navigate-panel-recent-changes"}'),
            json_decode('{"id": "navigate-panel-navigatecms-feed"}')
        ),
        3 => array(
            json_decode('{"id": "navigate-panel-top-elements"}'),
            json_decode('{"id": "navigate-panel-recent-elements"}'),
            json_decode('{"id": "navigate-panel-latest-searches"}')
        )
    );

    $dashboard_panels = json_decode($user->setting('dashboard-panels'));
    if(empty($dashboard_panels) || isset($_GET['reset_panels']))
    {
        $dashboard_panels = $default_order;
        $user->setting('dashboard-panels', json_encode($default_order));
    }

    $layout->add_script('    
        var navigate_dashboard_panels = '.json_encode($dashboard_panels).';

	    $.ajax({
	        type: "GET",
	        dataType: "script",
	        cache: true,
	        url: "lib/packages/dashboard/dashboard.js?r='.$current_version->revision.'",
	        complete: function()
	        {
                navigate_dashboard_run();                
	        }
	    });
	');

    $events->trigger(
        'dashboard',
        'tabs',
        array(
            "navibars" => &$navibars,
            "statistics" => &$stats
        )
    );

	return $navibars->generate();
}

function dashboard_panel_web_summary($params)
{
    global $DB;
    global $website;
    global $layout;

    $stats = &$params['statistics'];
    $navibars = &$params['navibars'];

//	$stats['pages_available'] = $DB->query_single('COUNT(DISTINCT object_id)', 'nv_paths', 'website = '.protect($website->id).' GROUP BY object_id');

    // count number of paths, ignoring extra languages (so if the item has 3 languages and 3 different paths, only one is counted)
    $DB->query('
	    SELECT COUNT(c.object_id) as total
	      FROM
	      (
                SELECT DISTINCT p.object_id
                  FROM nv_paths p
                 WHERE p.website = '.protect($website->id).'
              GROUP BY p.object_id
          ) c
    ');
    $count = $DB->first();
    $stats['pages_available'] = $count->total;

    // we need to include elements without paths assigned but accessible through /node/xx
    $DB->query('
        SELECT COUNT(i.id) as total
          FROM nv_items i
         WHERE i.website = '.protect($website->id).'
           AND i.embedding = 0
           AND (
                SELECT count(p.id)
                 FROM nv_paths p
                WHERE p.object_id = i.id
           ) < 1
    ');
    $count = $DB->first();
    $stats['pages_available'] += $count->total;

//	$stats['pages_viewed'] = $DB->query_single('SUM(i.views)', 'nv_items i', 'website = '.protect($website->id));
    $stats['comments_count'] = $DB->query_single('COUNT(*)', 'nv_comments', 'website = '.protect($website->id));
    $stats['comments_torevise'] = $DB->query_single('COUNT(*)', 'nv_comments', 'website = '.protect($website->id).' AND status = -1');

    $DB->query('
		SELECT SUM(x.page_views) as pages_viewed FROM
		(	
			SELECT i.views as page_views, i.id as id_item 
			  FROM nv_items i
			 WHERE i.website = '.protect($website->id).'
			   AND i.template > 0
			   AND i.embedding = 0

			UNION ALL

			SELECT s.views as page_views, s.id as id_category
			  FROM nv_structure s
			 WHERE s.website = '.protect($website->id).'
		) x
	');

    // i.embedding = 0  : all free items and category items not shown in the first page of a category (p.e. news item)
    // union all main category pages

    $stats['pages_viewed'] = $DB->first();
    $stats['pages_viewed'] = intval($stats['pages_viewed']->pages_viewed);

    $navibars->add_tab_content_panel('
	     <img src="img/icons/silk/chart_line.png" align="absmiddle" /> '.t(278, 'Web summary'),
        array(
            '<div class="navigate-panels-summary ui-corner-all"><h2>'.$stats['pages_available'].'</h2><br />'.t(279, 'pages available').'</div>',
            '<div class="navigate-panels-summary ui-corner-all"><h2>'.$stats['pages_viewed'].'</h2><br />'.t(280, 'pages viewed').'</div>',
            '<div class="navigate-panels-summary ui-corner-all"><h2>'.$stats['comments_count'].'</h2><br />'.t(250, 'Comments').'</div>',
            '<div class="navigate-panels-summary ui-corner-all"><h2>'.$stats['comments_torevise'].'</h2><br />'.t(281, 'comments to revise').'</div>'
        ),
        'navigate-panel-web-summary',
        '100%',
        '314px'
    );

    $layout->add_script('
        $(".navigate-panels-summary").each(function()
        {
            if($(this).height() > 78)
                $(this).find("br").remove();
        });
    ');

}

function dashboard_panel_top_pages($params)
{
    global $DB;
    global $website;

    $stats = &$params['statistics'];
    $navibars = &$params['navibars'];

    /* TOP PAGES */
    $sql = '
	    SELECT i.views as page_views, i.id as id_item, i.category as id_category, p.views as path_views, p.path as path
          FROM nv_items i, nv_paths p
         WHERE i.website = '.protect($website->id).'
           AND i.template > 0
           AND i.embedding = 0
           AND p.website = '.protect($website->id).'
           AND p.type = "item"
           AND p.object_id = i.id

        UNION ALL

        SELECT s.views as page_views, NULL as id_item, s.id as id_category, p.views as path_views, p.path as path
          FROM nv_structure s, nv_paths p
         WHERE s.website = '.protect($website->id).'
           AND p.website = '.protect($website->id).'
           AND p.type = "structure"
           AND p.object_id = s.id

        ORDER BY path_views DESC
        LIMIT 10
    ';

    $DB->query($sql, 'array');
    $pages = $DB->result();

    $pages_html = '';

    $url = $website->protocol;

    if(!empty($website->subdomain))
        $url .= $website->subdomain.'.';
    $url .= $website->domain;
    $url .= $website->folder;

    for($e = 0; $e < 10; $e++)
    {
        if(!$pages[$e]) break;

        $pages_html .= '<div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
            '<a href="'.$url.$pages[$e]['path'].'" target="_blank">'.
            '<strong>'.$pages[$e]['path_views'].'</strong> <img align="absmiddle" src="img/icons/silk/bullet_star.png" align="absmiddle"> '.$pages[$e]['path'].
            '</a>'.
            '</div>';
    }

    $navibars->add_tab_content_panel(
        '<img src="img/icons/silk/award_star_gold_3.png" align="absmiddle" /> '.t(296, 'Top pages'),
        $pages_html,
        'navigate-panel-top-pages',
        '100%',
        '314px'
    );
}

function dashboard_panel_recent_comments($params)
{
    global $DB;
    global $website;
    global $layout;

    $stats = &$params['statistics'];
    $navibars = &$params['navibars'];

    /* RECENT COMMENTS */
    $comments_limit = 25;

    $DB->query('
      SELECT nvc.*, nvwu.username, nvwu.avatar, nvwd.text as content_title
	    FROM nv_comments nvc
		LEFT OUTER JOIN nv_webusers nvwu 
				     ON nvwu.id = nvc.user
        LEFT OUTER JOIN nv_webdictionary nvwd
              ON nvwd.node_id = nvc.object_id AND
                 nvwd.website = nvc.website AND
                 nvwd.node_type = nvc.object_type AND
                 nvwd.subtype = "title" AND
                 nvwd.lang = '.protect($website->languages_published[0]).'
      WHERE nvc.website = '.$website->id.'
	  ORDER BY nvc.date_created DESC LIMIT '.$comments_limit
    );
    // removed
    /*
        .. AND nvwu.website = nvc.website
        .. WHERE nvc.website = '.protect($website->id).'

        to allow cross-website members
    */

    $comments = $DB->result();

    if(!empty($comments[0]))
    {
        $comments_html = '<div style=" height: 280px; overflow: auto; ">';
        for($c=0; $c < $comments_limit; $c++)
        {
            if(empty($comments[$c])) break;

            if($comments[$c]->status==2)		$comment_status = 'hidden';
            else if($comments[$c]->status==1)	$comment_status = 'private';
            else if($comments[$c]->status==-1)	$comment_status = 'new';
            else								$comment_status = 'public';

            $tmp = array(
                '<div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-'.$comment_status.'">'.
                '<a href="#" action-href="?fid=comments&act=1&oper=del&ids[]='.$comments[$c]->id.'" style="float: right;"
                        title="'.t(525, "Remove comment (without confirmation)").'" class="navigate-panel-recent-comments-remove">
                        <span class="ui-icon ui-icon-circle-close"></span>                        
                 </a>'.
                '<a href="?fid=comments&act=2&id='.$comments[$c]->id.'">'.
                    core_ts2date($comments[$c]->date_created, true).' '.
                    '<strong>'.(empty($comments[$c]->username)? $comments[$c]->name : $comments[$c]->username).'</strong>'.
                    '<div class="subcomment">'.core_string_cut($comments[$c]->content_title, 56).'</div>'.
                '</a>'.
                '</div>',
                '<div id="items-comment-'.$comments[$c]->id.'" class="navigate-panel-recent-comments-element">'.htmlentities($comments[$c]->message).'</div>');

            $comments_html .= implode("\n", $tmp);
        }
        $comments_html .= '</div>';

        $layout->add_script('
            $(".navigate-panel-recent-comments-username").hover(function()
            {
                $(this).addClass("ui-state-highlight");
            },
            function()
            {
                $(this).removeClass("ui-state-highlight");
            });

            $(".navigate-panel-recent-comments-remove").hover(function()
            {
                $(this).parent().addClass("ui-state-error");
            },
            function()
            {
                $(this).parent().removeClass("ui-state-error");
            });

            $(".navigate-panel-recent-comments-remove").on("click", function()
            {
                var el_comment = $(this).parent();

                $.getJSON(
                    $(this).attr("action-href"),
                    function(result)
                    {
                        if(result==true)
                        {
                            $(el_comment).fadeOut();
                            $(el_comment).next().fadeOut();
                        }
                    }
                );
            });
        ');

        $navibars->add_tab_content_panel(
            '<img src="img/icons/silk/comment.png" align="absmiddle" /> '.t(276, 'Recent comments'),
            $comments_html,
            'navigate-panel-recent-comments',
            '100%',
            '314px'
        );
    }
}

function dashboard_panel_recent_changes($params)
{
    global $DB;
    global $website;
    global $layout;

    $stats = &$params['statistics'];
    $navibars = &$params['navibars'];

    /* NV USER LOG */
    $DB->query('
        SELECT u.username, ul.action, f.lid as function_lid, f.icon as function_icon, f.id as function_id, 
               ul.item as item_id, ul.item_title as title, ul.date as action_date
          FROM nv_users_log ul, nv_users u, nv_functions f
         WHERE u.id = ul.user
           AND ul.action IN ("save", "remove")
           AND ul.website = '.$website->id.'
           AND f.id = ul.function
        ORDER BY action_date DESC
          LIMIT 100
    ', 'array');
    $users_log = $DB->result();

    if(!empty($users_log))
    {
        $users_log_html = '';
        $r = -1;
        for($e = 0; $e < 10; $e++)
        {
            $r++;
            if(!@$users_log[$r]) break;

            // row is repeated? (same action as the last only changing the timestamp?)
            if( $r > 0 && (
                $users_log[$r]['username'] == $users_log[$r-1]['username'] &&
                $users_log[$r]['action'] == $users_log[$r-1]['action'] &&
                $users_log[$r]['function_id'] == $users_log[$r-1]['function_id'] &&
                $users_log[$r]['item_id'] == $users_log[$r-1]['item_id'] &&
                $users_log[$r]['title'] == $users_log[$r-1]['title']
                )
            )
            {
                $e--;
                continue;  // ignore this row
            }

            if(empty($users_log[$r]['title']))
            {
                $users_log[$r]['title'] = '('.t(282, 'Untitled').')';
                if($users_log[$r]['function_id'] == 10) // function: Elements
                {
                    // try to retrieve the title, as it may be assigned later
                    $title = $DB->query_single('text', 'nv_webdictionary', 'website = '.$website->id.' AND node_type = "item" AND node_id = '.$users_log[$r]['item_id'].' AND subtype = "title"', 'id ASC');
                    if(!empty($title))
                        $users_log[$r]['title'] = $title;
                }
            }

            if($users_log[$r]['action']=='save')
            {
                $users_log_html .= '
                    <div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
                    '<a href="?fid='.$users_log[$r]['function_id'].'&act=2&id='.$users_log[$r]['item_id'].'" title="'.core_ts2date($users_log[$r]['action_date'], true).' - '.t($users_log[$r]['function_lid']).'">'.
                    '<span>'.core_ts2elapsed_time($users_log[$r]['action_date']).'</span><img align="absmiddle" src="img/icons/silk/bullet_green.png" align="absmiddle">'.$users_log[$r]['username'] . ' <img align="absmiddle" src="'.$users_log[$r]['function_icon'].'" align="absmiddle"> ' . $users_log[$r]['title'].
                    '</a>'.
                    '</div>';
            }
            else if($users_log[$r]['action']=='remove')
            {
                $users_log_html .= '
                    <div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
                    '<a href="?fid='.$users_log[$r]['function_id'].'" title="'.core_ts2date($users_log[$r]['action_date'], true).' - '.t($users_log[$r]['function_lid']).'">'.
                    '<span>'.core_ts2elapsed_time($users_log[$r]['action_date']).'</span><img align="absmiddle" src="img/icons/silk/bullet_red.png" align="absmiddle">'.$users_log[$r]['username'] . ' <img align="absmiddle" src="'.$users_log[$r]['function_icon'].'" align="absmiddle"> ' . $users_log[$r]['title'].
                    '</a>'.
                    '</div>';
            }
        }

        $navibars->add_tab_content_panel(
            '<img src="img/icons/silk/page_edit.png" align="absmiddle" /> '.t(577, 'Latest modifications'),
            $users_log_html,
            'navigate-panel-recent-changes',
            '100%',
            '314px'
        );
    }
}

function dashboard_panel_top_elements($params)
{
    global $DB;
    global $website;
    global $layout;

    $stats = &$params['statistics'];
    $navibars = &$params['navibars'];

    /* TOP ITEMS */
    // free items + category items + category templates (without items) -> ORDERED
    $sql = ' SELECT i.id, i.date_modified, i.views, d.text as title, d.lang as language,
	                u.username as author_username
			   FROM nv_items i
		  LEFT JOIN nv_webdictionary d
					 ON i.id = d.node_id
					AND d.node_type = "item"
					AND d.subtype = "title"
					AND d.lang = "'.$website->languages_list[0].'"
					AND d.website = '.$website->id.'
		  LEFT JOIN nv_users u
					 ON u.id = i.author
			  WHERE i.website = '.$website->id.'
				AND i.embedding = 0
				
		   UNION ALL
		   
			SELECT i.id, i.date_modified, s.views, d.text as title, d.lang as language,
			       u.username as author_username
			   FROM nv_items i
		  LEFT JOIN nv_webdictionary d
					 ON i.id = d.node_id
					AND d.node_type = "item"
					AND d.subtype = "title"
					AND d.lang = "'.$website->languages_list[0].'"
					AND d.website = '.$website->id.'
		  LEFT JOIN nv_users u
					 ON u.id = i.author
		  LEFT JOIN nv_structure s
		  			 ON s.id = i.category
			  WHERE i.website = '.$website->id.'
				AND i.embedding = 1
				
		   ORDER BY views DESC
			  LIMIT 4';

    $DB->query($sql, 'array');
    $elements = $DB->result();

    $elements_html = '';
    for($e = 0; $e < 4; $e++)
    {
        if(!@$elements[$e]) break;
        if(empty($elements[$e]['title'])) $elements[$e]['title'] = '('.t(282, 'Untitled').')';

        $elements_html .= '<div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
            '<a href="?fid=items&act=2&id='.$elements[$e]['id'].'" title="'.core_ts2date($elements[$e]['date_modified'], true).' | '.$elements[$e]['author_username'].'">'.
            '<strong>'.$elements[$e]['views'].'</strong> <img align="absmiddle" src="img/icons/silk/bullet_star.png" align="absmiddle"> '.$elements[$e]['title'].
            '</a>'.
            '</div>';
    }

    $navibars->add_tab_content_panel(
        '<img src="img/icons/silk/award_star_silver_3.png" align="absmiddle" /> '.t(277, 'Top elements'),
        $elements_html,
        'navigate-panel-top-elements',
        '100%',
        '145px'
    );
}

function dashboard_panel_recent_elements($params)
{
    global $DB;
    global $website;
    global $layout;

    $stats = &$params['statistics'];
    $navibars = &$params['navibars'];

    /* LAST MODIFIED ITEMS */
    $sql = ' SELECT i.*, d.text as title, d.lang as language, u.username as author_username
			   FROM nv_items i
		  LEFT JOIN nv_webdictionary d
					 ON i.id = d.node_id
					AND d.node_type = "item"
					AND d.subtype = "title"
					AND d.lang = "'.$website->languages_list[0].'"
					AND d.website = '.$website->id.'
		  LEFT JOIN nv_users u
					 ON u.id = i.author
			  WHERE i.website = '.$website->id.'
		   ORDER BY date_modified DESC
			  LIMIT 5';

    $DB->query($sql, 'array');
    $elements = $DB->result();

    $elements_html = '';
    for($e = 0; $e < 5; $e++)
    {
        if(!@$elements[$e]) break;
        if(empty($elements[$e]['title'])) $elements[$e]['title'] = '('.t(282, 'Untitled').')';
        $elements_html .= '<div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
            '<a href="?fid=items&act=2&id='.$elements[$e]['id'].'" title="'.core_ts2date($elements[$e]['date_modified'], true).' | '.$elements[$e]['author_username'].'">'.$elements[$e]['title'].'</a>'.
            '</div>';
    }

    $navibars->add_tab_content_panel(
        '<img src="img/icons/silk/pencil.png" align="absmiddle" /> '.t(275, 'Recent elements'),
        $elements_html,
        'navigate-panel-recent-elements',
        '100%',
        '162px'
    );
}

function dashboard_panel_latest_searches($params)
{
    global $DB;
    global $website;
    global $layout;

    $stats = &$params['statistics'];
    $navibars = &$params['navibars'];

    /* LATEST SEARCHES */
    $sql = ' SELECT date, text, origin
			   FROM nv_search_log
			  WHERE website = '.$website->id.'
		   ORDER BY date DESC
			  LIMIT 5';

    $DB->query($sql, 'array');
    $data = $DB->result();

    $data_html = '';
    if(!empty($data))
    {
        for($e = 0; $e < 5; $e++)
        {
            if(!@$data[$e]) break;
            $data_html .= '
                <div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
                '<div>'.core_ts2date($data[$e]['date']).' <img align="absmiddle" src="img/icons/silk/bullet_star.png" align="absmiddle"> '.htmlentities($data[$e]['text']).'</div>'.
                '</div>';
        }

        $navibars->add_tab_content_panel(
            '<img src="img/icons/silk/zoom.png" align="absmiddle" /> '.t(579, 'Latest searches'),
            $data_html,
            'navigate-panel-latest-searches',
            '100%',
            '162px'
        );
    }
}

function dashboard_panel_public_wall($params)
{
    global $DB;
    global $website;
    global $layout;

    $stats = &$params['statistics'];
    $navibars = &$params['navibars'];

    $layout->navigate_notes_dialog('website', $website->id);
    $public_wall_notes = grid_notes::comments('website', $website->id);
    $elements_html = '
        <div class="navigate-panel-model-row hidden">
            <div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public hide">
                <span data-field="date"></span> 
                <span data-field="username"><strong>'.$public_wall_notes[$e]['username'].'</strong></span>
            </div>
            <div data-field="note" class="navigate-panel-recent-comments-element"></div>
        </div>
    ';
    for($e = 0; $e < 4; $e++)
    {
        if(!isset($public_wall_notes[$e]))
            break;

        $tmp = array(
            '<div class="navigate-panel-public-wall-note" id="website-note-'.$public_wall_notes[$e]['id'].'">',
            '<div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
            '<span data-field="date">'.$public_wall_notes[$e]['date'].'</span> '.
            '<span data-field="username"><strong>'.$public_wall_notes[$e]['username'].'</strong></span>'.
            '</div>',
            '<div data-field="note" class="navigate-panel-recent-comments-element">'.$public_wall_notes[$e]['note'].'</div>',
            '</div>'
        );

        $elements_html .= implode("\n", $tmp);
    }

    $navibars->add_tab_content_panel(
        '<img src="img/icons/silk/note.png" align="absmiddle" /> '.t(637, 'Website notes').
        '<div style="float: right; cursor: pointer;" onclick="navigate_display_notes_dialog(navigate_dashboard_website_notes_update);">
            <img src="img/icons/silk/add.png" width="18px" height="18px" class="grid_note_edit" align="absmiddle" />
         </div>'.
        '<div style="float: right;" onclick="navigate_display_notes_dialog(navigate_dashboard_website_notes_update);">
            <span class="navigate_grid_notes_span">'.count($public_wall_notes).'</span><img src="img/skins/badge.png" ng-notes="'.count($public_wall_notes).'" width="18px" height="18px" class="grid_note_edit" align="absmiddle" />
        </div>',
        $elements_html,
        'navigate-panel-public-wall',
        '100%',
        '314px'//'162px'
    );
}

function dashboard_panel_navigatecms_news($params)
{
    global $DB;
    global $website;
    global $layout;
    global $current;

    $stats = &$params['statistics'];
    $navibars = &$params['navibars'];

    // we'll need to load the feed by AJAX to avoid blocking the dashboard function
    $html_loader = '<div class="navigate-panel-loader"><i class="fa fa-circle-o-notch fa-spin fa-3x fa-fw"></i></div>';

    $navibars->add_tab_content_panel(
        '<img src="img/icons/silk/rss.png" align="absmiddle" /> Navigate CMS RSS',
        $html_loader,
        'navigate-panel-navigatecms-feed',
        '100%',
        '314px'
    );

    $layout->add_script('
        $(window).on("load", function()
        {        
            $("#navigate-panel-navigatecms-feed .navigate-panel-body")
                .load("?fid=dashboard&act=json&oper=feed", {limit: 5, language: "en", url: "http://www.navigatecms.com/en/rss"});
        });
        
        $("#navigate-panel-navigatecms-feed").on("mouseenter", ".navigate-panel-body-title", function()
        {
            $(this).addClass("ui-state-highlight");
        });
        
        $("#navigate-panel-navigatecms-feed").on("mouseleave", ".navigate-panel-body-title", function()
        {
            $(this).removeClass("ui-state-highlight");
        });
    ');
}

?>